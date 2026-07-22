/**
 * ============================================================
 *  NetMonitor Stealth — detail.js
 *  DetailPanel: manages the aside device detail panel.
 * ============================================================
 */

class DetailPanel {
    constructor(api, toast) {
        this._api     = api;
        this._toast   = toast;
        this._panel   = document.getElementById('detailPanel');
        this._overlay = document.getElementById('overlay');
        this._body    = document.getElementById('detailBody');
        this._device  = null;
    }

    // ──────────────────────────────────────────────────────────
    //  PUBLIC
    // ──────────────────────────────────────────────────────────

    open(device) {
        if (!this._panel || !this._body) return;
        this._device = device;
        this._body.innerHTML = this._render(device);
        this._panel.classList.add('open');
        if (this._overlay) this._overlay.classList.add('open');
    }

    close() {
        if (!this._panel) return;
        this._panel.classList.remove('open');
        if (this._overlay) this._overlay.classList.remove('open');
        this._device = null;
    }

    // ──────────────────────────────────────────────────────────
    //  RENDER
    // ──────────────────────────────────────────────────────────

    _render(d) {
        const onlineBadge = d.is_online
            ? '<span class="badge badge-online"><i class="fas fa-circle"></i> Online</span>'
            : '<span class="badge badge-offline"><i class="fas fa-circle"></i> Offline</span>';

        return `
        <!-- Header -->
        <div class="device-info">
            <div class="detail-icon">
                <i class="fas ${this._deviceIcon(d.device_type, d.os)}"></i>
            </div>
            <div class="device-info-text">
                <h2 class="detail-hostname">${this._esc(d.hostname) || '<span style="opacity:.4">Desconocido</span>'}</h2>
                <p class="detail-mac">${this._esc(d.mac) || '—'}</p>
            </div>
            ${onlineBadge}
            <div class="dp-hdr-actions">
                <button class="dp-hdr-btn" onclick="app.wakeDevice('${this._esc(d.mac)}')" title="Wake-on-LAN">
                    <i class="fas fa-power-off"></i>
                </button>
                <button class="dp-hdr-btn" data-action="editDevice" data-id="${d.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="dp-hdr-btn" onclick="app.detail._sendMessage('${this._esc(d.ip)}')" title="Enviar mensaje al host">
                    <i class="fas fa-comment-dots"></i>
                </button>
                <button class="dp-hdr-btn danger" onclick="app.deleteDevice('${d.id}','${this._esc(d.hostname || d.ip)}')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>

        <hr class="detail-divider">

        <!-- Identity -->
        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-id-card"></i> Identidad</h4>
            ${this._row('IP',           d.ip)}
            ${this._row('MAC',          d.mac)}
            ${this._row('Hostname',     d.hostname)}
            ${this._row('Vendor',       d.vendor)}
            ${this._row('1ª vez visto', this._fmtTime(d.first_seen))}
            ${this._row('Último visto', this._fmtTime(d.last_seen))}
        </div>

        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-clipboard-check"></i> Inventario EA</h4>
            ${this._row('Coincidencia', d.inventory_match ? `Sí (${d.inventory_match_by})` : 'No está en inventario')}
            ${this._row('Equipo',       d.inventory_display_name || d.inventory_name)}
            ${this._row('Tipo',         d.inventory_type)}
            ${this._row('Marca/modelo', d.inventory_brand_model)}
            ${this._row('Serie',        d.inventory_serial)}
            ${this._row('Asignado/dueño', d.inventory_owner_display)}
            ${this._row('Área',         d.inventory_area)}
            ${this._row('Autorización', d.inventory_auth_status)}
        </div>

        <!-- Geolocation -->
        <div class="detail-section">
            <h4 class="detail-section-title dp-toggle" onclick="app.detail._loadGeo(${d.id}, '${this._esc(d.ip)}')">
                <i class="fas fa-map-marker-alt"></i> Ubicación IP
                <i class="fas fa-chevron-down dp-chevron" id="chevron_geo_${d.id}"></i>
            </h4>
            <div id="geo_${d.id}" class="dp-collapse"></div>
        </div>

        <!-- System -->
        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-laptop"></i> Sistema</h4>
            ${this._row('OS',    d.os)}
            ${this._row('Tipo',  d.device_type)}
            ${this._row('TTL',   d.ttl)}
        </div>

        <!-- Physical connection -->
        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-network-wired"></i> Conexión física</h4>
            ${this._row('Switch',    d.switch_name)}
            ${this._row('IP Switch', d.switch_ip)}
            ${this._row('Puerto',    d.port_name)}
            ${this._row('VLAN',      d.vlan)}
            ${this._row('Velocidad', d.speed ? d.speed + ' Mbps' : null)}
        </div>

        <!-- Bandwidth (SNMP) -->
        ${d.switch_name || d.port_name ? `
        <div class="detail-section">
            <h4 class="detail-section-title dp-toggle" onclick="app.detail._loadBandwidth(${d.id})">
                <i class="fas fa-tachometer-alt"></i> Tráfico del puerto
                <span class="dp-chip">SNMP</span>
                <i class="fas fa-chevron-down dp-chevron" id="chevron_bw_${d.id}"></i>
            </h4>
            <div id="bw_${d.id}" class="dp-collapse"></div>
        </div>` : ''}

        <!-- Notes -->
        ${d.notes ? `
        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-sticky-note"></i> Notas</h4>
            <p class="detail-notes">${this._esc(d.notes)}</p>
        </div>` : ''}

        <hr class="detail-divider">

        <!-- Uptime visual + history -->
        <div class="detail-section">
            <h4 class="detail-section-title dp-toggle" onclick="app.detail._loadUptime(${d.id})">
                <i class="fas fa-chart-area"></i> Disponibilidad (24h)
                <i class="fas fa-chevron-down dp-chevron" id="chevron_uptime_${d.id}"></i>
            </h4>
            <div id="uptimeHistory_${d.id}" class="dp-collapse"></div>
        </div>

        <!-- Rich event history -->
        <div class="detail-section">
            <h4 class="detail-section-title dp-toggle" onclick="app.detail._loadEvents(${d.id})">
                <i class="fas fa-stream"></i> Historial de eventos
                <i class="fas fa-chevron-down dp-chevron" id="chevron_events_${d.id}"></i>
            </h4>
            <div id="devEvents_${d.id}" class="dp-collapse"></div>
        </div>

        <hr class="detail-divider">

        <!-- Diagnostics -->
        <div class="detail-section">
            <h4 class="detail-section-title"><i class="fas fa-stethoscope"></i> Diagnóstico</h4>
            <div class="dp-diag-grid">
                <button class="dp-diag-btn" onclick="app.detail._runPingDetail('${this._esc(d.ip)}', ${d.id})" title="4 pings, RTT y pérdida">
                    <i class="fas fa-satellite-dish"></i><span>Ping</span>
                </button>
                <button class="dp-diag-btn" onclick="app.detail._runPortScan('${this._esc(d.ip)}', ${d.id})" title="Escaneo TCP en puertos comunes">
                    <i class="fas fa-door-open"></i><span>Puertos</span>
                </button>
                <button class="dp-diag-btn" onclick="app.detail._runTraceroute('${this._esc(d.ip)}', ${d.id})" title="Ruta hop a hop">
                    <i class="fas fa-route"></i><span>Traceroute</span>
                </button>
                <button class="dp-diag-btn dp-diag-btn--server" onclick="app.detail._runSpeedTest(${d.id})" title="Test de velocidad — medido DESDE el servidor">
                    <i class="fas fa-bolt"></i><span>Speedtest</span>
                </button>
            </div>
            <div id="diag_${d.id}" class="dp-diag-result"></div>
        </div>

        `;
    }

