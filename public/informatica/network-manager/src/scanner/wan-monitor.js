/**
 * WAN Monitor — detección automática de interfaz WAN + métricas en tiempo real.
 *
 * Flujo:
 *   1. Lee `ip route show default` para detectar interfaz WAN activa y gateway
 *   2. Lee /proc/net/dev para bytes acumulados de esa interfaz
 *   3. Delta entre dos muestras (POLL_MS) = RX/TX bps reales
 *   4. Pingea el gateway para latencia
 *   5. Si la WAN parece Starlink (192.168.100.x), consulta la API local gRPC-JSON
 *   6. Emite wan:update a todos los clientes Socket.io
 *
 * Emits (Socket.io):
 *   wan:update  { iface, gateway, rxBps, txBps, rxFmt, txFmt, latencyMs,
 *                 starlink, updatedAt }
 */

const { exec, execSync } = require('child_process');
const fs   = require('fs');
const http = require('http');
const os   = require('os');

const IS_LINUX = os.platform() === 'linux';

const POLL_MS       = 5_000;   // sample interval
const STARLINK_HOST = '192.168.100.1';
const STARLINK_PORT = 9201;    // dish HTTP status endpoint (unofficial)

let io         = null;
let _timer     = null;
let _prevSample = null; // { ts, iface, rxBytes, txBytes }

// ── /proc/net/dev parser ───────────────────────────────────────────────────────

/**
 * Returns { ifaceName: { rxBytes, txBytes } } from /proc/net/dev
 */
function readProcNetDev() {
    try {
        const raw = fs.readFileSync('/proc/net/dev', 'utf8');
        const result = {};
        for (const line of raw.split('\n').slice(2)) {
            const parts = line.trim().split(/\s+/);
            if (parts.length < 10) continue;
            const iface   = parts[0].replace(':', '');
            const rxBytes = parseInt(parts[1], 10)  || 0;
            const txBytes = parseInt(parts[9], 10)  || 0;
            result[iface] = { rxBytes, txBytes };
        }
        return result;
    } catch {
        return {};
    }
}

// ── WAN interface detection ────────────────────────────────────────────────────

/**
 * Reads `ip route show default` and returns { iface, gateway } for each
 * default route, sorted by metric (lowest first = primary WAN).
 */
function detectWanRoutes() {
    try {
        const out = execSync('ip route show default 2>/dev/null', { encoding: 'utf8', timeout: 3000 });
        const routes = [];
        for (const line of out.split('\n')) {
            // "default via 192.168.1.1 dev eth0 proto dhcp metric 100"
            const ifaceM   = line.match(/dev\s+(\S+)/);
            const gwM      = line.match(/via\s+([\d.]+)/);
            const metricM  = line.match(/metric\s+(\d+)/);
            if (ifaceM && gwM) {
                routes.push({
                    iface:   ifaceM[1],
                    gateway: gwM[1],
                    metric:  metricM ? parseInt(metricM[1], 10) : 0,
                });
            }
        }
        return routes.sort((a, b) => a.metric - b.metric);
    } catch {
        return [];
    }
}

// ── Latency ───────────────────────────────────────────────────────────────────

function pingGateway(gateway) {
    return new Promise((resolve) => {
        exec(`ping -c 3 -W 1 ${gateway}`, { timeout: 5000 }, (err, stdout) => {
            // "rtt min/avg/max/mdev = 1.234/1.234/1.234/0.000 ms"
            const rttM  = stdout?.match(/=\s*([\d.]+)\/([\d.]+)\/([\d.]+)/);
            // "3 packets transmitted, 2 received, 33% packet loss"
            const lossM = stdout?.match(/(\d+)%\s+packet loss/);
            resolve({
                latencyMs:   rttM  ? parseFloat(rttM[2])   : null,
                packetLoss:  lossM ? parseInt(lossM[1], 10) : (err ? 100 : 0),
            });
        });
    });
}

// ── Starlink API ──────────────────────────────────────────────────────────────

/**
 * Queries the Starlink dish local API (unofficial HTTP endpoint at :9201).
 * Returns null if not a Starlink gateway or unreachable.
 *
 * The dish exposes a gRPC-JSON gateway. We request the DeviceStatus:
 * POST http://192.168.100.1:9201/SpaceX.API.Device.Device/Handle
 *
 * Fallback: try the simpler metrics endpoint some firmware versions expose.
 */
