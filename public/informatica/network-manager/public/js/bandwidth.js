/**
 * ============================================================
 *  NetMonitor Stealth — bandwidth.js
 *  BandwidthPanel: network-wide bandwidth dashboard panel.
 *
 *  Renders:
 *    - Total RX / TX / combined counters
 *    - Per-switch utilization bars
 *    - Top consumers list with mini sparkbar
 *    - Traffic-by-device donut chart (trafficChart)
 *
 *  Auto-refreshes every POLL_MS via setInterval.
 *  Also called on dashboard:refresh socket event.
 * ============================================================
 */

const POLL_MS    = 30_000; // 30 s
const HISTORY_N  = 60;    // keep last 60 samples (~5 min at 5s interval)

class BandwidthPanel {
    /**
     * @param {Api}          api
     * @param {ChartManager} charts
     */
    constructor(api, charts) {
        this._api       = api;
        this._charts    = charts;
        this._timer     = null;
        this._data      = null;
        this._latHist   = [];  // { t, ms }
        this._bwHist    = [];  // { t, rx, tx }
        this._latChart  = null;
        this._bwChart   = null;
    }

    // ──────────────────────────────────────────────────────────
    //  PUBLIC
    // ──────────────────────────────────────────────────────────

    start(socket) {
        this.refresh();
        this._timer = setInterval(() => this.refresh(), POLL_MS);

        document.getElementById('btnRefreshBw')
            ?.addEventListener('click', () => this.refresh());

        // WAN real-time via socket (5s from wan-monitor.js)
        socket?.on('wan:update', (data) => this._renderWan(data));

        this._initWanCharts();
    }

    stop() {
        clearInterval(this._timer);
        this._timer = null;
    }

