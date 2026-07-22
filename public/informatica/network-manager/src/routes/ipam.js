/**
 * API Routes — IPAM (subnet map)
 *
 * GET /api/ipam?subnet=192.168.1
 *   Returns all 254 host slots for the given /24 prefix.
 *   If no subnet param, auto-detects from the most common /24 in devices.
 *
 * GET /api/ipam/subnets
 *   Returns the list of /24 prefixes that have at least one known device.
 */
const express = require('express');
const router  = express.Router();
const { getDb } = require('../db/database');

// GET /api/ipam/subnets
router.get('/subnets', (req, res) => {
    const db = getDb();
    try {
        const devices = db.prepare('SELECT ip FROM devices').all();
        const counts  = {};
        for (const { ip } of devices) {
            const parts = (ip ?? '').split('.');
            if (parts.length !== 4) continue;
            const prefix = parts.slice(0, 3).join('.');
            counts[prefix] = (counts[prefix] ?? 0) + 1;
        }
        const subnets = Object.entries(counts)
            .sort((a, b) => b[1] - a[1])
            .map(([prefix, count]) => ({ prefix, count }));
        res.json({ success: true, subnets });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/ipam/pinned — list pinned IPs
router.get('/pinned', (req, res) => {
    const db = getDb();
    try {
        const row  = db.prepare("SELECT value FROM settings WHERE key = 'ipam_pinned'").get();
        const pins = row ? JSON.parse(row.value || '[]') : [];
        res.json({ success: true, pinned: pins });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/ipam/pin — add a pinned IP { ip, label, color }
router.post('/pin', (req, res) => {
    const db = getDb();
    const { ip, label, color } = req.body ?? {};
    if (!ip || !/^\d{1,3}(\.\d{1,3}){3}$/.test(ip)) {
        return res.status(400).json({ success: false, error: 'IP inválida' });
    }
    try {
        const row  = db.prepare("SELECT value FROM settings WHERE key = 'ipam_pinned'").get();
        const pins = row ? JSON.parse(row.value || '[]') : [];
        if (!pins.find(p => p.ip === ip)) {
            pins.push({ ip, label: label || ip, color: color || '#f59e0b' });
            db.prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ipam_pinned', ?)")
              .run(JSON.stringify(pins));
        }
        res.json({ success: true, pinned: pins });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/ipam/pin — remove a pinned IP { ip }
router.delete('/pin', (req, res) => {
    const db = getDb();
    const { ip } = req.body ?? {};
    try {
        const row  = db.prepare("SELECT value FROM settings WHERE key = 'ipam_pinned'").get();
        const pins = row ? JSON.parse(row.value || '[]') : [];
        const next = pins.filter(p => p.ip !== ip);
        db.prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('ipam_pinned', ?)")
          .run(JSON.stringify(next));
        res.json({ success: true, pinned: next });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/ipam
router.get('/', (req, res) => {
    const db = getDb();
    try {
        const devices = db.prepare(
            `SELECT d.ip, d.hostname, d.mac, d.vendor, d.device_type, d.os, d.is_online, d.id
             FROM devices d`
        ).all();

        // Auto-detect subnet if not provided
        let subnet = (req.query.subnet ?? '').trim();
        if (!subnet) {
            const counts = {};
            for (const { ip } of devices) {
                const parts = (ip ?? '').split('.');
                if (parts.length !== 4) continue;
                const prefix = parts.slice(0, 3).join('.');
                counts[prefix] = (counts[prefix] ?? 0) + 1;
            }
            subnet = Object.entries(counts).sort((a, b) => b[1] - a[1])[0]?.[0] ?? '';
        }

        if (!subnet) {
            return res.json({ success: true, subnet: '', slots: [] });
        }

        // Build a map: last-octet → device
        const byHost = {};
        for (const d of devices) {
            const parts = (d.ip ?? '').split('.');
            if (parts.length !== 4) continue;
            const prefix = parts.slice(0, 3).join('.');
            if (prefix !== subnet) continue;
            const host = parseInt(parts[3], 10);
            if (host >= 1 && host <= 254) byHost[host] = d;
        }

        // Build full 254-slot array
        const slots = [];
        for (let i = 1; i <= 254; i++) {
            const d = byHost[i];
            slots.push({
                host:        i,
                ip:          `${subnet}.${i}`,
                known:       !!d,
                is_online:   d?.is_online ?? null,
                hostname:    d?.hostname  ?? '',
                mac:         d?.mac       ?? '',
                vendor:      d?.vendor    ?? '',
                device_type: d?.device_type ?? '',
                os:          d?.os        ?? '',
                id:          d?.id        ?? null,
            });
        }

        // Merge pinned IPs — mark them even if not in ARP table
        const pinRow = db.prepare("SELECT value FROM settings WHERE key = 'ipam_pinned'").get();
        const pins   = pinRow ? JSON.parse(pinRow.value || '[]') : [];
        for (const pin of pins) {
            const parts = pin.ip.split('.');
            if (parts.slice(0, 3).join('.') !== subnet) continue;
            const host = parseInt(parts[3], 10);
            if (host < 1 || host > 254) continue;
            Object.assign(slots[host - 1], {
                pinned:    true,
                pin_label: pin.label || pin.ip,
                pin_color: pin.color || '#f59e0b',
            });
        }

        res.json({ success: true, subnet, slots });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
