const snmp          = require('net-snmp');
const profiles      = require('./vendor-profiles');
const { getDb }     = require('../db/database');
const { logEvent }  = require('../db/device-events');
const telegram      = require('../notifier/telegram');

// ── Helpers ───────────────────────────────────────────────────────────────────

function snmpWalk(session, oid) {
    return new Promise((resolve) => {
        const results = [];
        session.subtree(oid, (varbinds) => {
            for (const vb of varbinds) {
                if (!snmp.isVarbindError(vb)) {
                    results.push({ oid: vb.oid.split('.').map(Number), value: vb.value, type: vb.type });
                }
            }
        }, (error) => {
            if (error) console.error('[SNMP] Walk error:', error.message);
            resolve(results);
        });
    });
}

function snmpGet(session, oids) {
    return new Promise((resolve) => {
        session.get(oids, (error, varbinds) => {
            if (error) return resolve({});
            const out = {};
            for (let i = 0; i < oids.length; i++) {
                if (!snmp.isVarbindError(varbinds[i])) {
                    out[oids[i]] = varbinds[i].value;
                }
            }
            resolve(out);
        });
    });
}

function readUint(value) {
    if (value == null) return 0;
    if (typeof value === 'number') return value;
    if (Buffer.isBuffer(value)) {
        if (value.length === 8) return Number(value.readBigUInt64BE(0));
        if (value.length >= 4) return value.readUInt32BE(0);
        return value.readUInt8(0);
    }
    return Number(value);
}

function fmtSpeed(bps) {
    if (!bps) return '';
    if (bps >= 1e9) return `${(bps / 1e9).toFixed(0)}G`;
    if (bps >= 1e6) return `${(bps / 1e6).toFixed(0)}M`;
    if (bps >= 1e3) return `${(bps / 1e3).toFixed(0)}K`;
    return `${bps}`;
}

// ── Core SNMP read ────────────────────────────────────────────────────────────

/**
 * Reads full switch topology from a single IP.
 * Returns null if unreachable / wrong community.
 */
