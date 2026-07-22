const { execSync } = require('child_process');
const net = require('net');

// Detectar subred WiFi (ignorar VPN, VM, Hyper-V)
const IGNORE_RE = /tailscale|radmin|hamachi|vmware|hyper|vethernet|loopback|virtualbox/i;
const ifaces = require('os').networkInterfaces();
const IGNORE_IP = ['100.', '26.', '172.', '10.'];
let subnet = '192.168.0';
for (const [name, addrs] of Object.entries(ifaces)) {
    if (IGNORE_RE.test(name)) continue;
    for (const a of addrs) {
        if (a.family === 'IPv4' && !a.internal && !IGNORE_IP.some(p => a.address.startsWith(p))) {
            subnet = a.address.split('.').slice(0, 3).join('.');
            console.log(`Usando interfaz: ${name} (${a.address}) — escaneando ${subnet}.0/24`);
        }
    }
}

// Ping sweep + check puerto 8009 (Cast)
const CAST_PORT = 8009;
const results = [];
let pending = 0;

console.log('Escaneando... puede tardar ~15s\n');

for (let i = 1; i <= 254; i++) {
    const ip = `${subnet}.${i}`;
    pending++;

    // Ping
    try {
        execSync(`ping -n 1 -w 300 ${ip}`, { stdio: 'ignore' });
    } catch (_) {}

    // Check puerto Cast
    const sock = new net.Socket();
    sock.setTimeout(400);
    sock.connect(CAST_PORT, ip, () => {
        console.log(`✓ CAST device encontrado: ${ip}:${CAST_PORT}`);
        results.push(ip);
        sock.destroy();
    });
    sock.on('error', () => sock.destroy());
    sock.on('timeout', () => sock.destroy());
    sock.on('close', () => {
        pending--;
        if (pending === 0) {
            console.log(`\nTotal Cast devices: ${results.length}`);
            if (results.length === 0) console.log('Ningún dispositivo Cast encontrado en', subnet + '.0/24');
        }
    });
}
