/**
 * API Routes — Bóveda de credenciales
 *
 * GET    /api/credentials          — listar
 * POST   /api/credentials          — crear
 * PUT    /api/credentials/:id      — actualizar
 * DELETE /api/credentials/:id      — eliminar
 * POST   /api/credentials/:id/link/:deviceId   — vincular a dispositivo
 * DELETE /api/credentials/:id/link/:deviceId   — desvincular
 */
const express  = require('express');
const router   = express.Router();
const { getDb } = require('../db/database');

// GET /api/credentials
router.get('/', (req, res) => {
    const db = getDb();
    try {
        const creds = db.prepare(`
            SELECT c.*,
                   GROUP_CONCAT(dc.device_id) AS linked_device_ids
            FROM credentials c
            LEFT JOIN device_credentials dc ON dc.credential_id = c.id
            GROUP BY c.id
            ORDER BY c.type, c.name
        `).all();

        // Parse linked_device_ids into arrays, hide password/key values
        const safe = creds.map(c => ({
            ...c,
            password:          c.password    ? '••••••' : '',
            private_key:       c.private_key ? '••••••' : '',
            _has_password:     !!c.password,
            _has_key:          !!c.private_key,
            linked_device_ids: c.linked_device_ids
                ? c.linked_device_ids.split(',').map(Number)
                : [],
        }));

        res.json({ success: true, credentials: safe });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/credentials
router.post('/', (req, res) => {
    const db = getDb();
    const { name, type, username, password, private_key } = req.body;

    if (!name || !type) {
        return res.status(400).json({ success: false, error: 'name y type son obligatorios' });
    }

    try {
        const result = db.prepare(`
            INSERT INTO credentials (name, type, username, password, private_key)
            VALUES (?, ?, ?, ?, ?)
        `).run(
            name.trim(),
            type.trim(),
            (username ?? '').trim(),
            password    ?? '',
            private_key ?? '',
        );
        res.json({ success: true, id: result.lastInsertRowid });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// PUT /api/credentials/:id
router.put('/:id', (req, res) => {
    const db = getDb();
    const { name, type, username, password, private_key } = req.body;

    try {
        const existing = db.prepare('SELECT id FROM credentials WHERE id = ?').get(req.params.id);
        if (!existing) return res.status(404).json({ success: false, error: 'No encontrada' });

        db.prepare(`
            UPDATE credentials
            SET name = COALESCE(?, name),
                type = COALESCE(?, type),
                username = COALESCE(?, username),
                password = CASE WHEN ? != '' THEN ? ELSE password END,
                private_key = CASE WHEN ? != '' THEN ? ELSE private_key END,
                updated_at = datetime('now','localtime')
            WHERE id = ?
        `).run(name, type, username, password ?? '', password, private_key ?? '', private_key, req.params.id);

        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/credentials/:id
router.delete('/:id', (req, res) => {
    const db = getDb();
    try {
        db.prepare('DELETE FROM credentials WHERE id = ?').run(req.params.id);
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/credentials/:id/link/:deviceId
router.post('/:id/link/:deviceId', (req, res) => {
    const db = getDb();
    try {
        db.prepare(
            'INSERT OR IGNORE INTO device_credentials (device_id, credential_id) VALUES (?, ?)'
        ).run(req.params.deviceId, req.params.id);
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/credentials/:id/link/:deviceId
router.delete('/:id/link/:deviceId', (req, res) => {
    const db = getDb();
    try {
        db.prepare(
            'DELETE FROM device_credentials WHERE device_id = ? AND credential_id = ?'
        ).run(req.params.deviceId, req.params.id);
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
