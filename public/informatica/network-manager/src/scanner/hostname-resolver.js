/**
 * Hostname Resolver — resolución multi-protocolo.
 *
 * Métodos en orden de prioridad:
 *   1. mDNS  (UDP 5353, multicast 224.0.0.251) — iPhones, Android, Mac, IoT
 *   2. SSDP  (UDP 1900, unicast al device)     — smart TVs, routers, NAS, impresoras
 *   3. DNS reverse                             — cualquier cosa con PTR en el DNS local
 *
 * Todos corren en paralelo. Se retorna el primer resultado no-vacío.
 * NetBIOS se maneja en fingerprinter.js (solo Windows), no se duplica aquí.
 */

const dgram  = require('dgram');
const dns    = require('dns').promises;
const http   = require('http');
const https  = require('https');

// ── Cache compartida ────────────────────────────────────────────
const _cache    = new Map();
const CACHE_TTL = 10 * 60 * 1000; // 10 min

function fromCache(ip)          { const e = _cache.get(ip); return (e && Date.now() - e.t < CACHE_TTL) ? e.v : null; }
function toCache(ip, hostname)  { _cache.set(ip, { v: hostname, t: Date.now() }); }

// ── Helpers ─────────────────────────────────────────────────────

function withTimeout(promise, ms) {
    return Promise.race([
        promise,
        new Promise(r => setTimeout(() => r(''), ms)),
    ]);
}

/** Retorna el primer valor truthy de un array de promesas (no hace short-circuit
 *  nativo, pero descarta vacíos y retorna tan pronto hay uno bueno). */
async function firstTruthy(promises) {
    return new Promise((resolve) => {
        let pending = promises.length;
        if (!pending) return resolve('');

        for (const p of promises) {
            p.then(v => {
                if (v && v.trim()) resolve(v.trim());
                else if (--pending === 0) resolve('');
            }).catch(() => { if (--pending === 0) resolve(''); });
        }
    });
}

// ── mDNS PTR ────────────────────────────────────────────────────

/**
 * Construye un paquete de query DNS para el PTR de una IP.
 * Ej: 192.168.1.5 → query para "5.1.168.192.in-addr.arpa" tipo PTR
 */
function _buildPtrQuery(ip) {
    const labels = [...ip.split('.').reverse(), 'in-addr', 'arpa'];
    const nameBytes = [];
    for (const label of labels) {
        nameBytes.push(label.length, ...Buffer.from(label));
    }
    nameBytes.push(0); // terminator
    return Buffer.concat([
        Buffer.from([0x00, 0x00, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00]),
        Buffer.from(nameBytes),
        Buffer.from([0x00, 0x0C, 0x00, 0x01]), // QTYPE=PTR, QCLASS=IN
    ]);
}

/**
 * Parsea un nombre DNS con soporte a punteros de compresión.
 */
function _readDnsName(buf, offset) {
    const labels  = [];
    let jumped    = false;
    let retOffset = -1;

    while (offset < buf.length) {
        const len = buf[offset];
        if (len === 0) { offset++; break; }
        if ((len & 0xC0) === 0xC0) {
            if (!jumped) retOffset = offset + 2;
            offset = ((len & 0x3F) << 8) | buf[offset + 1];
            jumped = true;
            continue;
        }
        labels.push(buf.subarray(offset + 1, offset + 1 + len).toString('ascii'));
        offset += 1 + len;
    }
    return { name: labels.join('.'), next: jumped ? retOffset : offset };
}

/**
 * Extrae el primer nombre PTR de una respuesta DNS.
 */
function _parsePtrResponse(buf) {
    if (buf.length < 12) return null;
    const qdcount = (buf[4] << 8) | buf[5];
    const ancount = (buf[6] << 8) | buf[7];
    if (!ancount) return null;

    let offset = 12;
    // Saltar sección de preguntas
    for (let i = 0; i < qdcount && offset < buf.length; i++) {
        offset = _readDnsName(buf, offset).next + 4; // +QTYPE+QCLASS
    }
    // Parsear respuestas
    for (let i = 0; i < ancount && offset < buf.length; i++) {
        const nameRes = _readDnsName(buf, offset);
        offset = nameRes.next;
        if (offset + 10 > buf.length) break;
        const type  = (buf[offset]     << 8) | buf[offset + 1];
        const rdlen = (buf[offset + 8] << 8) | buf[offset + 9];
        offset += 10;
        if (type === 12 /* PTR */) {
            const ptrRes = _readDnsName(buf, offset);
            // Limpiar sufijos: "iPhone-de-Juan.local" → "iPhone-de-Juan"
            const name = ptrRes.name.replace(/\.local\.?$/, '');
            if (name) return name;
        }
        offset += rdlen;
    }
    return null;
}

/**
 * Envía una query mDNS PTR a 224.0.0.251:5353 y escucha respuesta del device.
 */