async function getSwitchTopology(ip, community = 'public', version = snmp.Version2c) {
    console.log(`[SNMP] Analizando switch ${ip}…`);

    const session = snmp.createSession(ip, community, {
        version,
        timeout: 3000,
        retries: 1,
    });

    try {
        // 1. System info
        const sysVars = await snmpGet(session, [profiles.OIDS.sysDescr, profiles.OIDS.sysName]);
        const sysDescr = (sysVars[profiles.OIDS.sysDescr] ?? '').toString();
        const sysName  = (sysVars[profiles.OIDS.sysName]  ?? '').toString();

        if (!sysDescr) {
            console.log(`[SNMP] Sin respuesta o community incorrecta en ${ip}`);
            session.close();
            return null;
        }

        const profile = profiles.detectProfile(sysDescr);
        console.log(`[SNMP] Detectado: ${profile.name} — ${sysName}`);

        // 2. Port names (ifName / ifDescr depending on vendor)
        const portNames = {};
        const ifNameRaw = await snmpWalk(session, profile.getPortNameOid());
        ifNameRaw.forEach(v => {
            const idx = String(v.oid[v.oid.length - 1]);
            portNames[idx] = v.value.toString();
        });

        // 2.5 Bridge port → ifIndex mapping (dot1dBasePortIfIndex)
        // CRITICAL: dot1dTpFdbPort values are *bridge* port numbers, not ifIndex.
        // Without this map, portNames/portStatus lookups are wrong (CSS326 has lo/bridge
        // interfaces that shift ifIndex away from bridge port number).
        const bridgeToIfIndex = {};   // bridgePort(string) → ifIndex(string)
        const ifIndexToBridge = {};   // ifIndex(string) → bridgePort(string)
        try {
            const bpRaw = await snmpWalk(session, profiles.OIDS.dot1dBasePortIfIndex);
            bpRaw.forEach(v => {
                const bridgePort = String(v.oid[v.oid.length - 1]);
                const ifIdx      = String(readUint(v.value));
                bridgeToIfIndex[bridgePort] = ifIdx;
                ifIndexToBridge[ifIdx]      = bridgePort;
            });
            if (Object.keys(bridgeToIfIndex).length)
                console.log(`[SNMP] Bridge→ifIndex map: ${Object.keys(bridgeToIfIndex).length} entries`);
        } catch (e) {
            console.log('[SNMP] dot1dBasePortIfIndex no disponible, usando bridge port como ifIndex');
        }

        // Helper: convert bridge port → ifIndex (falls back to identity if map unavailable)
        const bp2if = (bridgePort) => bridgeToIfIndex[bridgePort] ?? bridgePort;

        // 3. MAC → port (Bridge-MIB dot1dTpFdbPort)
        const macToPort = {};
        const macTableRaw = await snmpWalk(session, profile.getMacTableOid());
        macTableRaw.forEach(m => {
            const oidArr = m.oid;
            const macArr = oidArr.slice(oidArr.length - 6);
            if (macArr.length === 6) {
                const macHex    = macArr.map(n => n.toString(16).padStart(2, '0')).join(':');
                const bridgePort = String(readUint(m.value));
                const ifIdx      = bp2if(bridgePort);
                macToPort[macHex] = {
                    portIndex: ifIdx,           // store ifIndex so it aligns with portNames/portStatus
                    portName:  portNames[ifIdx] || `Port ${bridgePort}`,
                };
            }
        });

        // 4. Traffic counters
        const inOctets  = {};
        const outOctets = {};
        const [inRaw, outRaw] = await Promise.all([
            snmpWalk(session, profiles.OIDS.ifInOctets),
            snmpWalk(session, profiles.OIDS.ifOutOctets),
        ]);
        inRaw.forEach(v  => { inOctets[String(v.oid[v.oid.length - 1])]  = readUint(v.value); });
        outRaw.forEach(v => { outOctets[String(v.oid[v.oid.length - 1])] = readUint(v.value); });

        // 5. Port operational status (ifOperStatus) — 1=up, 2=down
        const portStatus = {};
        const statusRaw = await snmpWalk(session, profiles.OIDS.ifOperStatus);
        statusRaw.forEach(v => {
            portStatus[String(v.oid[v.oid.length - 1])] = readUint(v.value);
        });

        // 6. Port speed — try ifHighSpeed (Mbps) first, fall back to ifSpeed (bps)
        const portSpeed = {};
        const hiSpeedRaw = await snmpWalk(session, profiles.OIDS.ifHighSpeed);
        if (hiSpeedRaw.length > 0) {
            hiSpeedRaw.forEach(v => {
                const mbps = readUint(v.value);
                if (mbps > 0) portSpeed[String(v.oid[v.oid.length - 1])] = mbps * 1_000_000;
            });
        }
        // For any port missing from ifHighSpeed, try ifSpeed
        const needSpeed = Object.keys(portNames).filter(idx => !portSpeed[idx]);
        if (needSpeed.length > 0) {
            const speedRaw = await snmpWalk(session, profiles.OIDS.ifSpeed);
            speedRaw.forEach(v => {
                const idx = String(v.oid[v.oid.length - 1]);
                if (!portSpeed[idx]) portSpeed[idx] = readUint(v.value);
            });
        }

        // 7. L3 data (VLANs, ARP, interface IPs, routes, CDP) — graceful on failure
        const l3 = await _pollL3(session, profile);

        session.close();

        return { vendor: profile.name, sysName, macTable: macToPort, portNames, inOctets, outOctets, portStatus, portSpeed, l3 };

    } catch (err) {
        console.error(`[SNMP] Error crítico en ${ip}:`, err.message);
        session.close();
        return null;
    }
}

// ── L3 helpers ────────────────────────────────────────────────────────────────

