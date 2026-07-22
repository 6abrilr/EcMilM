/**
 * Device Fingerprinter — identifica OS, tipo de dispositivo, y hostname.
 * 
 * Técnicas:
 * 1. TTL del ping → OS (Windows=128, Linux/Android=64, iOS=64, NetDev=255)
 * 2. NetBIOS (nbtstat) → hostname Windows
 * 3. Vendor MAC + OS → tipo de dispositivo
 */
const { exec } = require('child_process');
const os = require('os');
const isWin = os.platform() === 'win32';

// ── OS por TTL ──
const TTL_MAP = [
    { min: 120, max: 128, os: 'Windows', family: 'windows' },
    { min: 60, max: 64, os: 'Linux/Android/iOS', family: 'unix' },
    { min: 240, max: 255, os: 'Network Device', family: 'network' },
    { min: 30, max: 32, os: 'Embedded', family: 'embedded' },
];

// ── Vendor keyword → tipo de dispositivo ─────────────────────────────────────
// Matchea por substring (case-insensitive) contra el nombre completo de IEEE
const VENDOR_RULES = [
    // Network devices
    { match: 'cisco',       type: 'Switch/Router',  icon: '🔀' },
    { match: 'mikrotik',    type: 'Router',          icon: '🌐' },
    { match: 'tp-link',     type: 'Router/AP',       icon: '🌐' },
    { match: 'tenda',       type: 'Router/AP',       icon: '🌐' },
    { match: 'fortinet',    type: 'Firewall',         icon: '🛡️' },
    { match: 'aruba',       type: 'Switch/AP',        icon: '🔀' },
    { match: 'ubiquiti',    type: 'AP/Router',        icon: '🌐' },
    { match: 'nexxt',       type: 'Router/AP',        icon: '🌐' },
    { match: 'zyxel',       type: 'Router/AP',        icon: '🌐' },
    { match: 'netgear',     type: 'Router/AP',        icon: '🌐' },
    { match: 'd-link',      type: 'Router/AP',        icon: '🌐' },
    { match: 'linksys',     type: 'Router/AP',        icon: '🌐' },
    { match: 'asus',        type: 'Router/PC',        icon: '🌐' },

    // Phones / tablets
    { match: 'apple',       type: 'Apple Device',     icon: '📱' },
    { match: 'samsung',     type: 'Teléfono',         icon: '📱' },
    { match: 'huawei',      type: 'Teléfono',         icon: '📱' },
    { match: 'xiaomi',      type: 'Teléfono',         icon: '📱' },
    { match: 'motorola',    type: 'Teléfono',         icon: '📱' },
    { match: 'lg electron', type: 'Teléfono',         icon: '📱' },
    { match: 'oneplus',     type: 'Teléfono',         icon: '📱' },
    { match: 'oppo',        type: 'Teléfono',         icon: '📱' },
    { match: 'vivo',        type: 'Teléfono',         icon: '📱' },
    { match: 'realme',      type: 'Teléfono',         icon: '📱' },

    // Printers
    { match: 'epson',       type: 'Impresora',        icon: '🖨️' },
    { match: 'brother',     type: 'Impresora',        icon: '🖨️' },
    { match: 'canon',       type: 'Impresora',        icon: '🖨️' },
    { match: 'lexmark',     type: 'Impresora',        icon: '🖨️' },
    { match: 'ricoh',       type: 'Impresora',        icon: '🖨️' },
    { match: 'xerox',       type: 'Impresora',        icon: '🖨️' },
    { match: 'kyocera',     type: 'Impresora',        icon: '🖨️' },

    // Cámaras / NVR / IoT
    { match: 'hikvision',   type: 'Cámara IP',        icon: '📷' },
    { match: 'hangzhou hik',type: 'Cámara IP',        icon: '📷' },
    { match: 'dahua',       type: 'Cámara IP',        icon: '📷' },
    { match: 'axis comm',   type: 'Cámara IP',        icon: '📷' },
    { match: 'hanwha',      type: 'Cámara IP',        icon: '📷' },
    { match: 'reolink',     type: 'Cámara IP',        icon: '📷' },
    { match: 'wyze',        type: 'Cámara IP',        icon: '📷' },
    { match: 'sonos',       type: 'Altavoz',          icon: '🔊' },
    { match: 'roku',        type: 'Streaming',        icon: '📺' },
    { match: 'amazon tech', type: 'Echo/Fire',        icon: '📺' },
    { match: 'google',      type: 'Chromecast/Nest',  icon: '📺' },
    { match: 'tcl king',    type: 'Smart TV',         icon: '📺' },
    { match: 'universal el',type: 'Smart TV',         icon: '📺' },
    { match: 'espressif',   type: 'IoT/ESP',          icon: '📟' },
    { match: 'raspberry',   type: 'Raspberry Pi',     icon: '🖥️' },

    // PCs / servers
    { match: 'dell',        type: 'PC/Laptop',        icon: '💻' },
    { match: 'lenovo',      type: 'PC/Laptop',        icon: '💻' },
    { match: 'hewlett',     type: 'PC/Laptop',        icon: '💻' },
    { match: 'gigabyte',    type: 'PC',               icon: '🖥️' },
    { match: 'intel corp',  type: 'PC',               icon: '🖥️' },
    { match: 'realtek',     type: 'PC',               icon: '🖥️' },

    // VMs
    { match: 'vmware',      type: 'VM',               icon: '💻' },
    { match: 'hyper-v',     type: 'VM',               icon: '💻' },
    { match: 'virtualbox',  type: 'VM',               icon: '💻' },
    { match: 'qemu',        type: 'VM',               icon: '💻' },
];