    // ──────────────────────────────────────────────────────────
    //  GEOLOCATION
    // ──────────────────────────────────────────────────────────

    async _loadGeo(deviceId, ip) {
        const container = document.getElementById(`geo_${deviceId}`);
        const chevron   = document.getElementById(`chevron_geo_${deviceId}`);
        if (!container) return;

        if (container.classList.contains('open')) {
            container.classList.remove('open');
            chevron?.classList.remove('rotated');
            return;
        }

        container.classList.add('open');
        chevron?.classList.add('rotated');
        container.innerHTML = this._spinner();

        try {
            const r = await this._api.getDeviceGeo(deviceId);

            if (r.private) {
                container.innerHTML = `
                <div class="dp-geo-badge lan">
                    <span class="dp-geo-flag">🏠</span>
                    <div>
                        <span class="dp-geo-label">Red local (LAN)</span>
                        <span class="dp-geo-ip">${this._esc(ip)}</span>
                    </div>
                </div>`;
                return;
            }

            if (!r.country) {
                container.innerHTML = `<p class="dp-empty">Sin datos de ubicación para ${this._esc(ip)}</p>`;
                return;
            }

            const parts = [r.city, r.region, r.country].filter(Boolean);
            container.innerHTML = `
            <div class="dp-geo-badge">
                <span class="dp-geo-flag">${this._esc(r.flag)}</span>
                <div>
                    <span class="dp-geo-label">${this._esc(parts.join(', '))}</span>
                    <span class="dp-geo-ip">${this._esc(ip)} · ${this._esc(r.timezone || '')}</span>
                </div>
            </div>
            ${r.ll ? `<p class="dp-geo-coords">📍 ${r.ll[0].toFixed(4)}, ${r.ll[1].toFixed(4)}</p>` : ''}`;
        } catch (e) {
            container.innerHTML = `<p class="dp-error">Error: ${this._esc(e.message)}</p>`;
        }
    }

