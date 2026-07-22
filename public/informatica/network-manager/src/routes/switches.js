const express    = require('express');
const router     = express.Router();
const { getDb }  = require('../db/database');
const snmpClient = require('../scanner/snmp-client');

// ── helpers ───────────────────────────────────────────────────────────────────

function calcRate(samples) {
    if (samples.length < 2) return { rxBps: null, txBps: null };
    const [latest, prev] = samples;
    const dtSec = (new Date(latest.ts) - new Date(prev.ts)) / 1000;
    if (dtSec <= 0) return { rxBps: null, txBps: null };

    const wrap = (a, b) => a >= b ? a - b : (0xFFFFFFFF - b) + a;
    return {
        rxBps: Math.round((wrap(latest.in_octets,  prev.in_octets)  * 8) / dtSec),
        txBps: Math.round((wrap(latest.out_octets, prev.out_octets) * 8) / dtSec),
    };
}

// ── GET /api/switches ─────────────────────────────────────────────────────────

router.get('/', (req, res) => {
    try {
        const switches = getDb().prepare('SELECT * FROM switches').all();
        res.json({ success: true, switches });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── POST /api/switches ────────────────────────────────────────────────────────

router.post('/', (req, res) => {
    const { name, ip, snmp_community, snmp_version } = req.body;
    if (!name || !ip) return res.status(400).json({ success: false, error: 'Name e IP son requeridos' });

    try {
        const result = getDb().prepare(
            'INSERT INTO switches (name, ip, snmp_community, snmp_version, enabled) VALUES (?, ?, ?, ?, 1)'
        ).run(name, ip, snmp_community || 'public', snmp_version || 2);

        // Async initial poll
        snmpClient.scanSwitch(result.lastInsertRowid)
            .catch(e => console.error('[SNMP] Error en scan inicial:', e.message));

        res.json({ success: true, id: result.lastInsertRowid });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── POST /api/switches/:id/poll — manual on-demand poll ──────────────────────

router.post('/:id/poll', async (req, res) => {
    try {
        const sw = getDb().prepare('SELECT id FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const topology = await snmpClient.scanSwitch(req.params.id);
        if (!topology) {
            return res.status(503).json({ success: false, error: 'Sin respuesta SNMP — verificar IP, community y que SNMP esté habilitado en el dispositivo' });
        }
        res.json({
            success:  true,
            vendor:   topology.vendor,
            sysName:  topology.sysName,
            ports:    Object.keys(topology.portNames).length,
            devices:  Object.keys(topology.macTable).length,
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/debug — raw SNMP topology dump (temp diagnostic) ────

router.get('/:id/debug', async (req, res) => {
    try {
        const sw = getDb().prepare('SELECT * FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const topology = await snmpClient.getSwitchTopology(sw.ip, sw.snmp_community || 'public',
            require('net-snmp')[sw.snmp_version === 3 ? 'Version3' : 'Version2c']);

        if (!topology) return res.status(503).json({ success: false, error: 'Sin respuesta SNMP' });

        // Show raw mapping for diagnosis
        const portDump = Object.entries(topology.portNames).map(([ifIdx, name]) => ({
            ifIndex:    Number(ifIdx),
            name,
            status:     topology.portStatus[ifIdx] === 1 ? 'UP' : topology.portStatus[ifIdx] === 2 ? 'DOWN' : `raw:${topology.portStatus[ifIdx]}`,
            speed_bps:  topology.portSpeed[ifIdx] ?? 0,
        })).sort((a, b) => a.ifIndex - b.ifIndex);

        res.json({
            success: true,
            vendor: topology.vendor,
            sysName: topology.sysName,
            portCount: portDump.length,
            macCount: Object.keys(topology.macTable).length,
            ports: portDump,
            macTable: topology.macTable,
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/ports — CAM table + device info + traffic ───────────

router.get('/:id/ports', (req, res) => {
    const db = getDb();
    try {
        const sw = db.prepare('SELECT * FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        // Latest 2 samples per port for delta calculation
        const rows = db.prepare(`
            SELECT port_index, port_name, port_status, speed_bps, in_octets, out_octets, ts
            FROM port_traffic
            WHERE switch_id = ?
            ORDER BY port_index ASC, id DESC
        `).all(req.params.id);

        const byPort = {};
        for (const r of rows) {
            if (!byPort[r.port_index]) byPort[r.port_index] = [];
            if (byPort[r.port_index].length < 2) byPort[r.port_index].push(r);
        }

        // Device connections for this switch (keyed by port_index)
        const connections = db.prepare(`
            SELECT c.port_index, c.speed, c.vlan,
                   d.id, d.hostname, d.ip, d.mac, d.is_online, d.device_type, d.vendor
            FROM connections c
            JOIN devices d ON d.id = c.device_id
            WHERE c.switch_ip = ?
        `).all(sw.ip);

        const deviceByPort = {};
        for (const c of connections) deviceByPort[c.port_index] = c;

        const ports = Object.values(byPort).map(samples => {
            const latest       = samples[0];
            const { rxBps, txBps } = calcRate(samples);
            const device       = deviceByPort[latest.port_index] ?? null;

            return {
                port_index:  latest.port_index,
                port_name:   latest.port_name,
                port_status: latest.port_status ?? 0,  // 1=up 2=down 0=unknown
                speed_bps:   latest.speed_bps   ?? 0,
                rx_bps:      rxBps,
                tx_bps:      txBps,
                ts:          latest.ts,
                device: device ? {
                    id:          device.id,
                    hostname:    device.hostname,
                    ip:          device.ip,
                    mac:         device.mac,
                    is_online:   device.is_online,
                    device_type: device.device_type,
                    vendor:      device.vendor,
                } : null,
            };
        });

        // Sort: up first, then down, then unknown; within each group by port_index
        ports.sort((a, b) => {
            const order = s => s === 1 ? 0 : s === 2 ? 1 : 2;
            return order(a.port_status) - order(b.port_status) || a.port_index - b.port_index;
        });

        res.json({ success: true, switch: sw, ports });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/traffic — port traffic counters (legacy) ────────────

router.get('/:id/traffic', (req, res) => {
    const db = getDb();
    try {
        const rows = db.prepare(`
            SELECT port_index, port_name, in_octets, out_octets, ts
            FROM port_traffic WHERE switch_id = ?
            ORDER BY port_index ASC, id DESC
        `).all(req.params.id);

        const byPort = {};
        for (const r of rows) {
            if (!byPort[r.port_index]) byPort[r.port_index] = [];
            if (byPort[r.port_index].length < 2) byPort[r.port_index].push(r);
        }

        const ports = Object.values(byPort).map(samples => {
            const latest = samples[0];
            const { rxBps, txBps } = calcRate(samples);
            return {
                port_index: latest.port_index,
                port_name:  latest.port_name,
                in_octets:  latest.in_octets,
                out_octets: latest.out_octets,
                ts:         latest.ts,
                rx_bps:     rxBps,
                tx_bps:     txBps,
            };
        });

        res.json({ success: true, ports });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/l3 — combined L3 data ───────────────────────────────

router.get('/:id/l3', (req, res) => {
    const db = getDb();
    try {
        const sw = db.prepare('SELECT * FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const vlans = db.prepare(
            'SELECT vlan_id, vlan_name, tagged_ports, untagged_ports, updated_at FROM vlans WHERE switch_id = ? ORDER BY vlan_id'
        ).all(req.params.id).map(v => ({
            ...v,
            tagged_ports:   JSON.parse(v.tagged_ports   || '[]'),
            untagged_ports: JSON.parse(v.untagged_ports || '[]'),
        }));

        const interfaceIps = db.prepare(
            'SELECT if_index, ip_addr, netmask, updated_at FROM interface_ips WHERE switch_id = ? ORDER BY if_index'
        ).all(req.params.id);

        const arpEntries = db.prepare(`
            SELECT a.ip_addr, a.mac_addr, a.if_index, a.ts,
                   d.id AS device_id, d.hostname, d.device_type, d.vendor AS dev_vendor, d.is_online
            FROM arp_entries a
            LEFT JOIN devices d ON d.mac = a.mac_addr
            WHERE a.switch_id = ?
            ORDER BY a.ip_addr
        `).all(req.params.id);

        const routes = db.prepare(
            'SELECT dest, mask, next_hop, metric, route_type, ts FROM routing_entries WHERE switch_id = ? ORDER BY dest'
        ).all(req.params.id);

        const cdpNeighbors = db.prepare(
            'SELECT local_port, remote_device, remote_ip, remote_port, updated_at FROM cdp_neighbors WHERE switch_id = ? ORDER BY local_port'
        ).all(req.params.id);

        res.json({ success: true, switch: sw, vlans, interfaceIps, arpEntries, routes, cdpNeighbors });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/arp ─────────────────────────────────────────────────

router.get('/:id/arp', (req, res) => {
    const db = getDb();
    try {
        const sw = db.prepare('SELECT id FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const entries = db.prepare(`
            SELECT a.ip_addr, a.mac_addr, a.if_index, a.ts,
                   d.id AS device_id, d.hostname, d.device_type, d.vendor AS dev_vendor, d.is_online
            FROM arp_entries a
            LEFT JOIN devices d ON d.mac = a.mac_addr
            WHERE a.switch_id = ?
            ORDER BY a.ip_addr
        `).all(req.params.id);

        res.json({ success: true, count: entries.length, entries });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/vlans ───────────────────────────────────────────────

router.get('/:id/vlans', (req, res) => {
    const db = getDb();
    try {
        const sw = db.prepare('SELECT id FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const vlans = db.prepare(
            'SELECT vlan_id, vlan_name, tagged_ports, untagged_ports, updated_at FROM vlans WHERE switch_id = ? ORDER BY vlan_id'
        ).all(req.params.id).map(v => ({
            ...v,
            tagged_ports:   JSON.parse(v.tagged_ports   || '[]'),
            untagged_ports: JSON.parse(v.untagged_ports || '[]'),
        }));

        res.json({ success: true, count: vlans.length, vlans });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── GET /api/switches/:id/routes ──────────────────────────────────────────────

router.get('/:id/routes', (req, res) => {
    const db = getDb();
    try {
        const sw = db.prepare('SELECT id FROM switches WHERE id = ?').get(req.params.id);
        if (!sw) return res.status(404).json({ success: false, error: 'Switch no encontrado' });

        const routes = db.prepare(
            'SELECT dest, mask, next_hop, metric, route_type, ts FROM routing_entries WHERE switch_id = ? ORDER BY dest'
        ).all(req.params.id);

        res.json({ success: true, count: routes.length, routes });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// ── DELETE /api/switches/:id ──────────────────────────────────────────────────

router.delete('/:id', (req, res) => {
    try {
        getDb().prepare('DELETE FROM switches WHERE id = ?').run(req.params.id);
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
