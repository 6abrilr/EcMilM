const express = require('express');
const router  = express.Router();
const { getDb } = require('../db/database');

// GET /api/settings — devuelve todas las claves
router.get('/', (req, res) => {
    const db = getDb();
    try {
        const rows = db.prepare('SELECT key, value FROM settings').all();
        const settings = {};
        rows.forEach(r => { settings[r.key] = r.value; });
        res.json({ success: true, settings });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/settings — guarda un objeto { key: value, ... }
router.post('/', (req, res) => {
    const db = getDb();
    const entries = Object.entries(req.body ?? {});
    if (!entries.length) {
        return res.status(400).json({ success: false, error: 'No hay valores para guardar' });
    }
    try {
        const upsert = db.prepare(
            'INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)'
        );
        const save = db.transaction(() => {
            for (const [key, value] of entries) {
                upsert.run(String(key), String(value ?? ''));
            }
        });
        save();
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/settings/test — envía un mensaje de prueba a Telegram
router.post('/test', async (req, res) => {
    try {
        const telegram = require('../notifier/telegram');
        telegram.enqueue('🔔 <b>Test NetMonitor</b>\nConexión con Telegram funcionando correctamente.');
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