    // ──────────────────────────────────────────────────────────
    //  UPTIME — visual 24h bar + event list
    // ──────────────────────────────────────────────────────────

    async _loadUptime(deviceId) {
        const container = document.getElementById(`uptimeHistory_${deviceId}`);
        const chevron   = document.getElementById(`chevron_uptime_${deviceId}`);
        if (!container) return;

        if (container.classList.contains('open')) {
            container.classList.remove('open');
            chevron?.classList.remove('rotated');
            return;
        }

        container.classList.add('open');
        chevron?.classList.add('rotated');
        container.innerHTML = this._spinner();

        try {
            const res    = await this._api.getDeviceUptime(deviceId, 200);
            const events = res.events ?? [];

            if (!events.length) {
                container.innerHTML = '<p class="dp-empty">Sin eventos registrados aún.</p>';
                return;
            }

            // Build 24h bar: 48 slots of 30 min each
            const SLOTS  = 48;
            const now    = Date.now();
            const slotMs = 30 * 60 * 1000;
            const slots  = new Array(SLOTS).fill('unknown'); // 'up' | 'down' | 'unknown'

            // Sort events oldest→newest
            const sorted = [...events].sort((a, b) => new Date(a.ts) - new Date(b.ts));

            // Walk slots forward, applying state changes
            let currentState = null;
            for (let s = SLOTS - 1; s >= 0; s--) {
                const slotEnd = now - s * slotMs;
                // Find last event before slotEnd
                let stateAtSlot = currentState;
                for (const ev of sorted) {
                    const t = new Date(ev.ts).getTime();
                    if (t <= slotEnd) stateAtSlot = ev.event === 'online' ? 'up' : 'down';
                    else break;
                }
                slots[SLOTS - 1 - s] = stateAtSlot ?? 'unknown';
            }

            const barHtml = slots.map(s =>
                `<div class="up-slot ${s}" title="${s}"></div>`
            ).join('');

            const upCount  = slots.filter(s => s === 'up').length;
            const pct      = Math.round((upCount / SLOTS) * 100);
            const pctColor = pct >= 95 ? '#10b981' : pct >= 80 ? '#f59e0b' : '#ef4444';

            const listHtml = events.slice(0, 20).map(ev => {
                const up = ev.event === 'online';
                return `<div class="dp-event-row">
                    <span class="dp-event-dot ${up ? 'up' : 'down'}"></span>
                    <span class="dp-event-label ${up ? 'up' : 'down'}">${up ? 'Online' : 'Offline'}</span>
                    <span class="dp-event-ts">${this._fmtTime(ev.ts) ?? ev.ts}</span>
                </div>`;
            }).join('');

            container.innerHTML = `
            <div class="up-bar-wrap">
                <div class="up-bar">${barHtml}</div>
                <div class="up-bar-labels">
                    <span>24h atrás</span>
                    <span style="color:${pctColor};font-weight:600">${pct}% uptime</span>
                    <span>ahora</span>
                </div>
            </div>
            <div style="margin-top:10px">${listHtml}</div>`;
        } catch (e) {
            container.innerHTML = `<p class="dp-error">Error: ${this._esc(e.message)}</p>`;
        }
    }

