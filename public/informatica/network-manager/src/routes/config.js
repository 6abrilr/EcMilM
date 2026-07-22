/**
 * ============================================================
 *  NetMonitor Stealth — src/routes/config.js
 *  API Routes: Configuration & Maintenance
 *
 *  FIX: Added POST /api/config/reset endpoint.
 *  Previously the frontend called this route but it didn't
 *  exist, causing a 404 on the "Reset DB" action.
 * ============================================================
 */

const express = require('express');
const router  = express.Router();
const config  = require('../../config');
const { getDb } = require('../db/database');

// ── GET /api/config ─────────────────────────────────────────
// Returns the current (non-sensitive) server configuration.
router.get('/', (req, res) => {
    res.json({
        success: true,
        config: {
            server: {
                host: config.server.host,
                port: config.server.port,
            },
            scanner: { ...config.scanner },
            snmp: {
                enabled:    config.snmp.enabled,
                intervalMs: config.snmp.intervalMs,
            },
        },
    });
});

// ── POST /api/config/reset ───────────────────────────────────
// Wipes the device inventory AND scan history.
// Protected with a confirmation check on the frontend.
router.post('/reset', (req, res) => {
    const db = getDb();
    try {
        // Use a transaction so both deletes succeed or both roll back
        const resetAll = db.transaction(() => {
            const devicesResult = db.prepare('DELETE FROM devices').run();
            db.prepare('DELETE FROM scan_history').run();
            db.prepare('DELETE FROM connections').run();
            db.prepare('DELETE FROM services').run();
            db.prepare('DELETE FROM device_tags').run();
            return devicesResult.changes;
        });

        const deleted = resetAll();

        console.log(`[Config] Reset ejecutado: ${deleted} dispositivos eliminados`);

        res.json({
            success: true,
            message: `Inventario reseteado. ${deleted} dispositivos eliminados.`,
            deleted,
        });
    } catch (err) {
        console.error('[Config] Error en reset:', err.message);
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
