/**
 * OUI Lookup — identifica fabricante por MAC address.
 *
 * Prioridad:
 *   1. oui-db.json  (generado por oui-update.js, ~36k entradas IEEE completo)
 *   2. OUI_FALLBACK (tabla manual mínima para cuando no existe el JSON)
 *
 * Para actualizar la DB:  node src/utils/oui-update.js
 */

const fs   = require('fs');
const path = require('path');

const DB_PATH = path.join(__dirname, 'oui-db.json');

// ── Fallback mínimo (solo si no existe oui-db.json) ──────────────────────────
const OUI_FALLBACK = {
    '00:00:0c': 'Cisco',    '00:0c:42': 'MikroTik', '14:cc:20': 'TP-Link',
    'c8:3a:35': 'Tenda',    '00:09:0f': 'Fortinet',  '00:0b:cd': 'HP',
    '00:06:5b': 'Dell',     '00:e0:4c': 'Realtek',   '00:0c:29': 'VMware',
    '00:50:56': 'VMware',   '00:03:93': 'Apple',     '00:07:ab': 'Samsung',
    '00:e0:fc': 'Huawei',   '00:15:5d': 'Microsoft', '52:54:00': 'QEMU/KVM',
    '08:00:27': 'VirtualBox',
};

// ── Load DB ───────────────────────────────────────────────────────────────────
let _db = null;

function getDb() {
    if (_db) return _db;
    try {
        _db = JSON.parse(fs.readFileSync(DB_PATH, 'utf8'));
        console.log(`[OUI] DB cargada: ${Object.keys(_db).length} entradas`);
    } catch {
        console.warn('[OUI] oui-db.json no encontrado — usando fallback. Ejecutá: node src/utils/oui-update.js');
        _db = OUI_FALLBACK;
    }
    return _db;
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Busca el vendor de un dispositivo por su MAC address.
 * @param {string} mac - MAC en formato xx:xx:xx:xx:xx:xx
 * @returns {string} Nombre del vendor o cadena vacía
 */
function lookup(mac) {
    if (!mac) return '';
    const prefix = mac.toLowerCase().slice(0, 8);
    return getDb()[prefix] || '';
}

module.exports = { lookup };