    // ──────────────────────────────────────────────────────────
    //  RICH EVENT HISTORY
    // ──────────────────────────────────────────────────────────

    async _loadEvents(deviceId) {
        const container = document.getElementById(`devEvents_${deviceId}`);
        const chevron   = document.getElementById(`chevron_events_${deviceId}`);
        if (!container) return;

        if (container.classList.contains('open')) {
            container.classList.remove('open');
            chevron?.classList.remove('rotated');
            return;
        }

        container.classList.add('open');
        chevron?.classList.add('rotated');
        container.innerHTML = this._spinner();

        try {
            const res    = await this._api.getDeviceEvents(deviceId, 60);
            const events = res.events ?? [];

            if (!events.length) {
                container.innerHTML = '<p class="dp-empty">Sin eventos registrados aún.</p>';
                return;
            }

            const META = {
                new_device:       { icon: 'fa-star',          color: '#22d3ee', label: 'Nuevo dispositivo' },
                hostname_changed: { icon: 'fa-tag',           color: '#a78bfa', label: 'Hostname cambiado' },
                ip_changed:       { icon: 'fa-exchange-alt',  color: '#f59e0b', label: 'IP cambiada' },
                port_changed:     { icon: 'fa-plug',          color: '#f59e0b', label: 'Puerto cambiado' },
                note_updated:     { icon: 'fa-sticky-note',   color: '#64748b', label: 'Nota actualizada' },
            };

            container.innerHTML = events.map(ev => {
                const m    = META[ev.event_type] ?? { icon: 'fa-circle', color: '#475569', label: ev.event_type };
                let detail = '';
                try {
                    const d = JSON.parse(ev.detail || '{}');
                    if (ev.event_type === 'hostname_changed') detail = `${this._esc(d.from)} → ${this._esc(d.to)}`;
                    else if (ev.event_type === 'ip_changed')  detail = `${this._esc(d.from)} → ${this._esc(d.to)}`;
                    else if (ev.event_type === 'port_changed') detail = `${this._esc(d.from)} → ${this._esc(d.to)} (${this._esc(d.switch)})`;
                    else if (ev.event_type === 'note_updated') detail = this._esc((d.note || '').slice(0, 50));
                    else if (ev.event_type === 'new_device')   detail = `${this._esc(d.ip)} · ${this._esc(d.vendor || '')}`;
                } catch (_) {}

                return `<div class="dp-rich-event">
                    <span class="dp-rich-icon" style="color:${m.color}"><i class="fas ${m.icon}"></i></span>
                    <div class="dp-rich-body">
                        <span class="dp-rich-label">${m.label}</span>
                        ${detail ? `<span class="dp-rich-detail">${detail}</span>` : ''}
                    </div>
                    <span class="dp-event-ts">${this._fmtTime(ev.ts) ?? ev.ts}</span>
                </div>`;
            }).join('');
        } catch (e) {
            container.innerHTML = `<p class="dp-error">Error: ${this._esc(e.message)}</p>`;
        }
    }

    // ──────────────────────────────────────────────────────────
    //  BANDWIDTH (switch port SNMP)
    // ──────────────────────────────────────────────────────────

