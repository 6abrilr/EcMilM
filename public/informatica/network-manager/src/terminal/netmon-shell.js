/**
 * netmon-shell.js
 * Terminal dedicada a NetMonitor — comandos propios de la app via Socket.io.
 * No expone shell del sistema. Cada comando está explícitamente definido.
 */

'use strict';

const { exec }    = require('child_process');
const os          = require('os');
const { getDb }   = require('../db/database');

const IS_WIN = os.platform() === 'win32';

// ── Validadores ────────────────────────────────────────────────────────────────

const RE_IP  = /^(\d{1,3}\.){3}\d{1,3}$/;
const RE_MAC = /^([0-9a-f]{2}[:\-]){5}[0-9a-f]{2}$/i;

const validIp  = ip  => RE_IP.test(ip);
const validMac = mac => RE_MAC.test(mac);
const sanitizeText = t => t.replace(/["\\\r\n]/g, '').slice(0, 200);

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Ejecuta un comando y devuelve stdout+stderr como string */
const run = (cmd, timeout = 15000) => new Promise((resolve) => {
    exec(cmd, { timeout, encoding: 'utf8' }, (err, stdout, stderr) => {
        resolve((stdout || '') + (stderr || '') || (err?.message ?? 'sin salida'));
    });
});

/** Ejecuta via wmiexec.py en un host Windows remoto */
const wmiRun = (ip, user, pass, cmd) =>
    run(`wmiexec.py "${user}":"${pass}"@${ip} "${cmd}"`, 20000);

/** Verifica si wmiexec.py está disponible en el PATH */
const checkWmiexec = () => new Promise(resolve => {
    const probe = IS_WIN ? 'where wmiexec.py 2>nul' : 'which wmiexec.py 2>/dev/null';
    exec(probe, { timeout: 3000 }, (err, stdout) => resolve(!err && stdout.trim().length > 0));
});

/** Busca device en DB por IP o MAC */
const findDevice = (query) => {
    const db = getDb();
    return db.prepare(
        'SELECT * FROM devices WHERE ip = ? OR mac = ? LIMIT 1'
    ).get(query, query);
};

/** Lista todos los devices */
const listDevices = () => getDb().prepare(
    'SELECT ip, mac, hostname, vendor, device_type, is_online FROM devices ORDER BY ip'
).all();

// ── Comandos disponibles ──────────────────────────────────────────────────────

const COMMANDS = {

    /** help — lista de comandos */
    help: {
        usage: 'help',
        desc:  'Muestra este menú de ayuda',
        run:   async () => {
            const lines = ['NetMonitor Shell v1.0 — comandos disponibles:\n'];
            for (const [name, cmd] of Object.entries(COMMANDS)) {
                lines.push(`  ${cmd.usage.padEnd(40)} ${cmd.desc}`);
            }
            return lines.join('\n');
        },
    },

    /** devices — lista hosts en DB */
    devices: {
        usage: 'devices [filtro]',
        desc:  'Lista dispositivos (opcional: filtrar por vendor/tipo)',
        run:   async ([filter]) => {
            const all = listDevices();
            const filtered = filter
                ? all.filter(d =>
                    [d.vendor, d.device_type, d.hostname, d.ip]
                        .some(v => v?.toLowerCase().includes(filter.toLowerCase())))
                : all;
            if (!filtered.length) return 'Sin resultados.';
            return filtered.map(d =>
                `${d.is_online ? '●' : '○'} ${d.ip.padEnd(16)} ${(d.hostname || 'Desconocido').padEnd(20)} ${(d.vendor || '—').padEnd(20)} ${d.device_type || '—'}`
            ).join('\n');
        },
    },

    /** info — detalle de un host */
    info: {
        usage: 'info <ip|mac>',
        desc:  'Muestra detalle de un dispositivo',
        run:   async ([query]) => {
            if (!query) return 'Uso: info <ip|mac>';
            const d = findDevice(query);
            if (!d) return `No encontrado: ${query}`;
            return [
                `IP:          ${d.ip}`,
                `MAC:         ${d.mac}`,
                `Hostname:    ${d.hostname || '—'}`,
                `Vendor:      ${d.vendor || '—'}`,
                `Tipo:        ${d.device_type || '—'}`,
                `OS:          ${d.os || '—'}`,
                `TTL:         ${d.ttl ?? '—'}`,
                `Online:      ${d.is_online ? 'Sí' : 'No'}`,
                `Visto:       ${d.last_seen || '—'}`,
                `Primer visto:${d.first_seen || '—'}`,
            ].join('\n');
        },
    },

    /** ping — ping desde el server */
    ping: {
        usage: 'ping <ip>',
        desc:  'Ping desde el servidor al host',
        run:   async ([ip]) => {
            if (!ip || !validIp(ip)) return 'IP inválida. Uso: ping <ip>';
            const cmd = IS_WIN
                ? `ping -n 4 ${ip}`
                : `ping -c 4 -W 1 ${ip}`;
            return run(cmd, 8000);
        },
    },

    /** traceroute — ruta al host */
    traceroute: {
        usage: 'traceroute <ip>',
        desc:  'Traza la ruta al host',
        run:   async ([ip]) => {
            if (!ip || !validIp(ip)) return 'IP inválida. Uso: traceroute <ip>';
            if (IS_WIN) return run(`tracert -d -h 20 ${ip}`, 30000);
            // Linux: prefer traceroute, fall back to tracepath
            const hasTr = await new Promise(r =>
                exec('which traceroute 2>/dev/null', { timeout: 2000 }, (e, o) => r(!e && o.trim().length > 0))
            );
            return hasTr
                ? run(`traceroute -n -m 20 ${ip}`, 30000)
                : run(`tracepath -n ${ip}`, 30000);
        },
    },

    /** ports — escaneo de puertos comunes */
    ports: {
        usage: 'ports <ip>',
        desc:  'Escanea puertos comunes del host',
        run:   async ([ip]) => {
            if (!ip || !validIp(ip)) return 'IP inválida. Uso: ports <ip>';
            const PORTS = [21,22,23,25,53,80,135,139,443,445,3389,8080,8443];
            const net = require('net');
            const results = await Promise.all(PORTS.map(port =>
                new Promise(resolve => {
                    const s = new net.Socket();
                    s.setTimeout(600);
                    s.connect(port, ip, () => { s.destroy(); resolve(`  ${String(port).padEnd(6)} ABIERTO`); });
                    s.on('error', () => { s.destroy(); resolve(`  ${String(port).padEnd(6)} cerrado`); });
                    s.on('timeout', () => { s.destroy(); resolve(`  ${String(port).padEnd(6)} timeout`); });
                })
            ));
            return `Puertos en ${ip}:\n` + results.join('\n');
        },
    },

    /** wol — Wake-on-LAN */
    wol: {
        usage: 'wol <mac>',
        desc:  'Envía magic packet Wake-on-LAN',
        run:   async ([mac]) => {
            if (!mac || !validMac(mac)) return 'MAC inválida. Uso: wol <mac> (formato: AA:BB:CC:DD:EE:FF)';
            const dgram = require('dgram');
            const macBytes = mac.replace(/[:\-]/g, '');
            const buf = Buffer.alloc(102);
            buf.fill(0xff, 0, 6);
            for (let i = 0; i < 16; i++) {
                Buffer.from(macBytes, 'hex').copy(buf, 6 + i * 6);
            }
            return new Promise(resolve => {
                const sock = dgram.createSocket('udp4');
                sock.once('error', e => resolve(`Error: ${e.message}`));
                sock.bind(() => {
                    sock.setBroadcast(true);
                    sock.send(buf, 0, buf.length, 9, '255.255.255.255', () => {
                        sock.close();
                        resolve(`Magic packet enviado a ${mac}`);
                    });
                });
            });
        },
    },

    /** msg — mensaje popup en host Windows */
    msg: {
        usage: 'msg <ip> <usuario> <contraseña> <"mensaje">',
        desc:  'Muestra popup en pantalla del host Windows',
        run:   async ([ip, user, pass, ...rest]) => {
            if (!ip || !validIp(ip)) return 'Uso: msg <ip> <usuario> <contraseña> <"mensaje">';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            const text = sanitizeText(rest.join(' ').replace(/^"|"$/g, ''));
            if (!text) return 'Mensaje vacío';
            return wmiRun(ip, user, pass, `msg * "${text}"`);
        },
    },

    /** wmi — ejecutar comando WMI en host Windows */
    wmi: {
        usage: 'wmi <ip> <usuario> <contraseña> <comando>',
        desc:  'Ejecuta comando en host Windows remoto via WMI',
        run:   async ([ip, user, pass, ...cmdParts]) => {
            if (!ip || !validIp(ip)) return 'Uso: wmi <ip> <usuario> <contraseña> <comando>';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            const cmd = sanitizeText(cmdParts.join(' '));
            if (!cmd) return 'Comando vacío';
            // Whitelist de comandos permitidos
            const ALLOWED = ['tasklist','systeminfo','ipconfig','netstat','whoami','hostname','ver','dir','shutdown /s','shutdown /r','shutdown /l'];
            const allowed = ALLOWED.some(a => cmd.toLowerCase().startsWith(a));
            if (!allowed) return `Comando no permitido. Permitidos:\n  ${ALLOWED.join('\n  ')}`;
            return wmiRun(ip, user, pass, cmd);
        },
    },

    /** shutdown — apagar host Windows */
    shutdown: {
        usage: 'shutdown <ip> <usuario> <contraseña>',
        desc:  'Apaga un host Windows remoto',
        run:   async ([ip, user, pass]) => {
            if (!ip || !validIp(ip)) return 'Uso: shutdown <ip> <usuario> <contraseña>';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            return wmiRun(ip, user, pass, 'shutdown /s /t 0');
        },
    },

    /** reboot — reiniciar host Windows */
    reboot: {
        usage: 'reboot <ip> <usuario> <contraseña>',
        desc:  'Reinicia un host Windows remoto',
        run:   async ([ip, user, pass]) => {
            if (!ip || !validIp(ip)) return 'Uso: reboot <ip> <usuario> <contraseña>';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            return wmiRun(ip, user, pass, 'shutdown /r /t 0');
        },
    },

    /** lock — bloquear sesión */
    lock: {
        usage: 'lock <ip> <usuario> <contraseña>',
        desc:  'Bloquea la sesión del host Windows',
        run:   async ([ip, user, pass]) => {
            if (!ip || !validIp(ip)) return 'Uso: lock <ip> <usuario> <contraseña>';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            return wmiRun(ip, user, pass, 'rundll32.exe user32.dll,LockWorkStation');
        },
    },

    /** say — texto a voz en host Windows */
    say: {
        usage: 'say <ip> <usuario> <contraseña> <"texto">',
        desc:  'Reproduce texto a voz en el host Windows',
        run:   async ([ip, user, pass, ...rest]) => {
            if (!ip || !validIp(ip)) return 'Uso: say <ip> <usuario> <contraseña> <"texto">';
            if (!user || !pass)      return 'Usuario y contraseña requeridos';
            if (!await checkWmiexec()) return 'Error: wmiexec.py no encontrado. Instalar Impacket: pip install impacket';
            const text = sanitizeText(rest.join(' ').replace(/^"|"$/g, ''));
            if (!text) return 'Texto vacío';
            const ps = `powershell -Command "Add-Type -AssemblyName System.Speech; (New-Object System.Speech.Synthesis.SpeechSynthesizer).Speak('${text}')"`;
            return wmiRun(ip, user, pass, ps);
        },
    },

    /** scan — trigerea escaneo manual */
    scan: {
        usage: 'scan',
        desc:  'Trigerea un escaneo ARP manual',
        run:   async (_, io) => {
            const { scan } = require('../scanner/arp-scanner');
            const lines = [];
            await scan((type, msg) => lines.push(`[${type}] ${msg}`));
            return lines.join('\n') || 'Scan completado.';
        },
    },

    /** clear — limpiar terminal */
    clear: {
        usage: 'clear',
        desc:  'Limpia la terminal',
        run:   async () => '\x1b[CLEAR]',
    },
};

// ── Parser de input ───────────────────────────────────────────────────────────

/**
 * Parsea y ejecuta un comando recibido desde el cliente.
 * @param {string} input - Línea de texto ingresada por el usuario
 * @param {object} io    - Instancia de Socket.io (para comandos que emiten eventos)
 * @returns {Promise<string>} Resultado a mostrar en la terminal
 */
async function execute(input, io) {
    const trimmed = input.trim();
    if (!trimmed) return '';

    const [name, ...args] = trimmed.split(/\s+/);
    const cmd = COMMANDS[name.toLowerCase()];

    if (!cmd) {
        return `Comando no reconocido: "${name}". Escribí "help" para ver los comandos disponibles.`;
    }

    try {
        return await cmd.run(args, io);
    } catch (e) {
        return `Error ejecutando "${name}": ${e.message}`;
    }
}

module.exports = { execute, COMMANDS };
