/**
 * ARP Scanner — con ping sweep para descubrir todos los dispositivos.
 * 
 * Flujo:
 * 1. Detecta subnets locales (ipconfig)
 * 2. Ping sweep: envía un ping rápido a cada IP del subnet
 * 3. Lee la tabla ARP (ahora completa)
 * 4. Resuelve hostnames y vendors
 * 5. Actualiza la DB
 */
const { exec, execSync } = require('child_process');
const net = require('net');
const os = require('os');
const hostnameResolver = require('./hostname-resolver');
const fingerprinter = require('./fingerprinter');
const ouiLookup     = require('../utils/oui-lookup');
const { getDb }       = require('../db/database');
const { logEvent }    = require('../db/device-events');
const telegram        = require('../notifier/telegram');

// ── Interfaces a ignorar (VPN, VM, loopback virtual) ──────────────────────
const VPN_IFACE_RE = /tailscale|tap\d|vmware|virtualbox|vethernet|hamachi|zerotier|wsl|nordlynx|nordvpn|openvpn|wireguard|mullvad|tun\d|br-|hyper-v/i;
// Prefijos de IP reservados para VPN/overlay conocidos
const VPN_IP_PREFIXES = ['100.', '26.', '172.19.', '172.20.', '172.21.', '172.28.', '172.29.', '172.30.', '172.31.', '192.168.56.', '192.168.99.'];

function isVpnAddr(ifaceName, ip) {
    if (VPN_IFACE_RE.test(ifaceName)) return true;
    return VPN_IP_PREFIXES.some(p => ip.startsWith(p));
}

function getConfiguredTargetSubnets() {
    let targets = null;
    try {
        const row = getDb().prepare("SELECT value FROM settings WHERE key = 'scan_subnets'").get();
        if (row?.value) targets = JSON.parse(row.value);
    } catch { /* ignore */ }

    if (!targets || targets.length === 0) {
        const config = require('../../config');
        targets = config.scanner?.targetSubnets ?? null;
    }

    return Array.isArray(targets) && targets.length > 0 ? targets : null;
}

/**
 * Detecta las subnets locales del equipo (omite adaptadores VPN/VM).
 * Si config.scanner.targetSubnets está definido, usa esos rangos en lugar
 * de auto-detectar — más eficiente cuando solo interesa un subconjunto de la red.
 * @returns {Array<{ip: string, cidr: number, prefix: string}>}
 */
function getLocalSubnets() {
    // Lee subnets desde DB settings primero, cae a config.js si no hay
    const targets = getConfiguredTargetSubnets();

    if (targets && targets.length > 0) {
        return targets.map(cidrStr => {
            const [ip, bits] = cidrStr.split('/');
            const cidr = Number(bits);
            const prefix = ip.split('.').slice(0, 3).join('.');
            console.log(`[ARP] Usando subnet configurada: ${cidrStr}`);
            return { ip, cidr, prefix };
        });
    }

    const interfaces = os.networkInterfaces();
    const subnets = [];

    for (const [name, addrs] of Object.entries(interfaces)) {
        for (const addr of addrs) {
            if (addr.family === 'IPv4' && !addr.internal) {
                if (isVpnAddr(name, addr.address)) {
                    console.log(`[ARP] Interfaz omitida (VPN/VM): ${name} (${addr.address})`);
                    continue;
                }
                const parts  = addr.address.split('.');
                const prefix = parts.slice(0, 3).join('.');
                const cidr   = netmaskToCidr(addr.netmask);

                subnets.push({
                    ip: addr.address,
                    netmask: addr.netmask,
                    cidr,
                    prefix,
                    interface: name,
                });

                console.log(`[ARP] Subnet detectada: ${addr.address}/${cidr} (${name})`);
            }
        }
    }

    return subnets;
}

/** Convierte netmask (ej "255.255.240.0") a CIDR (ej 20) */
function netmaskToCidr(mask) {
    return mask.split('.').reduce((bits, octet) => bits + (Number(octet) >>> 0).toString(2).replace(/0/g, '').length, 0);
}

