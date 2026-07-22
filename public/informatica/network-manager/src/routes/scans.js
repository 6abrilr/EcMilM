/**
 * API Routes — Escaneos
 */
const express = require('express');
const router = express.Router();
const { getDb } = require('../db/database');
const scheduler = require('../scanner/scheduler');

// POST /api/scan/trigger — Forzar escaneo manual
router.post('/', async (req, res) => {
    try {
        const result = await scheduler.triggerManualScan();
        if (result) {
            res.json({ success: true, ...result });
        } else {
            res.json({ success: false, error: 'Scan ya en progreso o falló' });
        }
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/scan/status — Estado del scheduler
router.get('/status', (req, res) => {
    res.json({ success: true, ...scheduler.getStatus() });
});

// GET /api/scan/history — Historial de escaneos
router.get('/history', (req, res) => {
    const db = getDb();
    const limit = parseInt(req.query.limit) || 50;
    try {
        const history = db.prepare(
            'SELECT * FROM scan_history ORDER BY id DESC LIMIT ?'
        ).all(limit);
        res.json({ success: true, history });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