/** Parses a port-bitmap OCTET STRING (Q-BRIDGE-MIB style) → array of 1-indexed port numbers */
function parseBitmap(buf) {
    if (!Buffer.isBuffer(buf)) return [];
    const ports = [];
    for (let b = 0; b < buf.length; b++) {
        for (let bit = 7; bit >= 0; bit--) {
            if (buf[b] & (1 << bit)) ports.push(b * 8 + (7 - bit) + 1);
        }
    }
    return ports;
}

/** Converts a Buffer(4) or dotted-decimal string to "a.b.c.d" */
function bufToIp(v) {
    if (typeof v === 'string') return v;
    if (Buffer.isBuffer(v) && v.length === 4) return Array.from(v).join('.');
    return String(v);
}

/** Converts Buffer(6) → "aa:bb:cc:dd:ee:ff" */
function bufToMac(v) {
    if (!Buffer.isBuffer(v) || v.length !== 6) return null;
    return Array.from(v).map(b => b.toString(16).padStart(2, '0')).join(':');
}

const ROUTE_TYPES = { 1: 'other', 2: 'invalid', 3: 'direct', 4: 'indirect' };

/**
 * Polls L3 data from a switch (VLANs, ARP table, interface IPs, routing, CDP).
 * All sub-polls are independent try/catch — partial results are fine.
 */