/** Retorna el primer nombre de interfaz IPv4 no-VPN disponible */
function findLocalInterface() {
    const interfaces = os.networkInterfaces();
    for (const [name, addrs] of Object.entries(interfaces)) {
        for (const addr of addrs) {
            if (addr.family === 'IPv4' && !addr.internal && !isVpnAddr(name, addr.address)) {
                return name;
            }
        }
    }
    return 'eth0';
}

/** Verifica si una IP está dentro de alguno de los targetSubnets configurados */
function isInTargetSubnets(ip) {
    const targets = getConfiguredTargetSubnets();
    if (!targets || targets.length === 0) return true;

    const ipNum = ip.split('.').reduce((n, o) => (n << 8 | Number(o)) >>> 0, 0);
    return targets.some(cidrStr => {
        const [base, bits] = cidrStr.split('/');
        const cidr = Number(bits);
        const baseNum = base.split('.').reduce((n, o) => (n << 8 | Number(o)) >>> 0, 0);
        const mask = cidr === 0 ? 0 : (~0 << (32 - cidr)) >>> 0;
        return (ipNum & mask) === (baseNum & mask);
    });
}

/**
 * Hace ping sweep a un subnet completo de forma asíncrona pero controlada.
 * Utiliza una cola con límite de concurrencia para no saturar al SO (lo que causaba
 * que Windows descartara los paquetes sin poblar la tabla ARP).
 * @param {string} prefix - Ej: "192.168.1"
 */
/**
 * Genera la lista de IPs a barrer según la IP base y su CIDR.
 * Para /24 = 254 IPs, para /20 = 4094, etc.
 */
function buildIpRange(ip, cidr) {
    const parts = ip.split('.').map(Number);
    const ipNum = (parts[0] << 24 | parts[1] << 16 | parts[2] << 8 | parts[3]) >>> 0;
    const hostBits = 32 - cidr;
    const netBase  = (ipNum >>> hostBits) << hostBits;
    const total    = (1 << hostBits) - 2; // excluye network y broadcast
    const ips = [];
    for (let i = 1; i <= total; i++) {
        const n = (netBase + i) >>> 0;
        ips.push(`${(n >>> 24) & 0xFF}.${(n >>> 16) & 0xFF}.${(n >>> 8) & 0xFF}.${n & 0xFF}`);
    }
    return ips;
}

function pingSweep(subnet) {
    const ips = buildIpRange(subnet.ip, subnet.cidr);
    const total = ips.length;

    return new Promise((resolve) => {
        console.log(`[ARP] Ping sweep: ${total} IPs en ${subnet.ip}/${subnet.cidr} ...`);

        const isWin = os.platform() === 'win32';
        const concurrencyLimit = 50;
        let active = 0;
        let idx = 0;
        let completed = 0;

        function next() {
            while (active < concurrencyLimit && idx < total) {
                const ip = ips[idx++];
                active++;

                const pingCmd = isWin
                    ? `ping -n 1 -w 300 -l 1 ${ip}`
                    : `ping -c 1 -W 1 -s 1 ${ip}`;

                exec(pingCmd, { timeout: 1500 }, () => {
                    active--;
                    completed++;
                    if (completed >= total) {
                        console.log(`[ARP] Ping sweep completado: ${subnet.ip}/${subnet.cidr}`);
                        resolve();
                    } else {
                        next();
                    }
                });
            }
        }

        next();

        // Safety fallback — escala con el tamaño de la red
        const timeoutMs = Math.max(15000, Math.ceil(total / 50) * 1500);
        setTimeout(() => {
            if (completed < total) {
                console.log(`[ARP] Ping sweep timeout de seguridad (${completed}/${total} completados)`);
                resolve();
            }
        }, timeoutMs);
    });
}

