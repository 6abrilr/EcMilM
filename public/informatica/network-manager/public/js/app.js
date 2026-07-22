/**
 * ============================================================
 *  NetMonitor Stealth — app.js
 *  Main application controller.
 *
 *  Responsibilities:
 *    - Bootstrap the app on DOMContentLoaded
 *    - Coordinate modules: Api, TableManager, ChartManager,
 *      DetailPanel, ModalManager, ToastManager
 *    - Handle global event delegation (data-action)
 *    - Manage scan lifecycle
 *
 *  What this file does NOT do:
 *    - Render table rows           → table.js  (TableManager)
 *    - Draw charts                 → charts.js (ChartManager)
 *    - Render the detail aside     → detail.js (DetailPanel)
 *    - Show modals                 → modal.js  (ModalManager)
 *    - Make HTTP calls             → api.js    (Api)
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    window.app = new App();
    window.app.init();
});

// La página EA entrega los datos del inventario después de guardar el escaneo.
// El agente remoto no necesita acceso directo ni credenciales de MySQL.
window.addEventListener('message', (event) => {
    if (!event.data || event.data.type !== 'ea:inventory-enrichment' || !window.app) return;
    const enrichment = event.data.devices || {};
    window.app.devices = (window.app.devices || []).map(device => ({
        ...device,
        ...(enrichment[String(device.mac || '').toLowerCase()] || {})
    }));
    window.renderDevicesTable?.(window.app.devices);
});

class App {
    constructor() {
        // ── External module instances ──────────────────────────
        this.api     = new Api();
        this.toast   = new ToastManager();
        this.modal   = new ModalManager(this.toast);
        this.detail  = new DetailPanel(this.api, this.toast);
        this.charts    = new ChartManager();
        this.bandwidth = new BandwidthPanel(this.api, this.charts);
        this.table     = tableManager; // singleton from table.js
        this.socket  = typeof io !== 'undefined' ? io() : null;

        // ── App state ─────────────────────────────────────────
        this.devices   = [];
        this._scanMode = 'periodic'; // 'watch' | 'periodic' | 'scanning' — driven by scan:mode socket event
    }

    // ──────────────────────────────────────────────────────────
    //  INIT
    // ──────────────────────────────────────────────────────────
    async init() {
        console.log('%c🛰️ NetMonitor Stealth — Inicializado', 'color:#22d3ee;font-weight:bold;');
        this._bindEvents();
        this._bindSocketEvents();
        await this.refreshDashboard();
        this.bandwidth.start(this.socket);
    }

    // ──────────────────────────────────────────────────────────
    //  SOCKET.IO
    // ──────────────────────────────────────────────────────────
    _bindSocketEvents() {
        if (!this.socket) return;

        // scan:log — append line to console panel (opens it if not visible)
        this.socket.on('scan:log', ({ msg, type, ts }) => {
            this._appendScanLog(msg, type, ts);
        });

        // scan:complete — mark console as done, close after 4 s
        this.socket.on('scan:complete', ({ stats }) => {
            this._appendScanLog(
                `Scan completo — ${stats?.online ?? '?'} online, ${stats?.newDevices ?? 0} nuevos`,
                'success'
            );
            const statusEl = document.getElementById('scanConsoleStatus');
            if (statusEl) statusEl.innerHTML = '<i class="fas fa-check" style="color:#10b981"></i> Completado';
            setTimeout(() => this._hideScanConsole(), 4000);
        });

        // scan:mode — single source of truth for button/label state
        this.socket.on('scan:mode', ({ mode }) => {
            this._applyModeUI(mode);
        });

        // dashboard:refresh — reload device list + bandwidth
        this.socket.on('dashboard:refresh', () => {
            this.refreshDashboard();
            this.bandwidth.refresh();
        });

        // alert:offline — toast + row highlight
        this.socket.on('alert:offline', ({ device, offlineMin }) => {
            const label = device.hostname || device.ip;
            this.toast.show(`⚠️ ${label} lleva ${offlineMin} min offline`, 'warning', 10000);
            const row = document.querySelector(`tr[data-mac="${device.mac}"]`);
            row?.classList.add('row-alert');
            if (row) setTimeout(() => row.classList.remove('row-alert'), 15000);
        });
    }

    _appendScanLog(msg, type = 'info', ts) {
        const panel = document.getElementById('scanConsole');
        const body  = document.getElementById('scanConsoleBody');
        if (!body) return;

        if (panel && !panel.classList.contains('visible')) {
            body.innerHTML = ''; // clear previous run
            panel.classList.add('visible');
            const statusEl = document.getElementById('scanConsoleStatus');
            if (statusEl) statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> En progreso…';
        }

        const time = new Date(ts ?? Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const line = document.createElement('div');
        line.className = `scan-log-line ${type}`;
        line.innerHTML = `<span class="ts">${time}</span><span class="msg">${msg}</span>`;
        body.appendChild(line);
        body.scrollTop = body.scrollHeight;
    }

    _hideScanConsole() {
        const el = document.getElementById('scanConsole');
        el?.classList.remove('visible');
    }

    _applyModeUI(mode) {
        this._scanMode = mode;
        const btn       = document.getElementById('btnScan');
        const nextLabel = document.getElementById('nextScanLabel');
        const toggleBtn = document.getElementById('btnToggleMode');

        const states = {
            watch:    { html: '<i class="fas fa-eye"></i> NetWatch',          cls: 'btn btn-netwatch', disabled: false, label: '👁 Escucha activa',  title: 'Cambiar a modo periódico' },
            scanning: { html: '<i class="fas fa-spinner fa-spin"></i> Escaneando…', cls: null,             disabled: true,  label: null,               title: null },
            periodic: { html: '<i class="fas fa-sync-alt"></i> Escanear',     cls: 'btn btn-primary',  disabled: false, label: 'Modo periódico',    title: 'Cambiar a NetWatch' },
        };
        const s = states[mode] ?? states.periodic;

        if (btn) {
            btn.innerHTML = s.html;
            btn.disabled  = s.disabled;
            if (s.cls) btn.className = s.cls;
        }
        if (nextLabel && s.label) nextLabel.textContent = s.label;
        if (toggleBtn && s.title) toggleBtn.title = s.title;
    }

    // ──────────────────────────────────────────────────────────
    //  EVENT BINDING
    //  Uses a single delegated listener on document.
    //  Any element with [data-action="methodName"] will call
    //  this[methodName](element) automatically.
    // ──────────────────────────────────────────────────────────
    _bindEvents() {
        // Global action delegation
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;
            e.stopPropagation();

            const action = target.dataset.action;
            if (typeof this[action] === 'function') {
                this[action](target);
            } else {
                console.warn(`[App] Acción no implementada: "${action}"`);
            }
        });

        // Search / filter inputs
        const searchInput  = document.getElementById('searchInput');
        const filterStatus = document.getElementById('filterStatus');
        const filterVendor = document.getElementById('filterVendor');
        const filterType   = document.getElementById('filterType');
        if (searchInput)  searchInput.addEventListener('input',  () => this._applyFilters());
        if (filterStatus) filterStatus.addEventListener('change', () => this._applyFilters());
        if (filterVendor) filterVendor.addEventListener('change', () => this._applyFilters());
        if (filterType)   filterType.addEventListener('change',   () => this._applyFilters());

        // Scan console close button
        document.getElementById('scanConsoleClose')
            ?.addEventListener('click', () => this._hideScanConsole());

        // Escape key closes everything
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.modal.close();
                this.detail.close();
            }
        });

        // Overlay click → close detail panel
        const overlay = document.getElementById('overlay');
        if (overlay) overlay.addEventListener('click', () => this.detail.close());

        // Column sort (th[data-sort])
        document.querySelectorAll('thead th[data-sort]').forEach(th => {
            th.addEventListener('click', () => this.table.toggleSort(th.dataset.sort));
        });
    }

    // ──────────────────────────────────────────────────────────
    //  DASHBOARD REFRESH
    // ──────────────────────────────────────────────────────────
    async refreshDashboard() {
        try {
            const [devResponse, statusResponse] = await Promise.all([
                this.api.getDevices(),
                this.api.getScanStatus().catch(() => null),
            ]);

            this.devices = devResponse.devices ?? (Array.isArray(devResponse) ? devResponse : []);

            this._updateStats(this.devices);
            this._updateScanLabels(statusResponse);
            this._populateFilterDropdowns();
            this.table.render(this.devices);
            this.charts.update(this.devices);
        } catch (err) {
            console.error('[App] refreshDashboard error:', err);
            this.toast.show('Error de conexión con el servidor', 'error');
        }
    }

    _updateScanLabels(status) {
        const lastEl = document.getElementById('lastScanLabel');
        const nextEl = document.getElementById('nextScanLabel');
        if (!status) return;

        if (lastEl) {
            if (status.lastScanTime) {
                const d = new Date(status.lastScanTime);
                lastEl.textContent = `Último scan: ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            } else {
                lastEl.textContent = 'Último scan: —';
            }
        }
        if (nextEl) {
            if (status.isScanning) {
                nextEl.textContent = 'Escaneando…';
            } else if (status.nextScanTime) {
                const d = new Date(status.nextScanTime);
                nextEl.textContent = `Próximo: ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            } else {
                nextEl.textContent = 'Próximo: —';
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    //  STATS BAR  (header counters)
    // ──────────────────────────────────────────────────────────
    _updateStats(devices) {
        const total   = devices.length;
        const online  = devices.filter(d => d.is_online).length;
        const newToday = devices.filter(d => {
            if (!d.first_seen) return false;
            const today = new Date().toDateString();
            return new Date(d.first_seen.replace(' ', 'T')).toDateString() === today;
        }).length;

        const map = {
            totalCount:   total,
            onlineCount:  online,
            offlineCount: total - online,
            newCount: newToday,
        };
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        });

        this._lastStats = { total, online, offline: total - online, newToday };
    }

    // ──────────────────────────────────────────────────────────
    //  FILTER / SEARCH
    // ──────────────────────────────────────────────────────────
    _populateFilterDropdowns() {
        const vendors = [...new Set(this.devices.map(d => d.vendor).filter(Boolean))].sort();
        const types   = [...new Set(this.devices.map(d => d.device_type).filter(Boolean))].sort();

        const buildOptions = (el, values) => {
            if (!el) return;
            const current = el.value;
            el.innerHTML  = `<option value="">— Todos —</option>` +
                values.map(v => `<option value="${v}"${v === current ? ' selected' : ''}>${v}</option>`).join('');
        };

        buildOptions(document.getElementById('filterVendor'), vendors);
        buildOptions(document.getElementById('filterType'),   types);
    }

    _applyFilters() {
        const term   = (document.getElementById('searchInput')?.value  ?? '').toLowerCase();
        const status = document.getElementById('filterStatus')?.value ?? '';
        const vendor = document.getElementById('filterVendor')?.value ?? '';
        const type   = document.getElementById('filterType')?.value   ?? '';

        const filtered = this.devices.filter(d => {
            const matchSearch =
                (d.ip       ?? '').toLowerCase().includes(term) ||
                (d.hostname ?? '').toLowerCase().includes(term) ||
                (d.mac      ?? '').toLowerCase().includes(term) ||
                (d.vendor   ?? '').toLowerCase().includes(term) ||
                (d.inventory_display_name ?? '').toLowerCase().includes(term) ||
                (d.inventory_owner_display ?? '').toLowerCase().includes(term) ||
                (d.inventory_area ?? '').toLowerCase().includes(term);

            const matchStatus =
                status === '' ||
                (status === 'online'  &&  d.is_online) ||
                (status === 'offline' && !d.is_online);

            const matchVendor = vendor === '' || (d.vendor ?? '') === vendor;
            const matchType   = type   === '' || (d.device_type ?? '') === type;

            return matchSearch && matchStatus && matchVendor && matchType;
        });

        this.table.render(filtered);
        this.charts.update(filtered);
    }

    // ──────────────────────────────────────────────────────────
    //  ACTIONS  (called via data-action delegation)
    // ──────────────────────────────────────────────────────────

    /** Open device detail aside panel */
    showDeviceDetail(target) {
        const id = target.dataset.id;
        const device = this.devices.find(d => String(d.id) === String(id) || d.mac === id);
        if (!device) {
            this.toast.show('Dispositivo no encontrado', 'error');
            return;
        }
        this.detail.open(device);
    }

    /** Open device edit form in modal */
    editDevice(target) {
        const id = target.dataset.id;
        const device = this.devices.find(d => String(d.id) === String(id));
        if (!device) return;
        this.modal.openDeviceEdit(device, async (updated) => {
            try {
                await this.api.updateDevice(id, updated);
                this.toast.show('Dispositivo actualizado', 'success');
                await this.refreshDashboard();
            } catch (e) {
                this.toast.show('Error al guardar cambios', 'error');
            }
        });
    }

    /** Trigger manual ARP scan — visual state driven entirely by scan:mode socket events */
    async triggerScan() {
        if (this._scanMode === 'scanning') return;
        try {
            await this.api.triggerScan();
        } catch (e) {
            // HTTP failed before scan started — restore UI ourselves
            this._applyModeUI(this._scanMode === 'scanning' ? 'periodic' : this._scanMode);
            this.toast.show('Error en el escaneo', 'error');
        }
    }

    /** Export device list as Excel — two sheets: Inventario + Conexiones */
    exportXLSX() {
        if (!this.devices.length) {
            this.toast.show('No hay dispositivos para exportar', 'error');
            return;
        }
        if (typeof XLSX === 'undefined') {
            this.toast.show('SheetJS no cargado', 'error');
            return;
        }

        // Sheet 1 — Inventario
        const inventario = this.devices.map(d => ({
            ID:           d.id,
            Hostname:     d.hostname   || '',
            'Inventario EA': d.inventory_display_name || '',
            'Asignado / dueño': d.inventory_owner_display || '',
            'Área inventario': d.inventory_area || '',
            IP:           d.ip         || '',
            MAC:          d.mac        || '',
            Vendor:       d.vendor     || '',
            Tipo:         d.device_type|| '',
            OS:           d.os         || '',
            TTL:          d.ttl        || '',
            Estado:       d.is_online  ? 'Online' : 'Offline',
            'Primer visto': d.first_seen || '',
            'Último visto': d.last_seen  || '',
            Notas:        d.notes      || '',
        }));

        // Sheet 2 — Conexiones físicas
        const conexiones = this.devices
            .filter(d => d.switch_name || d.port_name)
            .map(d => ({
                Hostname:    d.hostname    || '',
                'Inventario EA': d.inventory_display_name || '',
                'Asignado / dueño': d.inventory_owner_display || '',
                'Área inventario': d.inventory_area || '',
                IP:          d.ip          || '',
                MAC:         d.mac         || '',
                Switch:      d.switch_name || '',
                'IP Switch': d.switch_ip   || '',
                Puerto:      d.port_name   || '',
                VLAN:        d.vlan        || '',
                Velocidad:   d.speed       || '',
            }));

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(inventario),  'Inventario');
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(conexiones),  'Conexiones');

        const filename = `netmonitor_${new Date().toISOString().slice(0,10)}.xlsx`;
        XLSX.writeFile(wb, filename);

        this.toast.show(`Excel exportado (${this.devices.length} dispositivos)`, 'success');
    }

    /** Export device list as CSV — generated client-side from current data */
    exportCSV() {
        if (!this.devices.length) {
            this.toast.show('No hay dispositivos para exportar', 'error');
            return;
        }

        const cols = ['id','ip','mac','hostname','inventory_display_name','inventory_owner_display','inventory_area','inventory_match_by','vendor','device_type','os','ttl',
                      'is_online','switch_name','switch_ip','port_name','vlan',
                      'first_seen','last_seen','notes'];

        const esc = (v) => {
            if (v == null) return '';
            const s = String(v);
            return s.includes(',') || s.includes('"') || s.includes('\n')
                ? `"${s.replace(/"/g, '""')}"` : s;
        };

        const rows = [
            cols.join(','),
            ...this.devices.map(d => cols.map(c => esc(d[c])).join(',')),
        ];

        const blob = new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `netmonitor_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);

        this.toast.show(`CSV exportado (${this.devices.length} dispositivos)`, 'success');
    }

    /** Export device list as PDF — opens print dialog in new window */
    exportPDF() {
        PdfExport.exportInventory(this.devices, this._lastStats ?? {});
    }

    /** Reset DB after confirmation */
    async resetDb() {
        if (!confirm('⚠ ¿Borrar el inventario completo? Esta acción no se puede deshacer.')) return;
        try {
            await this.api.resetDatabase();
            this.toast.show('Inventario reseteado', 'success');
            await this.refreshDashboard();
        } catch (e) {
            this.toast.show('Error al resetear la base de datos', 'error');
        }
    }

    /** Open settings modal */
    openSettings() {
        this.modal.openSettings(this.api);
    }

    async logout() {
        try { await this.api.logout(); } catch { /* ignore */ }
        window.location.href = '/';
    }

    /** Open SNMP switch manager */
    openSwitches() {
        this.modal.openSwitches(this.api);
    }

    /** Open topology map */
    openTopology() {
        this.modal.openTopology(this.api, this.devices);
    }

    /** Open IPAM subnet map */
    openIPAM() {
        this.modal.openIPAM(this.api);
    }

    /** Open RMM vault */
    openVault() {
        this.modal.openVault(this.api);
    }

    /** Open Network Tools (speed test + geo-traceroute) */
    openNetworkTools() {
        this.modal.openNetworkTools(this.api);
    }

    /** Open scan history chart */
    openScanHistory() {
        this.modal.openScanHistory(this.api);
    }

    /** Open terminal gateway */
    openTerminal() {
        this.modal.openTerminal();
    }

    /** Toggle between watch and periodic scan modes */
    toggleScanMode() {
        if (!this.socket) return;
        const next = this._scanMode === 'watch' ? 'periodic' : 'watch';
        this.socket.emit('set:mode', { mode: next });
        this.toast.show(`Modo cambiado a: ${next === 'watch' ? '👁 NetWatch' : '⟳ Periódico'}`, 'info');
    }

    /** Close modal (data-action="closeModal") */
    closeModal() {
        this.modal.close();
    }

    /** Close detail panel (data-action="closeDetail") */
    closeDetail() {
        this.detail.close();
    }

    /** Delete a device — called from detail panel */
    async deleteDevice(id, label) {
        if (!confirm(`¿Eliminar "${label}" del inventario?`)) return;
        try {
            await this.api.deleteDevice(id);
            this.toast.show('Dispositivo eliminado', 'success');
            this.detail.close();
            await this.refreshDashboard();
        } catch (e) {
            this.toast.show('Error al eliminar dispositivo', 'error');
        }
    }

    // ──────────────────────────────────────────────────────────
    //  POWER MANAGEMENT
    // ──────────────────────────────────────────────────────────

    /** WoL a un dispositivo — llamado desde detail panel */
    async wolDevice(id, label) {
        this.toast.show(`Enviando WoL a ${label}…`, 'info');
        try {
            const res = await this.api.wolDevice(id);
            this.toast.show(res.message, 'success');
        } catch (e) {
            this.toast.show(`Error WoL: ${e.message}`, 'error');
        }
    }

    /** Shutdown un dispositivo — llamado desde detail panel */
    async shutdownDevice(id, label) {
        if (!confirm(`¿Apagar "${label}"?`)) return;
        this.toast.show(`Apagando ${label}…`, 'info');
        try {
            const res = await this.api.shutdownDevice(id);
            this.toast.show(res.message, 'success');
            setTimeout(() => this.refreshDashboard(), 3000);
        } catch (e) {
            this.toast.show(`Error: ${e.message}`, 'error');
        }
    }

    /** Reboot un dispositivo — llamado desde detail panel */
    async rebootDevice(id, label) {
        if (!confirm(`¿Reiniciar "${label}"?`)) return;
        this.toast.show(`Reiniciando ${label}…`, 'info');
        try {
            const res = await this.api.rebootDevice(id);
            this.toast.show(res.message, 'success');
            setTimeout(() => this.refreshDashboard(), 5000);
        } catch (e) {
            this.toast.show(`Error: ${e.message}`, 'error');
        }
    }

    /** Abrir modal de Power Management masivo */
    openPowerPanel() {
        this.modal.openPowerPanel(this.api, this.devices);
    }

    /** Ping a device — called from detail panel */
    async ping(ip) {
        this.toast.show(`Ping a ${ip}…`, 'info');
        try {
            const res = await this.api.pingDevice(ip);
            const status = res.success ? '✅ Alcanzable' : '❌ Sin respuesta';
            this.toast.show(`${ip}: ${status}`, res.success ? 'success' : 'error');
            return res;
        } catch (e) {
            this.toast.show('Fallo de red', 'error');
        }
    }

    /** Wake-on-LAN — called from detail panel */
    async wakeDevice(mac) {
        this.toast.show(`Enviando WoL a ${mac}…`, 'info');
        try {
            const res = await this.api.wakeDevice(mac);
            this.toast.show(res.message ?? 'WoL enviado', 'success');
        } catch (e) {
            this.toast.show('Error enviando WoL', 'error');
        }
    }
}