    async _loadBandwidth(deviceId) {
        const container = document.getElementById(`bw_${deviceId}`);
        const chevron   = document.getElementById(`chevron_bw_${deviceId}`);
        if (!container) return;

        if (container.classList.contains('open')) {
            container.classList.remove('open');
            chevron?.classList.remove('rotated');
            return;
        }

        container.classList.add('open');
        chevron?.classList.add('rotated');
        container.innerHTML = this._spinner();

        try {
            const r = await this._api.getDeviceBandwidth(deviceId);

            if (!r.hasData) {
                container.innerHTML = `
                    <p class="dp-empty"><i class="fas fa-info-circle"></i> ${this._esc(r.reason)}</p>`;
                return;
            }

            const rxPct = Math.min(100, Math.round(r.rxBps / 1e8 * 100)); // scale to 100 Mbps
            const txPct = Math.min(100, Math.round(r.txBps / 1e8 * 100));

            container.innerHTML = `
                <div class="dp-bw-row">
                    <span class="dp-bw-label">↓ RX</span>
                    <div class="dp-bw-bar"><div class="dp-bw-fill rx" style="width:${rxPct}%"></div></div>
                    <span class="dp-bw-value">${this._esc(r.rxPretty)}</span>
                </div>
                <div class="dp-bw-row">
                    <span class="dp-bw-label">↑ TX</span>
                    <div class="dp-bw-bar"><div class="dp-bw-fill tx" style="width:${txPct}%"></div></div>
                    <span class="dp-bw-value">${this._esc(r.txPretty)}</span>
                </div>
                <p class="dp-bw-meta">Puerto: <b>${this._esc(r.port)}</b> · Switch: <b>${this._esc(r.switchName)}</b>
                    · <span style="opacity:.5">${this._esc(r.sampledAt)}</span>
                </p>
                <button class="dp-refresh-btn" onclick="app.detail._reloadBandwidth(${deviceId})">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>`;
        } catch (e) {
            container.innerHTML = `<p class="dp-error">Error: ${this._esc(e.message)}</p>`;
        }
    }

    async _reloadBandwidth(deviceId) {
        const container = document.getElementById(`bw_${deviceId}`);
        if (!container) return;
        container.classList.remove('open');
        await this._loadBandwidth(deviceId);
    }

    // ──────────────────────────────────────────────────────────
    //  DIAGNOSTICS
    // ──────────────────────────────────────────────────────────

    _diagContainer(deviceId) {
        return document.getElementById(`diag_${deviceId}`);
    }

    _diagStart(deviceId, title) {
        const el = this._diagContainer(deviceId);
        if (!el) return;
        el.innerHTML = `<div class="dp-diag-header">${title}</div>${this._spinner()}`;
        el.classList.add('active');
    }

    async _runPingDetail(ip, deviceId) {
        this._diagStart(deviceId, `<i class="fas fa-satellite-dish"></i> Ping a ${ip}`);
        const el = this._diagContainer(deviceId);
        try {
            const r = await this._api.pingDetail(ip);

            if (!r.reachable) {
                el.innerHTML = `
                    <div class="dp-diag-header"><i class="fas fa-satellite-dish"></i> Ping a ${ip}</div>
                    <div class="dp-ping-result offline">
                        <i class="fas fa-times-circle"></i>
                        <span>Sin respuesta — ${r.loss}% pérdida</span>
                    </div>`;
                return;
            }

            const lossColor = r.loss === 0 ? 'var(--accent-green)' : r.loss < 50 ? 'var(--accent-amber)' : 'var(--accent-red)';
            const avgColor  = r.avg < 5 ? 'var(--accent-green)' : r.avg < 30 ? 'var(--accent-cyan)' : r.avg < 100 ? 'var(--accent-amber)' : 'var(--accent-red)';

            el.innerHTML = `
                <div class="dp-diag-header"><i class="fas fa-satellite-dish"></i> Ping a ${ip}</div>
                <div class="dp-ping-result online">
                    <i class="fas fa-check-circle"></i>
                    <span>${r.received}/${r.sent} paquetes recibidos</span>
                    <span style="color:${lossColor};margin-left:auto">${r.loss}% pérdida</span>
                </div>
                <div class="dp-stat-grid">
                    <div class="dp-stat"><span class="dp-stat-label">Mín</span><span class="dp-stat-val">${r.min} ms</span></div>
                    <div class="dp-stat"><span class="dp-stat-label">Prom</span><span class="dp-stat-val" style="color:${avgColor}">${r.avg} ms</span></div>
                    <div class="dp-stat"><span class="dp-stat-label">Máx</span><span class="dp-stat-val">${r.max} ms</span></div>
                </div>`;
        } catch (e) {
            el.innerHTML = `<div class="dp-diag-header">Ping</div><p class="dp-error">${this._esc(e.message)}</p>`;
        }
    }

