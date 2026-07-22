/**
 * Scheduler — two operating modes:
 *
 *  'watch'    (default after initial deep scan)
 *             Runs quickScan every ~45 s.  Only emits Socket.io events
 *             when device state actually changes.  Near-zero network traffic.
 *
 *  'periodic' Legacy mode: full ARP scan (ping sweep) every N minutes.
 *
 * Socket.io events emitted:
 *   scan:log       { msg, type, ts }   — live console line
 *   scan:complete  { stats }           — deep scan finished
 *   scan:mode      { mode }            — mode changed
 *   dashboard:refresh                  — tell all clients to reload device list
 */
const config        = require('../../config');
const arpScanner    = require('./arp-scanner');
const snmpClient    = require('./snmp-client');
const alertManager  = require('../notifier/alert-manager');

// ── State ─────────────────────────────────────────────────────────────────────
let io          = null;
let mode        = 'periodic'; // 'watch' | 'periodic' | 'scanning'
let isScanning  = false;
let arpTimer    = null;   // periodic mode timeout
let watchTimer  = null;   // watch mode interval
let snmpTimer   = null;   // independent SNMP poll interval (5 min)
let dailyTimer  = null;
let lastScanTime = null;
let nextScanTime = null;

// ── Helpers ───────────────────────────────────────────────────────────────────

function emitLog(type, msg) {
    const ts = new Date().toISOString();
    console.log(`[Scheduler] ${msg}`);
    io?.emit('scan:log', { msg, type, ts });
}

function emitMode(newMode) {
    mode = newMode;
    io?.emit('scan:mode', { mode: newMode });
}

function withJitter(intervalMs) {
    const jitter = (Math.random() * 2 - 1) * config.scanner.jitterMs;
    return Math.max(intervalMs + jitter, 30000);
}

// ── Deep scan (full ping sweep + fingerprint) ─────────────────────────────────

async function runArpScan() {
    if (isScanning) {
        console.log('[Scheduler] Scan ya en progreso, saltando...');
        return null;
    }

    const previousMode = mode; // save before overwriting
    isScanning = true;
    emitMode('scanning');

    try {
        const result = await arpScanner.scan(emitLog);
        await snmpClient.scanAllSwitches().catch(e =>
            console.error('[SNMP] Error en scheduler:', e.message)
        );

        lastScanTime = new Date();

        // Verificar alertas offline tras scan completo
        alertManager.check((dev, offlineMin) => {
            const label = dev.hostname || dev.ip;
            emitLog('warn', `⚠️ ALERTA: ${label} lleva ${offlineMin} min offline`);
            io?.emit('alert:offline', { device: dev, offlineMin });
        });

        io?.emit('scan:complete', { stats: result.stats });
        io?.emit('dashboard:refresh');
        return result;
    } catch (err) {
        console.error('[Scheduler] Error en scan ARP:', err.message);
        emitLog('error', `Error en scan: ${err.message}`);
        return null;
    } finally {
        isScanning = false;
        // Restore the mode that was active before this scan started
        emitMode(previousMode !== 'scanning' ? previousMode : 'periodic');
    }
}

// ── Watch mode (quick ARP table read every 45 s) ──────────────────────────────

async function runQuickCheck() {
    if (isScanning) return;
    isScanning = true;
    try {
        const result = await arpScanner.quickScan(emitLog);

        // Limpiar dedup de alertas para dispositivos que volvieron online
        for (const ev of result.events ?? []) {
            if (ev.type === 'online') alertManager.clearAlert(ev.device.mac);
        }

        // Verificar umbrales de alerta offline
        alertManager.check((dev, offlineMin) => {
            const label = dev.hostname || dev.ip;
            emitLog('warn', `⚠️ ALERTA: ${label} lleva ${offlineMin} min offline`);
            io?.emit('alert:offline', { device: dev, offlineMin });
        });

        if (result.events?.length > 0) {
            lastScanTime = new Date();
            io?.emit('dashboard:refresh');
        }
    } catch (err) {
        console.error('[Scheduler] Error en quick check:', err.message);
    } finally {
        isScanning = false;
    }
}