async function _pollL3(session, profile) {
    const result = { vlans: [], ifaceIps: [], arpEntries: [], routes: [], cdpNeighbors: [] };

    // ── VLANs ────────────────────────────────────────────────────────────────
    try {
        const nameRaw     = await snmpWalk(session, profiles.OIDS.dot1qVlanStaticName);
        const egressRaw   = await snmpWalk(session, profiles.OIDS.dot1qVlanStaticEgressPorts);
        const untaggedRaw = await snmpWalk(session, profiles.OIDS.dot1qVlanStaticUntaggedPorts);

        const vlanNames   = {};
        const egressMap   = {};
        const untaggedMap = {};

        nameRaw.forEach(v => {
            const vid = v.oid[v.oid.length - 1];
            vlanNames[vid] = v.value?.toString() || `VLAN ${vid}`;
        });
        egressRaw.forEach(v => {
            const vid = v.oid[v.oid.length - 1];
            egressMap[vid] = parseBitmap(v.value);
        });
        untaggedRaw.forEach(v => {
            const vid = v.oid[v.oid.length - 1];
            untaggedMap[vid] = parseBitmap(v.value);
        });

        for (const vid of Object.keys(vlanNames)) {
            const egress   = egressMap[vid]   || [];
            const untagged = untaggedMap[vid]  || [];
            const tagged   = egress.filter(p => !untagged.includes(p));
            result.vlans.push({
                vlan_id:        Number(vid),
                vlan_name:      vlanNames[vid],
                tagged_ports:   JSON.stringify(tagged),
                untagged_ports: JSON.stringify(untagged),
            });
        }
        if (result.vlans.length) console.log(`[SNMP/L3] VLANs: ${result.vlans.length}`);
    } catch (e) { console.log('[SNMP/L3] VLANs no soportadas:', e.message); }

    // ── Interface IPs ─────────────────────────────────────────────────────────
    try {
        const ifIdxRaw = await snmpWalk(session, profiles.OIDS.ipAdEntIfIndex);
        const maskRaw  = await snmpWalk(session, profiles.OIDS.ipAdEntNetMask);

        const byIp = {};
        ifIdxRaw.forEach(v => {
            const ip = v.oid.slice(-4).join('.');
            byIp[ip] = { if_index: readUint(v.value), netmask: '' };
        });
        maskRaw.forEach(v => {
            const ip = v.oid.slice(-4).join('.');
            if (byIp[ip]) byIp[ip].netmask = bufToIp(v.value);
        });

        result.ifaceIps = Object.entries(byIp)
            .filter(([ip]) => ip !== '0.0.0.0')
            .map(([ip_addr, data]) => ({ ip_addr, ...data }));

        if (result.ifaceIps.length) console.log(`[SNMP/L3] Interface IPs: ${result.ifaceIps.length}`);
    } catch (e) { console.log('[SNMP/L3] Interface IPs no soportadas:', e.message); }

    // ── ARP table ─────────────────────────────────────────────────────────────
    try {
        const arpRaw = await snmpWalk(session, profiles.OIDS.ipNetToMediaPhysAddress);
        for (const v of arpRaw) {
            const tail = v.oid.slice(-5);
            if (tail.length === 5) {
                const mac = bufToMac(v.value);
                if (mac) {
                    result.arpEntries.push({
                        if_index: tail[0],
                        ip_addr:  tail.slice(1).join('.'),
                        mac_addr: mac,
                    });
                }
            }
        }
        if (result.arpEntries.length) console.log(`[SNMP/L3] ARP entries: ${result.arpEntries.length}`);
    } catch (e) { console.log('[SNMP/L3] ARP table no soportada:', e.message); }

    // ── Routing table ─────────────────────────────────────────────────────────
    try {
        const destRaw    = await snmpWalk(session, profiles.OIDS.ipRouteDest);
        const nextRaw    = await snmpWalk(session, profiles.OIDS.ipRouteNextHop);
        const maskRaw    = await snmpWalk(session, profiles.OIDS.ipRouteMask);
        const typeRaw    = await snmpWalk(session, profiles.OIDS.ipRouteType);
        const metricRaw  = await snmpWalk(session, profiles.OIDS.ipRouteMetric);

        const byDest = {};
        const key = v => v.oid.slice(-4).join('.');

        destRaw.forEach(v   => { const k = key(v); byDest[k] = { dest: bufToIp(v.value) }; });
        nextRaw.forEach(v   => { const k = key(v); if (byDest[k]) byDest[k].next_hop   = bufToIp(v.value); });
        maskRaw.forEach(v   => { const k = key(v); if (byDest[k]) byDest[k].mask        = bufToIp(v.value); });
        typeRaw.forEach(v   => { const k = key(v); if (byDest[k]) byDest[k].route_type  = ROUTE_TYPES[readUint(v.value)] || 'other'; });
        metricRaw.forEach(v => { const k = key(v); if (byDest[k]) byDest[k].metric      = readUint(v.value); });

        result.routes = Object.values(byDest).filter(r => r.dest && r.dest !== '0.0.0.0');
        if (result.routes.length) console.log(`[SNMP/L3] Rutas: ${result.routes.length}`);
    } catch (e) { console.log('[SNMP/L3] Routing table no soportada:', e.message); }

    // ── CDP (Cisco only) ──────────────────────────────────────────────────────
    if (profile.hasCdp) {
        try {
            const devRaw  = await snmpWalk(session, profiles.OIDS.cdpCacheDeviceId);
            const addrRaw = await snmpWalk(session, profiles.OIDS.cdpCacheAddress);
            const portRaw = await snmpWalk(session, profiles.OIDS.cdpCachePortId);

            const byIdx = {};
            const cdpKey = v => v.oid.slice(-2).join('.');  // ifIndex.entryIndex

            devRaw.forEach(v  => { const k = cdpKey(v); byIdx[k] = { local_port: String(v.oid[v.oid.length - 2]), remote_device: v.value?.toString() }; });
            addrRaw.forEach(v => {
                const k = cdpKey(v);
                if (byIdx[k]) {
                    // CDP address has 4-byte type prefix, then IP bytes
                    const buf = v.value;
                    if (Buffer.isBuffer(buf) && buf.length >= 8) {
                        byIdx[k].remote_ip = Array.from(buf.slice(4, 8)).join('.');
                    }
                }
            });
            portRaw.forEach(v => { const k = cdpKey(v); if (byIdx[k]) byIdx[k].remote_port = v.value?.toString(); });

            result.cdpNeighbors = Object.values(byIdx).filter(n => n.remote_device);
            if (result.cdpNeighbors.length) console.log(`[SNMP/L3] CDP neighbors: ${result.cdpNeighbors.length}`);
        } catch (e) { console.log('[SNMP/L3] CDP no soportado:', e.message); }
    }

    return result;
}