function tcpWakeSweep(subnet, emitFn) {
    const ips = buildIpRange(subnet.ip, subnet.cidr);
    const ports = [80, 443, 445, 3389, 9100, 22, 23, 8080, 135, 139];
    const tasks = [];
    for (const ip of ips) {
        for (const port of ports) tasks.push({ ip, port });
    }

    return new Promise((resolve) => {
        const concurrencyLimit = 220;
        const timeoutMs = 350;
        let active = 0;
        let idx = 0;
        let completed = 0;

        emitFn?.('info', `Sondeo TCP liviano: ${ips.length} IPs, puertos ${ports.join(', ')}...`);
        console.log(`[ARP] TCP wake sweep: ${tasks.length} intentos en ${subnet.ip}/${subnet.cidr}`);

        function done() {
            completed++;
            if (completed >= tasks.length) {
                console.log(`[ARP] TCP wake sweep completado: ${subnet.ip}/${subnet.cidr}`);
                resolve();
            } else {
                next();
            }
        }

        function probe(task) {
            active++;
            const socket = new net.Socket();
            let finished = false;
            const finish = () => {
                if (finished) return;
                finished = true;
                active--;
                socket.destroy();
                done();
            };

            socket.setTimeout(timeoutMs);
            socket.once('connect', finish);
            socket.once('timeout', finish);
            socket.once('error', finish);
            socket.connect(task.port, task.ip);
        }

        function next() {
            while (active < concurrencyLimit && idx < tasks.length) {
                probe(tasks[idx++]);
            }
        }

        next();

        const safetyMs = Math.max(25000, Math.ceil(tasks.length / concurrencyLimit) * (timeoutMs + 100));
        setTimeout(() => {
            if (completed < tasks.length) {
                console.log(`[ARP] TCP wake sweep timeout (${completed}/${tasks.length})`);
                resolve();
            }
        }, safetyMs);
    });
}

/**
 * Parsea la salida de `arp -a` en Windows.
 */
function parseArpTable(output) {
    const entries = [];
    if (!output) return entries;

    const lines = output.split('\n');
    let currentInterface = '';

    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed) continue;

        // Windows: "Interface: 10.20.10.195 --- 0xa"
        const ifMatch = trimmed.match(/^Interface:\s+([\d.]+)/i);
        if (ifMatch) {
            currentInterface = ifMatch[1];
            continue;
        }

        let ip, mac, type;

        // Windows: "10.20.10.1    00-0c-29-71-0d-e2    dynamic"
        const winMatch = trimmed.match(
            /^([\d.]+)\s+([\da-f]{2}[:-][\da-f]{2}[:-][\da-f]{2}[:-][\da-f]{2}[:-][\da-f]{2}[:-][\da-f]{2})\s+(\w+)/i
        );
        // Linux: "? (10.20.10.1) at 00:0c:29:71:0d:e2 [ether] on ens34"
        const linMatch = !winMatch && trimmed.match(
            /\(([\d.]+)\)\s+at\s+([\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2})\s+\[(\w+)\]/i
        );

        if (winMatch) {
            ip   = winMatch[1];
            mac  = winMatch[2].replace(/-/g, ':').toLowerCase();
            type = winMatch[3].toLowerCase();
        } else if (linMatch) {
            ip   = linMatch[1];
            mac  = linMatch[2].toLowerCase();
            type = linMatch[3].toLowerCase();
        } else {
            continue;
        }

        // ── Filtrar basura ──
        if (mac === 'ff:ff:ff:ff:ff:ff') continue;
        if (ip === '255.255.255.255') continue;
        if (ip.endsWith('.255')) continue;
        const firstOctet = parseInt(ip.split('.')[0]);
        if (firstOctet >= 224 && firstOctet <= 239) continue;
        if (mac.startsWith('01:00:5e:')) continue;
        if (firstOctet === 0) continue;
        if (ip.startsWith('169.254.')) continue;
        // Ignorar entradas "(incomplete)" de Linux
        if (mac === '00:00:00:00:00:00') continue;

        entries.push({ ip, mac, type, interface: currentInterface });
    }

    // -- AGREGAR LAS INTERFACES LOCALES DE ESTA PC --
    appendLocalInterfaces(entries);
    return entries;
}