    async _runPortScan(ip, deviceId) {
        this._diagStart(deviceId, `<i class="fas fa-door-open"></i> Scan de puertos — ${ip}`);
        const el = this._diagContainer(deviceId);
        try {
            const r    = await this._api.portScan(ip);
            const open = (r.ports ?? []).filter(p => p.open);
            const shut = (r.ports ?? []).filter(p => !p.open);

            el.innerHTML = `
                <div class="dp-diag-header"><i class="fas fa-door-open"></i> Puertos — ${ip}</div>
                ${open.length === 0
                    ? '<p class="dp-empty">No se encontraron puertos abiertos</p>'
                    : `<div class="dp-port-grid">
                        ${open.map(p => `
                            <div class="dp-port open">
                                <span class="dp-port-num">${p.port}</span>
                                <span class="dp-port-svc">${this._esc(p.service)}</span>
                            </div>`).join('')}
                    </div>`
                }
                <p class="dp-port-summary">${open.length} abiertos · ${shut.length} cerrados/filtrados</p>`;
        } catch (e) {
            el.innerHTML = `<div class="dp-diag-header">Puertos</div><p class="dp-error">${this._esc(e.message)}</p>`;
        }
    }

    async _runTraceroute(ip, deviceId) {
        this._diagStart(deviceId, `<i class="fas fa-route"></i> Traceroute → ${ip} (puede tardar ~30 s)`);
        const el = this._diagContainer(deviceId);
        try {
            const r = await this._api.traceroute(ip);

            if (!r.hops?.length) {
                el.innerHTML = `<div class="dp-diag-header">Traceroute</div><p class="dp-empty">Sin hops detectados.</p>`;
                return;
            }

            el.innerHTML = `
                <div class="dp-diag-header"><i class="fas fa-route"></i> Traceroute → ${ip}</div>
                <div class="dp-hops">
                    ${r.hops.map(h => `
                        <div class="dp-hop ${h.timeout ? 'timeout' : ''}">
                            <span class="dp-hop-n">${h.hop}</span>
                            <span class="dp-hop-ip">${this._esc(h.ip)}</span>
                            <span class="dp-hop-rtt">${this._esc(h.rtt)}</span>
                        </div>`).join('')}
                </div>`;
        } catch (e) {
            el.innerHTML = `<div class="dp-diag-header">Traceroute</div><p class="dp-error">${this._esc(e.message)}</p>`;
        }
    }

    async _runSpeedTest(deviceId) {
        this._diagStart(deviceId, '<i class="fas fa-bolt"></i> Speed test — midiendo velocidad del <b>servidor</b>…');
        const el = this._diagContainer(deviceId);
        try {
            const r = await this._api.speedTest();

            if (!r.success) throw new Error(r.error ?? 'Error desconocido');

            const mbps    = parseFloat(r.mbps);
            const barPct  = Math.min(100, Math.round(mbps / 10 * 100)); // scale to 1 Gbps reference
            const color   = mbps > 100 ? 'var(--accent-green)' : mbps > 20 ? 'var(--accent-cyan)' : mbps > 5 ? 'var(--accent-amber)' : 'var(--accent-red)';

            el.innerHTML = `
                <div class="dp-diag-header"><i class="fas fa-bolt"></i> Speed test del servidor</div>
                <div class="dp-speed-result">
                    <span class="dp-speed-val" style="color:${color}">${mbps}</span>
                    <span class="dp-speed-unit">Mbps</span>
                </div>
                <div class="dp-bw-bar" style="margin:8px 0">
                    <div class="dp-bw-fill rx" style="width:${barPct}%;background:${color}"></div>
                </div>
                <p class="dp-bw-meta">
                    ${r.bytes ? Math.round(r.bytes / 1024 / 1024) + ' MB en ' : ''}${r.elapsed} s
                    · medido desde el servidor hacia internet
                </p>
                <button class="dp-refresh-btn" onclick="app.detail._runSpeedTest(${deviceId})">
                    <i class="fas fa-redo"></i> Repetir
                </button>`;
        } catch (e) {
            el.innerHTML = `<div class="dp-diag-header">Speed test</div><p class="dp-error">${this._esc(e.message)}</p>`;
        }
    }

