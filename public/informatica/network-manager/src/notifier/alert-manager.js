/**
 * Alert Manager — detecta dispositivos que llevan demasiado tiempo offline.
 *
 * Flujo:
 *   scheduler llama a check(alertFn) después de cada quickScan / deep scan.
 *   check() consulta la DB, compara tiempo offline vs threshold,
 *   y llama a alertFn(device, offlineMin) por cada dispositivo que supere el umbral.
 *   La deduplicación evita re-alertar el mismo dispositivo dentro de DEDUP_MS.
 *
 * Configuración (tabla settings):
 *   alert_offline_enabled        '0' | '1'
 *   alert_offline_threshold_min  número (default 30)
 */

const { getDb }  = require('../db/database');
const telegram   = require('./telegram');

const DEDUP_MS = 30 * 60 * 1000;   // no re-alertar el mismo MAC en 30 min
const _alerted = new Map();         // mac → timestamp del último alert

// ── Helpers de configuración ────────────────────────────────────

function getSetting(key, fallback) {
    try {
        const row = getDb().prepare('SELECT value FROM settings WHERE key = ?').get(key);
        return row ? row.value : fallback;
    } catch { return fallback; }
}

function isEnabled()       { return getSetting('alert_offline_enabled', '0') === '1'; }
function getThresholdMin() { return Math.max(1, parseInt(getSetting('alert_offline_threshold_min', '30'), 10) || 30); }

// ── Lógica principal ─────────────────────────────────────────────

/**
 * Verifica todos los dispositivos offline contra el umbral configurado.
 * Llama a alertFn(device, offlineMin) por cada dispositivo que lo supere.
 * @param {Function} [alertFn]  - alertFn(device, offlineMin)
 */
function check(alertFn) {
    if (!isEnabled()) return;

    const threshold = getThresholdMin();
    const db  = getDb();
    const now = Date.now();

    // Dispositivos offline con la fecha de su último evento 'offline'
    const candidates = db.prepare(`
        SELECT d.id, d.mac, d.hostname, d.ip, d.device_type, d.is_critical,
               ue.ts AS offline_since
        FROM devices d
        JOIN (
            SELECT device_id, MAX(ts) AS ts
            FROM uptime_events
            WHERE event = 'offline'
            GROUP BY device_id
        ) ue ON ue.device_id = d.id
        WHERE d.is_online = 0
    `).all();

    for (const dev of candidates) {
        const offlineSince = new Date(dev.offline_since).getTime();
        if (isNaN(offlineSince)) continue;

        const offlineMin = (now - offlineSince) / 60000;
        if (offlineMin < threshold) continue;

        // Dedup
        const lastAlert = _alerted.get(dev.mac);
        if (lastAlert && now - lastAlert < DEDUP_MS) continue;

        _alerted.set(dev.mac, now);

        const isCritical = !!dev.is_critical;
        alertFn?.(dev, Math.round(offlineMin));

        // Only notify Telegram for critical devices — non-critical offline threshold
        // spam is suppressed (weekends, nights, nobody in office)
        if (isCritical) {
            telegram.enqueue(
                `🚨 <b>CRÍTICO — Sin respuesta</b>\n` +
                `${dev.hostname || dev.ip} lleva <b>${Math.round(offlineMin)} min</b> sin responder\n` +
                `IP: <code>${dev.ip}</code>  MAC: <code>${dev.mac}</code>`,
                { critical: true }
            );
        }
    }
}

/**
 * Limpia el estado de dedup de un dispositivo que volvió a estar online.
 * @param {string} mac
 */
function clearAlert(mac) { _alerted.delete(mac); }

/** Limpia todo el estado de dedup (útil al resetear la DB). */
function clearAll() { _alerted.clear(); }

module.exports = { check, clearAlert, clearAll };
