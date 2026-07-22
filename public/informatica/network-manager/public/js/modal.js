/**
 * ============================================================
 *  NetMonitor Stealth — modal.js
 *  ModalManager: controls the global modal overlay.
 *
 *  Replaces the ad-hoc openSettings / openTopology / etc.
 *  methods that were mixed into App.
 *
 *  Each open* method:
 *    1. Builds a self-contained HTML string.
 *    2. Calls this.open(title, html) to display it.
 *    3. Binds any required event listeners AFTER inserting HTML.
 *
 *  FIX for Icon.js error (topology): vis.js nodes.shape='icon'
 *  requires that each group definition includes `icon.code`.
 *  Previously only the nodes default had no code defined.
 *  Now every group explicitly defines icon.code.
 * ============================================================
 */

class ModalManager {
    /**
     * @param {ToastManager} toast
     */
    constructor(toast) {
        this._toast      = toast;
        this._modal      = document.getElementById('globalModal');
        this._titleEl    = document.getElementById('modalTitle');
        this._bodyEl     = document.getElementById('modalBody');

        if (!this._modal) {
            console.error('[ModalManager] #globalModal element not found in DOM.');
            return;
        }

        // Click on backdrop (outside modal-content) → close
        this._modal.addEventListener('click', (e) => {
            if (e.target === this._modal) this.close();
        });
    }

    // ──────────────────────────────────────────────────────────
    //  CORE OPEN / CLOSE
    // ──────────────────────────────────────────────────────────

    open(title, html, sizeClass = '') {
        if (!this._modal) return;
        this._titleEl.textContent = title;
        this._bodyEl.innerHTML    = html;
        const content = document.getElementById('modalContent');
        if (content && sizeClass) content.classList.add(sizeClass);
        this._modal.classList.add('open');
    }

    close() {
        if (!this._modal) return;
        this._modal.classList.remove('open');
        document.getElementById('modalContent')?.classList.remove('modal-wide', 'modal-fullish');
        this._bodyEl.innerHTML = '';
        this._ctxMenu?.hide();
    }

    // ──────────────────────────────────────────────────────────
    //  DEVICE EDIT MODAL
    // ──────────────────────────────────────────────────────────

