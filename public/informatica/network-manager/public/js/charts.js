/**
 * ============================================================
 *  NetMonitor Stealth — charts.js
 *  ChartManager: owns all Chart.js instances.
 *
 *  Charts rendered:
 *    - #osChart     → OS distribution doughnut  (with legend)
 *    - #vendorChart → Vendor distribution doughnut (with legend)
 *
 *  Design decisions:
 *    - Always destroys previous Chart instance before re-creating
 *      (avoids "Canvas is already in use" errors on refresh).
 *    - Slices beyond MAX_SLICES are grouped as "Otros" to avoid
 *      a legend with 30 entries.
 *    - Colours are deterministic per-label so they don't jump
 *      around when the dataset changes.
 * ============================================================
 */

const MAX_SLICES = 8;

// Colour palette — extend if needed, they cycle gracefully
const PALETTE = [
    '#22d3ee', '#3b82f6', '#10b981', '#f59e0b',
    '#ef4444', '#a78bfa', '#f472b6', '#34d399',
    '#fb923c', '#60a5fa', '#facc15', '#4ade80',
];

class ChartManager {
    constructor() {
        // Map of canvasId → Chart instance
        this._charts = {};
    }

    // ──────────────────────────────────────────────────────────
    //  PUBLIC: update all charts from a device array
    // ──────────────────────────────────────────────────────────
    update(devices = []) {
        const vendorMap = this._countByKey(devices, d => d.vendor || 'Desconocido');
        this._render('vendorChart', vendorMap);
    }

    /** Render traffic-by-device donut. Called by BandwidthPanel. */
    renderTrafficChart(trafficMap) {
        this._render('trafficChart', trafficMap, (label, val) => {
            const f = v => v >= 1e6 ? `${(v/1e6).toFixed(1)}M` : v >= 1e3 ? `${(v/1e3).toFixed(0)}K` : `${v}`;
            return ` ${label}: ${f(val)} bps`;
        });
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: count occurrences by a key function
    // ──────────────────────────────────────────────────────────
    _countByKey(devices, keyFn) {
        const map = {};
        devices.forEach(d => {
            const key = keyFn(d);
            map[key] = (map[key] ?? 0) + 1;
        });
        return map;
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: collapse long tail into "Otros"
    // ──────────────────────────────────────────────────────────
    _consolidate(rawMap) {
        const sorted = Object.entries(rawMap)
            .sort(([, a], [, b]) => b - a); // descending by count

        if (sorted.length <= MAX_SLICES) return rawMap;

        const top    = sorted.slice(0, MAX_SLICES - 1);
        const others = sorted.slice(MAX_SLICES - 1).reduce((sum, [, v]) => sum + v, 0);

        const result = Object.fromEntries(top);
        if (others > 0) result['Otros'] = others;
        return result;
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: assign stable colours by label hash
    // ──────────────────────────────────────────────────────────
    _colorForLabel(label) {
        let hash = 0;
        for (let i = 0; i < label.length; i++) {
            hash = (hash * 31 + label.charCodeAt(i)) >>> 0;
        }
        return PALETTE[hash % PALETTE.length];
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE: create or update a doughnut chart
    // ──────────────────────────────────────────────────────────
    _render(canvasId, rawMap, tooltipFn = null) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        // Destroy existing instance to avoid canvas reuse errors
        if (this._charts[canvasId]) {
            this._charts[canvasId].destroy();
            delete this._charts[canvasId];
        }

        const map    = this._consolidate(rawMap);
        const labels = Object.keys(map);
        const data   = Object.values(map);
        const colors = labels.map(l => this._colorForLabel(l));
        const total  = data.reduce((a, b) => a + b, 0);

        if (labels.length === 0) return;

        // Custom legend aside
        this._renderLegend(canvasId, labels, data, colors, total);

        this._charts[canvasId] = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor:  colors,
                    borderColor:      colors.map(c => c + '33'),
                    borderWidth:      1,
                    hoverOffset:      6,
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: tooltipFn ?? function(ctx) {
                                const t = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = t ? Math.round(ctx.parsed / t * 100) : 0;
                                return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                            },
                        },
                    },
                },
            },
            plugins: [],
        });
    }

    _renderLegend(canvasId, labels, data, colors, total) {
        const el = document.getElementById(canvasId + 'Legend');
        if (!el) return;

        el.innerHTML = labels.map((label, i) => {
            const pct = total ? Math.round(data[i] / total * 100) : 0;
            return `<div class="chart-legend-item">
                <span class="chart-legend-dot" style="background:${colors[i]}"></span>
                <span class="chart-legend-name" title="${label}">${label}</span>
                <span class="chart-legend-count">${data[i]} <small style="color:var(--text-muted)">(${pct}%)</small></span>
            </div>`;
        }).join('');
    }
}
