/**
 * device-events.js — helper to log rich device events into device_events table.
 * Kept separate so scanner, SNMP client and routes can all require it without circular deps.
 */
const { getDb } = require('./database');

/**
 * @param {number|string} deviceId
 * @param {string} eventType  — 'new_device' | 'hostname_changed' | 'ip_changed' | 'port_changed' | 'note_updated'
 * @param {object} [detail]   — free-form context object, stored as JSON
 */
function logEvent(deviceId, eventType, detail = {}) {
    try {
        getDb()
            .prepare('INSERT INTO device_events (device_id, event_type, detail) VALUES (?, ?, ?)')
            .run(deviceId, eventType, JSON.stringify(detail));
    } catch (e) {
        // Non-fatal — never crash the caller
        console.warn(`[DeviceEvents] Failed to log ${eventType} for device ${deviceId}:`, e.message);
    }
}

module.exports = { logEvent };