/**
 * Normaliza el vendor largo de IEEE a un nombre corto legible.
 * Ej: "TP-LINK TECHNOLOGIES CO., LTD." → "TP-Link"
 */
function normalizeVendor(raw) {
    if (!raw) return '';
    const r = raw.toLowerCase();
    if (r.includes('tp-link'))   return 'TP-Link';
    if (r.includes('mikrotik'))  return 'MikroTik';
    if (r.includes('cisco'))     return 'Cisco';
    if (r.includes('apple'))     return 'Apple';
    if (r.includes('samsung'))   return 'Samsung';
    if (r.includes('huawei'))    return 'Huawei';
    if (r.includes('xiaomi'))    return 'Xiaomi';
    if (r.includes('motorola'))  return 'Motorola';
    if (r.includes('fortinet'))  return 'Fortinet';
    if (r.includes('ubiquiti'))  return 'Ubiquiti';
    if (r.includes('aruba'))     return 'Aruba';
    if (r.includes('netgear'))   return 'Netgear';
    if (r.includes('zyxel'))     return 'ZyXEL';
    if (r.includes('d-link'))    return 'D-Link';
    if (r.includes('linksys'))   return 'Linksys';
    if (r.includes('tenda'))     return 'Tenda';
    if (r.includes('asus'))      return 'ASUS';
    if (r.includes('dell'))      return 'Dell';
    if (r.includes('lenovo'))    return 'Lenovo';
    if (r.includes('hewlett') || r.includes('hp ') || r === 'hp') return 'HP';
    if (r.includes('gigabyte'))  return 'Gigabyte';
    if (r.includes('intel'))     return 'Intel';
    if (r.includes('realtek'))   return 'Realtek';
    if (r.includes('vmware'))    return 'VMware';
    if (r.includes('epson') || r.includes('seiko epson')) return 'Epson';
    if (r.includes('brother'))   return 'Brother';
    if (r.includes('canon'))     return 'Canon';
    if (r.includes('lexmark'))   return 'Lexmark';
    if (r.includes('ricoh'))     return 'Ricoh';
    if (r.includes('xerox'))     return 'Xerox';
    if (r.includes('nexxt'))     return 'Nexxt';
    if (r.includes('hikvision') || r.includes('hangzhou hik')) return 'Hikvision';
    if (r.includes('dahua'))     return 'Dahua';
    if (r.includes('espressif')) return 'Espressif (IoT)';
    if (r.includes('tcl king'))  return 'TCL';
    if (r.includes('universal el')) return 'Universal Electronics';
    if (r.includes('google'))    return 'Google';
    if (r.includes('amazon'))    return 'Amazon';
    if (r.includes('lg electron')) return 'LG';
    if (r.includes('oppo'))      return 'OPPO';
    if (r.includes('vivo'))      return 'vivo';
    // Fallback: capitalize first word
    return raw.split(/[\s,]/)[0];
}

