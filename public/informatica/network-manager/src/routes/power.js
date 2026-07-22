/**
 * ============================================================
 *  power.js — Wake-on-LAN, Shutdown & Reboot remotos
 *
 *  Endpoints:
 *    POST /api/power/wol/:id           WoL a un dispositivo
 *    POST /api/power/wol/bulk          WoL masivo (rango IP, tipo, IDs)
 *    POST /api/power/shutdown/:id      Apagar un equipo via SSH
 *    POST /api/power/reboot/:id        Reiniciar via SSH
 *    POST /api/power/shutdown/bulk     Apagado masivo
 *    POST /api/power/reboot/bulk       Reinicio masivo
 *    GET  /api/power/config            Obtener config SSH/power
 *    PUT  /api/power/config            Guardar config SSH/power
 *    GET  /api/power/log               Historial de acciones power
 * ============================================================
 */

const express = require('express');
const dgram = require('dgram');
const { exec } = require('child_process');
const os = require('os');
const { getDb } = require('../db/database');

const router = express.Router();

// ─────────────────────────────────────────────────────────────
//  UTILIDADES
// ─────────────────────────────────────────────────────────────

function sanitizeIp(ip) {
    if (/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) return ip;
    return null;
}

function sanitizeMac(mac) {
    if (/^([0-9a-fA-F]{2}[:\-]){5}[0-9a-fA-F]{2}$/.test(mac)) return mac;
    return null;
}

function ipToInt(ip) {
    const parts = ip.split('.').map(Number);
    return ((parts[0] << 24) + (parts[1] << 16) + (parts[2] << 8) + parts[3]) >>> 0;
}

function ipInRange(ip, start, end) {
    const ipInt = ipToInt(ip);
    const startInt = ipToInt(start);
    const endInt = ipToInt(end);
    return ipInt >= startInt && ipInt <= endInt;
}

/**
 * Enviar Magic Packet (WoL) via UDP broadcast
 * No necesita dependencias externas — usa dgram nativo
 */
function sendMagicPacket(mac, broadcastAddr = '255.255.255.255', port = 9) {
    return new Promise((resolve, reject) => {
        // Normalizar MAC a bytes
        const macBytes = mac.replace(/[:\-]/g, '').match(/.{2}/g).map(b => parseInt(b, 16));

        // Magic packet: 6x 0xFF + 16x MAC address
        const packet = Buffer.alloc(6 + 16 * 6);
        for (let i = 0; i < 6; i++) packet[i] = 0xff;
        for (let rep = 0; rep < 16; rep++) {
            for (let b = 0; b < 6; b++) {
                packet[6 + rep * 6 + b] = macBytes[b];
            }
        }

        const socket = dgram.createSocket('udp4');
        socket.once('error', (err) => {
            socket.close();
            reject(err);
        });

        socket.bind(() => {
            socket.setBroadcast(true);
            socket.send(packet, 0, packet.length, port, broadcastAddr, (err) => {
                socket.close();
                if (err) reject(err);
                else resolve();
            });
        });
    });
}

/**
 * Ejecutar comando remoto via SSH
 */
function sshExec(ip, user, port, command, timeout = 10000) {
    return new Promise((resolve, reject) => {
        const safeIp = sanitizeIp(ip);
        if (!safeIp) return reject(new Error('IP inválida'));

        // Sanitizar user (solo alfanumérico, guion, underscore)
        if (!/^[a-zA-Z0-9_\-]+$/.test(user)) {
            return reject(new Error('Usuario SSH inválido'));
        }

        const sshCmd = `ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no -p ${port} ${user}@${safeIp} "${command}"`;

        exec(sshCmd, { timeout }, (err, stdout, stderr) => {
            if (err) {
                reject(new Error(stderr || err.message));
            } else {
                resolve(stdout);
            }
        });
    });
}

/**
 * Obtener config de power desde la DB
 */
