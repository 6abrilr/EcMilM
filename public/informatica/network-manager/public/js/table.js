/**
 * ============================================================
 *  NetMonitor Stealth — table.js
 *  tableManager (singleton): renders the device list table.
 *
 *  Key FIX:
 *    Every <tr> now has:
 *      data-action="showDeviceDetail"  data-id="<device.id>"
 *    This wires row clicks to App's event delegation system,
 *    which calls app.showDeviceDetail(target) → detail.open(device).
 *    Previously the attribute existed but the binding was broken
 *    because `renderDevicesTable` was called before App set up
 *    its delegation and the aside panel was never opened.
 *
 *  Responsibilities:
 *    - render(devices)    → build table rows
 *    - toggleSort(column) → sort and re-render
 *    - Exported as window.renderDevicesTable for App compatibility
 * ============================================================
 */

const tableManager = {
    currentSort: { column: 'is_online', direction: 'desc' },
    devices: [],

    // ──────────────────────────────────────────────────────────
    //  PUBLIC: render
    // ──────────────────────────────────────────────────────────
    render(devices) {
        this.devices = devices ?? [];
        const tbody = document.getElementById('devicesBody');
        if (!tbody) return;

        if (this.devices.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11"
                        style="text-align:center; padding:50px; opacity:0.5;">
                        No hay dispositivos — realiza un escaneo
                    </td>
                </tr>`;
            return;
        }

        const sorted = this._sort(this.devices);
        tbody.innerHTML = sorted.map(d => this._row(d)).join('');
    },

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: build a single table row
    //
    //  FIX: data-action="showDeviceDetail" on <tr> so that
    //  clicking anywhere on the row opens the detail panel.
    //  The edit button uses stopPropagation via the delegation
    //  in App._bindEvents (e.stopPropagation).
    // ──────────────────────────────────────────────────────────
    _row(d) {
        const tagMap = Object.fromEntries((d.tags || []).map(tag => [tag.key, tag.value]));
        const online   = d.is_online;
        const critical = !!d.is_critical;
        const rowClass = online ? '' : 'device-offline';
        const icon     = this._icon(d.device_type, d.os);
        const lastSeen = this._time(d.last_seen);
        const displayName = d.hostname || d.inventory_display_name || d.inventory_name || d.vendor || d.device_type || 'Desconocido';
        const subLine = [
            d.ip || '',
            !d.hostname && d.inventory_match ? `Inventario: ${d.inventory_match_by}` : '',
        ].filter(Boolean).join(' · ');

        return `
        <tr class="${rowClass}${critical ? ' device-critical' : ''}"
            data-action="showDeviceDetail"
            data-id="${d.id}"
            data-mac="${d.mac}"
            style="cursor:pointer;"
            title="Ver detalle">

            <td style="text-align:center">
                <span class="status-dot ${online ? 'online' : 'offline'}"></span>
                ${critical ? '<span title="Equipo crítico" style="font-size:.65rem; color:var(--accent-red); display:block; line-height:1">🚨</span>' : ''}
            </td>

            <td>
                <div style="font-weight:600; color:${critical ? 'var(--accent-red)' : 'var(--text-primary)'}; display:flex; align-items:center; gap:6px;">
                    ${this._esc(displayName)}
                </div>
                <div style="font-size:0.7rem; color:var(--text-secondary);
                            font-family:var(--font-mono)">
                    ${this._esc(subLine || 'N/A')}
                </div>
            </td>

            <td style="font-family:var(--font-mono); font-size:.74rem; white-space:nowrap;">
                ${this._esc(d.mac || '—')}
            </td>

            <td>
                ${(d.inventory_match || tagMap.ea_equipo) ? `
                    <div style="font-weight:700; color:var(--accent-green);">
                        ${this._esc(d.inventory_display_name || d.inventory_name || tagMap.ea_equipo || 'Inventario EA')}
                    </div>
                    <div style="font-size:.68rem; color:var(--text-secondary);">
                        ${this._esc([d.inventory_type, d.inventory_brand_model].filter(Boolean).join(' - ') || d.inventory_match_by)}
                    </div>
                ` : `
                    <button class="btn btn-ghost btn-sm"
                            data-action="editDevice"
                            data-id="${d.id}"
                            onclick="event.stopPropagation()"
                            title="Asignar un nombre local para reconocerlo">
                        Nombrar
                    </button>
                `}
            </td>

            <td class="cell-ip">${this._esc(d.ip || '0.0.0.0')}</td>

            <td>
                <span class="badge"
                      style="background:rgba(255,255,255,0.05);
                             border:1px solid var(--glass-border);">
                    <i class="fas ${icon}" style="margin-right:5px; opacity:0.7"></i>
                    ${this._esc(d.device_type || 'Genérico')}
                </span>
            </td>

            <td><span class="cell-os">${this._esc(d.os || '—')}</span></td>

            <td>
                <span class="cell-switch">
                    ${this._esc(d.switch_name || '—')}${d.port_name ? ':' + this._esc(d.port_name) : ''}
                </span>
            </td>

            <td>
                <div style="font-size:.75rem; color:var(--text-primary);">
                    ${this._esc(d.inventory_owner_display || d.owner || tagMap.ea_responsable || '—')}
                </div>
                <div style="font-size:.68rem; color:var(--text-secondary);">
                    ${this._esc(d.inventory_area || d.location || tagMap.ea_area || '')}
                </div>
            </td>

            <td class="cell-time">${lastSeen}</td>

            <td class="th-actions">
                <button class="btn-icon"
                        data-action="editDevice"
                        data-id="${d.id}"
                        title="Editar dispositivo"
                        onclick="event.stopPropagation()">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        </tr>`;
    },

    // ──────────────────────────────────────────────────────────
    //  PUBLIC: toggle column sort
    // ──────────────────────────────────────────────────────────
    toggleSort(column) {
        if (this.currentSort.column === column) {
            this.currentSort.direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.currentSort.column    = column;
            this.currentSort.direction = 'asc';
        }
        this.render(this.devices);
    },

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: sort helper
    // ──────────────────────────────────────────────────────────
    _sort(devices) {
        const { column, direction } = this.currentSort;
        const mult = direction === 'asc' ? 1 : -1;

        return [...devices].sort((a, b) => {
            let va = a[column] ?? '';
            let vb = b[column] ?? '';

            // Numeric IP sort
            if (column === 'ip' && typeof va === 'string') {
                va = va.split('.').map(n => n.padStart(3, '0')).join('');
                vb = vb.split('.').map(n => n.padStart(3, '0')).join('');
            }

            if (typeof va === 'string') va = va.toLowerCase();
            if (typeof vb === 'string') vb = vb.toLowerCase();

            if (va < vb) return -1 * mult;
            if (va > vb) return  1 * mult;
            return 0;
        });
    },

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: utility helpers
    // ──────────────────────────────────────────────────────────
    _icon(type, os) {
        const s = ((type ?? '') + ' ' + (os ?? '')).toLowerCase();
        if (s.includes('teléfono') || s.includes('iphone') || s.includes('android')) return 'fa-mobile-alt';
        if (s.includes('router')  || s.includes('ap')     || s.includes('mikrotik')) return 'fa-network-wired';
        if (s.includes('switch'))     return 'fa-server';
        if (s.includes('impresora') || s.includes('printer')) return 'fa-print';
        if (s.includes('laptop')   || s.includes('vm'))       return 'fa-laptop';
        if (s.includes('pc')       || s.includes('windows'))  return 'fa-desktop';
        return 'fa-microchip';
    },

    _time(ts) {
        if (!ts || typeof ts !== 'string') return '—';
        try {
            const date = new Date(ts.replace(' ', 'T'));
            if (isNaN(date.getTime())) return '—';
            const diff = Date.now() - date.getTime();
            if (diff < 60_000)   return 'Ahora';
            if (diff < 3_600_000) return `Hace ${Math.floor(diff / 60_000)}m`;
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch { return '—'; }
    },

    _esc(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    },
};