/**
 * Parsea la salida de `arp-scan` de Linux.
 * Formato: "10.25.96.1\t04:d5:90:99:fc:cc\tFortinet, Inc."
 */
function parseArpScanOutput(output) {
    const entries = [];
    if (!output) return entries;

    for (const line of output.split('\n')) {
        const match = line.match(/^([\d.]+)\s+([\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2}:[\da-f]{2})\s/i);
        if (!match) continue;

        const ip  = match[1];
        const mac = match[2].toLowerCase();

        // Filtrar basura
        if (mac === 'ff:ff:ff:ff:ff:ff') continue;
        if (mac === '00:00:00:00:00:00') continue;
        if (ip.endsWith('.255') || ip === '255.255.255.255') continue;
        const firstOctet = parseInt(ip.split('.')[0]);
        if (firstOctet >= 224 && firstOctet <= 239) continue;
        if (mac.startsWith('01:00:5e:')) continue;
        if (firstOctet === 0) continue;
        if (ip.startsWith('169.254.')) continue;

        // Deduplicar por MAC (arp-scan reporta DUPs)
        if (!entries.find(e => e.mac === mac)) {
            entries.push({ ip, mac, type: 'ether', interface: '' });
        }
    }

    appendLocalInterfaces(entries);
    return entries;
}

/** Agrega las interfaces locales del host al listado de entries */
function appendLocalInterfaces(entries) {
    const interfaces = os.networkInterfaces();
    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === 'IPv4' && !iface.internal) {
                if (isVpnAddr(name, iface.address)) continue;
                if (!entries.find(e => e.ip === iface.address)) {
                    entries.push({
                        ip: iface.address,
                        mac: iface.mac.toLowerCase(),
                        type: 'local',
                        interface: iface.address,
                        is_local_host: true,
                    });
                }
            }
        }
    }
}

/**
 * Escaneo rápido (sin ping sweep): solo lee `arp -a` y detecta cambios de estado.
 * Se usa en watch mode cada ~45 s.
 * @param {Function} [emitFn] - emitFn(type, msg) para eventos Socket.io
 * @returns {{ events: Array, onlineCount: number }}
 */
