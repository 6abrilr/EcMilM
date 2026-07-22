/**
 * API Routes — Bandwidth Summary
 *
 * GET /api/bandwidth/summary
 *   Aggregates traffic data from all switches:
 *   - Total RX/TX across the network
 *   - Per-device RX/TX (matched via connections table)
 *   - Per-switch utilization
 *   - Top consumers list
 */

const express   = require('express');
const router    = express.Router();
const { getDb } = require('../db/database');

function calcRate(samples) {
    if (samples.length < 2) return { rxBps: 0, txBps: 0 };
    const [latest, prev] = samples;
    const dtSec = (new Date(latest.ts) - new Date(prev.ts)) / 1000;
    if (dtSec <= 0) return { rxBps: 0, txBps: 0 };

    const wrap = (a, b) => (a >= b ? a - b : 0xFFFFFFFF - b + a);
    return {
        rxBps: Math.max(0, Math.round((wrap(latest.in_octets,  prev.in_octets)  * 8) / dtSec)),
        txBps: Math.max(0, Math.round((wrap(latest.out_octets, prev.out_octets) * 8) / dtSec)),
    };
}

function fmtBps(bps) {
    if (!bps) return '0 bps';
    if (bps >= 1e9) return `${(bps / 1e9).toFixed(2)} Gbps`;
    if (bps >= 1e6) return `${(bps / 1e6).toFixed(2)} Mbps`;
    if (bps >= 1e3) return `${(bps / 1e3).toFixed(1)} Kbps`;
    return `${bps} bps`;
}

// GET /api/bandwidth/summary
router.get('/summary', (req, res) => {
    const db = getDb();

    try {
        const switches = db.prepare('SELECT id, name, ip FROM switches WHERE enabled = 1').all();

        let totalRxBps = 0;
        let totalTxBps = 0;
        const switchStats = [];
        const portRates   = []; // flat list of { switchId, portIndex, rxBps, txBps, speedBps }

        for (const sw of switches) {
            // Latest 2 samples per port
            const rows = db.prepare(`
                SELECT port_index, port_name, port_status, speed_bps, in_octets, out_octets, ts
                FROM port_traffic
                WHERE switch_id = ?
                ORDER BY port_index ASC, id DESC
            `).all(sw.id);

            const byPort = {};
            for (const r of rows) {
                if (!byPort[r.port_index]) byPort[r.port_index] = [];
                if (byPort[r.port_index].length < 2) byPort[r.port_index].push(r);
            }

            let swRx = 0, swTx = 0, upPorts = 0, totalPorts = 0;

            for (const samples of Object.values(byPort)) {
                const latest = samples[0];
                if (latest.port_status === 2) { totalPorts++; continue; } // down
                totalPorts++;
                if (latest.port_status === 1) upPorts++;

                const { rxBps, txBps } = calcRate(samples);
                swRx += rxBps;
                swTx += txBps;
                portRates.push({
                    switchId:   sw.id,
                    switchName: sw.name,
                    portIndex:  latest.port_index,
                    portName:   latest.port_name,
                    portStatus: latest.port_status,
                    speedBps:   latest.speed_bps ?? 0,
                    rxBps,
                    txBps,
                });
            }

            totalRxBps += swRx;
            totalTxBps += swTx;
            switchStats.push({
                id:         sw.id,
                name:       sw.name,
                ip:         sw.ip,
                rxBps:      swRx,
                txBps:      swTx,
                rxFmt:      fmtBps(swRx),
                txFmt:      fmtBps(swTx),
                upPorts,
                totalPorts,
                lastSample: db.prepare('SELECT ts FROM port_traffic WHERE switch_id = ? ORDER BY id DESC LIMIT 1').get(sw.id)?.ts ?? null,
            });
        }

        // Match port rates to devices via connections table
        const connections = db.prepare(`
            SELECT c.port_index, c.switch_ip,
                   d.id, d.hostname, d.ip, d.mac, d.vendor, d.device_type, d.is_online, d.is_critical
            FROM connections c
            JOIN devices d ON d.id = c.device_id
        `).all();

        const switchIpToId = {};
        for (const sw of switches) switchIpToId[sw.ip] = sw.id;

        const deviceTraffic = {};
        for (const port of portRates) {
            const conn = connections.find(
                c => switchIpToId[c.switch_ip] === port.switchId &&
                     String(c.port_index) === String(port.portIndex)
            );
            if (!conn) continue;

            const key = conn.id;
            if (!deviceTraffic[key]) {
                deviceTraffic[key] = {
                    id:          conn.id,
                    hostname:    conn.hostname || conn.ip,
                    ip:          conn.ip,
                    mac:         conn.mac,
                    vendor:      conn.vendor,
                    device_type: conn.device_type,
                    is_online:   conn.is_online,
                    is_critical: conn.is_critical,
                    rxBps:       0,
                    txBps:       0,
                };
            }
            deviceTraffic[key].rxBps += port.rxBps;
            deviceTraffic[key].txBps += port.txBps;
        }

        const devices = Object.values(deviceTraffic).map(d => ({
            ...d,
            rxFmt:   fmtBps(d.rxBps),
            txFmt:   fmtBps(d.txBps),
            totalBps: d.rxBps + d.txBps,
        })).sort((a, b) => b.totalBps - a.totalBps);

        // Top 10 consumers
        const topConsumers = devices.slice(0, 10);

        // Utilization % per switch (vs max theoretical bandwidth)
        for (const sw of switchStats) {
            const swPorts = portRates.filter(p => p.switchId === sw.id && p.speedBps > 0);
            const maxBps  = swPorts.reduce((sum, p) => sum + p.speedBps, 0);
            sw.utilizationPct = maxBps > 0
                ? Math.min(100, Math.round(((sw.rxBps + sw.txBps) / maxBps) * 100))
                : null;
        }

        res.json({
            success: true,
            updatedAt: new Date().toISOString(),
            network: {
                totalRxBps,
                totalTxBps,
                totalBps:   totalRxBps + totalTxBps,
                rxFmt:      fmtBps(totalRxBps),
                txFmt:      fmtBps(totalTxBps),
                totalFmt:   fmtBps(totalRxBps + totalTxBps),
            },
            switches:     switchStats,
            topConsumers,
            allDevices:   devices,
            hasSwitches:  switches.length > 0,
            hasData:      portRates.length > 0,
        });

    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

module.exports = router;
