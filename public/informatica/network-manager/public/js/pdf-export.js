/**
 * PDF Export — genera un reporte imprimible del inventario de red.
 *
 * Usa window.print() en una ventana nueva con HTML auto-contenido.
 * Sin dependencias externas — el browser genera el PDF.
 *
 * Uso:
 *   PdfExport.exportInventory(devices, stats);
 */

const PdfExport = (() => {

    // ── Helpers ──────────────────────────────────────────────────

    function esc(str) {
        if (str == null || str === '') return '—';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fmtDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString('es-AR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        } catch { return iso; }
    }

    function statusDot(isOnline) {
        return isOnline
            ? '<span style="color:#10b981;font-size:10px">●</span>'
            : '<span style="color:#ef4444;font-size:10px">●</span>';
    }

    // ── HTML del reporte ─────────────────────────────────────────

    function buildHtml(devices, stats) {
        const now    = new Date().toLocaleString('es-AR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
        const online  = devices.filter(d => d.is_online).length;
        const offline = devices.length - online;
        const newToday = stats?.newToday ?? devices.filter(d => {
            if (!d.first_seen) return false;
            return new Date(d.first_seen).toDateString() === new Date().toDateString();
        }).length;

        const rows = devices.map((d, i) => `
            <tr class="${i % 2 === 0 ? 'even' : ''}">
                <td class="center">${statusDot(d.is_online)} ${d.is_online ? 'Online' : 'Offline'}</td>
                <td>
                    <strong>${esc(d.hostname || '—')}</strong>
                    ${d.mac ? `<br><small>${esc(d.mac)}</small>` : ''}
                </td>
                <td class="mono">${esc(d.ip)}</td>
                <td>${esc(d.vendor)}</td>
                <td>${esc(d.device_type)}</td>
                <td>${esc(d.os)}</td>
                <td>${d.switch_name ? `${esc(d.switch_name)}${d.port_name ? ':' + esc(d.port_name) : ''}` : '—'}</td>
                <td class="small">${fmtDate(d.last_seen)}</td>
            </tr>`).join('');

        return `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NetMonitor — Inventario ${now}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, 'Segoe UI', Arial, sans-serif;
            font-size: 11px; color: #1e293b; background: #fff;
            padding: 20px 24px;
        }

        /* ── Header ── */
        .report-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 2px solid #0ea5e9; padding-bottom: 12px; margin-bottom: 16px;
        }
        .report-title { font-size: 20px; font-weight: 700; color: #0f172a; }
        .report-subtitle { font-size: 11px; color: #64748b; margin-top: 2px; }
        .report-meta { text-align: right; font-size: 10px; color: #64748b; line-height: 1.6; }

        /* ── Stats ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 10px; margin-bottom: 16px;
        }
        .stat-box {
            border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 10px 12px; text-align: center;
        }
        .stat-box .val { font-size: 22px; font-weight: 700; color: #0ea5e9; }
        .stat-box .lbl { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
        .stat-box.online  .val { color: #10b981; }
        .stat-box.offline .val { color: #ef4444; }
        .stat-box.new     .val { color: #f59e0b; }

        /* ── Table ── */
        .section-title {
            font-size: 12px; font-weight: 600; color: #0f172a;
            margin-bottom: 8px; padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #0f172a; color: #fff; }
        thead th {
            padding: 6px 8px; text-align: left;
            font-size: 9px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .5px;
        }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        tbody tr.even td { background: #f8fafc; }
        tbody tr:hover td { background: #eff6ff; }
        .center { text-align: center; }
        .mono   { font-family: 'Courier New', monospace; font-size: 10px; }
        .small  { font-size: 9px; color: #64748b; }
        small   { font-size: 9px; color: #94a3b8; }

        /* ── Footer ── */
        .report-footer {
            margin-top: 20px; padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px; color: #94a3b8;
            display: flex; justify-content: space-between;
        }

        /* ── Print ── */
        @media print {
            body { padding: 10px 14px; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            .report-header { page-break-after: avoid; }
            .stats-grid    { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="report-header">
        <div>
            <div class="report-title">⊹ NetMonitor — Inventario de Red</div>
            <div class="report-subtitle">Reporte generado automáticamente por NetMonitor Stealth</div>
        </div>
        <div class="report-meta">
            <div><strong>Fecha:</strong> ${now}</div>
            <div><strong>Total dispositivos:</strong> ${devices.length}</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="val">${devices.length}</div>
            <div class="lbl">Total equipos</div>
        </div>
        <div class="stat-box online">
            <div class="val">${online}</div>
            <div class="lbl">Online</div>
        </div>
        <div class="stat-box offline">
            <div class="val">${offline}</div>
            <div class="lbl">Offline</div>
        </div>
        <div class="stat-box new">
            <div class="val">${newToday}</div>
            <div class="lbl">Nuevos hoy</div>
        </div>
    </div>

    <div class="section-title">Dispositivos (${devices.length})</div>
    <table>
        <thead>
            <tr>
                <th>Estado</th>
                <th>Hostname / MAC</th>
                <th>IP</th>
                <th>Vendor</th>
                <th>Tipo</th>
                <th>OS</th>
                <th>Switch:Boca</th>
                <th>Último visto</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
    </table>

    <div class="report-footer">
        <span>NetMonitor Stealth — Reporte de inventario</span>
        <span>${now} · ${devices.length} dispositivos</span>
    </div>

    <script>
        window.onload = () => {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>
</body>
</html>`;
    }

    // ── API pública ──────────────────────────────────────────────

    /**
     * Abre una ventana con el reporte y dispara el diálogo de impresión/PDF.
     * @param {Array}  devices  - lista de dispositivos del dashboard
     * @param {Object} [stats]  - stats opcionales (newToday, etc.)
     */
    function exportInventory(devices, stats = {}) {
        if (!devices?.length) {
            alert('No hay dispositivos para exportar.');
            return;
        }
        const html = buildHtml(devices, stats);
        const win  = window.open('', '_blank', 'width=1000,height=700');
        if (!win) {
            alert('El navegador bloqueó la ventana emergente. Permitila para exportar.');
            return;
        }
        win.document.write(html);
        win.document.close();
    }

    return { exportInventory };
})();