function getPowerConfig() {
    const db = getDb();
    const row = db.prepare('SELECT * FROM power_config WHERE id = 1').get();
    return row || {
        ssh_user: 'admin',
        ssh_port: 22,
        wol_port: 9,
        broadcast_addr: '255.255.255.255',
        default_shutdown_delay: 0,
    };
}

/**
 * Registrar accion en el log
 */
function logAction(action, deviceId, deviceName, ip, status, detail = '') {
    const db = getDb();
    db.prepare(`
        INSERT INTO power_log (action, device_id, device_name, ip, status, detail)
        VALUES (?, ?, ?, ?, ?, ?)
    `).run(action, deviceId, deviceName, ip, status, detail);
}

/**
 * Buscar dispositivos por filtros (rango IP, tipo, IDs)
 */
function getDevicesByFilter(filter) {
    const db = getDb();
    let devices;

    if (filter.ids && filter.ids.length > 0) {
        const placeholders = filter.ids.map(() => '?').join(',');
        devices = db.prepare(`SELECT * FROM devices WHERE id IN (${placeholders})`).all(...filter.ids);
    } else if (filter.ip_start && filter.ip_end) {
        // Traer todos y filtrar por rango (SQLite no tiene IP nativa)
        devices = db.prepare('SELECT * FROM devices').all()
            .filter(d => {
                const ip = sanitizeIp(d.ip);
                return ip && ipInRange(ip, filter.ip_start, filter.ip_end);
            });
    } else if (filter.device_type) {
        devices = db.prepare('SELECT * FROM devices WHERE device_type LIKE ?')
            .all(`%${filter.device_type}%`);
    } else if (filter.status === 'online') {
        devices = db.prepare('SELECT * FROM devices WHERE is_online = 1').all();
    } else {
        devices = db.prepare('SELECT * FROM devices').all();
    }

    return devices;
}

// ─────────────────────────────────────────────────────────────
//  ENDPOINTS: WAKE-ON-LAN
// ─────────────────────────────────────────────────────────────

// WoL a un dispositivo por ID
router.post('/wol/:id', async (req, res) => {
    const db = getDb();
    const device = db.prepare('SELECT * FROM devices WHERE id = ?').get(req.params.id);

    if (!device) {
        return res.status(404).json({ message: 'Dispositivo no encontrado' });
    }

    const mac = sanitizeMac(device.mac);
    if (!mac) {
        return res.status(400).json({ message: 'MAC address inválida para este dispositivo' });
    }

    const config = getPowerConfig();
    const broadcastAddr = req.body.broadcast_addr || config.broadcast_addr;
    const wolPort = req.body.wol_port || config.wol_port;

    try {
        await sendMagicPacket(mac, broadcastAddr, wolPort);
        logAction('wol', device.id, device.hostname || device.ip, device.ip, 'ok');
        res.json({
            success: true,
            message: `Magic packet enviado a ${device.hostname || device.ip} (${mac})`,
            device: { id: device.id, ip: device.ip, mac: device.mac, hostname: device.hostname }
        });
    } catch (err) {
        logAction('wol', device.id, device.hostname || device.ip, device.ip, 'error', err.message);
        res.status(500).json({ message: `Error al enviar WoL: ${err.message}` });
    }
});

