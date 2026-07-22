const path = require('path');

module.exports = {
    // ── Server ────────────────────────────────────────────
    server: {
        host: '0.0.0.0',
        port: 3000,
    },

    // ── Database ──────────────────────────────────────────
    db: {
        path: path.join(__dirname, 'data', 'network.db'),
    },

    // ── ARP Scanner (pasivo) ──────────────────────────────
    scanner: {
        arpIntervalMs: 5 * 60 * 1000,       // cada 5 minutos
        dnsTimeoutMs: 2000,                   // timeout DNS reverso
        offlineThresholdMs: 15 * 60 * 1000,  // 15 min sin ver = offline
        jitterMs: 30 * 1000,                  // ±30s aleatorio
        // Rangos específicos a escanear. null = auto-detectar desde interfaces.
        // Formato: ['ip/cidr', ...] — se puede configurar desde Settings UI en el cliente.
        targetSubnets: null,
    },

    // ── SNMP (Fase 2 — deshabilitado por defecto) ────────
    snmp: {
        enabled: false,
        intervalMs: 15 * 60 * 1000,          // cada 15 minutos
        rateLimitMs: 30 * 1000,              // 30s entre queries por switch
        timeout: 5000,
        retries: 1,
        defaultCommunity: 'public',
        version: 1,                           // 0=v1, 1=v2c, 3=v3
    },
};