    /**
     * @param {Object}   device   - current device data
     * @param {Function} onSave   - async callback(updatedData)
     */
    openDeviceEdit(device, onSave) {
        const esc = (v) => (v ?? '').toString()
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');

        const html = `
        <form id="deviceEditForm" style="display:grid; gap:12px;">
            <div class="form-grid">
                <label>Hostname / Inventario
                    <input type="text" id="edit_hostname"
                           value="${esc(device.hostname)}"
                           placeholder="Nombre del equipo">
                </label>
                <label>Tipo de dispositivo
                    <select id="edit_device_type">
                        ${['PC Windows','Linux/Android','VM','Router/AP','Switch/Router',
                           'Teléfono','Impresora','Firewall','Servidor','Genérico']
                          .map(t => `<option value="${t}" ${device.device_type===t?'selected':''}>${t}</option>`)
                          .join('')}
                    </select>
                </label>
                <label>Switch
                    <input type="text" id="edit_switch_name"
                           value="${esc(device.switch_name)}"
                           placeholder="Nombre del switch">
                </label>
                <label>Puerto
                    <input type="text" id="edit_port_name"
                           value="${esc(device.port_name)}"
                           placeholder="Ej: Gi0/1">
                </label>
                <label>VLAN
                    <input type="number" id="edit_vlan"
                           value="${esc(device.vlan)}"
                           placeholder="1">
                </label>
                <label>IP Switch
                    <input type="text" id="edit_switch_ip"
                           value="${esc(device.switch_ip)}"
                           placeholder="192.168.0.x">
                </label>
            </div>

            <label>Notas
                <textarea id="edit_notes" rows="3"
                          placeholder="Notas internas sobre este equipo…"
                          style="width:100%; resize:vertical;">${esc(device.notes)}</textarea>
            </label>

            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;
                          padding:8px 12px; border-radius:6px;
                          border:1px solid ${device.is_critical ? 'var(--accent-red)' : 'var(--glass-border)'};
                          background:${device.is_critical ? 'rgba(239,68,68,.08)' : 'transparent'};
                          transition:border-color .2s, background .2s;"
                  id="lbl_critical">
                <input type="checkbox" id="edit_is_critical" ${device.is_critical ? 'checked' : ''}
                       style="width:16px; height:16px; accent-color:var(--accent-red);">
                <span style="font-size:.85rem; font-weight:600; color:var(--accent-red);">🚨 Equipo crítico</span>
                <span style="font-size:.75rem; opacity:.5; margin-left:auto;">Las alertas de este equipo ignoran la ventana horaria de Telegram</span>
            </label>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-secondary" data-action="closeModal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btnSaveDevice">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </form>`;

        this.open(`Editar — ${device.hostname || device.ip}`, html);

        // Critical toggle visual feedback
        document.getElementById('edit_is_critical')?.addEventListener('change', (e) => {
            const lbl = document.getElementById('lbl_critical');
            if (lbl) {
                lbl.style.borderColor = e.target.checked ? 'var(--accent-red)' : 'var(--glass-border)';
                lbl.style.background  = e.target.checked ? 'rgba(239,68,68,.08)' : 'transparent';
            }
        });

        // Bind save after inserting HTML
        document.getElementById('btnSaveDevice')?.addEventListener('click', async () => {
            const updated = {
                hostname:    document.getElementById('edit_hostname')?.value.trim(),
                device_type: document.getElementById('edit_device_type')?.value,
                switch_name: document.getElementById('edit_switch_name')?.value.trim(),
                port_name:   document.getElementById('edit_port_name')?.value.trim(),
                switch_ip:   document.getElementById('edit_switch_ip')?.value.trim(),
                vlan:        parseInt(document.getElementById('edit_vlan')?.value) || null,
                notes:       document.getElementById('edit_notes')?.value.trim(),
                is_critical: document.getElementById('edit_is_critical')?.checked ?? false,
            };

            const btn = document.getElementById('btnSaveDevice');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';

            try {
                await onSave(updated);
                this.close();
            } catch (e) {
                this._toast.show('Error al guardar', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
            }
        });
    }

    // ──────────────────────────────────────────────────────────
    //  SETTINGS MODAL
    // ──────────────────────────────────────────────────────────
    async openSettings(api) {
        this.open('Configuración', '<p style="padding:8px 0">Cargando…</p>', 'modal-wide');

        let s = {};
        try {
            const res = await api.getSettings();
            s = res.settings ?? {};
        } catch (e) {
            this._bodyEl.innerHTML = '<p style="color:var(--accent-red)">Error al cargar configuración.</p>';
            return;
        }

        const checked = (key, def = '1') =>
            (s[key] ?? def) === '1' ? 'checked' : '';

        this._bodyEl.innerHTML = `
        <div style="display:grid; gap:20px;">

            <!-- Telegram -->
            <fieldset style="border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:16px;">
                <legend style="padding:0 8px; font-size:0.75rem; font-weight:600;
                               text-transform:uppercase; letter-spacing:.6px; color:var(--accent-cyan);">
                    Telegram
                </legend>
                <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                    <label>Bot Token
                        <input type="password" id="cfg_telegram_token"
                               value="${s.telegram_token ?? ''}"
                               placeholder="123456:ABC-DEF…"
                               autocomplete="new-password">
                    </label>
                    <label>Chat ID
                        <input type="text" id="cfg_telegram_chat_id"
                               value="${s.telegram_chat_id ?? ''}"
                               placeholder="-100123456789">
                    </label>
                </div>
                <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_telegram_enabled" ${checked('telegram_enabled','0')}>
                        Activar notificaciones
                    </label>
                    <button class="btn btn-ghost btn-sm" id="btnTestTelegram">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </fieldset>

            <!-- Eventos Telegram -->
            <fieldset style="border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:16px;">
                <legend style="padding:0 8px; font-size:0.75rem; font-weight:600;
                               text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted);">
                    Eventos a notificar
                </legend>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:4px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_evt_new"     ${checked('telegram_event_new_device')}>
                        🆕 Nuevo dispositivo
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_evt_offline" ${checked('telegram_event_offline')}>
                        🔴 Dispositivo offline
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_evt_roaming" ${checked('telegram_event_roaming')}>
                        ⚠️ MAC Roaming
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_evt_daily"   ${checked('telegram_event_daily')}>
                        📋 Resumen diario
                    </label>
                </div>
            </fieldset>

            <!-- Rutina de notificaciones — grupos -->
            <fieldset style="border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:16px;">
                <legend style="padding:0 8px; font-size:0.75rem; font-weight:600;
                               text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted);">
                    Rutina de notificaciones
                </legend>
                <p style="font-size:11px; opacity:.5; margin:0 0 12px;">
                    Cada grupo define qué días y en qué horario notificar. Fuera de cualquier grupo solo se envían alertas de equipos <b>críticos</b>.
                </p>
                <div id="scheduleGroups" style="display:flex; flex-direction:column; gap:10px;"></div>
                <button type="button" id="btnAddGroup" class="btn btn-ghost btn-sm" style="margin-top:10px; font-size:.75rem;">
                    <i class="fas fa-plus"></i> Agregar grupo
                </button>
            </fieldset>

            <fieldset class="settings-fieldset">
                <legend><i class="fas fa-bell"></i> Alertas offline</legend>
                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:.82rem; cursor:pointer;">
                        <input type="checkbox" id="cfg_alert_enabled" ${checked('alert_offline_enabled','0')}>
                        Toast en dashboard
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:.82rem;">
                        Umbral:
                        <input type="number" id="cfg_alert_threshold" min="1" max="1440"
                               value="${s.alert_offline_threshold_min ?? '30'}"
                               style="width:64px; background:var(--bg-secondary); border:1px solid var(--glass-border);
                                      color:var(--text-primary); border-radius:4px; padding:3px 6px; font-size:.82rem;">
                        min offline
                    </label>
                </div>
                <p style="font-size:11px; opacity:.45; margin-top:8px;">
                    Solo emite un aviso visual en el dashboard. Telegram solo notifica si el equipo está marcado como 🚨 crítico.
                </p>
            </fieldset>

            <!-- Seguridad -->
            <fieldset style="border:1px solid var(--glass-border); border-radius:var(--radius-md); padding:16px;">
                <legend style="padding:0 8px; font-size:0.75rem; font-weight:600;
                               text-transform:uppercase; letter-spacing:.6px; color:var(--accent-red);">
                    <i class="fas fa-lock"></i> Seguridad
                </legend>
                <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr; margin-bottom:10px;">
                    <label>Usuario
                        <input type="text" id="cfg_auth_user" autocomplete="username"
                               placeholder="admin">
                    </label>
                    <label>Contraseña actual
                        <input type="password" id="cfg_auth_current" autocomplete="current-password"
                               placeholder="••••••••">
                    </label>
                    <label>Nueva contraseña
                        <input type="password" id="cfg_auth_new" autocomplete="new-password"
                               placeholder="••••••••">
                    </label>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="btnChangePassword">
                    <i class="fas fa-key"></i> Cambiar credenciales
                </button>
                <p style="font-size:11px; opacity:.45; margin-top:8px;">
                    Dejá los campos vacíos si no querés cambiar la contraseña.
                    Credenciales por defecto: <code>admin</code> / <code>netmonitor</code>
                </p>
            </fieldset>

            <!-- Subnets de escaneo -->
            <fieldset class="settings-fieldset">
                <legend><i class="fas fa-network-wired"></i> Subnets de escaneo</legend>
                <p style="font-size:11px; opacity:.5; margin-bottom:10px;">
                    Solo se escanean estas subnets. Formato CIDR: <code>10.25.96.0/24</code>. Vacío = auto-detectar desde interfaces.
                </p>
                <div id="subnetList" style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;"></div>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="subnetInput" placeholder="ej: 10.25.96.0/24"
                           style="flex:1; background:var(--bg-secondary); border:1px solid var(--glass-border);
                                  color:var(--text-primary); border-radius:4px; padding:5px 10px; font-size:.82rem;">
                    <button type="button" class="btn btn-ghost btn-sm" id="btnAddSubnet">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
            </fieldset>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-secondary" data-action="closeModal">Cancelar</button>
                <button class="btn btn-primary" id="btnSaveSettings">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </div>`;

        // ── Subnets de escaneo ───────────────────────────────────
        let subnets = [];
        try { subnets = JSON.parse(s.scan_subnets || '[]'); } catch { subnets = []; }

        const renderSubnets = () => {
            const list = document.getElementById('subnetList');
            if (!list) return;
            list.innerHTML = subnets.length
                ? subnets.map((sn, i) => `
                    <div style="display:flex; align-items:center; gap:8px; background:var(--bg-secondary);
                                border:1px solid var(--glass-border); border-radius:4px; padding:5px 10px;">
                        <code style="flex:1; font-size:.82rem;">${this._esc(sn)}</code>
                        <button type="button" class="btn-icon btn-sm" data-subnet-del="${i}" title="Eliminar"
                                style="color:var(--accent-red); background:none; border:none; cursor:pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>`).join('')
                : '<p style="font-size:.78rem; opacity:.4; margin:0">Sin subnets configuradas — se auto-detectan desde interfaces.</p>';

            list.querySelectorAll('[data-subnet-del]').forEach(btn => {
                btn.addEventListener('click', () => {
                    subnets.splice(Number(btn.dataset.subnetDel), 1);
                    renderSubnets();
                });
            });
        };
        renderSubnets();

        document.getElementById('btnAddSubnet')?.addEventListener('click', () => {
            const input = document.getElementById('subnetInput');
            const val = input.value.trim();
            if (!val) return;
            if (!/^\d{1,3}(\.\d{1,3}){3}\/\d{1,2}$/.test(val)) {
                this._toast.show('Formato inválido. Usá CIDR: 10.25.96.0/24', 'warning');
                return;
            }
            if (!subnets.includes(val)) subnets.push(val);
            input.value = '';
            renderSubnets();
        });

        document.getElementById('subnetInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('btnAddSubnet')?.click();
        });

        // ── Schedule groups ──────────────────────────────────────
        const DAY_LABELS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

        const renderGroup = (container, group, idx) => {
            const div = document.createElement('div');
            div.className   = 'sched-group';
            div.dataset.idx = idx;
            div.style.cssText = 'background:var(--bg-secondary);border:1px solid var(--glass-border);border-radius:6px;padding:10px 12px;display:flex;flex-direction:column;gap:8px;';

            // Day pills
            const pillRow = document.createElement('div');
            pillRow.style.cssText = 'display:flex;gap:5px;flex-wrap:wrap;align-items:center;';

            DAY_LABELS.forEach((label, i) => {
                const d      = String(i + 1);
                const active = group.days.includes(d);
                const pill   = document.createElement('button');
                pill.type      = 'button';
                pill.textContent = label;
                pill.dataset.day = d;
                pill.style.cssText = `width:34px;height:34px;border-radius:50%;border:1px solid ${active ? 'var(--accent-cyan)' : 'var(--glass-border)'};
                    background:${active ? 'rgba(34,211,238,.15)' : 'transparent'};
                    color:${active ? 'var(--accent-cyan)' : 'var(--text-muted)'};
                    font-size:.72rem;font-weight:600;cursor:pointer;transition:all .15s;`;
                pill.addEventListener('click', () => {
                    const on = group.days.includes(d);
                    group.days = on
                        ? group.days.replace(d, '')
                        : (group.days + d).split('').sort().join('');
                    pill.style.borderColor = !on ? 'var(--accent-cyan)' : 'var(--glass-border)';
                    pill.style.background  = !on ? 'rgba(34,211,238,.15)' : 'transparent';
                    pill.style.color       = !on ? 'var(--accent-cyan)'   : 'var(--text-muted)';
                });
                pillRow.appendChild(pill);
            });

            // Separator + time inputs
            const timeRow = document.createElement('div');
            timeRow.style.cssText = 'display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:.8rem;color:var(--text-secondary);';
            timeRow.innerHTML = `
                <label style="display:flex;align-items:center;gap:5px;">
                    Desde <input type="time" value="${group.from ?? ''}"
                        style="background:var(--bg-primary);border:1px solid var(--glass-border);color:var(--text-primary);border-radius:4px;padding:2px 5px;font-size:.8rem;">
                </label>
                <label style="display:flex;align-items:center;gap:5px;">
                    Hasta <input type="time" value="${group.to ?? ''}"
                        style="background:var(--bg-primary);border:1px solid var(--glass-border);color:var(--text-primary);border-radius:4px;padding:2px 5px;font-size:.8rem;">
                </label>
                <span style="font-size:.7rem;opacity:.4;">vacío = silencio ese día</span>
                <button type="button" style="margin-left:auto;background:none;border:none;color:var(--accent-red);cursor:pointer;font-size:.8rem;" data-remove="${idx}">
                    <i class="fas fa-trash"></i>
                </button>`;

            timeRow.querySelectorAll('input[type=time]').forEach((inp, ti) => {
                inp.addEventListener('change', () => {
                    if (ti === 0) group.from = inp.value;
                    else          group.to   = inp.value;
                });
            });
            timeRow.querySelector('[data-remove]')?.addEventListener('click', () => {
                const i = schedGroups.indexOf(group);
                if (i !== -1) schedGroups.splice(i, 1);
                div.remove();
            });

            div.appendChild(pillRow);
            div.appendChild(timeRow);
            container.appendChild(div);
        };

        // Load existing groups
        let schedGroups = [];
        try {
            const raw = s.telegram_schedule_groups;
            schedGroups = raw ? JSON.parse(raw) : [];
        } catch { schedGroups = []; }

        // Migrate legacy single-group
        if (!schedGroups.length && s.telegram_schedule_days) {
            schedGroups = [{ days: s.telegram_schedule_days, from: s.telegram_schedule_from ?? '', to: s.telegram_schedule_to ?? '' }];
        }
        if (!schedGroups.length) {
            schedGroups = [{ days: '12345', from: '08:00', to: '20:00' }];
        }

        const groupsContainer = document.getElementById('scheduleGroups');
        schedGroups.forEach((g, i) => renderGroup(groupsContainer, g, i));

        document.getElementById('btnAddGroup')?.addEventListener('click', () => {
            const newGroup = { days: '', from: '08:00', to: '20:00' };
            schedGroups.push(newGroup);
            renderGroup(groupsContainer, newGroup, schedGroups.length - 1);
        });

        // Test
        document.getElementById('btnTestTelegram')?.addEventListener('click', async () => {
            try {
                await api.saveSettings({
                    telegram_token:   document.getElementById('cfg_telegram_token').value.trim(),
                    telegram_chat_id: document.getElementById('cfg_telegram_chat_id').value.trim(),
                    telegram_enabled: '1',
                });
                await fetch('/api/settings/test', { method: 'POST' });
                this._toast.show('Mensaje de prueba enviado', 'success');
            } catch {
                this._toast.show('Error al enviar test', 'error');
            }
        });

        // Cambiar contraseña
        document.getElementById('btnChangePassword')?.addEventListener('click', async () => {
            const user    = document.getElementById('cfg_auth_user')?.value.trim();
            const current = document.getElementById('cfg_auth_current')?.value;
            const newPass = document.getElementById('cfg_auth_new')?.value;
            if (!current || !newPass) {
                this._toast.show('Completá contraseña actual y nueva', 'warning');
                return;
            }
            const btn = document.getElementById('btnChangePassword');
            btn.disabled = true;
            try {
                await api.changePassword({ username: user, currentPassword: current, newPassword: newPass });
                this._toast.show('Credenciales actualizadas', 'success');
                document.getElementById('cfg_auth_current').value = '';
                document.getElementById('cfg_auth_new').value     = '';
            } catch (e) {
                this._toast.show(e.message || 'Error al cambiar contraseña', 'error');
            } finally {
                btn.disabled = false;
            }
        });

        // Guardar
        document.getElementById('btnSaveSettings')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnSaveSettings');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';

            const bool = id => document.getElementById(id)?.checked ? '1' : '0';

            try {
                await api.saveSettings({
                    scan_subnets:                JSON.stringify(subnets),
                    telegram_token:              document.getElementById('cfg_telegram_token').value.trim(),
                    telegram_chat_id:            document.getElementById('cfg_telegram_chat_id').value.trim(),
                    telegram_enabled:            bool('cfg_telegram_enabled'),
                    telegram_event_new_device:   bool('cfg_evt_new'),
                    telegram_event_offline:      bool('cfg_evt_offline'),
                    telegram_event_roaming:      bool('cfg_evt_roaming'),
                    telegram_event_daily:        bool('cfg_evt_daily'),
                    alert_offline_enabled:        bool('cfg_alert_enabled'),
                    alert_offline_threshold_min:  document.getElementById('cfg_alert_threshold')?.value ?? '30',
                    telegram_schedule_groups:     JSON.stringify(
                        schedGroups.filter(g => g.days)
                    ),
                });
                this._toast.show('Configuración guardada', 'success');
                this.close();
            } catch {
                this._toast.show('Error al guardar', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
            }
        });
    }

    // ──────────────────────────────────────────────────────────
    //  SWITCH MANAGER MODAL
    // ──────────────────────────────────────────────────────────

    async openSwitches(api) {
        this.open('Gestor de Switches SNMP', '<p>Cargando…</p>');

        let switches = [];
        try {
            const res = await api.getSwitches();
            switches  = res.switches ?? [];
        } catch (e) {
            this._bodyEl.innerHTML = '<p style="color:var(--accent-red)">Error al cargar switches.</p>';
            return;
        }

        const statusBadge = (sw) => {
            if (!sw.last_polled) return '<span class="snmp-badge unknown">Sin datos</span>';
            const mins = Math.round((Date.now() - new Date(sw.last_polled)) / 60000);
            return `<span class="snmp-badge ok">Hace ${mins < 1 ? '<1' : mins} min</span>`;
        };

        const rows = switches.length
            ? switches.map(sw => `
              <tr>
                <td><strong>${this._esc(sw.name)}</strong></td>
                <td class="mono">${this._esc(sw.ip)}</td>
                <td>${this._esc(sw.snmp_community ?? 'public')}</td>
                <td>${this._esc(sw.vendor || '—')}${sw.model ? `<br><small style="opacity:.5">${this._esc(sw.model)}</small>` : ''}</td>
                <td>${statusBadge(sw)}</td>
                <td style="display:flex;gap:5px;flex-wrap:nowrap;">
                    <button class="btn-icon accent-blue" title="Ver puertos / CAM table"
                            onclick="app.modal._showSwitchPorts(${sw.id}, '${this._esc(sw.name)}', app.api)">
                        <i class="fas fa-sitemap"></i>
                    </button>
                    <button class="btn-icon" title="Ver tráfico"
                            onclick="app.modal._showSwitchTraffic(${sw.id}, '${this._esc(sw.name)}', app.api)">
                        <i class="fas fa-chart-bar"></i>
                    </button>
                    <button class="btn-icon accent-green" title="Panel L3 — VLANs / ARP / Rutas"
                            onclick="app.modal._showSwitchL3(${sw.id}, '${this._esc(sw.name)}', app.api)">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <button class="btn-icon accent-amber" title="Escanear ahora"
                            onclick="app.modal._pollSwitch(${sw.id}, '${this._esc(sw.name)}', app.api, this)">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button class="btn-icon btn-danger" title="Eliminar"
                            onclick="app.modal._deleteSwitchRow(${sw.id}, this, app.api)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
              </tr>`).join('')
            : '<tr><td colspan="6" style="text-align:center;opacity:0.5">No hay switches configurados</td></tr>';

        this._bodyEl.innerHTML = `
        <table class="modal-table">
            <thead>
                <tr>
                    <th>Nombre</th><th>IP</th><th>Community</th>
                    <th>Vendor / Modelo</th><th>Último poll</th><th></th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>

        <hr style="border-color:var(--glass-border); margin:20px 0">

        <h4 style="margin-bottom:10px">Agregar switch</h4>
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;">
            <label>Nombre
                <input type="text" id="sw_name" placeholder="Core-01">
            </label>
            <label>IP
                <input type="text" id="sw_ip" placeholder="192.168.0.1">
            </label>
            <label>Community
                <input type="text" id="sw_community" placeholder="public">
            </label>
        </div>
        <button class="btn btn-primary" style="margin-top:12px" id="btnAddSwitch">
            <i class="fas fa-plus"></i> Agregar Switch
        </button>`;

        document.getElementById('btnAddSwitch')?.addEventListener('click', async () => {
            const name      = document.getElementById('sw_name')?.value.trim();
            const ip        = document.getElementById('sw_ip')?.value.trim();
            const community = document.getElementById('sw_community')?.value.trim() || 'public';

            if (!name || !ip) {
                this._toast.show('Nombre e IP son obligatorios', 'error');
                return;
            }
            try {
                await api.createSwitch({ name, ip, snmp_community: community });
                this._toast.show('Switch agregado — iniciando poll SNMP…', 'success');
                this.openSwitches(api);
            } catch (e) {
                this._toast.show('Error al agregar switch', 'error');
            }
        });
    }

    async _pollSwitch(id, name, api, btn) {
        const icon = btn.querySelector('i');
        icon?.classList.add('fa-spin');
        btn.disabled = true;
        try {
            const r = await api.pollSwitch(id);
            this._toast.show(`${name}: ${r.ports} puertos, ${r.devices} dispositivos detectados`, 'success');
        } catch (e) {
            this._toast.show(`Error al escanear ${name}: ${e.message}`, 'error');
        } finally {
            icon?.classList.remove('fa-spin');
            btn.disabled = false;
        }
    }

    async _showSwitchPorts(switchId, switchName, api) {
        this.open(`Puertos — ${switchName}`, '<p style="padding:8px 0">Cargando…</p>');
        try {
            const res   = await api.getSwitchPorts(switchId);
            const ports = res.ports ?? [];

            const fmtBps = (bps) => {
                if (bps === null || bps === undefined) return '<span style="opacity:.3">—</span>';
                if (bps >= 1e9) return `<span style="color:#22d3ee">${(bps/1e9).toFixed(2)} Gbps</span>`;
                if (bps >= 1e6) return `<span style="color:#22d3ee">${(bps/1e6).toFixed(1)} Mbps</span>`;
                if (bps >= 1e3) return `<span style="color:#94a3b8">${(bps/1e3).toFixed(0)} Kbps</span>`;
                return `<span style="color:#475569">${bps} bps</span>`;
            };

            const fmtSpeed = (bps) => {
                if (!bps) return '';
                if (bps >= 1e9) return `${(bps/1e9).toFixed(0)}G`;
                if (bps >= 1e6) return `${(bps/1e6).toFixed(0)}M`;
                return `${bps}`;
            };

            const statusBadge = (s) => {
                if (s === 1) return '<span class="snmp-badge ok">UP</span>';
                if (s === 2) return '<span class="snmp-badge down">DOWN</span>';
                return '<span class="snmp-badge unknown">—</span>';
            };

            const deviceCell = (d) => {
                if (!d) return '<span style="opacity:.3">—</span>';
                const dot = d.is_online ? '🟢' : '🔴';
                return `${dot} <strong>${this._esc(d.hostname || d.ip)}</strong>
                        <br><small style="opacity:.5">${this._esc(d.ip)} · ${this._esc(d.vendor || d.device_type || '')}</small>`;
            };

            if (!ports.length) {
                this._bodyEl.innerHTML = `
                    <p style="opacity:.5;padding:8px 0">Sin datos de puertos. Ejecutá un poll SNMP primero.</p>
                    <button class="btn btn-primary" onclick="app.modal._pollAndRefreshPorts(${switchId},'${switchName}',app.api)">
                        <i class="fas fa-sync-alt"></i> Escanear ahora
                    </button>`;
                return;
            }

            const rows = ports.map(p => `
            <tr>
                <td class="mono" style="font-size:12px">${this._esc(p.port_name || String(p.port_index))}</td>
                <td>${statusBadge(p.port_status)}</td>
                <td style="font-size:11px;opacity:.6">${fmtSpeed(p.speed_bps)}</td>
                <td>${deviceCell(p.device)}</td>
                <td>${fmtBps(p.rx_bps)}</td>
                <td>${fmtBps(p.tx_bps)}</td>
                <td style="font-size:10px;opacity:.4">${p.ts ? p.ts.slice(11,16) : '—'}</td>
            </tr>`).join('');

            const upCount   = ports.filter(p => p.port_status === 1).length;
            const downCount = ports.filter(p => p.port_status === 2).length;
            const withDev   = ports.filter(p => p.device).length;

            this._bodyEl.innerHTML = `
            <div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
                <span class="snmp-badge ok">▲ ${upCount} UP</span>
                <span class="snmp-badge down">▼ ${downCount} DOWN</span>
                <span style="font-size:12px;opacity:.6">${withDev} dispositivo(s) detectado(s)</span>
                <button class="btn btn-ghost btn-sm" style="margin-left:auto"
                        onclick="app.modal._pollAndRefreshPorts(${switchId},'${switchName}',app.api)">
                    <i class="fas fa-sync-alt"></i> Poll ahora
                </button>
            </div>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Puerto</th><th>Estado</th><th>Velocidad</th>
                        <th>Dispositivo</th><th style="color:#22d3ee">RX</th>
                        <th style="color:#f59e0b">TX</th><th>Hora</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            <p style="font-size:11px;opacity:.35;margin-top:10px">
                * Tasa calculada entre las últimas 2 muestras SNMP. Puertos Down sin tráfico pueden omitirse.
            </p>`;
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
        }
    }

    async _pollAndRefreshPorts(switchId, switchName, api) {
        this._toast.show(`Escaneando ${switchName}…`, 'info');
        try {
            const r = await api.pollSwitch(switchId);
            this._toast.show(`${switchName}: ${r.ports} puertos encontrados`, 'success');
            await this._showSwitchPorts(switchId, switchName, api);
        } catch (e) {
            this._toast.show(`Error: ${e.message}`, 'error');
        }
    }

    async _showSwitchTraffic(switchId, switchName, api) {
        this.open(`Tráfico — ${switchName}`, '<p style="padding:8px 0">Cargando…</p>');
        try {
            const res   = await api.getSwitchTraffic(switchId);
            const ports = (res.ports ?? []).filter(p => p.rx_bps !== null || p.in_octets > 0);

            if (!ports.length) {
                this._bodyEl.innerHTML = '<p style="opacity:0.5;padding:8px 0">Sin datos de tráfico aún. Esperando al menos 2 ciclos de polling SNMP.</p>';
                return;
            }

            const fmt = (bps) => {
                if (bps === null) return '<span style="opacity:0.35">—</span>';
                if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
                if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
                if (bps >= 1e3) return (bps / 1e3).toFixed(1) + ' Kbps';
                return bps + ' bps';
            };

            const rows = ports.map(p => `
            <tr>
                <td>${p.port_name || p.port_index}</td>
                <td style="color:#22d3ee">${fmt(p.rx_bps)}</td>
                <td style="color:#f59e0b">${fmt(p.tx_bps)}</td>
                <td style="opacity:0.45;font-size:11px">${p.ts ?? '—'}</td>
            </tr>`).join('');

            this._bodyEl.innerHTML = `
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Puerto</th>
                        <th style="color:#22d3ee">RX</th>
                        <th style="color:#f59e0b">TX</th>
                        <th>Último muestreo</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            <p style="font-size:11px;opacity:0.4;margin-top:12px">
                * Tasa calculada entre las últimas 2 muestras SNMP.
                Puertos sin tráfico detectado se omiten.
            </p>`;
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
        }
    }

    async _showSwitchL3(switchId, switchName, api) {
        this.open(`L3 — ${switchName}`, '<p style="padding:8px 0">Cargando datos L3…</p>');
        try {
            const d = await api.getSwitchL3(switchId);

            // ── Helpers ──────────────────────────────────────────────────────
            const empty = (msg) => `<tr><td colspan="10" style="text-align:center;opacity:.4;padding:10px">${msg}</td></tr>`;
            const esc   = s => this._esc(String(s ?? ''));
            const badge = (online) => online ? '🟢' : '🔴';

            // ── VLANs tab ────────────────────────────────────────────────────
            const vlanRows = d.vlans.length
                ? d.vlans.map(v => `
                <tr>
                    <td class="mono">${v.vlan_id}</td>
                    <td>${esc(v.vlan_name)}</td>
                    <td style="font-size:11px;opacity:.7">${(v.tagged_ports || []).join(', ') || '—'}</td>
                    <td style="font-size:11px;opacity:.7">${(v.untagged_ports || []).join(', ') || '—'}</td>
                </tr>`).join('')
                : empty('Sin VLANs descubiertas. Ejecutá un poll SNMP — el equipo debe soportar Q-BRIDGE-MIB.');

            // ── Interface IPs tab ─────────────────────────────────────────────
            const ifRows = d.interfaceIps.length
                ? d.interfaceIps.map(i => `
                <tr>
                    <td class="mono">${i.if_index}</td>
                    <td class="mono">${esc(i.ip_addr)}</td>
                    <td class="mono" style="opacity:.6">${esc(i.netmask)}</td>
                </tr>`).join('')
                : empty('Sin IPs de interfaces. El equipo puede no soportar IP-MIB o es solo L2.');

            // ── ARP table tab ─────────────────────────────────────────────────
            const arpRows = d.arpEntries.length
                ? d.arpEntries.map(a => `
                <tr>
                    <td class="mono">${esc(a.ip_addr)}</td>
                    <td class="mono" style="font-size:11px">${esc(a.mac_addr)}</td>
                    <td style="font-size:11px;opacity:.5">${a.if_index || '—'}</td>
                    <td>${a.device_id
                        ? `${badge(a.is_online)} <strong>${esc(a.hostname || a.ip_addr)}</strong><br><small style="opacity:.5">${esc(a.dev_vendor || a.device_type || '')}</small>`
                        : '<span style="opacity:.3;font-size:11px">No registrado</span>'}</td>
                    <td style="font-size:10px;opacity:.3">${esc(a.ts?.slice(0,16) || '')}</td>
                </tr>`).join('')
                : empty('Sin entradas ARP. El equipo puede no soportar ipNetToMedia MIB.');

            // ── Routing table tab ─────────────────────────────────────────────
            const RTYPE = { direct: '🔵 Directo', indirect: '🟡 Remoto', other: '⚪ Otro', invalid: '❌ Inválido' };
            const routeRows = d.routes.length
                ? d.routes.map(r => `
                <tr>
                    <td class="mono">${esc(r.dest)}</td>
                    <td class="mono" style="opacity:.6">${esc(r.mask)}</td>
                    <td class="mono">${esc(r.next_hop) || '—'}</td>
                    <td style="font-size:11px">${RTYPE[r.route_type] || r.route_type || '—'}</td>
                    <td style="opacity:.5;font-size:11px">${r.metric ?? '—'}</td>
                </tr>`).join('')
                : empty('Sin tabla de rutas. El equipo puede ser L2 only o no soportar IP-MIB routing.');

            // ── CDP Neighbors tab ─────────────────────────────────────────────
            const cdpRows = d.cdpNeighbors.length
                ? d.cdpNeighbors.map(n => `
                <tr>
                    <td class="mono">${esc(n.local_port)}</td>
                    <td><strong>${esc(n.remote_device)}</strong></td>
                    <td class="mono" style="opacity:.7">${esc(n.remote_ip) || '—'}</td>
                    <td class="mono" style="font-size:11px;opacity:.6">${esc(n.remote_port) || '—'}</td>
                </tr>`).join('')
                : empty('Sin vecinos CDP. Solo Cisco con CDP habilitado.');

            // ── Tabs render ───────────────────────────────────────────────────
            const tabStyle = (active) => `style="padding:6px 14px;border:none;border-radius:6px 6px 0 0;cursor:pointer;font-size:12px;font-weight:600;background:${active ? 'var(--accent-blue)' : 'var(--glass-bg)'};color:${active ? '#fff' : 'var(--text-muted)'};"`;

            this._bodyEl.innerHTML = `
            <div style="margin-bottom:4px;display:flex;gap:4px;flex-wrap:wrap">
                <button id="l3tab-iface"   ${tabStyle(true)}  onclick="app.modal._l3Tab('iface')">🌐 Interfaces <span style="opacity:.6">(${d.interfaceIps.length})</span></button>
                <button id="l3tab-vlans"   ${tabStyle(false)} onclick="app.modal._l3Tab('vlans')">🏷 VLANs <span style="opacity:.6">(${d.vlans.length})</span></button>
                <button id="l3tab-arp"     ${tabStyle(false)} onclick="app.modal._l3Tab('arp')">📋 ARP <span style="opacity:.6">(${d.arpEntries.length})</span></button>
                <button id="l3tab-routes"  ${tabStyle(false)} onclick="app.modal._l3Tab('routes')">🗺 Rutas <span style="opacity:.6">(${d.routes.length})</span></button>
                <button id="l3tab-cdp"     ${tabStyle(false)} onclick="app.modal._l3Tab('cdp')">🔗 CDP <span style="opacity:.6">(${d.cdpNeighbors.length})</span></button>
            </div>

            <div id="l3panel-iface" class="l3panel">
                <table class="modal-table">
                    <thead><tr><th>IF Index</th><th>IP</th><th>Máscara</th></tr></thead>
                    <tbody>${ifRows}</tbody>
                </table>
            </div>
            <div id="l3panel-vlans" class="l3panel" style="display:none">
                <table class="modal-table">
                    <thead><tr><th>VLAN ID</th><th>Nombre</th><th>Tagged ports</th><th>Untagged ports</th></tr></thead>
                    <tbody>${vlanRows}</tbody>
                </table>
            </div>
            <div id="l3panel-arp" class="l3panel" style="display:none">
                <table class="modal-table">
                    <thead><tr><th>IP</th><th>MAC</th><th>IF</th><th>Dispositivo</th><th>Actualizado</th></tr></thead>
                    <tbody>${arpRows}</tbody>
                </table>
            </div>
            <div id="l3panel-routes" class="l3panel" style="display:none">
                <table class="modal-table">
                    <thead><tr><th>Destino</th><th>Máscara</th><th>Next Hop</th><th>Tipo</th><th>Métrica</th></tr></thead>
                    <tbody>${routeRows}</tbody>
                </table>
            </div>
            <div id="l3panel-cdp" class="l3panel" style="display:none">
                <table class="modal-table">
                    <thead><tr><th>Puerto local</th><th>Vecino</th><th>IP remota</th><th>Puerto remoto</th></tr></thead>
                    <tbody>${cdpRows}</tbody>
                </table>
            </div>
            <p style="font-size:11px;opacity:.3;margin-top:10px">
                Datos del último poll SNMP. Vacío = equipo no soporta ese MIB. Ejecutá Poll para actualizar.
            </p>`;
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
        }
    }

    _l3Tab(name) {
        const tabs   = ['iface', 'vlans', 'arp', 'routes', 'cdp'];
        const active = 'var(--accent-blue)';
        const idle   = 'var(--glass-bg)';
        tabs.forEach(t => {
            const btn   = document.getElementById(`l3tab-${t}`);
            const panel = document.getElementById(`l3panel-${t}`);
            if (!btn || !panel) return;
            const isActive = t === name;
            panel.style.display  = isActive ? '' : 'none';
            btn.style.background = isActive ? active : idle;
            btn.style.color      = isActive ? '#fff' : 'var(--text-muted)';
        });
    }

    async _deleteSwitchRow(id, btn, api) {
        if (!confirm('¿Eliminar este switch?')) return;
        try {
            await api.deleteSwitch(id);
            btn.closest('tr')?.remove();
            this._toast.show('Switch eliminado', 'success');
        } catch (e) {
            this._toast.show('Error al eliminar', 'error');
        }
    }

    // ──────────────────────────────────────────────────────────
    //  TOPOLOGY MODAL  (vis.js)
    //
    //  FIX: nodes.shape = 'icon' requires icon.code in EVERY
    //  group definition. Previously only the fallback default
    //  lacked a code, causing the Icon.js console error.
    // ──────────────────────────────────────────────────────────

    openTopology(api, devices) {
        const mc = document.getElementById('modalContent');
        if (mc) mc.classList.add('modal-wide', 'modal-fullish');

        this.open('Mapa de Topología', `
        <div class="topo-toolbar">
            <button id="btnTopoFit"    class="btn btn-ghost btn-sm"><i class="fas fa-compress-arrows-alt"></i> Ajustar</button>
            <button id="btnTopoZoomIn" class="btn btn-ghost btn-sm"><i class="fas fa-search-plus"></i></button>
            <button id="btnTopoZoomOut"class="btn btn-ghost btn-sm"><i class="fas fa-search-minus"></i></button>
            <div class="topo-legend">
                <span class="topo-leg-item"><i class="fas fa-globe"        style="color:#f59e0b"></i> Gateway/Router</span>
                <span class="topo-leg-item"><i class="fas fa-network-wired" style="color:#22d3ee"></i> Switch</span>
                <span class="topo-leg-item"><i class="fas fa-desktop"      style="color:#3b82f6"></i> PC/VM</span>
                <span class="topo-leg-item"><i class="fas fa-mobile-alt"   style="color:#10b981"></i> Móvil</span>
                <span class="topo-leg-item"><i class="fas fa-question-circle" style="color:#64748b"></i> Genérico</span>
            </div>
        </div>
        <div id="topologyNetwork" class="topo-canvas"></div>`);

        setTimeout(() => this._drawTopology('topologyNetwork', api, devices), 250);
    }

    async _drawTopology(containerId, api, devices) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (typeof vis === 'undefined') {
            container.innerHTML =
                '<p style="padding:20px;color:#ef4444">vis.js no está cargado.</p>';
            return;
        }

        let topology;
        try {
            topology = await api.getTopology();
        } catch (e) {
            container.innerHTML =
                `<p style="padding:20px;color:#ef4444">Error: ${e.message}</p>`;
            return;
        }

        const FA = (code, size, color) => ({
            face: '"Font Awesome 5 Free"', weight: '900', size, color, code,
        });

        const options = {
            nodes: {
                font:  { color: '#cbd5e1', size: 13, strokeWidth: 3, strokeColor: '#050a14' },
                shape: 'icon',
                icon:  FA('\uf108', 28, '#64748b'),
            },
            groups: {
                subnet:   {
                    shape: 'diamond', size: 32,
                    color: { background: '#0c2233', border: '#22d3ee', highlight: { background: '#164e63', border: '#67e8f9' } },
                    font:  { color: '#22d3ee', size: 14, bold: true, strokeWidth: 3, strokeColor: '#050a14' },
                },
                router:   { icon: FA('\uf0ac', 42, '#f59e0b') },
                gateway:  { icon: FA('\uf0ac', 42, '#f59e0b') },
                switch:   { icon: FA('\uf6ff', 36, '#22d3ee') },
                computer: { icon: FA('\uf109', 30, '#3b82f6') },
                android:  { icon: FA('\uf17b', 28, '#10b981') },
                apple:    { icon: FA('\uf179', 28, '#e2e8f0') },
                printer:  { icon: FA('\uf02f', 28, '#a78bfa') },
                generic:  { icon: FA('\uf108', 26, '#475569') },
            },
            edges: {
                color:   { color: '#334155', highlight: '#60a5fa', hover: '#60a5fa', opacity: 0.9 },
                width:   1.8,
                smooth:  { type: 'cubicBezier', forceDirection: 'vertical', roundness: 0.35 },
                font:    { color: '#64748b', size: 10, strokeWidth: 0, align: 'middle' },
                arrows:  { to: { enabled: true, scaleFactor: 0.45 } },
                hoverWidth: 2.5,
            },
            layout: {
                hierarchical: {
                    enabled:              true,
                    direction:            'UD',
                    sortMethod:           'directed',
                    levelSeparation:      160,
                    nodeSpacing:          110,
                    treeSpacing:          200,
                    blockShifting:        true,
                    edgeMinimization:     true,
                    parentCentralization: true,
                },
            },
            physics: { enabled: false },
            interaction: {
                hover:             true,
                tooltipDelay:      120,
                navigationButtons: false,
                zoomView:          true,
                dragView:          true,
                multiselect:       false,
            },
        };

        const network = new vis.Network(container, topology, options);
        network.once('stabilized', () => {
            network.fit({ animation: false });
            const s = network.getScale();
            if (s < 0.65) network.moveTo({ scale: 0.65, animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
        });

        // Toolbar buttons
        document.getElementById('btnTopoFit')?.addEventListener('click', () =>
            network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } }));
        document.getElementById('btnTopoZoomIn')?.addEventListener('click',  () =>
            network.moveTo({ scale: network.getScale() * 1.35, animation: { duration: 250 } }));
        document.getElementById('btnTopoZoomOut')?.addEventListener('click', () =>
            network.moveTo({ scale: network.getScale() / 1.35, animation: { duration: 250 } }));

        // Click simple → resalta nodo (selección visual), doble-click abre detalle
        network.on('click', () => { /* selección manejada por vis */ });

        // Right-click en nodo → context menu con acciones
        const ctxMenu = window.app?._ctxMenu ?? new ContextMenu();
        if (window.app) window.app._ctxMenu = ctxMenu;

        // Hover tooltip
        network.on('hoverNode', (params) => {
            const nodeId = params.node;
            const device = devices.find(d => d.mac === nodeId || String(d.id) === String(nodeId));
            if (!device) return;
            const dot = device.is_online ? '🟢' : '🔴';
            const tip = `${dot} ${device.hostname || device.ip}\n${device.ip}${device.vendor ? ' · ' + device.vendor : ''}${device.device_type ? ' · ' + device.device_type : ''}`;
            container.title = tip;
        });
        network.on('blurNode', () => { container.title = ''; });

        // Double-click en nodo → abrir detalle
        network.on('doubleClick', (params) => {
            if (!params.nodes.length) return;
            const nodeId = params.nodes[0];
            const device = devices.find(d => d.mac === nodeId || String(d.id) === String(nodeId));
            if (device && window.app) window.app.detail.open(device);
        });

        // Right-click → context menu
        network.on('oncontext', (params) => {
            params.event.preventDefault();
            if (!params.nodes.length) return;

            const nodeId = params.nodes[0];
            const device = devices.find(d => d.mac === nodeId || String(d.id) === String(nodeId));
            if (!device) return;

            const label = device.hostname || device.ip;
            const { x, y } = params.event;

            ctxMenu.show(x, y, [
                {
                    icon: 'fa-info-circle', label: `Ver detalle — ${label}`,
                    action: () => window.app?.detail.open(device),
                },
                { separator: true },
                {
                    icon: 'fa-wifi', label: 'Ping rápido',
                    action: async () => {
                        window.app?.toast.show(`Ping a ${device.ip}…`, 'info');
                        const r = await window.app?.api.pingDevice(device.ip).catch(() => null);
                        window.app?.toast.show(r?.success
                            ? `✅ ${device.ip} responde`
                            : `❌ ${device.ip} sin respuesta`, r?.success ? 'success' : 'error');
                    },
                },
                {
                    icon: 'fa-bolt', label: 'Wake on LAN',
                    disabled: !device.mac,
                    action: async () => {
                        const r = await window.app?.api.wakeDevice(device.mac).catch(() => null);
                        window.app?.toast.show(r?.message ?? (r?.success ? `WoL enviado a ${device.mac}` : 'Error WoL'),
                            r?.success ? 'success' : 'error');
                    },
                },
                { separator: true },
                {
                    icon: 'fa-copy', label: 'Copiar IP',
                    action: () => {
                        navigator.clipboard?.writeText(device.ip);
                        window.app?.toast.show(`IP ${device.ip} copiada`, 'info');
                    },
                },
            ]);
        });

    }

    // ──────────────────────────────────────────────────────────
    //  VAULT MODAL  (SSH keys, passwords, SNMP credentials)
    // ──────────────────────────────────────────────────────────

    async openVault(api) {
        this.open('Bóveda RMM', '<p style="padding:8px 0">Cargando…</p>');

        let creds = [];
        try {
            const res = await api.getCredentials();
            creds = res.credentials ?? [];
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
            return;
        }

        const typeIcon = { ssh_key: 'fa-key', password: 'fa-lock', snmp: 'fa-network-wired' };
        const typeLabel = { ssh_key: 'SSH Key', password: 'Contraseña', snmp: 'SNMP' };

        const rows = creds.length
            ? creds.map(c => `
              <tr>
                <td><i class="fas ${typeIcon[c.type] ?? 'fa-shield-alt'}" style="margin-right:6px;opacity:.7"></i>${typeLabel[c.type] ?? c.type}</td>
                <td><b>${c.name}</b></td>
                <td>${c.username || '<span style="opacity:.4">—</span>'}</td>
                <td style="font-family:monospace">${c._has_password || c._has_key ? '••••••' : '<span style="opacity:.4">—</span>'}</td>
                <td style="opacity:.45;font-size:11px">${c.linked_device_ids?.length ? c.linked_device_ids.length + ' dispositivos' : '—'}</td>
                <td>
                    <button class="btn-icon btn-danger" onclick="app.modal._deleteCredential(${c.id}, this, app.api)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
              </tr>`).join('')
            : '<tr><td colspan="6" style="text-align:center;opacity:.4;padding:20px">Sin credenciales guardadas</td></tr>';

        this._bodyEl.innerHTML = `
        <table class="modal-table">
            <thead><tr>
                <th>Tipo</th><th>Nombre</th><th>Usuario</th>
                <th>Secreto</th><th>Vínculos</th><th></th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>

        <hr style="border-color:var(--glass-border);margin:20px 0">
        <h4 style="margin-bottom:12px">Agregar credencial</h4>
        <form id="vaultAddForm" autocomplete="off" onsubmit="return false">
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;">
            <label>Tipo
                <select id="vc_type">
                    <option value="ssh_key">SSH Key</option>
                    <option value="password">Contraseña</option>
                    <option value="snmp">SNMP Community</option>
                </select>
            </label>
            <label>Nombre / Etiqueta
                <input type="text" id="vc_name" placeholder="Servidor Principal" autocomplete="off">
            </label>
            <label>Usuario
                <input type="text" id="vc_username" placeholder="admin" autocomplete="off">
            </label>
        </div>
        <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-top:10px;">
            <label>Contraseña / Community
                <input type="password" id="vc_password" placeholder="••••••••" autocomplete="new-password">
            </label>
            <label>Clave privada (SSH)
                <textarea id="vc_key" rows="3"
                    style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--glass-border);border-radius:6px;padding:6px 10px;width:100%;font-family:monospace;font-size:11px;resize:vertical"
                    placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
            </label>
        </div>
        <button class="btn btn-primary" style="margin-top:12px" id="btnAddCred" type="submit">
            <i class="fas fa-plus"></i> Guardar credencial
        </button>
        </form>`;

        document.getElementById('btnAddCred')?.addEventListener('click', async () => {
            const type        = document.getElementById('vc_type')?.value;
            const name        = document.getElementById('vc_name')?.value.trim();
            const username    = document.getElementById('vc_username')?.value.trim();
            const password    = document.getElementById('vc_password')?.value;
            const private_key = document.getElementById('vc_key')?.value.trim();

            if (!name) { this._toast.show('El nombre es obligatorio', 'error'); return; }
            try {
                await api.createCredential({ type, name, username, password, private_key });
                this._toast.show('Credencial guardada', 'success');
                this.openVault(api);  // reload
            } catch (e) {
                this._toast.show('Error al guardar', 'error');
            }
        });
    }

    async _deleteCredential(id, btn, api) {
        if (!confirm('¿Eliminar esta credencial?')) return;
        try {
            await api.deleteCredential(id);
            btn.closest('tr')?.remove();
            this._toast.show('Credencial eliminada', 'success');
        } catch (e) {
            this._toast.show('Error al eliminar', 'error');
        }
    }

    // ──────────────────────────────────────────────────────────
    //  IPAM SUBNET MAP
    // ──────────────────────────────────────────────────────────

    async openIPAM(api) {
        this.open('Mapa de Subred (IPAM)', '<p style="padding:8px 0">Cargando…</p>');

        let subnets = [];
        try {
            const r = await api.getIpamSubnets();
            subnets = r.subnets ?? [];
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
            return;
        }

        if (!subnets.length) {
            this._bodyEl.innerHTML = '<p style="opacity:0.5;padding:8px 0">No hay dispositivos en la base de datos aún.</p>';
            return;
        }

        const subnetOptions = subnets
            .map(s => `<option value="${s.prefix}">${s.prefix}.0/24 (${s.count} dispositivos)</option>`)
            .join('');

        this._bodyEl.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary)">
                Subred:
                <select id="ipam_subnet" style="background:var(--bg-secondary);color:var(--text-primary);border:1px solid var(--glass-border);border-radius:6px;padding:4px 10px;font-size:13px;">
                    ${subnetOptions}
                </select>
            </label>
            <div style="display:flex;gap:16px;font-size:12px;margin-left:auto;">
                <span><span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:3px;margin-right:4px;vertical-align:middle"></span>Online</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#ef4444;border-radius:3px;margin-right:4px;vertical-align:middle"></span>Offline</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#1e293b;border:1px solid #334155;border-radius:3px;margin-right:4px;vertical-align:middle"></span>Libre</span>
            </div>
        </div>
        <div id="ipam_grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(44px,1fr));gap:5px;max-height:48vh;overflow-y:auto;padding:4px 2px;"></div>
        <div id="ipam_tooltip" style="display:none;position:fixed;background:var(--bg-card);border:1px solid var(--glass-border);border-radius:8px;padding:10px 14px;font-size:12px;pointer-events:none;z-index:1000;min-width:180px;box-shadow:0 8px 24px rgba(0,0,0,.5)"></div>
        <hr style="border-color:var(--glass-border);margin:14px 0 10px">
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <label style="font-size:12px;color:var(--text-secondary)">Anclar IP
                <input type="text" id="ipam_pin_ip" placeholder="192.168.1.50"
                    style="display:block;margin-top:4px;background:var(--bg-secondary);color:var(--text-primary);
                           border:1px solid var(--glass-border);border-radius:6px;padding:4px 10px;font-size:13px;width:160px;">
            </label>
            <label style="font-size:12px;color:var(--text-secondary)">Etiqueta
                <input type="text" id="ipam_pin_label" placeholder="Impresora Color"
                    style="display:block;margin-top:4px;background:var(--bg-secondary);color:var(--text-primary);
                           border:1px solid var(--glass-border);border-radius:6px;padding:4px 10px;font-size:13px;width:160px;">
            </label>
            <label style="font-size:12px;color:var(--text-secondary)">Color
                <input type="color" id="ipam_pin_color" value="#f59e0b"
                    style="display:block;margin-top:4px;height:30px;width:48px;border:1px solid var(--glass-border);border-radius:6px;background:none;cursor:pointer;">
            </label>
            <button class="btn btn-primary" id="btnPinIp" style="height:30px;padding:0 14px;font-size:12px;">
                <i class="fas fa-thumbtack"></i> Anclar
            </button>
        </div>`;

        const loadGrid = async (subnet) => {
            const grid = document.getElementById('ipam_grid');
            if (!grid) return;
            grid.innerHTML = '<span style="opacity:0.4;font-size:12px">Cargando…</span>';

            let data;
            try {
                data = await api.getIpam(subnet);
            } catch (e) {
                grid.innerHTML = `<p style="color:var(--accent-red)">Error: ${e.message}</p>`;
                return;
            }

            const tip = document.getElementById('ipam_tooltip');

            grid.innerHTML = (data.slots ?? []).map(slot => {
                let bg, border, textColor;
                if (slot.pinned && !slot.known) {
                    bg        = slot.pin_color ?? '#f59e0b';
                    border    = bg;
                    textColor = '#000';
                } else if (!slot.known) {
                    bg        = '#0f172a';
                    border    = '#1e293b';
                    textColor = '#334155';
                } else if (slot.is_online) {
                    bg        = slot.pinned ? slot.pin_color ?? '#10b981' : '#10b981';
                    border    = 'transparent';
                    textColor = '#fff';
                } else {
                    bg        = '#ef4444';
                    border    = 'transparent';
                    textColor = '#fff';
                }
                const label = slot.pinned && !slot.known
                    ? (slot.pin_label || slot.host)
                    : (slot.known ? (slot.hostname || slot.ip.split('.').pop()) : slot.host);
                const pinMark = slot.pinned ? '📌' : '';

                return `<div class="ipam-cell"
                    data-ip="${slot.ip}"
                    data-known="${slot.known}"
                    data-pinned="${slot.pinned ?? false}"
                    data-online="${slot.is_online}"
                    data-hostname="${slot.hostname}"
                    data-mac="${slot.mac}"
                    data-vendor="${slot.vendor}"
                    data-type="${slot.device_type}"
                    data-os="${slot.os}"
                    data-id="${slot.id ?? ''}"
                    style="background:${bg};border-radius:5px;padding:4px 3px;text-align:center;
                           font-size:11px;cursor:${slot.known || slot.pinned ? 'pointer' : 'default'};
                           color:${textColor};border:1px solid ${border};
                           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                           transition:transform .1s,box-shadow .1s;"
                    >${pinMark}${label}</div>`;
            }).join('');

            // Tooltip on hover (all cells)
            grid.querySelectorAll('.ipam-cell').forEach(cell => {
                const d = cell.dataset;
                cell.addEventListener('mouseenter', (e) => {
                    tip.innerHTML = `
                        <div style="font-weight:600;margin-bottom:4px;color:var(--text-primary)">${d.ip}</div>
                        ${d.hostname ? `<div>Hostname: <b>${d.hostname}</b></div>` : ''}
                        ${d.mac && d.mac !== 'false' && d.mac ? `<div>MAC: <code style="font-size:11px">${d.mac}</code></div>` : ''}
                        ${d.vendor   ? `<div>Vendor: ${d.vendor}</div>` : ''}
                        ${d.type     ? `<div>Tipo: ${d.type}</div>` : ''}
                        ${d.os       ? `<div>OS: ${d.os}</div>` : ''}
                        ${d.known === 'true'
                            ? `<div style="margin-top:6px">${d.online === '1' ? '<span style="color:#10b981">● Online</span>' : '<span style="color:#ef4444">● Offline</span>'}</div>`
                            : (d.pinned === 'true' ? '<div style="margin-top:6px;color:#f59e0b">📌 Anclada</div>' : '<div style="margin-top:6px;opacity:.4">● Libre</div>')
                        }
                        <div style="margin-top:6px;opacity:.5;font-size:10px">Click derecho → anclar/desanclar</div>
                    `;
                    tip.style.display = 'block';
                    tip.style.left = (e.clientX + 14) + 'px';
                    tip.style.top  = (e.clientY - 10) + 'px';
                });
                cell.addEventListener('mousemove', (e) => {
                    tip.style.left = (e.clientX + 14) + 'px';
                    tip.style.top  = (e.clientY - 10) + 'px';
                });
                cell.addEventListener('mouseleave', () => { tip.style.display = 'none'; });

                // Left click → open device detail if known
                cell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const id = d.id;
                    if (!id || !window.app) return;
                    const device = window.app.devices.find(dev => String(dev.id) === id);
                    if (device) { this.close(); window.app.detail.open(device); }
                });

                // Right click → pin / unpin
                cell.addEventListener('contextmenu', async (e) => {
                    e.preventDefault();
                    tip.style.display = 'none';
                    if (d.pinned === 'true') {
                        await api.unpinIp(d.ip).catch(() => {});
                        this._toast?.show(`${d.ip} desanclada`, 'info');
                    } else {
                        const label = d.hostname || d.ip;
                        await api.pinIp({ ip: d.ip, label }).catch(() => {});
                        this._toast?.show(`${d.ip} anclada`, 'success');
                    }
                    const sel = document.getElementById('ipam_subnet');
                    if (sel) loadGrid(sel.value);
                });
            });
        };

        const sel = document.getElementById('ipam_subnet');
        sel?.addEventListener('change', () => loadGrid(sel.value));

        // Pin button handler
        document.getElementById('btnPinIp')?.addEventListener('click', async () => {
            const ip    = document.getElementById('ipam_pin_ip')?.value.trim();
            const label = document.getElementById('ipam_pin_label')?.value.trim() || ip;
            const color = document.getElementById('ipam_pin_color')?.value || '#f59e0b';
            if (!ip) { this._toast?.show('Ingresá una IP', 'error'); return; }
            try {
                await api.pinIp({ ip, label, color });
                this._toast?.show(`${ip} anclada`, 'success');
                loadGrid(sel?.value ?? subnets[0].prefix);
            } catch (e) {
                this._toast?.show('Error al anclar IP', 'error');
            }
        });

        loadGrid(subnets[0].prefix);
    }

    // ──────────────────────────────────────────────────────────
    //  NETWORK TOOLS — Speed test + Geo-traceroute
    // ──────────────────────────────────────────────────────────
    openNetworkTools(api) {
        // Make modal wider for the map
        const mc = document.getElementById('modalContent');
        if (mc) mc.classList.add('modal-wide');

        this.open('Herramientas de Red', `
        <div class="nt-tabs">
            <button class="nt-tab active" data-tab="speedtest">
                <i class="fas fa-bolt"></i> Speed Test
            </button>
            <button class="nt-tab" data-tab="tracemap">
                <i class="fas fa-map-marked-alt"></i> Geo Traceroute
            </button>
        </div>

        <!-- ── Speed Test ── -->
        <div id="nt-speedtest" class="nt-pane active">
            <p class="nt-desc">
                Descarga 10 MB desde Cloudflare y mide la velocidad real del servidor hacia internet.
                No mide el ancho de banda del dispositivo seleccionado.
            </p>
            <div style="text-align:center;margin-bottom:20px">
                <button id="btnSpeed" class="btn btn-primary" style="padding:10px 28px">
                    <i class="fas fa-bolt"></i> Medir ahora
                </button>
            </div>
            <div id="nt-speed-result"></div>
        </div>

        <!-- ── Geo Traceroute ── -->
        <div id="nt-tracemap" class="nt-pane" style="display:none">
            <div class="nt-trace-bar">
                <input id="nt-trace-target" type="text"
                    placeholder="IP o hostname destino — ej: 8.8.8.8, google.com"
                    class="nt-trace-input">
                <button id="btnTrace" class="btn btn-primary">
                    <i class="fas fa-route"></i> Trazar
                </button>
            </div>
            <div id="nt-path-diagram"></div>
            <div id="nt-hops-table"></div>
        </div>`);

        // ── Tab switcher ──────────────────────────────────────────
        document.querySelectorAll('.nt-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.nt-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.nt-pane').forEach(p => p.style.display = 'none');
                tab.classList.add('active');
                const pane = document.getElementById(`nt-${tab.dataset.tab}`);
                if (pane) pane.style.display = 'block';
            });
        });

        // ── Speed test ────────────────────────────────────────────
        document.getElementById('btnSpeed')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnSpeed');
            const out = document.getElementById('nt-speed-result');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Midiendo…';
            btn.disabled = true;
            out.innerHTML = '<div class="nt-gauge-wrap"><div class="nt-gauge-spinner"><i class="fas fa-spinner fa-spin"></i></div></div>';
            try {
                const r    = await api.speedTest();
                const mbps = parseFloat(r.mbps);
                const pct  = Math.min(100, mbps / 10);
                const color = mbps > 100 ? '#10b981' : mbps > 25 ? '#22d3ee' : mbps > 5 ? '#f59e0b' : '#ef4444';
                out.innerHTML = `
                    <div class="nt-gauge-wrap">
                        <svg viewBox="0 0 200 110" class="nt-gauge-svg">
                            <path d="M10,100 A90,90 0 0,1 190,100" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="16" stroke-linecap="round"/>
                            <path d="M10,100 A90,90 0 0,1 190,100" fill="none" stroke="${color}" stroke-width="16" stroke-linecap="round"
                                  stroke-dasharray="${Math.PI * 90}" stroke-dashoffset="${Math.PI * 90 * (1 - pct / 100)}"
                                  style="transition:stroke-dashoffset 1s ease"/>
                            <text x="100" y="92" text-anchor="middle" font-size="30" font-weight="700"
                                  fill="${color}" font-family="monospace">${mbps}</text>
                            <text x="100" y="108" text-anchor="middle" font-size="12" fill="rgba(255,255,255,.5)">Mbps</text>
                        </svg>
                        <p class="nt-gauge-meta">
                            ${r.bytes ? Math.round(r.bytes / 1024 / 1024) + ' MB · ' : ''}${r.elapsed} s · via Cloudflare
                        </p>
                    </div>`;
            } catch (e) {
                out.innerHTML = `<p style="color:var(--accent-red);text-align:center">${this._esc(e.message)}</p>`;
            } finally {
                btn.innerHTML = '<i class="fas fa-redo"></i> Repetir';
                btn.disabled = false;
            }
        });

        // ── Geo Traceroute ────────────────────────────────────────
        document.getElementById('btnTrace')?.addEventListener('click', async () => {
            const input  = document.getElementById('nt-trace-target');
            const target = (input?.value ?? '').trim();
            if (!target) { input?.focus(); return; }

            const btn      = document.getElementById('btnTrace');
            const diagEl   = document.getElementById('nt-path-diagram');
            const hopsEl   = document.getElementById('nt-hops-table');
            btn.innerHTML  = '<i class="fas fa-spinner fa-spin"></i> Trazando…';
            btn.disabled   = true;
            diagEl.innerHTML = '';
            hopsEl.innerHTML = '<p class="nt-trace-msg">Trazando ruta… puede tardar hasta 30 s.</p>';

            try {
                const r = await api.geoTraceroute(target);

                if (!r.hops?.length) {
                    hopsEl.innerHTML = '<p class="nt-trace-msg">Sin hops detectados.</p>';
                    return;
                }

                // ── Path diagram ──────────────────────────────────
                const nodeIcon = (h) => {
                    if (h.timeout || !h.ip) return 'fa-question';
                    if (!h.isPrivate) return 'fa-globe';
                    const t = (h.device?.device_type || '').toLowerCase();
                    if (t.includes('switch'))  return 'fa-network-wired';
                    if (t.includes('router'))  return 'fa-route';
                    if (t.includes('printer')) return 'fa-print';
                    if (t.includes('ap') || t.includes('wifi')) return 'fa-wifi';
                    return 'fa-server';
                };
                const nodeType = (h) => {
                    if (h.timeout || !h.ip) return 'timeout';
                    return h.isPrivate ? 'lan' : 'wan';
                };
                const nodeLabel = (h) => {
                    if (h.timeout || !h.ip) return '* timeout';
                    if (h.isPrivate && h.device?.hostname) return h.device.hostname;
                    if (h.isPrivate && h.device?.vendor)   return h.device.vendor;
                    if (!h.isPrivate && h.ptr)             return h.ptr;
                    return h.ip;
                };
                const nodeSub = (h) => {
                    if (h.isPrivate && h.device) {
                        const parts = [];
                        if (h.device.switch_name) parts.push(`${h.device.switch_name}${h.device.port_name ? ':' + h.device.port_name : ''}`);
                        if (h.device.vlan) parts.push(`VLAN ${h.device.vlan}`);
                        return parts.join(' · ') || (h.device.device_type || '');
                    }
                    return '';
                };

                const nodes = r.hops.map((h, i) => {
                    const isLast = i === r.hops.length - 1;
                    const type   = nodeType(h);
                    const connector = i < r.hops.length - 1
                        ? `<div class="nt-path-connector ${type}"></div>` : '';
                    const sub = nodeSub(h);
                    return `
                        <div class="nt-path-node ${type}${isLast ? ' last' : ''}">
                            <div class="nt-path-dot">
                                <i class="fas ${nodeIcon(h)}"></i>
                            </div>
                            <div class="nt-path-info">
                                <span class="nt-path-hop">#${h.hop}</span>
                                <span class="nt-path-ip">${h.ip || '*'}</span>
                                <span class="nt-path-name">${this._esc(nodeLabel(h))}</span>
                                ${sub ? `<span class="nt-path-sub">${this._esc(sub)}</span>` : ''}
                                <span class="nt-path-rtt ${h.timeout ? 'nt-rtt-timeout' : ''}">${h.rtt}</span>
                            </div>
                        </div>
                        ${connector}`;
                }).join('');

                diagEl.innerHTML = `<div class="nt-path-chain">${nodes}</div>`;

                // ── Hop table ─────────────────────────────────────
                hopsEl.innerHTML = `
                    <table class="nt-hop-table">
                        <thead>
                            <tr><th>#</th><th>IP</th><th>Tipo</th><th>Nombre / Host</th><th>Detalle</th><th>RTT</th></tr>
                        </thead>
                        <tbody>
                            ${r.hops.map(h => {
                                const type   = h.timeout ? '—' : h.isPrivate
                                    ? '<span class="nt-badge-lan">LAN</span>'
                                    : '<span class="nt-badge-wan">WAN</span>';
                                const name   = h.isPrivate
                                    ? this._esc(h.device?.hostname || h.device?.vendor || '—')
                                    : this._esc(h.ptr || '—');
                                const detail = h.isPrivate && h.device
                                    ? this._esc([
                                        h.device.device_type,
                                        h.device.switch_name ? `${h.device.switch_name}${h.device.port_name ? ':' + h.device.port_name : ''}` : null,
                                        h.device.vlan ? `VLAN ${h.device.vlan}` : null,
                                      ].filter(Boolean).join(' · '))
                                    : '—';
                                return `
                                <tr class="${h.timeout ? 'nt-hop-timeout' : ''}">
                                    <td class="nt-hop-n">${h.hop}</td>
                                    <td class="nt-hop-ip">${h.ip || '*'}</td>
                                    <td>${type}</td>
                                    <td>${name}</td>
                                    <td class="nt-hop-isp">${detail}</td>
                                    <td class="nt-hop-rtt">${h.rtt}</td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>`;
            } catch (e) {
                hopsEl.innerHTML = `<p style="color:var(--accent-red)">${this._esc(e.message)}</p>`;
            } finally {
                btn.innerHTML = '<i class="fas fa-route"></i> Trazar';
                btn.disabled  = false;
            }
        });

        // Allow Enter key to trigger trace
        document.getElementById('nt-trace-target')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('btnTrace')?.click();
        });
    }

    _flag(code) {
        if (!code || code.length !== 2) return '';
        return String.fromCodePoint(...[...code.toUpperCase()].map(c => 0x1F1E0 + c.charCodeAt(0) - 65));
    }

    _esc(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ──────────────────────────────────────────────────────────
    //  SCAN HISTORY MODAL
    // ──────────────────────────────────────────────────────────

    async openScanHistory(api) {
        this.open('Historial de Escaneos', '<p style="padding:8px 0">Cargando…</p>');

        let rows;
        try {
            const r = await api.getScanHistory(120);
            rows = (r.history ?? []).reverse(); // cronológico asc para el chart
        } catch (e) {
            this._bodyEl.innerHTML = `<p style="color:var(--accent-red)">Error: ${this._esc(e.message)}</p>`;
            return;
        }

        if (!rows.length) {
            this._bodyEl.innerHTML = '<p style="opacity:.5;padding:8px 0">Sin historial aún — realizá un scan completo.</p>';
            return;
        }

        // ── Resumen ──────────────────────────────────────────
        const last    = rows[rows.length - 1];
        const maxOnline = Math.max(...rows.map(r => r.devices_online));
        const avgDur    = Math.round(rows.reduce((s, r) => s + r.duration_ms, 0) / rows.length / 1000);

        this._bodyEl.innerHTML = `
        <div class="sh-summary">
            <div class="sh-stat"><span class="sh-val">${rows.length}</span><span class="sh-lbl">Scans registrados</span></div>
            <div class="sh-stat"><span class="sh-val">${last.devices_online}</span><span class="sh-lbl">Online (último)</span></div>
            <div class="sh-stat"><span class="sh-val">${maxOnline}</span><span class="sh-lbl">Máx. online</span></div>
            <div class="sh-stat"><span class="sh-val">${avgDur}s</span><span class="sh-lbl">Duración media</span></div>
        </div>
        <div class="sh-chart-wrap"><canvas id="shChart"></canvas></div>
        <div class="sh-table-wrap">
            <table class="sh-table">
                <thead><tr>
                    <th>Hora</th><th>Online</th><th>Total</th><th>Nuevos</th><th>Duración</th>
                </tr></thead>
                <tbody>
                    ${[...rows].reverse().slice(0, 40).map(r => `
                    <tr>
                        <td class="sh-time">${r.scan_time?.slice(0, 16) ?? '—'}</td>
                        <td class="sh-online">${r.devices_online}</td>
                        <td>${r.devices_found}</td>
                        <td class="sh-new">${r.new_devices > 0 ? '+' + r.new_devices : '—'}</td>
                        <td class="sh-dur">${(r.duration_ms / 1000).toFixed(1)}s</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;

        // ── Chart ────────────────────────────────────────────
        if (typeof Chart === 'undefined') return;
        const labels  = rows.map(r => r.scan_time?.slice(11, 16) ?? '');
        const online  = rows.map(r => r.devices_online);
        const total   = rows.map(r => r.devices_found);

        new Chart(document.getElementById('shChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Online',
                        data: online,
                        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)',
                        borderWidth: 2, pointRadius: 2, fill: true, tension: 0.3,
                    },
                    {
                        label: 'Total',
                        data: total,
                        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.06)',
                        borderWidth: 1.5, pointRadius: 1.5, fill: true, tension: 0.3,
                        borderDash: [4, 3],
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    x: { ticks: { color: '#475569', maxTicksLimit: 12, font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.04)' } },
                    y: { ticks: { color: '#475569', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.04)' }, beginAtZero: true },
                },
            },
        });
    }

    // ──────────────────────────────────────────────────────────
    //  POWER MANAGEMENT MODAL
    // ──────────────────────────────────────────────────────────

    async openPowerPanel(api, devices) {
        let config = {};
        try {
            config = await api.getPowerConfig();
        } catch (e) { /* usar defaults */ }

        const onlineCount = devices.filter(d => d.is_online).length;
        const offlineCount = devices.filter(d => !d.is_online).length;

        const html = `
        <div class="power-panel">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                <div class="stat-mini">
                    <span style="color:var(--accent-green)"><i class="fas fa-circle"></i> ${onlineCount} Online</span>
                </div>
                <div class="stat-mini">
                    <span style="color:var(--accent-red)"><i class="fas fa-circle"></i> ${offlineCount} Offline</span>
                </div>
            </div>

            <h4 style="margin-bottom:12px"><i class="fas fa-filter"></i> Seleccionar equipos</h4>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <label>IP Inicio (rango)
                    <input type="text" id="pw_ip_start" placeholder="192.168.1.1">
                </label>
                <label>IP Fin (rango)
                    <input type="text" id="pw_ip_end" placeholder="192.168.1.254">
                </label>
            </div>
            <div class="form-grid" style="margin-top:8px;">
                <label>O filtrar por tipo
                    <select id="pw_device_type">
                        <option value="">— Todos —</option>
                        <option value="PC Windows">PC Windows</option>
                        <option value="Linux/Android">Linux/Android</option>
                        <option value="Router/AP">Router/AP</option>
                        <option value="Switch/Router">Switch/Router</option>
                        <option value="Servidor">Servidor</option>
                    </select>
                </label>
            </div>

            <div id="pw_preview" style="margin:16px 0; max-height:200px; overflow-y:auto; font-size:0.85rem; color:var(--text-secondary);">
                <p style="opacity:0.5">Ingresá un rango IP o seleccioná un tipo para previsualizar los equipos.</p>
            </div>

            <button class="btn btn-secondary" id="btnPwPreview" style="margin-bottom:16px;">
                <i class="fas fa-eye"></i> Previsualizar
            </button>

            <hr style="border-color:var(--glass-border); margin:16px 0">

            <h4 style="margin-bottom:12px"><i class="fas fa-bolt"></i> Acciones masivas</h4>

            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <label>Broadcast (WoL)
                    <input type="text" id="pw_broadcast" value="${config.broadcast_addr || '255.255.255.255'}" placeholder="255.255.255.255">
                </label>
                <label>Delay apagado (min)
                    <input type="number" id="pw_delay" value="${config.default_shutdown_delay || 0}" min="0" placeholder="0 = inmediato">
                </label>
            </div>

            <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                <button class="btn btn-success" id="btnPwWol">
                    <i class="fas fa-bolt"></i> WoL Masivo
                </button>
                <button class="btn btn-warning" id="btnPwReboot">
                    <i class="fas fa-redo"></i> Reinicio Masivo
                </button>
                <button class="btn btn-danger" id="btnPwShutdown">
                    <i class="fas fa-power-off"></i> Apagado Masivo
                </button>
            </div>

            <div id="pw_results" style="margin-top:16px; max-height:200px; overflow-y:auto; font-size:0.85rem;"></div>

            <hr style="border-color:var(--glass-border); margin:16px 0">

            <h4 style="margin-bottom:8px"><i class="fas fa-history"></i> Últimas acciones</h4>
            <div id="pw_log" style="max-height:150px; overflow-y:auto; font-size:0.8rem; color:var(--text-secondary);">
                <p style="opacity:0.5">Cargando log…</p>
            </div>
        </div>`;

        this.open('Power Management', html);

        // Cargar log
        this._loadPowerLog(api);

        // Preview
        document.getElementById('btnPwPreview')?.addEventListener('click', () => {
            this._previewPowerDevices(devices);
        });

        // WoL masivo
        document.getElementById('btnPwWol')?.addEventListener('click', async () => {
            const filter = this._getPowerFilter();
            if (!this._confirmPowerAction('WoL', filter)) return;
            const btn = document.getElementById('btnPwWol');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando…';
            try {
                const res = await api.wolBulk(filter);
                this._showPowerResults(res);
                this._toast.show(res.message, 'success');
            } catch (e) {
                this._toast.show(`Error: ${e.message}`, 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt"></i> WoL Masivo';
            this._loadPowerLog(api);
        });

        // Shutdown masivo
        document.getElementById('btnPwShutdown')?.addEventListener('click', async () => {
            const filter = this._getPowerFilter();
            filter.delay = parseInt(document.getElementById('pw_delay')?.value) || 0;
            if (!this._confirmPowerAction('APAGADO', filter)) return;
            const btn = document.getElementById('btnPwShutdown');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Apagando…';
            try {
                const res = await api.shutdownBulk(filter);
                this._showPowerResults(res);
                this._toast.show(res.message, 'success');
                setTimeout(() => window.app?.refreshDashboard(), 5000);
            } catch (e) {
                this._toast.show(`Error: ${e.message}`, 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-power-off"></i> Apagado Masivo';
            this._loadPowerLog(api);
        });

        // Reboot masivo
        document.getElementById('btnPwReboot')?.addEventListener('click', async () => {
            const filter = this._getPowerFilter();
            if (!this._confirmPowerAction('REINICIO', filter)) return;
            const btn = document.getElementById('btnPwReboot');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reiniciando…';
            try {
                const res = await api.rebootBulk(filter);
                this._showPowerResults(res);
                this._toast.show(res.message, 'success');
                setTimeout(() => window.app?.refreshDashboard(), 5000);
            } catch (e) {
                this._toast.show(`Error: ${e.message}`, 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo"></i> Reinicio Masivo';
            this._loadPowerLog(api);
        });
    }

    _getPowerFilter() {
        const filter = {};
        const ipStart = document.getElementById('pw_ip_start')?.value.trim();
        const ipEnd = document.getElementById('pw_ip_end')?.value.trim();
        const deviceType = document.getElementById('pw_device_type')?.value;
        const broadcast = document.getElementById('pw_broadcast')?.value.trim();

        if (ipStart && ipEnd) {
            filter.ip_start = ipStart;
            filter.ip_end = ipEnd;
        }
        if (deviceType) filter.device_type = deviceType;
        if (broadcast) filter.broadcast_addr = broadcast;

        return filter;
    }

    _confirmPowerAction(action, filter) {
        let scope = 'TODOS los dispositivos';
        if (filter.ip_start && filter.ip_end) {
            scope = `rango ${filter.ip_start} — ${filter.ip_end}`;
        } else if (filter.device_type) {
            scope = `tipo "${filter.device_type}"`;
        }
        return confirm(`¿Ejecutar ${action} masivo sobre ${scope}?`);
    }

    _previewPowerDevices(devices) {
        const filter = this._getPowerFilter();
        const container = document.getElementById('pw_preview');
        if (!container) return;

        let filtered = devices;
        if (filter.ip_start && filter.ip_end) {
            filtered = devices.filter(d => {
                if (!d.ip) return false;
                const parts = ip => ip.split('.').map(Number);
                const ipToInt = ip => { const p = parts(ip); return ((p[0]<<24)+(p[1]<<16)+(p[2]<<8)+p[3])>>>0; };
                try {
                    const ipInt = ipToInt(d.ip);
                    return ipInt >= ipToInt(filter.ip_start) && ipInt <= ipToInt(filter.ip_end);
                } catch { return false; }
            });
        }
        if (filter.device_type) {
            filtered = filtered.filter(d => (d.device_type || '').includes(filter.device_type));
        }

        if (filtered.length === 0) {
            container.innerHTML = '<p style="color:var(--accent-red)">No se encontraron equipos con esos filtros.</p>';
            return;
        }

        const rows = filtered.map(d => {
            const status = d.is_online
                ? '<span style="color:var(--accent-green)">●</span>'
                : '<span style="color:var(--accent-red)">●</span>';
            return `<div style="display:flex; gap:8px; padding:4px 0; border-bottom:1px solid var(--glass-border);">
                ${status}
                <span style="min-width:130px">${d.ip}</span>
                <span style="flex:1">${d.hostname || '—'}</span>
                <span style="opacity:0.5">${d.device_type || '—'}</span>
            </div>`;
        }).join('');

        container.innerHTML = `
            <p style="margin-bottom:8px"><strong>${filtered.length}</strong> equipo(s) encontrado(s):</p>
            ${rows}`;
    }

    _showPowerResults(res) {
        const container = document.getElementById('pw_results');
        if (!container || !res.results) return;

        const rows = res.results.map(r => {
            const icon = r.status === 'ok' ? '✅' :
                         r.status === 'skip' ? '⏭️' : '❌';
            return `<div style="padding:3px 0; font-size:0.85rem;">
                ${icon} ${r.hostname || r.ip} (${r.ip}) — ${r.status}${r.detail ? ': ' + r.detail : ''}
            </div>`;
        }).join('');

        container.innerHTML = `<p style="margin-bottom:6px; font-weight:600">${res.message}</p>${rows}`;
    }

    async _loadPowerLog(api) {
        const container = document.getElementById('pw_log');
        if (!container) return;

        try {
            const res = await api.getPowerLog(20);
            if (!res.logs || res.logs.length === 0) {
                container.innerHTML = '<p style="opacity:0.5">Sin acciones recientes.</p>';
                return;
            }
            container.innerHTML = res.logs.map(l => {
                const icon = l.status === 'ok' ? '✅' : '❌';
                const action = l.action.toUpperCase();
                return `<div style="padding:3px 0; border-bottom:1px solid var(--glass-border);">
                    ${icon} <strong>${action}</strong> ${l.device_name || l.ip} — ${l.timestamp}
                    ${l.detail ? `<span style="opacity:0.5">(${l.detail})</span>` : ''}
                </div>`;
            }).join('');
        } catch (e) {
            container.innerHTML = '<p style="color:var(--accent-red)">Error al cargar log.</p>';
        }
    }

    // ──────────────────────────────────────────────────────────
    //  POWER CONFIG MODAL (SSH settings)
    // ──────────────────────────────────────────────────────────

    async openPowerConfig(api) {
        let config = {};
        try {
            config = await api.getPowerConfig();
        } catch (e) { /* defaults */ }

        const html = `
        <form id="powerConfigForm" style="display:grid; gap:12px;">
            <div class="form-grid">
                <label>Usuario SSH
                    <input type="text" id="pcfg_ssh_user" value="${config.ssh_user || 'admin'}" placeholder="admin">
                </label>
                <label>Puerto SSH
                    <input type="number" id="pcfg_ssh_port" value="${config.ssh_port || 22}" min="1" max="65535">
                </label>
                <label>Puerto WoL (UDP)
                    <input type="number" id="pcfg_wol_port" value="${config.wol_port || 9}" min="1" max="65535">
                </label>
                <label>Broadcast Address
                    <input type="text" id="pcfg_broadcast" value="${config.broadcast_addr || '255.255.255.255'}" placeholder="255.255.255.255">
                </label>
                <label>Delay apagado por defecto (min)
                    <input type="number" id="pcfg_delay" value="${config.default_shutdown_delay || 0}" min="0">
                </label>
            </div>
            <p style="font-size:0.8rem; color:var(--text-secondary); margin-top:4px;">
                <i class="fas fa-info-circle"></i>
                Para operar via VPN, asegurate que el servidor tenga acceso a la red destino.
                Ajustá el broadcast al de la subnet remota (ej: 10.0.0.255).
            </p>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
                <button type="button" class="btn btn-secondary" data-action="closeModal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSavePowerConfig">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </form>`;

        this.open('Config — Power Management', html);

        document.getElementById('btnSavePowerConfig')?.addEventListener('click', async () => {
            const data = {
                ssh_user: document.getElementById('pcfg_ssh_user')?.value.trim(),
                ssh_port: parseInt(document.getElementById('pcfg_ssh_port')?.value) || 22,
                wol_port: parseInt(document.getElementById('pcfg_wol_port')?.value) || 9,
                broadcast_addr: document.getElementById('pcfg_broadcast')?.value.trim(),
                default_shutdown_delay: parseInt(document.getElementById('pcfg_delay')?.value) || 0,
            };

            try {
                await api.savePowerConfig(data);
                this._toast.show('Configuración de Power guardada', 'success');
                this.close();
            } catch (e) {
                this._toast.show('Error al guardar', 'error');
            }
        });
    }

    // ──────────────────────────────────────────────────────────
    //  TERMINAL MODAL  (placeholder — requires WebSocket work)
    // ──────────────────────────────────────────────────────────
    openTerminal() {
        this.open('NetMonitor Shell', `
        <div id="netmon-terminal" style="
            display:flex; flex-direction:column; height:420px;
            background:#0a0a0a; border-radius:6px; overflow:hidden;
            border:1px solid rgba(34,211,238,0.2); font-family:'Courier New',monospace;">

            <!-- Output -->
            <div id="term-output" style="
                flex:1; overflow-y:auto; padding:12px 14px;
                font-size:12.5px; line-height:1.6; color:#22d3ee;
                white-space:pre-wrap; word-break:break-word;">
                <span style="color:#00ff41">NetMonitor Shell v1.0</span> — Escribí <b>help</b> para ver los comandos disponibles.
            </div>

            <!-- Input -->
            <div style="
                display:flex; align-items:center; gap:8px;
                border-top:1px solid rgba(34,211,238,0.15);
                padding:8px 12px; background:#050505;">
                <span style="color:#ff00ff;font-size:12px;flex-shrink:0">netmon&gt;</span>
                <input id="term-input" type="text" autocomplete="off" spellcheck="false"
                    placeholder="help"
                    style="flex:1; background:transparent; border:none; outline:none;
                           color:#e2e8f0; font-family:'Courier New',monospace; font-size:12.5px;">
            </div>
        </div>`);

        // Inicializar la terminal una vez renderizado el DOM
        setTimeout(() => this._initTerminal(), 50);
    }

    /** Conecta el input/output de la terminal via Socket.io */
    _initTerminal() {
        const output = document.getElementById('term-output');
        const input  = document.getElementById('term-input');
        if (!output || !input) return;

        const socket = window._netmonSocket || (window._netmonSocket = io());
        const history = [];
        let histIdx = -1;

        // Función para agregar línea al output
        const print = (text, color = '#22d3ee') => {
            const line = document.createElement('div');
            line.style.color = color;
            line.style.whiteSpace = 'pre-wrap';
            line.textContent = text;
            output.appendChild(line);
            output.scrollTop = output.scrollHeight;
        };

        // Limpiar listeners anteriores (evita duplicados al reabrir el terminal)
        socket.removeAllListeners('terminal:output');
        socket.removeAllListeners('terminal:clear');

        // Recibir output del servidor
        socket.on('terminal:output', ({ text }) => print(text));
        socket.on('terminal:clear',  () => { output.innerHTML = ''; });

        // Enviar input al servidor
        const send = () => {
            const raw = input.value.trim();
            if (!raw) return;
            print(`netmon> ${raw}`, '#ff00ff');
            history.unshift(raw);
            histIdx = -1;
            input.value = '';
            socket.emit('terminal:input', { input: raw });
        };

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                send();
            } else if (e.key === 'ArrowUp') {
                if (histIdx < history.length - 1) input.value = history[++histIdx];
                e.preventDefault();
            } else if (e.key === 'ArrowDown') {
                if (histIdx > 0) input.value = history[--histIdx];
                else { histIdx = -1; input.value = ''; }
                e.preventDefault();
            }
        });

        input.focus();
    }
}