function queryStarlink() {
    return new Promise((resolve) => {
        const body = JSON.stringify({ getDeviceInfo: {} });
        const req  = http.request({
            hostname: STARLINK_HOST,
            port:     STARLINK_PORT,
            path:     '/SpaceX.API.Device.Device/Handle',
            method:   'POST',
            headers:  {
                'Content-Type':   'application/json',
                'Content-Length': Buffer.byteLength(body),
            },
        }, (res) => {
            let data = '';
            res.on('data', d => { data += d; });
            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    const ds   = json?.dishGetStatus ?? json?.getDeviceInfo ?? null;
                    if (!ds) return resolve(null);

                    resolve({
                        uplinkThroughputBps:   ds.uplinkThroughputBps   ?? null,
                        downlinkThroughputBps: ds.downlinkThroughputBps ?? null,
                        popPingLatencyMs:      ds.popPingLatencyMs       ?? null,
                        obstructionPct:        ds.obstructionStats?.fractionObstructed != null
                            ? Math.round(ds.obstructionStats.fractionObstructed * 100)
                            : null,
                        alerts: ds.alerts ? Object.entries(ds.alerts)
                            .filter(([, v]) => v === true)
                            .map(([k]) => k) : [],
                    });
                } catch {
                    resolve(null);
                }
            });
        });
        req.on('error', () => resolve(null));
        req.setTimeout(3000, () => { req.destroy(); resolve(null); });
        req.write(body);
        req.end();
    });
}

function isStarlinkGateway(gateway) {
    return gateway?.startsWith('192.168.100.');
}

// ── Formatting ────────────────────────────────────────────────────────────────

function fmtBps(bps) {
    if (!bps && bps !== 0) return '—';
    if (bps >= 1e9) return `${(bps / 1e9).toFixed(2)} Gbps`;
    if (bps >= 1e6) return `${(bps / 1e6).toFixed(2)} Mbps`;
    if (bps >= 1e3) return `${(bps / 1e3).toFixed(1)} Kbps`;
    return `${bps} bps`;
}

// ── Main poll loop ────────────────────────────────────────────────────────────

async function poll() {
    try { await _poll(); } catch (e) {
        console.error('[WAN] Error en poll:', e.message);
    }
}

async function _poll() {
    if (!IS_LINUX) return; // WAN monitoring only supported on Linux
    const routes = detectWanRoutes();
    if (!routes.length) {
        io?.emit('wan:update', { error: 'No se detectó ruta WAN', updatedAt: new Date().toISOString() });
        return;
    }

    // Use primary WAN (lowest metric)
    const { iface, gateway } = routes[0];
    const procDev = readProcNetDev();
    const ifStats = procDev[iface];

    if (!ifStats) {
        io?.emit('wan:update', { error: `Interfaz ${iface} no encontrada en /proc/net/dev`, updatedAt: new Date().toISOString() });
        return;
    }

    const now = Date.now();
    let rxBps = null, txBps = null;

    if (_prevSample && _prevSample.iface === iface) {
        const dtSec = (now - _prevSample.ts) / 1000;
        if (dtSec > 0) {
            rxBps = Math.round(((ifStats.rxBytes - _prevSample.rxBytes) * 8) / dtSec);
            txBps = Math.round(((ifStats.txBytes - _prevSample.txBytes) * 8) / dtSec);
            // Guard against counter wrap or interface reset
            if (rxBps < 0) rxBps = 0;
            if (txBps < 0) txBps = 0;
        }
    }

    _prevSample = { ts: now, iface, rxBytes: ifStats.rxBytes, txBytes: ifStats.txBytes };

    // Run latency + Starlink in parallel
    const [pingResult, starlink] = await Promise.all([
        pingGateway(gateway),
        isStarlinkGateway(gateway) ? queryStarlink() : Promise.resolve(null),
    ]);
    const latencyMs  = pingResult?.latencyMs ?? null;
    const packetLoss = pingResult?.packetLoss ?? null;

    // All active WAN interfaces (multi-WAN support)
    const allRoutes = routes.map(r => {
        const s = procDev[r.iface];
        return {
            iface:   r.iface,
            gateway: r.gateway,
            metric:  r.metric,
            active:  r.iface === iface,
            rxBytes: s?.rxBytes ?? 0,
            txBytes: s?.txBytes ?? 0,
        };
    });

    const payload = {
        iface,
        gateway,
        rxBps,
        txBps,
        rxFmt:     fmtBps(rxBps),
        txFmt:     fmtBps(txBps),
        latencyMs,
        packetLoss,
        isStarlink: isStarlinkGateway(gateway),
        starlink,
        allRoutes,
        updatedAt: new Date().toISOString(),
    };

    io?.emit('wan:update', payload);
    console.log(`[WAN] ${iface} via ${gateway} — ▼${fmtBps(rxBps)} ▲${fmtBps(txBps)} lat:${latencyMs != null ? latencyMs.toFixed(1) + 'ms' : '—'}`);
}

// ── Public API ────────────────────────────────────────────────────────────────

function start(ioInstance) {
    io = ioInstance;
    poll(); // immediate first sample
    _timer = setInterval(poll, POLL_MS);
    console.log(`[WAN] Monitor iniciado (intervalo ${POLL_MS / 1000}s)`);
}

function stop() {
    if (_timer) { clearInterval(_timer); _timer = null; }
}

module.exports = { start, stop };
