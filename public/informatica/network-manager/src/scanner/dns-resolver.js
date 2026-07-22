/**
 * DNS Resolver — resolución reversa de IP a hostname.
 * Usa el DNS interno de la red (tráfico normal, no detectable).
 */
const dns = require('dns');
const config = require('../../config');

// Cache para evitar queries repetidas
const cache = new Map();
const CACHE_TTL = 10 * 60 * 1000; // 10 minutos

/**
 * Resolución reversa de IP a hostname.
 * @param {string} ip
 * @returns {Promise<string>}
 */
function reverse(ip) {
    // Revisar cache
    const cached = cache.get(ip);
    if (cached && Date.now() - cached.time < CACHE_TTL) {
        return Promise.resolve(cached.hostname);
    }

    return new Promise((resolve) => {
        const timer = setTimeout(() => {
            resolve('');
        }, config.scanner.dnsTimeoutMs);

        dns.reverse(ip, (err, hostnames) => {
            clearTimeout(timer);
            if (err || !hostnames || hostnames.length === 0) {
                cache.set(ip, { hostname: '', time: Date.now() });
                resolve('');
            } else {
                const hostname = hostnames[0];
                cache.set(ip, { hostname, time: Date.now() });
                resolve(hostname);
            }
        });
    });
}

/**
 * Limpia la cache de DNS.
 */
function clearCache() {
    cache.clear();
}

module.exports = { reverse, clearCache };