// WoL masivo (rango IP, tipo, lista de IDs)
router.post('/wol/bulk', async (req, res) => {
    const { ip_start, ip_end, device_type, ids, broadcast_addr, wol_port } = req.body;
    const config = getPowerConfig();
    const bcast = broadcast_addr || config.broadcast_addr;
    const port = wol_port || config.wol_port;

    const devices = getDevicesByFilter({ ip_start, ip_end, device_type, ids });

    if (devices.length === 0) {
        return res.json({ success: true, message: 'No se encontraron dispositivos', results: [] });
    }

    const results = [];

    for (const device of devices) {
        const mac = sanitizeMac(device.mac);
        if (!mac) {
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'skip', detail: 'MAC inválida' });
            continue;
        }

        try {
            await sendMagicPacket(mac, bcast, port);
            logAction('wol', device.id, device.hostname || device.ip, device.ip, 'ok');
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, mac, status: 'ok' });
        } catch (err) {
            logAction('wol', device.id, device.hostname || device.ip, device.ip, 'error', err.message);
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'error', detail: err.message });
        }
    }

    const ok = results.filter(r => r.status === 'ok').length;
    const errors = results.filter(r => r.status === 'error').length;

    res.json({
        success: true,
        message: `WoL enviado: ${ok} OK, ${errors} errores de ${devices.length} dispositivos`,
        results
    });
});

// ─────────────────────────────────────────────────────────────
//  ENDPOINTS: SHUTDOWN / REBOOT
// ─────────────────────────────────────────────────────────────

// Apagar un dispositivo
router.post('/shutdown/:id', async (req, res) => {
    const db = getDb();
    const device = db.prepare('SELECT * FROM devices WHERE id = ?').get(req.params.id);

    if (!device) {
        return res.status(404).json({ message: 'Dispositivo no encontrado' });
    }

    const ip = sanitizeIp(device.ip);
    if (!ip) {
        return res.status(400).json({ message: 'IP inválida' });
    }

    const config = getPowerConfig();
    const user = req.body.ssh_user || config.ssh_user;
    const port = req.body.ssh_port || config.ssh_port;
    const delay = req.body.delay || config.default_shutdown_delay;
    const shutdownCmd = delay > 0 ? `sudo shutdown -h +${delay}` : 'sudo shutdown -h now';

    try {
        await sshExec(ip, user, port, shutdownCmd);
        logAction('shutdown', device.id, device.hostname || device.ip, ip, 'ok', `delay=${delay}min`);
        res.json({
            success: true,
            message: `Apagado enviado a ${device.hostname || ip}${delay > 0 ? ` (en ${delay} min)` : ''}`,
        });
    } catch (err) {
        logAction('shutdown', device.id, device.hostname || device.ip, ip, 'error', err.message);
        res.status(500).json({ message: `Error SSH: ${err.message}` });
    }
});

// Reiniciar un dispositivo
router.post('/reboot/:id', async (req, res) => {
    const db = getDb();
    const device = db.prepare('SELECT * FROM devices WHERE id = ?').get(req.params.id);

    if (!device) {
        return res.status(404).json({ message: 'Dispositivo no encontrado' });
    }

    const ip = sanitizeIp(device.ip);
    if (!ip) {
        return res.status(400).json({ message: 'IP inválida' });
    }

    const config = getPowerConfig();
    const user = req.body.ssh_user || config.ssh_user;
    const port = req.body.ssh_port || config.ssh_port;

    try {
        await sshExec(ip, user, port, 'sudo reboot');
        logAction('reboot', device.id, device.hostname || device.ip, ip, 'ok');
        res.json({
            success: true,
            message: `Reinicio enviado a ${device.hostname || ip}`,
        });
    } catch (err) {
        logAction('reboot', device.id, device.hostname || device.ip, ip, 'error', err.message);
        res.status(500).json({ message: `Error SSH: ${err.message}` });
    }
});