function startSnmpPolling() {
    if (snmpTimer) clearInterval(snmpTimer);
    snmpTimer = setInterval(() => {
        snmpClient.scanAllSwitches().catch(e =>
            console.error('[SNMP] Error en poll periódico:', e.message)
        );
    }, 5 * 60 * 1000); // every 5 minutes
}

function startWatching() {
    if (watchTimer) clearInterval(watchTimer);
    emitMode('watch');
    emitLog('success', '👁  NetWatch activo — escuchando eventos de red cada 45 s');
    watchTimer = setInterval(runQuickCheck, 45 * 1000);
}

function stopWatching() {
    if (watchTimer) { clearInterval(watchTimer); watchTimer = null; }
}

// ── Periodic mode helpers ─────────────────────────────────────────────────────

function scheduleNext() {
    if (arpTimer) clearTimeout(arpTimer);
    const interval = withJitter(config.scanner.arpIntervalMs);
    nextScanTime   = new Date(Date.now() + interval);
    arpTimer = setTimeout(async () => {
        await runArpScan();
        if (mode === 'periodic') scheduleNext();
    }, interval);
    console.log(`[Scheduler] Próximo scan periódico en ${Math.round(interval / 1000)}s`);
}

// ── Mode switching ─────────────────────────────────────────────────────────────

function setMode(newMode) {
    if (newMode === mode) return;
    if (newMode === 'watch') {
        if (arpTimer) { clearTimeout(arpTimer); arpTimer = null; }
        startWatching();
    } else if (newMode === 'periodic') {
        stopWatching();
        emitMode('periodic');
        emitLog('info', 'Modo periódico activado');
        scheduleNext();
    }
}

// ── Daily summary ─────────────────────────────────────────────────────────────

function scheduleDailySummary() {
    const now   = new Date();
    const next8 = new Date(now);
    next8.setHours(8, 0, 0, 0);
    if (next8 <= now) next8.setDate(next8.getDate() + 1);

    const msUntil = next8.getTime() - now.getTime();
    console.log(`[Scheduler] Resumen diario programado para ${next8.toLocaleTimeString()} (en ${Math.round(msUntil / 3600000)}h)`);

    dailyTimer = setTimeout(async () => {
        try {
            const { getDb } = require('../db/database');
            const telegram  = require('../notifier/telegram');
            const db        = getDb();
            const total     = db.prepare('SELECT COUNT(*) AS c FROM devices').get().c;
            const online    = db.prepare('SELECT COUNT(*) AS c FROM devices WHERE is_online = 1').get().c;
            const newToday  = db.prepare("SELECT COUNT(*) AS c FROM devices WHERE date(first_seen) = date('now','localtime')").get().c;
            telegram.sendDailySummary({ total, online, offline: total - online, newToday, lastScan: lastScanTime?.toLocaleTimeString() ?? null });
        } catch (e) {
            console.error('[Scheduler] Error en resumen diario:', e.message);
        }
        scheduleDailySummary();
    }, msUntil);
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Inicia el scheduler.
 * @param {import('socket.io').Server} [ioInstance]
 */
async function start(ioInstance) {
    if (ioInstance) io = ioInstance;

    console.log('[Scheduler] Iniciando scheduler de red...');
    scheduleDailySummary();

    emitLog('info', 'Iniciando escaneo profundo inicial…');

    // Deep initial scan
    await runArpScan();

    // After initial scan, default to watch mode + start independent SNMP polling
    startWatching();
    startSnmpPolling();
}

function stop() {
    if (arpTimer)   { clearTimeout(arpTimer);    arpTimer   = null; }
    if (watchTimer) { clearInterval(watchTimer); watchTimer = null; }
    if (snmpTimer)  { clearInterval(snmpTimer);  snmpTimer  = null; }
    if (dailyTimer) { clearTimeout(dailyTimer);  dailyTimer = null; }
    console.log('[Scheduler] Scheduler detenido');
}

async function triggerManualScan() {
    return await runArpScan();
}

function getStatus() {
    return {
        isScanning,
        mode,
        lastScanTime: lastScanTime ? lastScanTime.toISOString() : null,
        nextScanTime: nextScanTime ? nextScanTime.toISOString() : null,
        intervalMs:   config.scanner.arpIntervalMs,
        jitterMs:     config.scanner.jitterMs,
    };
}

module.exports = { start, stop, triggerManualScan, getStatus, setMode };