async function quickScan(emitFn) {
    const db  = getDb();
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
    let arpOutput;
    const isWin = os.platform() === 'win32';

    let entries;
    if (isWin) {
        try {
            arpOutput = execSync('arp -a', { encoding: 'utf-8', timeout: 10000 });
            entries = parseArpTable(arpOutput);
        } catch (e) {
            console.error('[ARP] Error ejecutando arp -a:', e.message);
            return { events: [], onlineCount: 0 };
        }
    } else {
        try {
            // arp-scan --localnet devuelve TODOS los dispositivos (arp -a pierde muchos)
            const subnets = getLocalSubnets();
            const defaultIface = findLocalInterface();
            let combined = '';
            for (const s of subnets) {
                const iface = s.interface || defaultIface;
                const out = execSync(`arp-scan --interface=${iface} ${s.ip}/${s.cidr} 2>/dev/null || true`, { encoding: 'utf-8', timeout: 30000 });
                combined += out + '\n';
            }
            entries = parseArpScanOutput(combined);
        } catch (e) {
            console.error('[ARP] Error en quick scan Linux:', e.message);
            return { events: [], onlineCount: 0 };
        }
    }
    entries = entries.filter(e => isInTargetSubnets(e.ip));
    const macs    = entries.map(e => e.mac);
    const events  = [];

    for (const entry of entries) {
        const existing = db.prepare('SELECT id, ip, hostname, is_online FROM devices WHERE mac = ?').get(entry.mac);
        if (existing) {
            if (existing.is_online === 0) {
                db.prepare('UPDATE devices SET is_online = 1, ip = ?, last_seen = ? WHERE mac = ?').run(entry.ip, now, entry.mac);
                db.prepare("INSERT INTO uptime_events (device_id, event) VALUES (?, 'online')").run(existing.id);
                const label = existing.hostname || entry.ip;
                emitFn?.('event', `🟢 Online: ${label} (${entry.ip})`);
                events.push({ type: 'online', device: existing });
            } else {
                if (existing.ip !== entry.ip) {
                    logEvent(existing.id, 'ip_changed', { from: existing.ip, to: entry.ip });
                }
                db.prepare('UPDATE devices SET ip = ?, last_seen = ? WHERE mac = ?').run(entry.ip, now, entry.mac);
            }
        } else {
            const vendor = ouiLookup.lookup(entry.mac);
            const result = db.prepare(
                'INSERT INTO devices (ip, mac, vendor, is_online, first_seen, last_seen) VALUES (?, ?, ?, 1, ?, ?)'
            ).run(entry.ip, entry.mac, vendor, now, now);
            const newId  = result.lastInsertRowid;
            db.prepare("INSERT INTO uptime_events (device_id, event) VALUES (?, 'online')").run(newId);
            const newDevice = { id: newId, ...entry, vendor };
            logEvent(newId, 'new_device', { ip: entry.ip, mac: entry.mac, vendor });
            emitFn?.('event', `✨ Nuevo dispositivo: ${vendor || entry.mac} (${entry.ip})`);
            events.push({ type: 'new', device: newDevice });
            telegram.notifyNewDevice(newDevice);
        }
    }

    // Detectar dispositivos que se fueron offline.
    // Windows limpia la tabla ARP con facilidad; en modo watch no marcamos offline
    // por una sola lectura ausente, sino cuando supera el umbral configurado.
    if (!isWin && macs.length > 0) {
        const ph          = macs.map(() => '?').join(',');
        const config      = require('../../config');
        const staleSeconds = Math.max(60, Math.floor((config.scanner?.offlineThresholdMs || 15 * 60 * 1000) / 1000));
        const goingOffline = db.prepare(
            `SELECT id, ip, mac, hostname
             FROM devices
             WHERE mac NOT IN (${ph})
               AND is_online = 1
               AND datetime(last_seen) <= datetime('now','localtime', ?)`
        ).all(...macs, `-${staleSeconds} seconds`);

        if (goingOffline.length) {
            const offlineMacs = goingOffline.map(d => d.mac);
            const offlinePh = offlineMacs.map(() => '?').join(',');
            db.prepare(`UPDATE devices SET is_online = 0 WHERE mac IN (${offlinePh})`).run(...offlineMacs);
        }

        for (const d of goingOffline) {
            db.prepare("INSERT INTO uptime_events (device_id, event) VALUES (?, 'offline')").run(d.id);
            emitFn?.('warn', `🔴 Offline: ${d.hostname || d.ip}`);
            events.push({ type: 'offline', device: d });
            telegram.notifyDeviceOffline(d);
        }
    }

    return { events, onlineCount: macs.length };
}

/**
 * Ejecuta un escaneo completo:
 * 1. Ping sweep de todos los subnets
 * 2. Lee tabla ARP
 * 3. Actualiza DB
 * @param {Function} [emitFn] - emitFn(type, msg) para live log
 * @returns {{ devices: Array, stats: Object }}
 */