/**
 * Writes L3 data (VLANs, iface IPs, ARP, routes, CDP) to the DB.
 */
function _applyL3Data(sw, l3, db) {
    const upsertVlan = db.prepare(`
        INSERT INTO vlans (switch_id, vlan_id, vlan_name, tagged_ports, untagged_ports, updated_at)
        VALUES (?, ?, ?, ?, ?, datetime('now','localtime'))
        ON CONFLICT(switch_id, vlan_id) DO UPDATE SET
            vlan_name      = excluded.vlan_name,
            tagged_ports   = excluded.tagged_ports,
            untagged_ports = excluded.untagged_ports,
            updated_at     = excluded.updated_at
    `);
    for (const v of l3.vlans) upsertVlan.run(sw.id, v.vlan_id, v.vlan_name, v.tagged_ports, v.untagged_ports);

    const upsertIp = db.prepare(`
        INSERT INTO interface_ips (switch_id, if_index, ip_addr, netmask, updated_at)
        VALUES (?, ?, ?, ?, datetime('now','localtime'))
        ON CONFLICT(switch_id, ip_addr) DO UPDATE SET
            if_index   = excluded.if_index,
            netmask    = excluded.netmask,
            updated_at = excluded.updated_at
    `);
    for (const i of l3.ifaceIps) upsertIp.run(sw.id, i.if_index, i.ip_addr, i.netmask);

    const upsertArp = db.prepare(`
        INSERT INTO arp_entries (switch_id, ip_addr, mac_addr, if_index, ts)
        VALUES (?, ?, ?, ?, datetime('now','localtime'))
        ON CONFLICT(switch_id, ip_addr) DO UPDATE SET
            mac_addr = excluded.mac_addr,
            if_index = excluded.if_index,
            ts       = excluded.ts
    `);
    for (const a of l3.arpEntries) upsertArp.run(sw.id, a.ip_addr, a.mac_addr, a.if_index);

    const upsertRoute = db.prepare(`
        INSERT INTO routing_entries (switch_id, dest, mask, next_hop, metric, route_type, ts)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now','localtime'))
        ON CONFLICT(switch_id, dest, next_hop) DO UPDATE SET
            mask       = excluded.mask,
            metric     = excluded.metric,
            route_type = excluded.route_type,
            ts         = excluded.ts
    `);
    for (const r of l3.routes) upsertRoute.run(sw.id, r.dest, r.mask || '', r.next_hop || '', r.metric || 0, r.route_type || '');

    const upsertCdp = db.prepare(`
        INSERT INTO cdp_neighbors (switch_id, local_port, remote_device, remote_ip, remote_port, updated_at)
        VALUES (?, ?, ?, ?, ?, datetime('now','localtime'))
        ON CONFLICT(switch_id, local_port, remote_device) DO UPDATE SET
            remote_ip   = excluded.remote_ip,
            remote_port = excluded.remote_port,
            updated_at  = excluded.updated_at
    `);
    for (const n of l3.cdpNeighbors) upsertCdp.run(sw.id, n.local_port || '', n.remote_device, n.remote_ip || '', n.remote_port || '');
}

// ── DB write ──────────────────────────────────────────────────────────────────

/**
 * Persists topology data for one switch into the DB.
 * Shared by scanAllSwitches() and scanSwitch().
 */
