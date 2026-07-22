/**
 * Network Monitor — Server Entry Point
 */
const express = require('express');
const path = require('path');
const http = require('http');
const os = require('os');
const { Server: SocketServer } = require('socket.io'); // Renombramos para evitar conflicto
const config = require('./config');
const { getDb, close: closeDb } = require('./src/db/database');
const scheduler  = require('./src/scanner/scheduler');
const wanMonitor = require('./src/scanner/wan-monitor');
const { router: authRouter, requireAuth } = require('./src/auth/auth');

// Routes - IMPORTANTE: La carpeta es 'routes', no 'api'
const devicesRouter  = require('./src/routes/devices');
const scansRouter    = require('./src/routes/scans');
const configRouter   = require('./src/routes/config');
const switchesRouter = require('./src/routes/switches');
const topologyRouter = require('./src/routes/topology');
const actionsRouter  = require('./src/routes/actions');
const settingsRouter = require('./src/routes/settings');
const ipamRouter        = require('./src/routes/ipam');
const credentialsRouter = require('./src/routes/credentials');
const powerRouter       = require('./src/routes/power');
const bandwidthRouter   = require('./src/routes/bandwidth');

const shell = require('./src/terminal/netmon-shell');

const app = express();
const server = http.createServer(app); // Este es tu servidor HTTP
const io = new SocketServer(server);    // Vinculamos Socket.io al servidor
const internalAuthEnabled = process.env.NM_AUTH_ENABLED === '1';

// Middleware
app.use(express.json());

// Permite que la pantalla central de EA consulte al agente que corre en
// 127.0.0.1 de la PC operadora. No se habilitan credenciales del navegador.
app.use((req, res, next) => {
    const origin = String(req.headers.origin || '');
    if (/^https?:\/\/(localhost|127\.0\.0\.1|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)[^/]*$/i.test(origin)) {
        res.setHeader('Access-Control-Allow-Origin', origin);
        res.setHeader('Vary', 'Origin');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        res.setHeader('Access-Control-Allow-Private-Network', 'true');
    }
    if (req.method === 'OPTIONS') return res.sendStatus(204);
    next();
});

// Identidad del puesto donde se ejecuta el agente. La usa EA para registrar
// desde qué computadora física se hizo cada relevamiento.
app.get('/api/agent-info', (req, res) => {
    const addresses = [];
    for (const [interfaceName, entries] of Object.entries(os.networkInterfaces())) {
        for (const entry of entries || []) {
            if (entry.family === 'IPv4' && !entry.internal) {
                addresses.push({ interface: interfaceName, ip: entry.address, netmask: entry.netmask });
            }
        }
    }
    res.json({ success: true, hostname: os.hostname(), addresses });
});

// El modulo ya se usa dentro de EA, asi que por defecto no pedimos
// un segundo login propio de NetMonitor. Para reactivarlo: NM_AUTH_ENABLED=1.
if (internalAuthEnabled) {
    app.use('/auth', authRouter);
    app.use(requireAuth);
} else {
    app.use('/auth', (req, res) => res.redirect('/'));
}

app.use(express.static(path.join(__dirname, 'public')));

// API Routes - La URL será /api/..., pero el archivo viene de /src/routes/
app.use('/api/devices',  devicesRouter);
app.use('/api/scans',    scansRouter);
app.use('/api/config',   configRouter);
app.use('/api/switches', switchesRouter);
app.use('/api/topology', topologyRouter);
app.use('/api/actions',  actionsRouter);
app.use('/api/settings', settingsRouter);
app.use('/api/ipam',        ipamRouter);
app.use('/api/credentials', credentialsRouter);
app.use('/api/power',       powerRouter);
app.use('/api/bandwidth',   bandwidthRouter);

// SPA fallback
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Guardamos io en app para usarlo en las rutas si hace falta
app.set('io', io);

// Socket.io: modo de escaneo + terminal dedicada
io.on('connection', (socket) => {
    socket.on('set:mode', ({ mode }) => {
        if (mode === 'watch' || mode === 'periodic') {
            scheduler.setMode(mode);
        }
    });

    // Terminal NetMonitor — recibe input, devuelve output
    socket.on('terminal:input', async ({ input }) => {
        if (typeof input !== 'string') return;
        const output = await shell.execute(input.trim(), io);
        if (output === '\x1b[CLEAR]') {
            socket.emit('terminal:clear');
        } else if (output) {
            socket.emit('terminal:output', { text: output });
        }
    });
});

// Iniciar servidor usando 'server.listen' (NO app.listen)
server.listen(config.server.port, config.server.host, async () => {
    console.log('');
    console.log('╔══════════════════════════════════════════════════╗');
    console.log('║        🖧  Network Monitor — Stealth Mode        ║');
    console.log('╠══════════════════════════════════════════════════╣');
    console.log(`║  URL:  http://${config.server.host}:${config.server.port}             ║`);
    console.log(`║  DB:   ${path.basename(config.db.path).padEnd(40)}║`);
    console.log('║  Mode: Passive ARP only (0 network traffic)      ║');
    console.log('╚══════════════════════════════════════════════════╝');
    console.log('');

    getDb();
    console.log('[DB] SQLite inicializado');
    await scheduler.start(io);
    wanMonitor.start(io);
});

// Graceful shutdown
function shutdown() {
    console.log('\n[Server] Cerrando...');
    scheduler.stop();
    wanMonitor.stop();
    closeDb();
    server.close(() => {
        console.log('[Server] Cerrado.');
        process.exit(0);
    });
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