function queryMdns(ip) {
    return new Promise((resolve) => {
        let done = false;
        const finish = (v) => { if (!done) { done = true; try { sock.close(); } catch (_) {} resolve(v || ''); } };

        const sock = dgram.createSocket({ type: 'udp4', reuseAddr: true });
        sock.on('error', () => finish(''));

        sock.on('message', (msg, rinfo) => {
            // Aceptar solo respuestas del dispositivo objetivo
            if (rinfo.address !== ip) return;
            const name = _parsePtrResponse(msg);
            if (name) finish(name);
        });

        sock.bind(0, () => {
            try { sock.addMembership('224.0.0.251'); } catch (_) {}
            const pkt = _buildPtrQuery(ip);
            sock.send(pkt, 5353, '224.0.0.251', (err) => {
                if (err) finish('');
            });
        });
    });
}

// ── SSDP / UPnP ─────────────────────────────────────────────────

/**
 * Obtiene la URL del descriptor UPnP y extrae el <friendlyName>.
 */
function _fetchFriendlyName(url) {
    return new Promise((resolve) => {
        const mod = url.startsWith('https') ? https : http;
        let data  = '';
        const req = mod.get(url, { timeout: 1800 }, (res) => {
            res.on('data', c => { data += c; if (data.length > 4096) req.destroy(); });
            res.on('end', () => {
                const m = data.match(/<friendlyName>\s*(.*?)\s*<\/friendlyName>/i);
                resolve(m ? m[1] : '');
            });
        });
        req.on('error', () => resolve(''));
        req.setTimeout(1800, () => { req.destroy(); resolve(''); });
    });
}

/**
 * Envía un M-SEARCH SSDP unicast al device y parsea la LOCATION del descriptor.
 */
function querySsdp(ip) {
    return new Promise((resolve) => {
        let done = false;
        const finish = (v) => { if (!done) { done = true; try { sock.close(); } catch (_) {} resolve(v || ''); } };

        const sock = dgram.createSocket('udp4');
        sock.on('error', () => finish(''));

        sock.on('message', async (msg) => {
            const text = msg.toString('utf8');
            const locMatch = text.match(/^LOCATION:\s*(\S+)/im);
            if (locMatch) {
                const name = await _fetchFriendlyName(locMatch[1]);
                finish(name);
            }
        });

        const msearch = Buffer.from(
            'M-SEARCH * HTTP/1.1\r\n' +
            `HOST: ${ip}:1900\r\n` +
            'MAN: "ssdp:discover"\r\n' +
            'MX: 1\r\n' +
            'ST: ssdp:all\r\n' +
            '\r\n'
        );

        sock.bind(0, () => {
            sock.send(msearch, 1900, ip, (err) => { if (err) finish(''); });
        });
    });
}

// ── DNS reverse ─────────────────────────────────────────────────

function queryDnsReverse(ip) {
    return dns.reverse(ip)
        .then(hosts => hosts[0] || '')
        .catch(() => '');
}

// ── HTTP title grab ─────────────────────────────────────────────

/**
 * Intenta conectar a puerto 80/443 y extrae el <title> de la página.
 * Útil para routers, impresoras, NAS, interfaces de gestión.
 */
function queryHttpTitle(ip) {
    return new Promise((resolve) => {
        let done = false;
        const finish = (v) => { if (!done) { done = true; resolve(v || ''); } };

        const tryPort = (port, mod) => new Promise((res) => {
            let data = '';
            const req = mod.get(`http${port === 443 ? 's' : ''}://${ip}${port !== 80 ? ':' + port : ''}/`, {
                timeout: 1500,
                rejectUnauthorized: false,
            }, (response) => {
                // Ignorar redirects y errores HTTP — solo 2xx
                if (response.statusCode < 200 || response.statusCode >= 400 || (response.statusCode >= 300 && response.statusCode < 400)) {
                    req.destroy();
                    return res('');
                }
                response.on('data', c => {
                    data += c;
                    if (data.length > 2048) req.destroy();
                });
                response.on('end', () => {
                    const m = data.match(/<title[^>]*>\s*(.*?)\s*<\/title>/is);
                    res(m ? m[1].replace(/\s+/g, ' ').trim().slice(0, 60) : '');
                });
            });
            req.on('error', () => res(''));
            req.setTimeout(1500, () => { req.destroy(); res(''); });
        });

        Promise.any([tryPort(80, http), tryPort(443, https)])
            .then(finish)
            .catch(() => finish(''));
    });
}

// ── API pública ─────────────────────────────────────────────────

/**
 * Resuelve el hostname de una IP usando todos los métodos disponibles.
 * @param {string} ip
 * @param {string} [osFamily] — 'windows'|'unix'|'network'|'' (ayuda a priorizar)
 * @returns {Promise<string>}
 */
async function resolve(ip, osFamily = '') {
    const cached = fromCache(ip);
    if (cached !== null) return cached;

    const candidates = [
        withTimeout(queryMdns(ip),       1800),
        withTimeout(queryDnsReverse(ip), 1500),
        withTimeout(queryHttpTitle(ip),  2000),
    ];

    // SSDP solo para no-Windows (Windows ya tiene NetBIOS más confiable)
    if (osFamily !== 'windows') {
        candidates.push(withTimeout(querySsdp(ip), 2200));
    }

    const result = await firstTruthy(candidates);
    toCache(ip, result);
    return result;
}

function clearCache() { _cache.clear(); }

module.exports = { resolve, clearCache };
