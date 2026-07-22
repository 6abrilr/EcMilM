/**
 * Telegram Notifier — eventos selectivos, no invasivos.
 *
 * Eventos soportados (cada uno con su toggle en settings):
 *   telegram_event_new_device  — nuevo MAC en la red
 *   telegram_event_offline     — dispositivo crítico se fue offline
 *   telegram_event_roaming     — MAC apareció en otra boca del switch
 *   telegram_event_daily       — resumen diario (disparado por scheduler)
 *
 * Deduplicación: el mismo evento no se reenvía dentro de DEDUP_MS.
 * Rate limit: máximo 1 mensaje por segundo (límite de la API de Telegram).
 */

const https = require('https');
const { getDb } = require('../db/database');

const DEDUP_MS      = 5  * 60 * 1000;   // 5 min — evita spam del mismo evento
const RATE_MS       = 1200;              // ≥1s entre mensajes (API limit)
const FAIL_LIMIT    = 3;                 // fallos consecutivos antes de cooldown
const COOLDOWN_MS   = 5  * 60 * 1000;   // 5 min sin reintentar tras FAIL_LIMIT fallos

const _dedup        = new Map();
let   _lastSent     = 0;
const _queue        = [];
let   _sending      = false;
let   _failCount    = 0;
let   _cooldownUntil = 0;

// ── Helpers de configuración ────────────────────────────────────

function getSetting(key, fallback = '') {
    try {
        const row = getDb()
            .prepare('SELECT value FROM settings WHERE key = ?')
            .get(key);
        return row ? row.value : fallback;
    } catch { return fallback; }
}

function isEnabled()  { return getSetting('telegram_enabled',  '0') === '1'; }
function getToken()   { return getSetting('telegram_token',    ''); }
function getChatId()  { return getSetting('telegram_chat_id',  ''); }
function eventOn(k)   { return getSetting(k, '1') === '1'; }

/**
 * Returns true if current time is within the configured notification schedule.
 * Settings:
 *   telegram_schedule_days  — string of active ISO weekday numbers "12345" (Mon=1 … Sun=7)
 *   telegram_schedule_from  — HH:MM start
 *   telegram_schedule_to    — HH:MM end
 * If none configured, always returns true.
 * Critical alerts always bypass the schedule.
 */
/**
 * Schedule format (stored as JSON in settings key 'telegram_schedule_groups'):
 * [
 *   { days: "12345", from: "08:00", to: "20:00" },
 *   { days: "6",     from: "10:00", to: "14:00" },
 *   { days: "7",     from: "",      to: ""       }  ← empty = silent all day
 * ]
 * Falls back to legacy keys telegram_schedule_days/from/to if no groups set.
 */
function getScheduleGroups() {
    const raw = getSetting('telegram_schedule_groups', '');
    if (raw) {
        try { return JSON.parse(raw); } catch { /* fall through */ }
    }
    // Legacy single-group fallback
    const days = getSetting('telegram_schedule_days', '');
    const from = getSetting('telegram_schedule_from', '');
    const to   = getSetting('telegram_schedule_to',   '');
    if (!days && !from && !to) return null; // no schedule = always on
    return [{ days, from, to }];
}

function timeInWindow(from, to) {
    if (!from || !to) return false; // empty window = silent
    const now  = new Date();
    const cur  = now.getHours() * 60 + now.getMinutes();
    const [fh, fm] = from.split(':').map(Number);
    const [th, tm] = to.split(':').map(Number);
    const startMin = fh * 60 + fm;
    const endMin   = th * 60 + tm;
    if (startMin <= endMin) return cur >= startMin && cur <= endMin;
    return cur >= startMin || cur <= endMin; // overnight
}

function isWithinSchedule(isCritical = false) {
    if (isCritical) return true;

    const groups = getScheduleGroups();
    if (!groups) return true; // no schedule configured → always on

    const now    = new Date();
    const jsDay  = now.getDay();
    const isoDay = String(jsDay === 0 ? 7 : jsDay); // Sun→7

    for (const g of groups) {
        if (!g.days || !g.days.includes(isoDay)) continue;
        // This group covers today — check time window
        return timeInWindow(g.from, g.to);
    }

    // Today not covered by any group → silent
    return false;
}

// ── Deduplicación ───────────────────────────────────────────────

function isDuplicate(key) {
    const now  = Date.now();
    const last = _dedup.get(key);
    if (last && now - last < DEDUP_MS) return true;
    _dedup.set(key, now);
    return false;
}

// ── Envío con rate-limit y cola ─────────────────────────────────