// Apagado masivo
router.post('/shutdown/bulk', async (req, res) => {
    const { ip_start, ip_end, device_type, ids, delay, ssh_user, ssh_port } = req.body;
    const config = getPowerConfig();
    const user = ssh_user || config.ssh_user;
    const port = ssh_port || config.ssh_port;
    const shutdownDelay = delay || config.default_shutdown_delay;

    const devices = getDevicesByFilter({ ip_start, ip_end, device_type, ids, status: 'online' });

    if (devices.length === 0) {
        return res.json({ success: true, message: 'No se encontraron dispositivos online', results: [] });
    }

    const results = [];
    const shutdownCmd = shutdownDelay > 0 ? `sudo shutdown -h +${shutdownDelay}` : 'sudo shutdown -h now';

    for (const device of devices) {
        const ip = sanitizeIp(device.ip);
        if (!ip) {
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'skip', detail: 'IP inválida' });
            continue;
        }

        if (!device.is_online) {
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'skip', detail: 'Offline' });
            continue;
        }

        try {
            await sshExec(ip, user, port, shutdownCmd);
            logAction('shutdown', device.id, device.hostname || ip, ip, 'ok', `delay=${shutdownDelay}`);
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'ok' });
        } catch (err) {
            logAction('shutdown', device.id, device.hostname || ip, ip, 'error', err.message);
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'error', detail: err.message });
        }
    }

    const ok = results.filter(r => r.status === 'ok').length;
    const errors = results.filter(r => r.status === 'error').length;

    res.json({
        success: true,
        message: `Apagado: ${ok} OK, ${errors} errores de ${devices.length} dispositivos`,
        results
    });
});

// Reinicio masivo
router.post('/reboot/bulk', async (req, res) => {
    const { ip_start, ip_end, device_type, ids, ssh_user, ssh_port } = req.body;
    const config = getPowerConfig();
    const user = ssh_user || config.ssh_user;
    const port = ssh_port || config.ssh_port;

    const devices = getDevicesByFilter({ ip_start, ip_end, device_type, ids, status: 'online' });

    if (devices.length === 0) {
        return res.json({ success: true, message: 'No se encontraron dispositivos online', results: [] });
    }

    const results = [];

    for (const device of devices) {
        const ip = sanitizeIp(device.ip);
        if (!ip || !device.is_online) {
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'skip' });
            continue;
        }

        try {
            await sshExec(ip, user, port, 'sudo reboot');
            logAction('reboot', device.id, device.hostname || ip, ip, 'ok');
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'ok' });
        } catch (err) {
            logAction('reboot', device.id, device.hostname || ip, ip, 'error', err.message);
            results.push({ id: device.id, ip: device.ip, hostname: device.hostname, status: 'error', detail: err.message });
        }
    }

    const ok = results.filter(r => r.status === 'ok').length;
    res.json({
        success: true,
        message: `Reinicio: ${ok} OK de ${devices.length} dispositivos`,
        results
    });
});

// ─────────────────────────────────────────────────────────────
//  ENDPOINTS: CONFIG & LOG
// ─────────────────────────────────────────────────────────────

// Obtener config de power
router.get('/config', (req, res) => {
    res.json(getPowerConfig());
});

// Guardar config de power
router.put('/config', (req, res) => {
    const db = getDb();
    const { ssh_user, ssh_port, wol_port, broadcast_addr, default_shutdown_delay } = req.body;

    db.prepare(`
        INSERT INTO power_config (id, ssh_user, ssh_port, wol_port, broadcast_addr, default_shutdown_delay)
        VALUES (1, ?, ?, ?, ?, ?)
        ON CONFLICT(id) DO UPDATE SET
            ssh_user = excluded.ssh_user,
            ssh_port = excluded.ssh_port,
            wol_port = excluded.wol_port,
            broadcast_addr = excluded.broadcast_addr,
            default_shutdown_delay = excluded.default_shutdown_delay
    `).run(
        ssh_user || 'admin',
        ssh_port || 22,
        wol_port || 9,
        broadcast_addr || '255.255.255.255',
        default_shutdown_delay || 0
    );

    res.json({ success: true, message: 'Configuración guardada' });
});

// Log de acciones power
router.get('/log', (req, res) => {
    const db = getDb();
    const limit = parseInt(req.query.limit) || 50;
    const logs = db.prepare('SELECT * FROM power_log ORDER BY timestamp DESC LIMIT ?').all(limit);
    res.json({ logs });
});

module.exports = router;
