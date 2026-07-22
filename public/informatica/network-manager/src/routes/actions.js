const express = require('express');
const { exec } = require('child_process');
const dgram    = require('dgram');
const net      = require('net');
const https    = require('https');
const os       = require('os');

const router = express.Router();

function sanitizeIp(ip) {
    if (/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) return ip;
    return null;
}

// ── POST /api/actions/ping — quick single ping ────────────────────────────────
router.post('/ping', (req, res) => {
    const ip = sanitizeIp(req.body.ip);
    if (!ip) return res.status(400).json({ message: 'Invalid IP address format' });

    const command = os.platform() === 'win32'
        ? `ping -n 1 -w 1000 ${ip}`
        : `ping -c 1 -W 1 ${ip}`;

    exec(command, { timeout: 2000 }, (err, stdout, stderr) => {
        if (err || stderr) {
            return res.json({ success: false, message: 'Host unreachable.', output: (stdout||'')+(stderr||'') });
        }
        res.json({ success: true, message: 'Host is reachable.', output: stdout });
    });
});

// ── POST /api/actions/ping-detail — 4 pings with RTT stats ───────────────────
router.post('/ping-detail', (req, res) => {
    const ip = sanitizeIp(req.body.ip);
    if (!ip) return res.status(400).json({ success: false, message: 'IP inválida' });

    const cmd = os.platform() === 'win32'
        ? `ping -n 4 -w 1500 ${ip}`
        : `ping -c 4 -W 1 ${ip}`;

    exec(cmd, { timeout: 12000 }, (err, stdout) => {
        const out = stdout || '';

        // Windows EN: Minimum = Xms, Maximum = Xms, Average = Xms
        // Windows ES: Mínimo = Xms, Máximo = Xms, Media = Xms (o Promedio)
        const rttEN = out.match(/Minimum\s*=\s*(\d+)ms,\s*Maximum\s*=\s*(\d+)ms,\s*Average\s*=\s*(\d+)ms/i);
        const rttES = out.match(/[Mm]ín\w*\s*=\s*(\d+)ms,\s*[Mm]áx\w*\s*=\s*(\d+)ms,\s*(?:Media|Promedio)\s*=\s*(\d+)ms/i);
        // Linux: rtt min/avg/max/mdev = 0.123/0.456/0.789/0.012 ms
        const rttLinux = out.match(/=\s*([\d.]+)\/([\d.]+)\/([\d.]+)/);
        const rtt   = rttEN || rttES || rttLinux;

        // Packets sent/received (EN/ES)
        const pktEN = out.match(/Sent\s*=\s*(\d+),\s*Received\s*=\s*(\d+)/i);
        const pktES = out.match(/enviados\s*=\s*(\d+),\s*recibidos\s*=\s*(\d+)/i);
        const pkt   = pktEN || pktES;

        const sent     = pkt ? parseInt(pkt[1]) : 4;
        const received = pkt ? parseInt(pkt[2]) : (err ? 0 : 4);
        const loss     = Math.round((sent - received) / sent * 100);

        res.json({
            success:   !err && received > 0,
            reachable: received > 0,
            min:   rtt ? parseInt(rtt[1]) : null,
            max:   rtt ? parseInt(rtt[2]) : null,
            avg:   rtt ? parseInt(rtt[3]) : null,
            sent, received, loss,
        });
    });
});

// ── POST /api/actions/portscan — TCP connect on common ports ──────────────────
router.post('/portscan', (req, res) => {
    const ip = sanitizeIp(req.body.ip);
    if (!ip) return res.status(400).json({ success: false, message: 'IP inválida' });

    const PORTS = [
        { port: 21,   service: 'FTP'       },
        { port: 22,   service: 'SSH'       },
        { port: 23,   service: 'Telnet'    },
        { port: 80,   service: 'HTTP'      },
        { port: 135,  service: 'RPC'       },
        { port: 139,  service: 'NetBIOS'   },
        { port: 443,  service: 'HTTPS'     },
        { port: 445,  service: 'SMB'       },
        { port: 3306, service: 'MySQL'     },
        { port: 3389, service: 'RDP'       },
        { port: 5900, service: 'VNC'       },
        { port: 8080, service: 'HTTP-Alt'  },
        { port: 8443, service: 'HTTPS-Alt' },
        { port: 9100, service: 'Printer'   },
        { port: 161,  service: 'SNMP'      },
    ];

    const checks = PORTS.map(({ port, service }) => new Promise(resolve => {
        const sock = net.createConnection({ host: ip, port });
        sock.setTimeout(1200);
        const done = (open) => { try { sock.destroy(); } catch (_) {} resolve({ port, service, open }); };
        sock.once('connect', () => done(true));
        sock.once('timeout', () => done(false));
        sock.once('error',   () => done(false));
    }));

    Promise.all(checks)
        .then(results => res.json({ success: true, ports: results }))
        .catch(err    => res.status(500).json({ success: false, error: err.message }));
});