function _sendRaw(token, chatId, text) {
    return new Promise((resolve) => {
        const body = JSON.stringify({
            chat_id:    chatId,
            text,
            parse_mode: 'HTML',
        });
        const req = https.request({
            hostname: 'api.telegram.org',
            path:     `/bot${token}/sendMessage`,
            method:   'POST',
            headers:  {
                'Content-Type':   'application/json',
                'Content-Length': Buffer.byteLength(body),
            },
        }, (res) => {
            res.resume();
            res.on('end', () => { _failCount = 0; resolve(true); });
        });
        req.on('error', () => resolve(false));
        req.setTimeout(6000, () => { req.destroy(); resolve(false); });
        req.write(body);
        req.end();
    });
}

async function _drain() {
    if (_sending || !_queue.length) return;

    // Circuit breaker: en cooldown, descartar cola silenciosamente
    if (Date.now() < _cooldownUntil) {
        _queue.length = 0;
        return;
    }

    _sending = true;

    while (_queue.length) {
        const msg  = _queue.shift();
        const wait = RATE_MS - (Date.now() - _lastSent);
        if (wait > 0) await new Promise(r => setTimeout(r, wait));

        const ok = await _sendRaw(getToken(), getChatId(), msg);
        _lastSent = Date.now();

        if (!ok) {
            _failCount++;
            if (_failCount >= FAIL_LIMIT) {
                const mins = Math.round(COOLDOWN_MS / 60000);
                console.warn(`[Telegram] Sin conexión — pausando notificaciones ${mins} min.`);
                _cooldownUntil = Date.now() + COOLDOWN_MS;
                _queue.length  = 0;   // vaciar cola acumulada
                break;
            }
        }
    }

    _sending = false;
}

function enqueue(msg, { critical = false } = {}) {
    if (!isEnabled()) return;
    if (!isWithinSchedule(critical)) {
        console.log('[Telegram] Fuera de ventana horaria, mensaje omitido.');
        return;
    }
    const token  = getToken();
    const chatId = getChatId();
    if (!token || !chatId) {
        console.warn('[Telegram] Token o Chat ID no configurado, se omite mensaje.');
        return;
    }
    _queue.push(msg);
    _drain().catch(e => console.error('[Telegram] drain error:', e.message));
}

// ── Eventos públicos ────────────────────────────────────────────

function notifyNewDevice(device) {
    if (!eventOn('telegram_event_new_device')) return;
    if (isDuplicate(`new_${device.mac}`)) return;

    enqueue(
        `🆕 <b>Nuevo dispositivo detectado</b>\n` +
        `IP: <code>${device.ip}</code>\n` +
        `MAC: <code>${device.mac}</code>\n` +
        `Hostname: ${device.hostname || '—'}\n` +
        `Vendor: ${device.vendor || '—'}\n` +
        `Tipo: ${device.device_type || '—'}`
    );
}

function notifyDeviceOffline(device) {
    if (!eventOn('telegram_event_offline')) return;
    if (isDuplicate(`offline_${device.mac}`)) return;

    const isCritical = !!device.is_critical;
    const prefix = isCritical ? '🚨 <b>CRÍTICO — Dispositivo offline</b>' : '🔴 <b>Dispositivo offline</b>';
    enqueue(
        `${prefix}\n` +
        `IP: <code>${device.ip}</code>\n` +
        `Hostname: ${device.hostname || '—'}\n` +
        `MAC: <code>${device.mac}</code>`,
        { critical: isCritical }
    );
}

function notifyMacRoaming(device, oldPort, newPort, switchName) {
    if (!eventOn('telegram_event_roaming')) return;
    if (isDuplicate(`roaming_${device.mac}_${newPort}`)) return;

    enqueue(
        `⚠️ <b>MAC Roaming detectado</b>\n` +
        `Dispositivo: ${device.hostname || device.ip}\n` +
        `MAC: <code>${device.mac}</code>\n` +
        `Switch: ${switchName}\n` +
        `Puerto anterior: <code>${oldPort}</code> → <code>${newPort}</code>`
    );
}

function sendDailySummary(stats) {
    if (!eventOn('telegram_event_daily')) return;

    enqueue(
        `📋 <b>Resumen diario — NetMonitor</b>\n` +
        `Total: ${stats.total} dispositivos\n` +
        `🟢 Online: ${stats.online}  🔴 Offline: ${stats.offline}\n` +
        `🆕 Nuevos hoy: ${stats.newToday}\n` +
        `Último scan: ${stats.lastScan || '—'}`
    );
}

module.exports = {
    notifyNewDevice,
    notifyDeviceOffline,
    notifyMacRoaming,
    sendDailySummary,
    enqueue,       // para mensajes manuales de test
    getSetting,
};