async function scan(emitFn) {
    const startTime = Date.now();
    const db = getDb();

    // 1. Detectar subnets y hacer ping sweep
    const subnets = getLocalSubnets();
    const isWin   = os.platform() === 'win32';
    emitFn?.('info', `Subnets detectadas: ${subnets.map(s => `${s.ip}/${s.cidr}`).join(', ')}`);

    let entries;

    if (isWin) {
        // Windows: ping sweep para popular la tabla ARP, luego leer arp -a
        for (const subnet of subnets) {
            emitFn?.('info', `Ping sweep: ${subnet.ip}/${subnet.cidr} (50 concurrentes)…`);
            await pingSweep(subnet);
            emitFn?.('success', `Ping sweep completado: ${subnet.ip}/${subnet.cidr}`);
            await tcpWakeSweep(subnet, emitFn);
            emitFn?.('success', `Sondeo TCP completado: ${subnet.ip}/${subnet.cidr}`);
        }
        await new Promise(r => setTimeout(r, 1000));
        try {
            const arpOutput = execSync('arp -a', { encoding: 'utf-8', timeout: 10000 });
            entries = parseArpTable(arpOutput);
        } catch (e) {
            console.error('[ARP] Error ejecutando arp -a:', e.message);
            return { devices: [], stats: { error: e.message } };
        }
    } else {
        // Linux: arp-scan por cada subnet configurada
        const defaultIface = findLocalInterface();
        let combined = '';
        for (const subnet of subnets) {
            const iface = subnet.interface || defaultIface;
            emitFn?.('info', `arp-scan en ${iface} (${subnet.ip}/${subnet.cidr})…`);
            try {
                const out = execSync(`arp-scan --interface=${iface} ${subnet.ip}/${subnet.cidr} 2>/dev/null || true`, { encoding: 'utf-8', timeout: 30000 });
                combined += out + '\n';
            } catch (_) { /* ignore */ }
            emitFn?.('success', `arp-scan completado: ${subnet.ip}/${subnet.cidr}`);
        }
        entries = parseArpScanOutput(combined);
    }

    // Filtrar IPs fuera de los rangos target (aplica cuando targetSubnets está configurado)
    const beforeFilter = entries.length;
    entries = entries.filter(e => isInTargetSubnets(e.ip));
    if (entries.length < beforeFilter) {
        console.log(`[ARP] Filtro de rangos: ${beforeFilter} → ${entries.length} entradas (descartadas ${beforeFilter - entries.length} IPs fuera de target)`);
    }
    console.log(`[ARP] ${entries.length} dispositivos unicast encontrados en tabla ARP`);
    emitFn?.('info', `Tabla ARP leída: ${entries.length} dispositivos unicast`);

    let newDevices = 0;
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19);

    // 4. Fingerprint en batches de 5 (para no saturar)
    const devices = [];
    const batchSize = 5;

    for (let i = 0; i < entries.length; i += batchSize) {
        const batch = entries.slice(i, i + batchSize);
        const results = await Promise.all(batch.map(async (entry) => {
            // Vendor por OUI
            const vendor = ouiLookup.lookup(entry.mac);

            // Fingerprint: TTL → OS, NetBIOS → hostname, vendor+OS → tipo
            const fp = await fingerprinter.fingerprint(entry.ip, vendor);

            // Resolver hostname: priorizar NetBIOS, luego mDNS/SSDP/DNS
            let hostname = fp.hostname || '';
            if (!hostname) {
                hostname = await hostnameResolver.resolve(entry.ip, fp.osFamily);
            }

            return { entry, vendor, fp, hostname };
        }));

        for (const { entry, vendor, fp, hostname } of results) {
            const existing = db.prepare('SELECT id, ip, hostname, device_type, is_online FROM devices WHERE mac = ?').get(entry.mac);

            if (existing) {
                const wasOffline = existing.is_online === 0;
                db.prepare(`
                    UPDATE devices
                    SET ip = ?, is_online = 1, last_seen = ?,
                        hostname = CASE WHEN ? != '' THEN ? ELSE hostname END,
                        vendor = CASE WHEN ? != '' THEN ? ELSE vendor END,
                        os = CASE WHEN ? != '' THEN ? ELSE os END,
                        ttl = CASE WHEN ? > 0 THEN ? ELSE ttl END,
                        device_type = CASE WHEN ? != '' AND ? NOT IN ('Genérico','Linux/Android') THEN ? WHEN device_type = '' THEN ? ELSE device_type END
                    WHERE mac = ?
                `).run(
                    entry.ip, now,
                    hostname, hostname,
                    vendor, vendor,
                    fp.os, fp.os,
                    fp.ttl, fp.ttl,
                    fp.deviceType, fp.deviceType, fp.deviceType, fp.deviceType,
                    entry.mac
                );
                if (wasOffline) {
                    db.prepare('INSERT INTO uptime_events (device_id, event) VALUES (?, \'online\')').run(existing.id);
                }
                if (existing.ip !== entry.ip) {
                    logEvent(existing.id, 'ip_changed', { from: existing.ip, to: entry.ip });
                }
                if (hostname && existing.hostname && existing.hostname !== hostname) {
                    logEvent(existing.id, 'hostname_changed', { from: existing.hostname, to: hostname });
                }

                devices.push({
                    id: existing.id, ...entry,
                    hostname: hostname || existing.hostname,
                    vendor, os: fp.os, ttl: fp.ttl,
                    device_type: fp.deviceType || existing.device_type,
                    deviceIcon: fp.deviceIcon,
                    isNew: false,
                });
            } else {
                const result = db.prepare(`
                    INSERT INTO devices (hostname, ip, mac, vendor, device_type, os, ttl, is_online, first_seen, last_seen)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                `).run(hostname, entry.ip, entry.mac, vendor, fp.deviceType, fp.os, fp.ttl, now, now);

                newDevices++;
                const newId = result.lastInsertRowid;
                const newDevice = {
                    id: newId, ...entry,
                    hostname, vendor, os: fp.os, ttl: fp.ttl,
                    device_type: fp.deviceType,
                    deviceIcon: fp.deviceIcon,
                    isNew: true,
                };
                db.prepare('INSERT INTO uptime_events (device_id, event) VALUES (?, \'online\')').run(newId);
                logEvent(newId, 'new_device', { ip: entry.ip, mac: entry.mac, vendor, hostname });
                devices.push(newDevice);
                emitFn?.('event', `✨ Nuevo: ${hostname || vendor || entry.mac} — ${entry.ip}`);
                telegram.notifyNewDevice(newDevice);
            }
        }

        if (i + batchSize < entries.length) {
            const done = Math.min(i + batchSize, entries.length);
            console.log(`[ARP] Fingerprinting: ${done}/${entries.length} dispositivos...`);
            emitFn?.('info', `Fingerprinting: ${done}/${entries.length}…`);
        }
    }

    // Marcar offline los que no aparecieron y notificar
    if (entries.length > 0) {
        const placeholders = entries.map(() => '?').join(',');
        const macs = entries.map(e => e.mac);

        // Capturar los que van a pasar a offline antes de actualizarlos
        const goingOffline = db.prepare(`
            SELECT id, ip, mac, hostname FROM devices
            WHERE mac NOT IN (${placeholders}) AND is_online = 1
        `).all(...macs);

        db.prepare(`
            UPDATE devices SET is_online = 0
            WHERE mac NOT IN (${placeholders}) AND is_online = 1
        `).run(...macs);

        for (const d of goingOffline) {
            db.prepare('INSERT INTO uptime_events (device_id, event) VALUES (?, \'offline\')').run(d.id);
            telegram.notifyDeviceOffline(d);
        }
    }

    const duration = Date.now() - startTime;
    const onlineCount = entries.length;
    const totalDevices = db.prepare('SELECT COUNT(*) as count FROM devices').get().count;

    // Registrar en historial
    db.prepare(`
    INSERT INTO scan_history (scan_type, devices_found, devices_online, new_devices, duration_ms)
    VALUES ('arp', ?, ?, ?, ?)
  `).run(totalDevices, onlineCount, newDevices, duration);

    console.log(`[ARP] Scan completado en ${duration}ms — ${onlineCount} online, ${newDevices} nuevos, ${totalDevices} total`);
    emitFn?.('success', `Scan completo en ${(duration/1000).toFixed(1)}s — ${onlineCount} online, ${newDevices} nuevos, ${totalDevices} total`);

    return {
        devices,
        stats: {
            total: totalDevices,
            online: onlineCount,
            newDevices,
            durationMs: duration,
        },
    };
}

module.exports = { scan, quickScan, parseArpTable, parseArpScanOutput, getLocalSubnets };
