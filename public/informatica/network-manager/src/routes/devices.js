/**
 * API Routes — Dispositivos
 */
const express       = require('express');
const router        = express.Router();
const geoip         = require('geoip-lite');
const mysql         = require('mysql2/promise');
const { getDb }     = require('../db/database');
const { logEvent }  = require('../db/device-events');

// ── helpers ───────────────────────────────────────────────────────────────────

const PRIVATE_RE = /^(10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|127\.|169\.254\.|::1|fc|fd)/;

function isPrivate(ip) { return PRIVATE_RE.test(ip); }

const COUNTRY_FLAGS = {
    AR:'🇦🇷', US:'🇺🇸', BR:'🇧🇷', CL:'🇨🇱', UY:'🇺🇾', PY:'🇵🇾', BO:'🇧🇴', PE:'🇵🇪',
    CO:'🇨🇴', VE:'🇻🇪', EC:'🇪🇨', MX:'🇲🇽', ES:'🇪🇸', DE:'🇩🇪', FR:'🇫🇷', GB:'🇬🇧',
    CN:'🇨🇳', RU:'🇷🇺', JP:'🇯🇵', KR:'🇰🇷', IN:'🇮🇳', AU:'🇦🇺', CA:'🇨🇦', NL:'🇳🇱',
    SE:'🇸🇪', NO:'🇳🇴', FI:'🇫🇮', IT:'🇮🇹', PL:'🇵🇱', PT:'🇵🇹', UA:'🇺🇦', ZA:'🇿🇦',
};

function flagFor(cc) { return COUNTRY_FLAGS[cc] ?? '🌐'; }

let eaPool = null;

function getEaPool() {
    if (!eaPool) {
        eaPool = mysql.createPool({
            host: process.env.EA_DB_HOST || '127.0.0.1',
            port: Number(process.env.EA_DB_PORT || 3306),
            user: process.env.EA_DB_USER || 'root',
            password: process.env.EA_DB_PASS || '',
            database: process.env.EA_DB_NAME || 'unidad',
            waitForConnections: true,
            connectionLimit: 4,
            charset: 'utf8mb4',
        });
    }
    return eaPool;
}

function normalizeMac(value) {
    const hex = String(value || '').toLowerCase().replace(/[^0-9a-f]/g, '');
    if (hex.length < 12) return '';
    return hex.slice(0, 12).replace(/(.{2})(?=.)/g, '$1:');
}

function normalizeName(value) {
    return String(value || '').trim().toUpperCase().replace(/\..*$/, '').replace(/\s+/g, '');
}

function displayOwner(row) {
    if (row.propiedad === 'personal' && row.propietario_nombre) {
        return [row.propietario_nombre, row.propietario_dni ? `DNI ${row.propietario_dni}` : '']
            .filter(Boolean)
            .join(' - ');
    }
    return row.asignado_nombre || row.usuario_asignado || '';
}

async function loadInventoryIndex() {
    try {
        const [rows] = await getEaPool().query(`
            SELECT
                a.id, a.etiqueta, a.descripcion, a.dispositivo_tipo, a.equipo_nombre,
                a.marca, a.modelo, a.nro_serie, a.mac, a.ip, a.usuario_asignado,
                a.propiedad, a.propietario_nombre, a.propietario_dni, a.autorizacion_estado,
                COALESCE(pudi.nombre, adi.nombre, d.nombre, '') AS area_nombre,
                pu.apellido_nombre AS asignado_nombre,
                pu.dni AS asignado_dni
            FROM it_activos a
            LEFT JOIN personal_unidad pu ON pu.id = a.asignado_personal_id
            LEFT JOIN destino_interno adi ON adi.id = a.area_id
            LEFT JOIN destino_interno pudi ON pudi.id = pu.destino_interno
            LEFT JOIN destino d ON d.id = a.area_id
            WHERE a.categoria = 'informatica'
              AND a.condicion <> 'deposito'
        `);

        const byMac = new Map();
        const byIp = new Map();
        const byName = new Map();

        for (const row of rows) {
            row.inventory_display_name = row.equipo_nombre || row.etiqueta || row.descripcion || '';
            row.inventory_owner_display = displayOwner(row);

            const mac = normalizeMac(row.mac);
            const name = normalizeName(row.equipo_nombre || row.etiqueta || row.descripcion);
            if (mac) byMac.set(mac, row);
            if (row.ip) byIp.set(String(row.ip).trim(), row);
            if (name) byName.set(name, row);
        }

        return { byMac, byIp, byName };
    } catch (err) {
        console.error('[EA Inventory] No se pudo cargar inventario:', err.message);
        return { byMac: new Map(), byIp: new Map(), byName: new Map(), error: err.message };
    }
}