// ── POST /api/actions/traceroute ──────────────────────────────────────────────
router.post('/traceroute', (req, res) => {
    const ip = sanitizeIp(req.body.ip);
    if (!ip) return res.status(400).json({ success: false, message: 'IP inválida' });

    const cmd = os.platform() === 'win32'
        ? `tracert -d -h 15 -w 500 ${ip}`
        : `traceroute -n -m 15 -w 1 ${ip}`;

    exec(cmd, { timeout: 35000 }, (err, stdout) => {
        const hops = [];
        for (const line of (stdout || '').split('\n')) {
            // Match: "  1    <1 ms    <1 ms    <1 ms  192.168.1.1"
            const m = line.match(/^\s+(\d+)\s+(.*)/);
            if (!m) continue;
            const hop = parseInt(m[1]);
            if (isNaN(hop) || hop > 30) continue;

            const ipMatch  = m[2].match(/(\d{1,3}(?:\.\d{1,3}){3})\s*$/);
            const rttMatch = m[2].match(/(<?\d+)\s*ms/i);

            hops.push({
                hop,
                ip:      ipMatch  ? ipMatch[1]  : '*',
                rtt:     rttMatch ? rttMatch[1] + ' ms' : '*',
                timeout: !ipMatch,
            });
        }
        res.json({ success: true, hops, raw: stdout });
    });
});

// ── GET /api/actions/speedtest — server-side internet speed ──────────────────
router.get('/speedtest', (req, res) => {
    // Download 10 MB from Cloudflare's speed test endpoint
    const TEST_URL = 'https://speed.cloudflare.com/__down?bytes=10000000';
    const start    = Date.now();
    let   bytes    = 0;

    const request = https.get(TEST_URL, (response) => {
        response.on('data', chunk => { bytes += chunk.length; });
        response.on('end',  () => {
            if (res.headersSent) return;
            const elapsedSec = (Date.now() - start) / 1000;
            const mbps       = ((bytes * 8) / elapsedSec / 1_000_000).toFixed(2);
            res.json({ success: true, bytes, elapsed: elapsedSec.toFixed(2), mbps });
        });
        response.on('error', err => {
            if (!res.headersSent) res.status(500).json({ success: false, error: err.message });
        });
    });

    request.on('error', err => {
        if (!res.headersSent) res.status(500).json({ success: false, error: err.message });
    });
    request.setTimeout(20000, () => {
        request.destroy();
        if (!res.headersSent) res.status(504).json({ success: false, error: 'Timeout' });
    });
});

// POST /api/actions/wol — Wake-on-LAN magic packet
router.post('/wol', (req, res) => {
    const rawMac = (req.body.mac ?? '').replace(/[:\-]/g, '').toLowerCase();

    if (!/^[0-9a-f]{12}$/.test(rawMac)) {
        return res.status(400).json({ success: false, message: 'MAC inválida' });
    }

    // Magic packet: 6 bytes 0xFF + MAC repetida 16 veces = 102 bytes
    const macBytes    = Buffer.from(rawMac, 'hex');
    const magic       = Buffer.alloc(102);
    magic.fill(0xff, 0, 6);
    for (let i = 0; i < 16; i++) macBytes.copy(magic, 6 + i * 6);

    const sock = dgram.createSocket('udp4');
    sock.once('error', (err) => {
        sock.close();
        res.status(500).json({ success: false, message: err.message });
    });
    sock.bind(() => {
        sock.setBroadcast(true);
        sock.send(magic, 0, magic.length, 9, '255.255.255.255', (err) => {
            sock.close();
            if (err) return res.status(500).json({ success: false, message: err.message });
            res.json({ success: true, message: `WoL enviado a ${req.body.mac}` });
        });
    });
});

// ── Helpers for geo-traceroute ────────────────────────────────────────────────

function sanitizeHost(h) {
    if (!h) return null;
    const ip = sanitizeIp(h);
    if (ip) return ip;
    // Basic hostname: letters, digits, dots, hyphens only
    if (/^[a-zA-Z0-9][a-zA-Z0-9.\-]{0,252}$/.test(h)) return h;
    return null;
}

