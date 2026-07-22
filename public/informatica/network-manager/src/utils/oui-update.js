/**
 * oui-update.js — descarga el CSV oficial de IEEE y genera oui-db.json
 *
 * Uso:  node src/utils/oui-update.js
 *
 * Fuente: https://regauth.standards.ieee.org/standards-ra-web/pub/view.html#registries
 * CSV MA-L (MAC Address Large): ~36k entradas, ~4 MB
 */

const https   = require('https');
const fs      = require('fs');
const path    = require('path');
const zlib    = require('zlib');

const CSV_URL  = 'https://standards-oui.ieee.org/oui/oui.csv';
const OUT_FILE = path.join(__dirname, 'oui-db.json');

function fetchCsv(url) {
    return new Promise((resolve, reject) => {
        https.get(url, { headers: { 'Accept-Encoding': 'gzip' } }, (res) => {
            if (res.statusCode === 301 || res.statusCode === 302) {
                return fetchCsv(res.headers.location).then(resolve).catch(reject);
            }
            if (res.statusCode !== 200) {
                return reject(new Error(`HTTP ${res.statusCode}`));
            }

            const chunks = [];
            const stream = res.headers['content-encoding'] === 'gzip'
                ? res.pipe(zlib.createGunzip())
                : res;

            stream.on('data', c => chunks.push(c));
            stream.on('end',  () => resolve(Buffer.concat(chunks).toString('utf8')));
            stream.on('error', reject);
        }).on('error', reject);
    });
}

function parseCsv(csv) {
    const db = {};
    const lines = csv.split('\n');
    // Skip header row: Registry,Assignment,Organization Name,Organization Address
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;

        // Split on first 3 commas — vendor name may contain commas if quoted
        // Format: MA-L,AABBCC,"Vendor Name" or MA-L,AABBCC,Vendor Name,...
        const parts = line.split(',');
        if (parts.length < 3) continue;

        const hex = parts[1].trim().replace(/"/g, '');
        if (!/^[0-9A-F]{6}$/i.test(hex)) continue;

        // Vendor name: may be quoted or not
        let vendor = parts[2].trim().replace(/^"|"$/g, '');
        if (!vendor) continue;

        const h = hex.toLowerCase();
        const prefix = `${h[0]}${h[1]}:${h[2]}${h[3]}:${h[4]}${h[5]}`;
        db[prefix] = vendor;
    }
    return db;
}

async function main() {
    console.log('[OUI] Descargando base de datos IEEE...');
    const csv = await fetchCsv(CSV_URL);
    console.log(`[OUI] Parseando ${csv.split('\n').length} líneas...`);
    const db  = parseCsv(csv);
    const count = Object.keys(db).length;
    fs.writeFileSync(OUT_FILE, JSON.stringify(db));
    console.log(`[OUI] Generado oui-db.json con ${count} entradas → ${OUT_FILE}`);
}

main().catch(e => { console.error('[OUI] Error:', e.message); process.exit(1); });