function applyInventoryMatch(device, index) {
    const hit = [
        ['MAC', index.byMac.get(normalizeMac(device.mac))],
        ['IP', index.byIp.get(String(device.ip || '').trim())],
        ['NOMBRE', index.byName.get(normalizeName(device.custom_name || device.hostname))],
    ].find(([, row]) => row);

    device.inventory_match = Boolean(hit);
    device.inventory_match_by = hit ? hit[0] : '';
    device.inventory_error = index.error || '';

    if (!hit) return device;

    const row = hit[1];
    device.inventory_id = row.id;
    device.inventory_name = row.inventory_display_name;
    device.inventory_display_name = row.inventory_display_name;
    device.inventory_type = row.dispositivo_tipo || '';
    device.inventory_brand_model = [row.marca, row.modelo].filter(Boolean).join(' ');
    device.inventory_serial = row.nro_serie || '';
    device.inventory_area = row.area_nombre || '';
    device.inventory_owner_display = row.inventory_owner_display;
    device.inventory_propiedad = row.propiedad || '';
    device.inventory_auth_status = row.autorizacion_estado || '';

    return device;
}

// GET /api/devices — Lista todos los dispositivos con sus conexiones y servicios
router.get('/', async (req, res) => {
    const db = getDb();
    const { search, status, vendor, switch_name } = req.query;

    let sql = `
    SELECT 
      d.*,
      c.switch_name, c.switch_ip, c.port_name, c.port_index, c.vlan, c.speed,
      s.has_data_service, s.rag_origin, s.provider, s.service_type, s.details as service_details
    FROM devices d
    LEFT JOIN connections c ON c.device_id = d.id
    LEFT JOIN services s ON s.device_id = d.id
    WHERE 1=1
  `;
    const params = [];

    if (search) {
        sql += ` AND (d.hostname LIKE ? OR d.ip LIKE ? OR d.mac LIKE ? OR d.vendor LIKE ? OR d.notes LIKE ?)`;
        const term = `%${search}%`;
        params.push(term, term, term, term, term);
    }

    if (status === 'online') {
        sql += ` AND d.is_online = 1`;
    } else if (status === 'offline') {
        sql += ` AND d.is_online = 0`;
    }

    if (vendor) {
        sql += ` AND d.vendor LIKE ?`;
        params.push(`%${vendor}%`);
    }

    if (switch_name) {
        sql += ` AND c.switch_name LIKE ?`;
        params.push(`%${switch_name}%`);
    }

    sql += ` ORDER BY d.is_online DESC, d.ip ASC`;

    try {
        const devices = db.prepare(sql).all(...params);
        const inventoryIndex = await loadInventoryIndex();

        // Obtener tags para cada dispositivo
        const tagStmt = db.prepare('SELECT key, value FROM device_tags WHERE device_id = ?');
        for (const device of devices) {
            device.tags = tagStmt.all(device.id);
            applyInventoryMatch(device, inventoryIndex);
        }

        res.json({ success: true, devices, count: devices.length });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/stats — Estadísticas generales
router.get('/stats', (req, res) => {
    const db = getDb();
    try {
        const total = db.prepare('SELECT COUNT(*) as count FROM devices').get().count;
        const online = db.prepare('SELECT COUNT(*) as count FROM devices WHERE is_online = 1').get().count;
        const offline = total - online;
        const vendors = db.prepare("SELECT vendor, COUNT(*) as count FROM devices WHERE vendor != '' GROUP BY vendor ORDER BY count DESC").all();
        const lastScan = db.prepare('SELECT * FROM scan_history ORDER BY id DESC LIMIT 1').get();
        const newToday = db.prepare(`
      SELECT COUNT(*) as count FROM devices 
      WHERE date(first_seen) = date('now','localtime')
    `).get().count;

        res.json({
            success: true,
            stats: { total, online, offline, newToday, vendors, lastScan },
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/:id/geo — Geolocalización offline (geoip-lite)
router.get('/:id/geo', (req, res) => {
    const db = getDb();
    try {
        const device = db.prepare('SELECT ip FROM devices WHERE id = ?').get(req.params.id);
        if (!device) return res.status(404).json({ success: false, error: 'Dispositivo no encontrado' });

        const ip = device.ip;

        if (isPrivate(ip)) {
            return res.json({ success: true, private: true, ip, label: 'Red local (LAN)' });
        }

        const geo = geoip.lookup(ip);
        if (!geo) {
            return res.json({ success: true, private: false, ip, label: 'Sin datos de ubicación' });
        }

        res.json({
            success: true,
            private: false,
            ip,
            country:  geo.country,
            flag:     flagFor(geo.country),
            region:   geo.region || null,
            city:     geo.city   || null,
            timezone: geo.timezone || null,
            ll:       geo.ll,
            label:    [geo.city, geo.region, geo.country].filter(Boolean).join(', '),
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/:id/events — Historial de eventos ricos
router.get('/:id/events', (req, res) => {
    const db = getDb();
    try {
        const limit  = Math.min(parseInt(req.query.limit) || 60, 200);
        const events = db.prepare(
            'SELECT id, event_type, detail, ts FROM device_events WHERE device_id = ? ORDER BY id DESC LIMIT ?'
        ).all(req.params.id, limit);
        res.json({ success: true, events });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// POST /api/devices/:id/events — Registrar evento (interno, usado por otros módulos)
router.post('/:id/events', (req, res) => {
    const db = getDb();
    const { event_type, detail } = req.body;
    if (!event_type) return res.status(400).json({ success: false, error: 'event_type requerido' });
    try {
        db.prepare('INSERT INTO device_events (device_id, event_type, detail) VALUES (?, ?, ?)')
            .run(req.params.id, event_type, typeof detail === 'object' ? JSON.stringify(detail) : (detail || ''));
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/:id/uptime — Historial de eventos up/down
router.get('/:id/uptime', (req, res) => {
    const db = getDb();
    try {
        const limit = Math.min(parseInt(req.query.limit) || 50, 200);
        const events = db.prepare(
            'SELECT id, event, ts FROM uptime_events WHERE device_id = ? ORDER BY id DESC LIMIT ?'
        ).all(req.params.id, limit);
        res.json({ success: true, events });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/:id/bandwidth — tráfico del puerto switch via SNMP
router.get('/:id/bandwidth', (req, res) => {
    const db = getDb();
    try {
        const device = db.prepare(
            `SELECT d.*, c.switch_ip, c.port_name, c.switch_name
             FROM devices d
             LEFT JOIN connections c ON c.device_id = d.id
             WHERE d.id = ?`
        ).get(req.params.id);

        if (!device) return res.status(404).json({ success: false, error: 'Dispositivo no encontrado' });

        const portName  = device.port_name;
        const switchIp  = device.switch_ip;
        const switchName = device.switch_name;

        if (!portName) {
            return res.json({ success: true, hasData: false, reason: 'Sin puerto switch asignado (SNMP no configurado)' });
        }

        const sw = switchIp
            ? db.prepare('SELECT id FROM switches WHERE ip = ?').get(switchIp)
            : switchName
                ? db.prepare('SELECT id FROM switches WHERE name = ?').get(switchName)
                : null;

        if (!sw) {
            return res.json({ success: true, hasData: false, reason: 'Switch no registrado en SNMP' });
        }

        const samples = db.prepare(
            'SELECT * FROM port_traffic WHERE switch_id = ? AND port_name = ? ORDER BY ts DESC LIMIT 2'
        ).all(sw.id, portName);

        if (samples.length < 2) {
            return res.json({ success: true, hasData: false, reason: 'Esperando muestras SNMP (necesita 2)' });
        }

        const [s1, s2]  = samples;
        const deltaSec  = (new Date(s1.ts) - new Date(s2.ts)) / 1000;
        if (deltaSec <= 0) return res.json({ success: true, hasData: false, reason: 'Delta de tiempo inválido' });

        const WRAP       = 0x100000000;
        let deltaIn      = s1.in_octets  - s2.in_octets;
        let deltaOut     = s1.out_octets - s2.out_octets;
        if (deltaIn  < 0) deltaIn  += WRAP;
        if (deltaOut < 0) deltaOut += WRAP;

        const rxBps = Math.round((deltaIn  * 8) / deltaSec);
        const txBps = Math.round((deltaOut * 8) / deltaSec);

        function fmt(bps) {
            if (bps > 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
            if (bps > 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
            if (bps > 1e3) return (bps / 1e3).toFixed(1) + ' Kbps';
            return bps + ' bps';
        }

        res.json({
            success:    true,
            hasData:    true,
            port:       portName,
            rxBps,      txBps,
            rxPretty:   fmt(rxBps),
            txPretty:   fmt(txBps),
            sampledAt:  s1.sampled_at,
            switchName: switchName || switchIp,
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// GET /api/devices/:id — Detalle de un dispositivo
router.get('/:id', async (req, res) => {
    const db = getDb();
    try {
        const device = db.prepare(`
      SELECT d.*,
        c.switch_name, c.switch_ip, c.port_name, c.port_index, c.vlan, c.speed, c.duplex,
        s.has_data_service, s.rag_origin, s.provider, s.service_type, s.details as service_details
      FROM devices d
      LEFT JOIN connections c ON c.device_id = d.id
      LEFT JOIN services s ON s.device_id = d.id
      WHERE d.id = ?
    `).get(req.params.id);

        if (!device) {
            return res.status(404).json({ success: false, error: 'Dispositivo no encontrado' });
        }

        device.tags = db.prepare('SELECT key, value FROM device_tags WHERE device_id = ?').all(device.id);
        applyInventoryMatch(device, await loadInventoryIndex());

        res.json({ success: true, device });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// PUT /api/devices/:id — Editar datos de un dispositivo
router.put('/:id', (req, res) => {
    const db = getDb();
    const { hostname, device_type, notes, is_critical, switch_name, switch_ip, port_name, port_index, vlan,
        has_data_service, rag_origin, provider, service_type, service_details, tags } = req.body;

    try {
        const device = db.prepare('SELECT id FROM devices WHERE id = ?').get(req.params.id);
        if (!device) {
            return res.status(404).json({ success: false, error: 'Dispositivo no encontrado' });
        }

        // Actualizar dispositivo
        if (hostname !== undefined || device_type !== undefined || notes !== undefined) {
            const prev    = db.prepare('SELECT hostname, notes FROM devices WHERE id = ?').get(req.params.id);
            const updates = [];
            const params  = [];
            if (hostname    !== undefined) { updates.push('hostname = ?');    params.push(hostname); }
            if (device_type !== undefined) { updates.push('device_type = ?'); params.push(device_type); }
            if (notes       !== undefined) { updates.push('notes = ?');       params.push(notes); }
            if (is_critical !== undefined) { updates.push('is_critical = ?'); params.push(is_critical ? 1 : 0); }
            params.push(req.params.id);
            db.prepare(`UPDATE devices SET ${updates.join(', ')} WHERE id = ?`).run(...params);

            if (notes !== undefined && prev && notes !== prev.notes) {
                logEvent(req.params.id, 'note_updated', { note: notes });
            }
            if (hostname !== undefined && prev && hostname && hostname !== prev.hostname) {
                logEvent(req.params.id, 'hostname_changed', { from: prev.hostname, to: hostname });
            }
        }

        // Upsert conexión
        if (switch_name !== undefined || port_name !== undefined) {
            const existing = db.prepare('SELECT id FROM connections WHERE device_id = ?').get(req.params.id);
            if (existing) {
                db.prepare(`
          UPDATE connections SET 
            switch_name = COALESCE(?, switch_name),
            switch_ip = COALESCE(?, switch_ip),
            port_name = COALESCE(?, port_name),
            port_index = COALESCE(?, port_index),
            vlan = COALESCE(?, vlan),
            updated_at = datetime('now','localtime')
          WHERE device_id = ?
        `).run(switch_name, switch_ip, port_name, port_index, vlan, req.params.id);
            } else {
                db.prepare(`
          INSERT INTO connections (device_id, switch_name, switch_ip, port_name, port_index, vlan)
          VALUES (?, ?, ?, ?, ?, ?)
        `).run(req.params.id, switch_name || '', switch_ip || '', port_name || '', port_index || 0, vlan || 0);
            }
        }

        // Upsert servicios
        if (has_data_service !== undefined || rag_origin !== undefined) {
            const existing = db.prepare('SELECT id FROM services WHERE device_id = ?').get(req.params.id);
            if (existing) {
                db.prepare(`
          UPDATE services SET 
            has_data_service = COALESCE(?, has_data_service),
            rag_origin = COALESCE(?, rag_origin),
            provider = COALESCE(?, provider),
            service_type = COALESCE(?, service_type),
            details = COALESCE(?, details)
          WHERE device_id = ?
        `).run(has_data_service, rag_origin, provider, service_type, service_details, req.params.id);
            } else {
                db.prepare(`
          INSERT INTO services (device_id, has_data_service, rag_origin, provider, service_type, details)
          VALUES (?, ?, ?, ?, ?, ?)
        `).run(req.params.id, has_data_service || 0, rag_origin || '', provider || '', service_type || '', service_details || '');
            }
        }

        // Actualizar tags
        if (tags && Array.isArray(tags)) {
            const upsertTag = db.prepare(`
        INSERT INTO device_tags (device_id, key, value) VALUES (?, ?, ?)
        ON CONFLICT(device_id, key) DO UPDATE SET value = excluded.value
      `);
            for (const tag of tags) {
                upsertTag.run(req.params.id, tag.key, tag.value);
            }
        }

        // Retornar dispositivo actualizado
        const updated = db.prepare(`
      SELECT d.*,
        c.switch_name, c.switch_ip, c.port_name, c.port_index, c.vlan, c.speed,
        s.has_data_service, s.rag_origin, s.provider, s.service_type, s.details as service_details
      FROM devices d
      LEFT JOIN connections c ON c.device_id = d.id
      LEFT JOIN services s ON s.device_id = d.id
      WHERE d.id = ?
    `).get(req.params.id);
        updated.tags = db.prepare('SELECT key, value FROM device_tags WHERE device_id = ?').all(req.params.id);

        res.json({ success: true, device: updated });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/devices/bulk/offline — Eliminar todos los dispositivos offline
router.delete('/bulk/offline', (req, res) => {
    const db = getDb();
    try {
        const result = db.prepare('DELETE FROM devices WHERE is_online = 0').run();
        res.json({ success: true, count: result.changes });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/devices/bulk/all — Eliminar TODOS los dispositivos (reset de inventario)
router.delete('/bulk/all', (req, res) => {
    const db = getDb();
    try {
        const result = db.prepare('DELETE FROM devices').run();
        // También limpiar historial para que empiece de cero
        db.prepare('DELETE FROM scan_history').run();
        res.json({ success: true, count: result.changes });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

// DELETE /api/devices/:id — Eliminar un dispositivo
router.delete('/:id', (req, res) => {
    const db = getDb();
    try {
        const result = db.prepare('DELETE FROM devices WHERE id = ?').run(req.params.id);
        if (result.changes === 0) {
            return res.status(404).json({ success: false, error: 'Dispositivo no encontrado' });
        }
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});



module.exports = router;