function _applyTopology(sw, topology, db) {
    // Update switch meta
    db.prepare('UPDATE switches SET vendor = ?, model = ?, last_polled = ? WHERE id = ?')
        .run(topology.vendor, topology.sysName, new Date().toISOString(), sw.id);

    // L3 data (VLANs, ARP, interface IPs, routes, CDP)
    if (topology.l3) _applyL3Data(sw, topology.l3, db);

    // Insert port traffic samples, keep last 2 per port
    const insertTraffic = db.prepare(
        'INSERT INTO port_traffic (switch_id, port_index, port_name, in_octets, out_octets, port_status, speed_bps) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    const pruneTraffic = db.prepare(
        `DELETE FROM port_traffic WHERE switch_id = ? AND port_index = ?
         AND id NOT IN (
           SELECT id FROM port_traffic
           WHERE switch_id = ? AND port_index = ?
           ORDER BY id DESC LIMIT 2
         )`
    );

    const portIdxSet = new Set([
        ...Object.keys(topology.inOctets),
        ...Object.keys(topology.outOctets),
        ...Object.keys(topology.portStatus),
    ]);

    for (const idx of portIdxSet) {
        const pName  = topology.portNames?.[idx] ?? `Port ${idx}`;
        const status = topology.portStatus[idx] ?? 0;
        const speed  = topology.portSpeed[idx]  ?? 0;
        insertTraffic.run(sw.id, idx, pName, topology.inOctets[idx] ?? 0, topology.outOctets[idx] ?? 0, status, speed);
        pruneTraffic.run(sw.id, idx, sw.id, idx);
    }

    // Update device ↔ port connections from CAM table
    for (const [mac, portInfo] of Object.entries(topology.macTable)) {
        const device = db.prepare('SELECT id FROM devices WHERE mac = ?').get(mac);
        if (!device) continue;

        const switchName = sw.name || topology.sysName;
        const speedBps   = topology.portSpeed[portInfo.portIndex] ?? 0;
        const speedLabel = fmtSpeed(speedBps);
        const existing   = db.prepare('SELECT device_id, port_name, switch_name FROM connections WHERE device_id = ?').get(device.id);

        if (existing) {
            const portChanged   = existing.port_name   !== portInfo.portName;
            const switchChanged = existing.switch_name !== switchName;

            if (portChanged || switchChanged) {
                const deviceRow = db.prepare('SELECT ip, mac, hostname FROM devices WHERE id = ?').get(device.id);
                telegram.notifyMacRoaming(deviceRow, existing.port_name, portInfo.portName, switchName);
                logEvent(device.id, 'port_changed', {
                    from: existing.port_name,
                    to:   portInfo.portName,
                    switch: switchName,
                });
            }

            db.prepare('UPDATE connections SET switch_name=?, switch_ip=?, port_name=?, port_index=?, speed=? WHERE device_id=?')
                .run(switchName, sw.ip, portInfo.portName, portInfo.portIndex, speedLabel, device.id);
        } else {
            db.prepare('INSERT INTO connections (device_id, switch_name, switch_ip, port_name, port_index, speed) VALUES (?, ?, ?, ?, ?, ?)')
                .run(device.id, switchName, sw.ip, portInfo.portName, portInfo.portIndex, speedLabel);
        }
    }
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Scans all enabled switches and updates the DB.
 */
async function scanAllSwitches() {
    const db = getDb();
    const switches = db.prepare(
        'SELECT id, name, ip, snmp_community, snmp_version FROM switches WHERE enabled = 1'
    ).all();

    if (!switches.length) return;
    console.log(`[SNMP] Polling ${switches.length} switch(es)…`);

    for (const sw of switches) {
        const version  = sw.snmp_version === 3 ? snmp.Version3 : snmp.Version2c;
        const topology = await getSwitchTopology(sw.ip, sw.snmp_community || 'public', version);
        if (topology) _applyTopology(sw, topology, db);
    }

    console.log('[SNMP] Polling completado.');
}

/**
 * Scans a single switch by DB id.
 * Returns the topology object (or null on failure).
 */
async function scanSwitch(switchId) {
    const db = getDb();
    const sw = db.prepare(
        'SELECT id, name, ip, snmp_community, snmp_version FROM switches WHERE id = ? AND enabled = 1'
    ).get(String(switchId));

    if (!sw) return null;

    const version  = sw.snmp_version === 3 ? snmp.Version3 : snmp.Version2c;
    const topology = await getSwitchTopology(sw.ip, sw.snmp_community || 'public', version);
    if (topology) _applyTopology(sw, topology, db);
    return topology;
}

module.exports = { getSwitchTopology, scanAllSwitches, scanSwitch };
