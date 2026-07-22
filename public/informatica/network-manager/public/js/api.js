/**
 * ============================================================
 *  NetMonitor Stealth — api.js
 *  Centralises ALL HTTP communication with the backend.
 *
 *  Rules:
 *    - Every method returns a Promise that resolves to parsed JSON.
 *    - On non-2xx responses, throws an Error with a human-readable
 *      message extracted from the server body when available.
 *    - No UI code here — no toasts, no DOM, no alerts.
 * ============================================================
 */

class Api {
    constructor() {
        this.baseUrl = window.location.origin;
    }

    // ──────────────────────────────────────────────────────────
    //  CORE FETCH HELPER
    // ──────────────────────────────────────────────────────────
    async _fetch(path, options = {}) {
        const safePath = path.startsWith('/') ? path : `/${path}`;
        const url = `${this.baseUrl}${safePath}`;

        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers ?? {}),
            },
        };

        let response;
        try {
            response = await fetch(url, config);
        } catch (networkError) {
            // Network-level failure (server down, no connection, etc.)
            throw new Error(`Red no disponible: ${networkError.message}`);
        }

        if (!response.ok) {
            // Try to get a meaningful error message from the server JSON body
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message ?? body.error ?? `HTTP ${response.status} — ${response.statusText}`);
        }

        return response.json();
    }

    // ──────────────────────────────────────────────────────────
    //  DEVICES
    // ──────────────────────────────────────────────────────────

    /** GET /api/devices — full device list with connections & services */
    getDevices() {
        return this._fetch('api/devices');
    }

    /** GET /api/devices/:id — single device detail */
    getDevice(id) {
        return this._fetch(`/api/devices/${id}`);
    }

    /** GET /api/devices/:id/uptime — up/down event history */
    getDeviceUptime(id, limit = 50) {
        return this._fetch(`/api/devices/${id}/uptime?limit=${limit}`);
    }

    /**
     * PUT /api/devices/:id — update editable fields.
     * @param {string|number} id
     * @param {Object} data  — { hostname, device_type, notes, switch_name,
     *                          switch_ip, port_name, vlan, tags, ... }
     */
    updateDevice(id, data) {
        return this._fetch(`/api/devices/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    /** DELETE /api/devices/:id */
    deleteDevice(id) {
        return this._fetch(`/api/devices/${id}`, { method: 'DELETE' });
    }

    /** DELETE /api/devices/bulk/offline — purge all offline devices */
    deleteOfflineDevices() {
        return this._fetch('api/devices/bulk/offline', { method: 'DELETE' });
    }

    /** GET /api/devices/:id/geo — geolocalización offline (geoip-lite) */
    getDeviceGeo(id) {
        return this._fetch(`/api/devices/${id}/geo`);
    }

    /** GET /api/devices/:id/events — historial de eventos ricos */
    getDeviceEvents(id, limit = 60) {
        return this._fetch(`/api/devices/${id}/events?limit=${limit}`);
    }

    /** GET /api/devices/:id/uptime — historial up/down */
    getDeviceUptime(id, limit = 48) {
        return this._fetch(`/api/devices/${id}/uptime?limit=${limit}`);
    }

    // ──────────────────────────────────────────────────────────
    //  SCANS
    // ──────────────────────────────────────────────────────────

    /** POST /api/scans — trigger a manual ARP scan */
    triggerScan() {
        return this._fetch('api/scans', { method: 'POST' });
    }

    /** GET /api/scans/status — scheduler status */
    getScanStatus() {
        return this._fetch('api/scans/status');
    }

    /** GET /api/scans/history — scan history log */
    getScanHistory(limit = 50) {
        return this._fetch(`/api/scans/history?limit=${limit}`);
    }

    // ──────────────────────────────────────────────────────────
    //  TOPOLOGY
    // ──────────────────────────────────────────────────────────

    /** GET /api/topology — nodes + edges for vis.js */
    getTopology() {
        return this._fetch('api/topology');
    }

    // ──────────────────────────────────────────────────────────
    //  SWITCHES
    // ──────────────────────────────────────────────────────────

    /** GET /api/switches */
    getSwitches() {
        return this._fetch('api/switches');
    }

    /**
     * POST /api/switches
     * @param {{ name:string, ip:string, snmp_community:string, snmp_version:number }} data
     */
    createSwitch(data) {
        return this._fetch('api/switches', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    /** GET /api/switches/:id/traffic — port traffic counters with rate */
    getSwitchTraffic(id) {
        return this._fetch(`/api/switches/${id}/traffic`);
    }

    /** GET /api/switches/:id/ports — CAM table + device info + traffic */
    getSwitchPorts(id) {
        return this._fetch(`/api/switches/${id}/ports`);
    }

    /** POST /api/switches/:id/poll — trigger on-demand SNMP poll */
    pollSwitch(id) {
        return this._fetch(`/api/switches/${id}/poll`, { method: 'POST' });
    }

    /** DELETE /api/switches/:id */
    deleteSwitch(id) {
        return this._fetch(`/api/switches/${id}`, { method: 'DELETE' });
    }

    /** GET /api/switches/:id/l3 — VLANs + ARP + interface IPs + routes + CDP */
    getSwitchL3(id) {
        return this._fetch(`/api/switches/${id}/l3`);
    }

    /** GET /api/switches/:id/arp — ARP table only */
    getSwitchArp(id) {
        return this._fetch(`/api/switches/${id}/arp`);
    }

    /** GET /api/switches/:id/vlans — VLAN list with port membership */
    getSwitchVlans(id) {
        return this._fetch(`/api/switches/${id}/vlans`);
    }

    /** GET /api/switches/:id/routes — routing table */
    getSwitchRoutes(id) {
        return this._fetch(`/api/switches/${id}/routes`);
    }

    // ──────────────────────────────────────────────────────────
    //  ACTIONS  (ping, scan, traceroute, speedtest, wol)
    // ──────────────────────────────────────────────────────────

    /** POST /api/actions/ping */
    pingDevice(ip) {
        return this._fetch('api/actions/ping', { method: 'POST', body: JSON.stringify({ ip }) });
    }

    /** POST /api/actions/ping-detail — 4 pings with RTT stats */
    pingDetail(ip) {
        return this._fetch('api/actions/ping-detail', { method: 'POST', body: JSON.stringify({ ip }) });
    }

    /** POST /api/actions/portscan — TCP connect on common ports */
    portScan(ip) {
        return this._fetch('api/actions/portscan', { method: 'POST', body: JSON.stringify({ ip }) });
    }

    /** POST /api/actions/traceroute */
    traceroute(ip) {
        return this._fetch('api/actions/traceroute', { method: 'POST', body: JSON.stringify({ ip }) });
    }

    /** GET /api/actions/speedtest — server-side internet speed */
    speedTest() {
        return this._fetch('api/actions/speedtest');
    }

    /** POST /api/actions/geo-traceroute — traceroute enriched with geolocation */
    geoTraceroute(target) {
        return this._fetch('api/actions/geo-traceroute', {
            method: 'POST',
            body: JSON.stringify({ target }),
        });
    }

    /** GET /api/devices/:id/bandwidth — switch port RX/TX via SNMP */
    getDeviceBandwidth(id) {
        return this._fetch(`/api/devices/${id}/bandwidth`);
    }

    /** GET /api/bandwidth/summary — network-wide bandwidth aggregation */
    getBandwidthSummary() {
        return this._fetch('/api/bandwidth/summary');
    }

    /** POST /api/actions/wol — Wake-on-LAN magic packet */
    wakeDevice(mac) {
        return this._fetch('api/actions/wol', { method: 'POST', body: JSON.stringify({ mac }) });
    }

    /** POST /auth/change-password */
    changePassword(data) {
        return this._fetch('/auth/change-password', { method: 'POST', body: JSON.stringify(data) });
    }

    /** POST /auth/logout */
    logout() {
        return this._fetch('/auth/logout', { method: 'POST' });
    }

    /** GET /api/settings */
    getSettings() {
        return this._fetch('api/settings');
    }

    /** POST /api/settings — { key: value, ... } */
    saveSettings(data) {
        return this._fetch('api/settings', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    // ──────────────────────────────────────────────────────────
    //  POWER MANAGEMENT  (WoL / Shutdown / Reboot)
    // ──────────────────────────────────────────────────────────

    /** POST /api/power/wol/:id — WoL a un dispositivo */
    wolDevice(id, options = {}) {
        return this._fetch(`/api/power/wol/${id}`, {
            method: 'POST',
            body: JSON.stringify(options),
        });
    }

    /**
     * POST /api/power/wol/bulk — WoL masivo
     * @param {{ ip_start?, ip_end?, device_type?, ids?, broadcast_addr?, wol_port? }} filter
     */
    wolBulk(filter) {
        return this._fetch('/api/power/wol/bulk', {
            method: 'POST',
            body: JSON.stringify(filter),
        });
    }

    /** POST /api/power/shutdown/:id */
    shutdownDevice(id, options = {}) {
        return this._fetch(`/api/power/shutdown/${id}`, {
            method: 'POST',
            body: JSON.stringify(options),
        });
    }

    /** POST /api/power/reboot/:id */
    rebootDevice(id, options = {}) {
        return this._fetch(`/api/power/reboot/${id}`, {
            method: 'POST',
            body: JSON.stringify(options),
        });
    }

    /**
     * POST /api/power/shutdown/bulk
     * @param {{ ip_start?, ip_end?, device_type?, ids?, delay?, ssh_user?, ssh_port? }} filter
     */
    shutdownBulk(filter) {
        return this._fetch('/api/power/shutdown/bulk', {
            method: 'POST',
            body: JSON.stringify(filter),
        });
    }

    /** POST /api/power/reboot/bulk */
    rebootBulk(filter) {
        return this._fetch('/api/power/reboot/bulk', {
            method: 'POST',
            body: JSON.stringify(filter),
        });
    }

    /** GET /api/power/config */
    getPowerConfig() {
        return this._fetch('/api/power/config');
    }

    /** PUT /api/power/config */
    savePowerConfig(data) {
        return this._fetch('/api/power/config', {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    /** GET /api/power/log */
    getPowerLog(limit = 50) {
        return this._fetch(`/api/power/log?limit=${limit}`);
    }

    // ──────────────────────────────────────────────────────────
    //  CONFIG / MAINTENANCE
    // ──────────────────────────────────────────────────────────

    // ──────────────────────────────────────────────────────────
    //  IPAM
    // ──────────────────────────────────────────────────────────

    /** GET /api/ipam?subnet=X.X.X — subnet slot map */
    getIpam(subnet = '') {
        const qs = subnet ? `?subnet=${encodeURIComponent(subnet)}` : '';
        return this._fetch(`/api/ipam${qs}`);
    }

    /** GET /api/ipam/subnets — list of known /24 prefixes */
    getIpamSubnets() {
        return this._fetch('api/ipam/subnets');
    }

    /** POST /api/ipam/pin — pin a specific IP { ip, label, color } */
    pinIp(data) {
        return this._fetch('api/ipam/pin', { method: 'POST', body: JSON.stringify(data) });
    }

    /** DELETE /api/ipam/pin — unpin a specific IP { ip } */
    unpinIp(ip) {
        return this._fetch('api/ipam/pin', { method: 'DELETE', body: JSON.stringify({ ip }) });
    }

    // ──────────────────────────────────────────────────────────
    //  CREDENTIALS (vault)
    // ──────────────────────────────────────────────────────────

    getCredentials()          { return this._fetch('api/credentials'); }
    createCredential(data)    { return this._fetch('api/credentials',      { method: 'POST',   body: JSON.stringify(data) }); }
    deleteCredential(id)      { return this._fetch(`/api/credentials/${id}`,{ method: 'DELETE' }); }
    linkCredential(id, devId) { return this._fetch(`/api/credentials/${id}/link/${devId}`, { method: 'POST' }); }

    /** GET /api/config */
    getConfig() {
        return this._fetch('api/config');
    }

    /** POST /api/config/reset — wipe device inventory */
    resetDatabase() {
        return this._fetch('api/config/reset', { method: 'POST' });
    }
}