function isPrivateIp(ip) {
    if (!ip || ip === '*') return true;
    const p = ip.split('.').map(Number);
    return p[0] === 10 || p[0] === 127 ||
        (p[0] === 172 && p[1] >= 16 && p[1] <= 31) ||
        (p[0] === 192 && p[1] === 168);
}

function parseHops(stdout) {
    const hops = [];
    for (const line of (stdout || '').split('\n')) {
        const m = line.match(/^\s+(\d+)\s+(.*)/);
        if (!m) continue;
        const hop = parseInt(m[1]);
        if (isNaN(hop) || hop > 30) continue;
        const ipMatch  = m[2].match(/(\d{1,3}(?:\.\d{1,3}){3})\s*$/);
        const rttMatch = m[2].match(/(<?\d+)\s*ms/i);
        hops.push({
            hop,
            ip:      ipMatch  ? ipMatch[1]  : null,
            rtt:     rttMatch ? rttMatch[1] + ' ms' : '*',
            timeout: !ipMatch,
        });
    }
    return hops;
}

// ── Offline enrichment helpers ────────────────────────────────────────────────

/** Reverse DNS lookup — uses the system/LAN DNS resolver (no internet needed for LAN PTRs). */
function reverseLookup(ip) {
    const dns = require('dns').promises;
    return Promise.race([
        dns.reverse(ip).then(hosts => hosts[0] ?? null).catch(() => null),
        new Promise(resolve => setTimeout(() => resolve(null), 1800)),
    ]);
}

/** Look up a LAN device by IP from our own inventory DB. */
function lookupDeviceByIp(ip) {
    try {
        const { getDb } = require('../db/database');
        const db = getDb();
        return db.prepare(`
            SELECT d.id, d.hostname, d.mac, d.vendor, d.device_type, d.os,
                   c.switch_name, c.switch_ip, c.port_name, c.vlan, c.speed
            FROM devices d
            LEFT JOIN connections c ON c.device_id = d.id
            WHERE d.ip = ?
        `).get(ip) ?? null;
    } catch { return null; }
}

// ── POST /api/actions/geo-traceroute — 100% offline ──────────────────────────
router.post('/geo-traceroute', (req, res) => {
    const target = sanitizeHost(req.body.target ?? req.body.ip ?? '');
    if (!target) return res.status(400).json({ success: false, message: 'Destino inválido' });

    const cmd = os.platform() === 'win32'
        ? `tracert -d -h 20 -w 800 ${target}`
        : `traceroute -n -m 20 -w 1 ${target}`;

    exec(cmd, { timeout: 45000 }, async (err, stdout) => {
        try {
            const hops = parseHops(stdout || '');

            // Enrich every hop in parallel
            const enriched = await Promise.all(hops.map(async hop => {
                if (!hop.ip) return { ...hop, isPrivate: false, device: null, ptr: null };

                const isPrivate = isPrivateIp(hop.ip);

                // LAN hops → full device info from our DB (no network call)
                const device = isPrivate ? lookupDeviceByIp(hop.ip) : null;

                // Public hops → reverse DNS (uses local resolver; works offline for many ISPs)
                const ptr = !isPrivate ? await reverseLookup(hop.ip) : null;

                return { ...hop, isPrivate, device, ptr };
            }));

            res.json({ success: true, target, hops: enriched });
        } catch (e) {
            res.status(500).json({ success: false, error: e.message });
        }
    });
});

// ── POST /api/actions/message — envía mensaje popup a host Windows vía WMI ───
// Body: { ip, username, password, text }
router.post('/message', (req, res) => {
    const ip  = sanitizeIp(req.body.ip);
    const usr = (req.body.username || '').replace(/[^a-zA-Z0-9_.\\-]/g, '');
    const pwd = (req.body.password || '').replace(/["\\]/g, '');
    const txt = (req.body.text    || '').replace(/["\\]/g, '').slice(0, 200);

    if (!ip)  return res.status(400).json({ success: false, error: 'IP inválida' });
    if (!usr) return res.status(400).json({ success: false, error: 'Usuario requerido' });
    if (!txt) return res.status(400).json({ success: false, error: 'Mensaje vacío' });

    // wmiexec.py ejecuta: msg * "texto" en el host remoto
    const cmd = `wmiexec.py "${usr}":"${pwd}"@${ip} "msg * \\"${txt}\\""`;
    exec(cmd, { timeout: 15000 }, (err, stdout, stderr) => {
        const output = (stdout || '') + (stderr || '');
        if (err && !output.includes('[-]') === false) {
            return res.json({ success: false, error: output.trim() || err.message });
        }
        res.json({ success: true, output: output.trim() });
    });
});

module.exports = router;