/**
 * Obtiene el TTL de un ping a una IP.
 * @param {string} ip
 * @returns {Promise<number|null>} TTL value o null
 */
function getTTL(ip) {
    return new Promise((resolve) => {
        const cmd = isWin
            ? `ping -n 1 -w 1000 ${ip}`
            : `ping -c 1 -W 1 ${ip}`;

        exec(cmd, { timeout: 3000 }, (err, stdout) => {
            if (err || !stdout) return resolve(null);
            // Windows EN: "TTL=128", Windows ES: "Duración de vida=128"
            // Linux: "ttl=64"
            const match = stdout.match(/ttl[=:]\s*(\d+)/i) || stdout.match(/vida[=:]\s*(\d+)/i);
            resolve(match ? parseInt(match[1]) : null);
        });
    });
}

/**
 * Identifica el OS basado en el TTL.
 * @param {number} ttl
 * @returns {{ os: string, family: string }}
 */
function identifyOS(ttl) {
    if (!ttl) return { os: '', family: '' };

    for (const entry of TTL_MAP) {
        if (ttl >= entry.min && ttl <= entry.max) {
            return { os: entry.os, family: entry.family };
        }
    }

    // Inferir por cercanía
    if (ttl > 64 && ttl <= 128) return { os: 'Windows', family: 'windows' };
    if (ttl > 0 && ttl <= 64) return { os: 'Linux/Android/iOS', family: 'unix' };
    if (ttl > 128) return { os: 'Network Device', family: 'network' };

    return { os: `Desconocido (TTL=${ttl})`, family: '' };
}

/**
 * Obtiene el hostname NetBIOS de un equipo Windows.
 * @param {string} ip
 * @returns {Promise<string>} hostname o ''
 */
function getNetBIOSName(ip) {
    return new Promise((resolve) => {
        if (isWin) {
            // Windows: nbtstat -A <ip>
            exec(`nbtstat -A ${ip}`, { timeout: 5000, encoding: 'utf-8' }, (err, stdout) => {
                if (err || !stdout) return resolve('');
                const lines = stdout.split('\n');
                for (const line of lines) {
                    const match = line.match(/^\s+(\S+)\s+<00>\s+UNIQUE/i);
                    if (match) {
                        const name = match[1].trim();
                        if (name && !name.startsWith('~') && name.length > 1) {
                            return resolve(name);
                        }
                    }
                }
                resolve('');
            });
        } else {
            // Linux: nmblookup -A <ip> (samba-common)
            exec(`nmblookup -A ${ip} 2>/dev/null`, { timeout: 5000, encoding: 'utf-8' }, (err, stdout) => {
                if (err || !stdout) return resolve('');
                const lines = stdout.split('\n');
                for (const line of lines) {
                    // "HOSTNAME       <00> -         B <ACTIVE>"
                    const match = line.match(/^\s+(\S+)\s+<00>\s+-\s+\w\s+<ACTIVE>/i);
                    if (match) {
                        const name = match[1].trim();
                        if (name && name.length > 1) return resolve(name);
                    }
                }
                resolve('');
            });
        }
    });
}

/**
 * Infiere el tipo de dispositivo basado en vendor (nombre IEEE completo) + OS.
 */