    async refresh() {
        const btn = document.getElementById('btnRefreshBw');
        if (btn) btn.querySelector('i')?.classList.add('fa-spin');

        try {
            const res = await this._api.getBandwidthSummary();
            this._data = res;
            this._render(res);
        } catch (e) {
            console.warn('[BandwidthPanel] Error:', e.message);
        } finally {
            if (btn) btn.querySelector('i')?.classList.remove('fa-spin');
        }
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE — RENDER
    // ──────────────────────────────────────────────────────────

    _render(data) {
        const noData = document.getElementById('bwNoData');
        const hasData = data.hasSwitches && data.hasData;

        // Toggle no-data state
        noData?.classList.toggle('bw-nodata--hidden', hasData);

        // Updated timestamp
        const upEl = document.getElementById('bwUpdatedAt');
        if (upEl && data.updatedAt) {
            const t = new Date(data.updatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            upEl.textContent = `actualizado ${t}`;
        }

        if (!hasData) return;

        // ── Totals ────────────────────────────────────────────
        this._setText('bwRx',    data.network.rxFmt);
        this._setText('bwTx',    data.network.txFmt);
        this._setText('bwTotal', data.network.totalFmt);

        // ── Switch utilization bars ───────────────────────────
        const swEl = document.getElementById('bwSwitches');
        if (swEl) {
            swEl.innerHTML = data.switches.map(sw => {
                const pct     = sw.utilizationPct ?? 0;
                const warnCls = pct >= 70 ? 'warn' : '';
                return `
                <div class="bw-switch-row">
                    <div class="bw-switch-header">
                        <span class="bw-switch-name">${this._esc(sw.name)}</span>
                        <span>▼${sw.rxFmt} ▲${sw.txFmt}
                            ${sw.utilizationPct != null ? `<span style="color:var(--text-muted)"> · ${pct}%</span>` : ''}
                        </span>
                    </div>
                    <div class="bw-bar-track">
                        <div class="bw-bar-fill ${warnCls}" style="width:${pct}%"></div>
                    </div>
                    <div style="font-size:.68rem; color:var(--text-muted)">${sw.upPorts}/${sw.totalPorts} puertos activos</div>
                </div>`;
            }).join('');
        }

        // ── Top consumers ─────────────────────────────────────
        const listEl = document.getElementById('bwTopList');
        if (listEl) {
            const maxBps = data.topConsumers[0]?.totalBps || 1;
            listEl.innerHTML = data.topConsumers.length
                ? data.topConsumers.map(d => {
                    const pct      = Math.round((d.totalBps / maxBps) * 100);
                    const critCls  = d.is_critical ? 'critical' : '';
                    const critIcon = d.is_critical ? '🚨 ' : '';
                    return `
                    <div class="bw-consumer-row">
                        <div>
                            <div class="bw-consumer-name ${critCls}">${critIcon}${this._esc(d.hostname)}</div>
                            <div class="bw-consumer-ip">${this._esc(d.ip)}</div>
                        </div>
                        <div class="bw-consumer-stats">
                            <span class="bw-consumer-rx">▼${d.rxFmt}</span>
                            <span class="bw-consumer-tx">▲${d.txFmt}</span>
                        </div>
                        <div class="bw-consumer-bar">
                            <div class="bw-consumer-bar-fill" style="width:${pct}%"></div>
                        </div>
                    </div>`;
                }).join('')
                : '<div style="font-size:.78rem; color:var(--text-muted); padding:8px 0">Sin dispositivos con tráfico identificado</div>';
        }

        // ── Traffic donut chart ───────────────────────────────
        if (data.allDevices.length) {
            const trafficMap = {};
            for (const d of data.allDevices.slice(0, 10)) {
                if (d.totalBps > 0) trafficMap[d.hostname] = d.totalBps;
            }
            if (Object.keys(trafficMap).length) {
                this._charts.renderTrafficChart(trafficMap);
            }
        }
    }

    _setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    _renderWan(data) {
        const dot   = document.getElementById('bwWanDot');
        const label = document.getElementById('bwWanLabel');
        const lat   = document.getElementById('bwWanLat');
        const loss  = document.getElementById('bwWanLoss');
        const sl    = document.getElementById('bwWanStarlink');
        if (!dot) return;

        if (data.error) {
            dot.className     = 'bw-wan-dot offline';
            label.textContent = data.error;
            lat.textContent   = '';
            if (loss) loss.textContent = '';
            sl.textContent    = '';
            return;
        }

        // Dot + label
        dot.className = 'bw-wan-dot online';
        const rateHtml = data.rxBps != null
            ? `<span class="bw-wan-rate"><span class="rx">▼${data.rxFmt}</span><span class="tx">▲${data.txFmt}</span></span>`
            : '';
        label.innerHTML = `${this._esc(data.iface)} <span style="color:var(--text-muted);font-weight:400">via ${this._esc(data.gateway)}</span> ${rateHtml}`;

        // Latency with color coding
        if (data.latencyMs != null) {
            const ms  = data.latencyMs;
            const cls = ms < 30 ? 'good' : ms < 80 ? 'warn' : 'bad';
            lat.className   = `bw-wan-lat ${cls}`;
            lat.textContent = `${ms.toFixed(1)} ms`;
        } else {
            lat.className   = 'bw-wan-lat';
            lat.textContent = '';
        }

        // Packet loss
        if (loss && data.packetLoss != null) {
            const pct = data.packetLoss;
            loss.className   = `bw-wan-loss${pct >= 10 ? ' bad' : pct > 0 ? ' warn' : ''}`;
            loss.textContent = pct > 0 ? `loss:${pct}%` : '';
        }

        // Starlink extras
        if (data.isStarlink && data.starlink) {
            const s      = data.starlink;
            const obs    = s.obstructionPct != null ? ` obst:${s.obstructionPct}%` : '';
            const alerts = s.alerts?.length ? ` ⚠️${s.alerts.length}` : '';
            sl.textContent = `🛰 Starlink${obs}${alerts}`;
            sl.title       = s.alerts?.join(', ') || 'Starlink OK';
        } else if (data.isStarlink) {
            sl.textContent = '🛰 Starlink';
        } else {
            sl.textContent = '';
        }

        // Update historical charts
        const t = new Date(data.updatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        this._pushHistory(this._latHist, t, data.latencyMs ?? null);
        this._pushHistory(this._bwHist,  t, { rx: (data.rxBps ?? 0) / 1000, tx: (data.txBps ?? 0) / 1000 });
        this._updateWanCharts();
    }

    _pushHistory(arr, t, val) {
        arr.push({ t, val });
        if (arr.length > HISTORY_N) arr.shift();
    }

    _initWanCharts() {
        if (typeof Chart === 'undefined') return;

        const latCtx = document.getElementById('wanLatencyChart')?.getContext('2d');
        const bwCtx  = document.getElementById('wanThroughputChart')?.getContext('2d');

        const commonOpts = {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
            scales: {
                x: { display: false },
                y: { display: true, ticks: { color: '#6b7280', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
            },
        };

        if (latCtx) {
            this._latChart = new Chart(latCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Latencia (ms)',
                        data: [],
                        borderColor: '#22d3ee',
                        backgroundColor: 'rgba(34,211,238,0.1)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.3,
                        fill: true,
                        spanGaps: true,
                    }],
                },
                options: { ...commonOpts, scales: { ...commonOpts.scales, y: { ...commonOpts.scales.y, min: 0 } } },
            });
        }

        if (bwCtx) {
            this._bwChart = new Chart(bwCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: '▼ RX (Kbps)',
                            data: [],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            borderWidth: 1.5,
                            pointRadius: 0,
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: '▲ TX (Kbps)',
                            data: [],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245,158,11,0.1)',
                            borderWidth: 1.5,
                            pointRadius: 0,
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: { ...commonOpts, plugins: { ...commonOpts.plugins, legend: { display: true, labels: { color: '#9ca3af', font: { size: 10 }, boxWidth: 12 } } } },
            });
        }
    }

    _updateWanCharts() {
        if (this._latChart && this._latHist.length) {
            this._latChart.data.labels              = this._latHist.map(p => p.t);
            this._latChart.data.datasets[0].data    = this._latHist.map(p => p.val);
            this._latChart.update('none');
        }
        if (this._bwChart && this._bwHist.length) {
            this._bwChart.data.labels              = this._bwHist.map(p => p.t);
            this._bwChart.data.datasets[0].data    = this._bwHist.map(p => p.val?.rx ?? 0);
            this._bwChart.data.datasets[1].data    = this._bwHist.map(p => p.val?.tx ?? 0);
            this._bwChart.update('none');
        }
    }

    _esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str ?? '');
        return d.innerHTML;
    }
}
