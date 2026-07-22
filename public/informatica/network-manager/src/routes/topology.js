/**
 * API Routes — Topología de red
 *
 * Construye un grafo jerárquico para vis.js:
 *
 *   Tier 0  — Subnets (nodo virtual por cada /24 detectada)
 *   Tier 1  — Gateways (.1 de cada subnet) + switches configurados
 *   Tier 2  — Dispositivos con conexión SNMP conocida
 *   Tier 3  — Resto de dispositivos (conectados a su gateway/subnet)
 *
 * El ID de cada nodo sigue estas convenciones:
 *   - Dispositivo real  → su MAC (para que los clicks del cliente funcionen)
 *   - Switch SNMP       → "sw_<id>"
 *   - Subnet virtual    → "net_<prefix>"
 */
const express  = require('express');
const { getDb } = require('../db/database.js');
const router   = express.Router();

// ── Icon group helper ─────────────────────────────────────────────────────────
function getGroup(device) {
    const type = (device.device_type ?? '').toLowerCase();
    const os   = (device.os ?? '').toLowerCase();
    const vnd  = (device.vendor ?? '').toLowerCase();

    if (type.includes('router') || type.includes('gateway') || type.includes('firewall')) return 'router';
    if (type.includes('switch'))     return 'switch';
    if (type.includes('impresora') || type.includes('printer'))  return 'printer';
    if (type.includes('teléfono') || type.includes('phone'))     return 'android';
    if (os.includes('android'))      return 'android';
    if (os.includes('ios') || os.includes('macos') || vnd.includes('apple')) return 'apple';
    if (os.includes('windows') || os.includes('linux')) return 'computer';
    return 'generic';
}

// ── Guess if a device is the subnet gateway ────────────────────────────────
function isGateway(ip) {
    const last = parseInt(ip.split('.').pop(), 10);
    return last === 1 || last === 254;
}

router.get('/', (req, res) => {
    const db = getDb();

    try {
        // Fetch all devices with their connection info
        const devices = db.prepare(`
            SELECT d.id, d.mac, d.ip, d.hostname, d.vendor, d.device_type,
                   d.os, d.is_online, d.ttl,
                   c.switch_ip, c.switch_name, c.port_name
            FROM devices d
            LEFT JOIN connections c ON c.device_id = d.id
        `).all();

        // Fetch configured switches
        const switches = db.prepare('SELECT * FROM switches').all();

        const nodes = [];
        const edges = [];
        const added = new Set();  // track node IDs to avoid duplicates

        const addNode = (n) => { if (!added.has(n.id)) { nodes.push(n); added.add(n.id); } };

        // ── Build subnet map ────────────────────────────────────────────────
        const subnetDevices = {};  // prefix → [device]
        for (const d of devices) {
            const parts = (d.ip ?? '').split('.');
            if (parts.length !== 4) continue;
            const prefix = parts.slice(0, 3).join('.');
            (subnetDevices[prefix] = subnetDevices[prefix] ?? []).push(d);
        }

        const subnets     = Object.keys(subnetDevices);
        const multiSubnet = subnets.length > 1;

        // ── Tier 0 — Subnet nodes (only when multiple subnets exist) ────────
        if (multiSubnet) {
            for (const prefix of subnets) {
                const count = subnetDevices[prefix].length;
                addNode({
                    id:    `net_${prefix}`,
                    label: `${prefix}.0/24`,
                    title: `Subred ${prefix}.0/24 — ${count} dispositivos`,
                    group: 'subnet',
                    level: 0,
                    shape: 'diamond',
                    size:  28,
                    font:  { size: 13, bold: true },
                    color: { background: '#1e3a4c', border: '#22d3ee', highlight: { background: '#164e63' } },
                });
            }
        }

        // ── Tier 1 — Gateways ───────────────────────────────────────────────
        for (const d of devices) {
            if (!isGateway(d.ip)) continue;

            const parts  = d.ip.split('.');
            const prefix = parts.slice(0, 3).join('.');

            addNode({
                id:    d.mac,
                label: d.hostname || d.ip,
                title: `Gateway: ${d.ip}\nVendor: ${d.vendor || 'N/A'}\nOS: ${d.os || 'N/A'}`,
                group: 'router',
                level: 1,
                color: d.is_online ? undefined : { background: '#374151', border: '#4b5563' },
            });

            // Connect gateway to its subnet node (multi-subnet) or leave as root (single)
            if (multiSubnet && added.has(`net_${prefix}`)) {
                edges.push({ from: `net_${prefix}`, to: d.mac, dashes: false, width: 2 });
            }
        }

        // ── Tier 1 — Configured switches (from switches table) ──────────────
        for (const sw of switches) {
            const swId = `sw_${sw.id}`;
            addNode({
                id:    swId,
                label: sw.name || sw.ip,
                title: `Switch: ${sw.name}\nIP: ${sw.ip}\nVendor: ${sw.vendor || 'N/A'}`,
                group: 'switch',
                level: 1,
                color: { background: '#1e3558', border: '#3b82f6' },
            });

            // Try to link switch to its gateway or subnet
            const parts  = (sw.ip ?? '').split('.');
            const prefix = parts.slice(0, 3).join('.');
            const gwDev  = devices.find(d => d.ip === `${prefix}.1` || d.ip === `${prefix}.254`);

            if (gwDev && added.has(gwDev.mac)) {
                edges.push({ from: gwDev.mac, to: swId, width: 2, dashes: false });
            } else if (multiSubnet && added.has(`net_${prefix}`)) {
                edges.push({ from: `net_${prefix}`, to: swId, width: 2 });
            }
        }

        // ── Tier 2 / 3 — All other devices ──────────────────────────────────
        for (const d of devices) {
            if (added.has(d.mac)) continue;  // gateway already added above

            const parts  = (d.ip ?? '').split('.');
            const prefix = parts.slice(0, 3).join('.');

            addNode({
                id:    d.mac,
                label: d.hostname || d.ip,
                title: `IP: ${d.ip}\nMAC: ${d.mac}\nVendor: ${d.vendor || 'N/A'}\nOS: ${d.os || '—'}\nTipo: ${d.device_type || '—'}`,
                group: getGroup(d),
                level: d.switch_ip ? 2 : 3,
                color: d.is_online ? undefined : { background: '#374151', border: '#4b5563', font: { color: '#6b7280' } },
            });

            let connected = false;

            // Prefer SNMP switch connection
            if (d.switch_ip) {
                const sw = switches.find(s => s.ip === d.switch_ip);
                if (sw && added.has(`sw_${sw.id}`)) {
                    edges.push({
                        from:  `sw_${sw.id}`,
                        to:    d.mac,
                        label: d.port_name || undefined,
                        title: d.port_name ? `Puerto: ${d.port_name}` : undefined,
                    });
                    connected = true;
                }
            }

            if (!connected) {
                // Fall back: connect to subnet gateway
                const gw = devices.find(g => g.ip === `${prefix}.1` || g.ip === `${prefix}.254`);
                if (gw && added.has(gw.mac)) {
                    edges.push({
                        from: gw.mac, to: d.mac,
                        color: { color: '#1e293b', opacity: 0.5 },
                        dashes: [4, 4],
                    });
                    connected = true;
                }
            }

            // Last resort: connect to subnet virtual node
            if (!connected && multiSubnet && added.has(`net_${prefix}`)) {
                edges.push({ from: `net_${prefix}`, to: d.mac, color: { opacity: 0.3 } });
            }
        }

        res.json({ nodes, edges });

    } catch (error) {
        console.error('[Topology] Error:', error.message);
        res.status(500).json({ message: error.message });
    }
});

module.exports = router;