function inferDeviceType(vendorRaw, osFamily) {
    const v = (vendorRaw || '').toLowerCase();

    for (const rule of VENDOR_RULES) {
        if (v.includes(rule.match)) {
            // Refinar Apple: unix=iPhone/iPad, windows=Mac Boot Camp, network=AirPort
            if (rule.match === 'apple') {
                if (osFamily === 'windows') return { type: 'Mac (Boot Camp)', icon: '💻' };
                if (osFamily === 'network') return { type: 'AirPort/HomePod', icon: '🌐' };
                return { type: 'iPhone/iPad/Mac', icon: '📱' };
            }
            return { type: rule.type, icon: rule.icon };
        }
    }

    // Fallback por OS
    if (osFamily === 'network')  return { type: 'Equipo de Red',        icon: '🔀' };
    if (osFamily === 'windows')  return { type: 'PC Windows',           icon: '🖥️' };
    if (osFamily === 'unix')     return { type: 'Linux/Android',        icon: '💻' };
    if (osFamily === 'embedded') return { type: 'Dispositivo embebido', icon: '📟' };

    return { type: 'Genérico', icon: '❓' };
}

/**
 * Reverse DNS lookup para obtener hostname.
 */
function getReverseDNS(ip) {
    return new Promise((resolve) => {
        exec(`nslookup ${ip} 2>/dev/null`, { timeout: 2000 }, (err, stdout) => {
            if (err || !stdout) return resolve('');
            const m = stdout.match(/name\s*=\s*([^\s]+)/i);
            if (m) {
                // Limpiar trailing dot y sufijos locales
                const name = m[1].replace(/\.$/, '').replace(/\.local$/, '');
                return resolve(name.split('.')[0]); // solo el primer label
            }
            resolve('');
        });
    });
}

/**
 * mDNS lookup via avahi-browse (Linux) para hostname y tipo de servicio.
 */
function getMdnsName(ip) {
    return new Promise((resolve) => {
        if (isWin) return resolve('');
        exec(`avahi-resolve -a ${ip} 2>/dev/null`, { timeout: 2000 }, (err, stdout) => {
            if (err || !stdout) return resolve('');
            const m = stdout.match(/\S+\s+(\S+\.local)/i);
            if (m) return resolve(m[1].replace(/\.local$/, ''));
            resolve('');
        });
    });
}

/**
 * Fingerprinting completo de un dispositivo.
 * @param {string} ip
 * @param {string} vendorRaw  - nombre IEEE completo (ej: "TP-LINK TECHNOLOGIES CO.")
 * @returns {Promise<{os, osFamily, ttl, hostname, vendor, deviceType, deviceIcon}>}
 */
async function fingerprint(ip, vendorRaw) {
    // 1. TTL → OS
    const ttl = await getTTL(ip);
    const { os, family: osFamily } = identifyOS(ttl);

    // 2. Hostname: NetBIOS (Windows) + reverse DNS + mDNS en paralelo
    const [netbios, rdns, mdns] = await Promise.all([
        osFamily === 'windows' ? getNetBIOSName(ip) : Promise.resolve(''),
        getReverseDNS(ip),
        getMdnsName(ip),
    ]);
    const hostname = netbios || mdns || rdns || '';

    // 3. Vendor normalizado
    const vendor = normalizeVendor(vendorRaw);

    // 4. Tipo de dispositivo
    let { type: deviceType, icon: deviceIcon } = inferDeviceType(vendorRaw, osFamily);

    // 5. Refinar gateway: .1/.254 + (TTL de network device O vendor de networking)
    const lastOctet = parseInt(ip.split('.').pop(), 10);
    const isGatewayIp = lastOctet === 1 || lastOctet === 254;
    const isNetworkVendor = ['router', 'gateway', 'firewall', 'switch', 'ap'].some(k => deviceType.toLowerCase().includes(k));
    const isNetworkTTL = osFamily === 'network';
    if (isGatewayIp && (isNetworkVendor || isNetworkTTL || deviceType === 'Genérico')) {
        deviceType = 'Router/Gateway';
        deviceIcon = '🌐';
    }

    return { os, osFamily, ttl, hostname, vendor, deviceType, deviceIcon };
}

module.exports = { fingerprint, getTTL, identifyOS, getNetBIOSName, getMdnsName, getReverseDNS, inferDeviceType, normalizeVendor };