    // ── Enviar mensaje popup a host Windows vía WMI ───────────
    _sendMessage(ip) {
        const html = `
            <div style="display:flex;flex-direction:column;gap:10px;min-width:280px">
                <div style="font-size:.8rem;color:var(--text-muted)">Credenciales del host <b>${ip}</b></div>
                <input id="msg-user" placeholder="usuario (ej: Administrador)" style="padding:6px 8px;background:var(--bg-card);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:.85rem">
                <input id="msg-pass" type="password" placeholder="contraseña" style="padding:6px 8px;background:var(--bg-card);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:.85rem">
                <textarea id="msg-text" rows="3" placeholder="Mensaje a mostrar en pantalla..." style="padding:6px 8px;background:var(--bg-card);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:.85rem;resize:vertical"></textarea>
                <button id="msg-send-btn" style="padding:7px;background:var(--accent-cyan);color:#000;border:none;border-radius:4px;font-weight:bold;cursor:pointer">
                    <i class="fas fa-paper-plane"></i> Enviar
                </button>
                <div id="msg-result" style="font-size:.78rem;min-height:18px"></div>
            </div>`;

        if (window.app?.modal) {
            window.app.modal.open(`💬 Mensaje → ${ip}`, html);
        } else {
            // fallback: inline en el panel de diagnóstico si no hay modal
            const el = document.querySelector('.dp-diag-output');
            if (el) el.innerHTML = html;
        }

        setTimeout(() => {
            const btn = document.getElementById('msg-send-btn');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                const user = document.getElementById('msg-user').value.trim();
                const pass = document.getElementById('msg-pass').value;
                const text = document.getElementById('msg-text').value.trim();
                const res  = document.getElementById('msg-result');
                if (!user || !text) { res.style.color='var(--accent-red)'; res.textContent='Usuario y mensaje requeridos.'; return; }
                btn.disabled = true;
                res.style.color = 'var(--text-muted)';
                res.textContent = 'Enviando…';
                try {
                    const r = await fetch('/api/actions/message', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ip, username: user, password: pass, text })
                    }).then(x => x.json());
                    if (r.success) { res.style.color='var(--accent-green)'; res.textContent='✓ Mensaje enviado.'; }
                    else           { res.style.color='var(--accent-red)';   res.textContent='Error: ' + (r.error || 'desconocido'); }
                } catch(e) {
                    res.style.color='var(--accent-red)'; res.textContent='Error de red: ' + e.message;
                }
                btn.disabled = false;
            });
        }, 100);
    }

    // ──────────────────────────────────────────────────────────
    //  HELPERS
    // ──────────────────────────────────────────────────────────

    _spinner() {
        return '<div class="dp-spinner"><i class="fas fa-spinner fa-spin"></i></div>';
    }

    _row(label, value) {
        const display = value != null && value !== ''
            ? `<span class="detail-value">${this._esc(String(value))}</span>`
            : `<span class="detail-value dp-muted">—</span>`;
        return `<div class="detail-row"><span class="detail-key">${label}</span>${display}</div>`;
    }

    _fmtTime(ts) {
        if (!ts) return null;
        try {
            const d = new Date(ts.replace(' ', 'T'));
            if (isNaN(d.getTime())) return ts;
            return d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
        } catch { return ts; }
    }

    _deviceIcon(type, os) {
        const s = ((type ?? '') + ' ' + (os ?? '')).toLowerCase();
        if (s.includes('teléfono') || s.includes('phone') || s.includes('iphone') || s.includes('android')) return 'fa-mobile-alt';
        if (s.includes('router')  || s.includes('ap')    || s.includes('mikrotik')) return 'fa-network-wired';
        if (s.includes('switch'))    return 'fa-server';
        if (s.includes('impresora') || s.includes('printer')) return 'fa-print';
        if (s.includes('laptop')   || s.includes('vm'))  return 'fa-laptop';
        if (s.includes('pc')       || s.includes('windows')) return 'fa-desktop';
        return 'fa-microchip';
    }

    _esc(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }
}
