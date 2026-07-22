const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');
const config = require('../../config');

let db = null;

function getDb() {
  if (db) return db;

  // Asegurar que el directorio data/ existe
  const dir = path.dirname(config.db.path);
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }

  db = new Database(config.db.path);

  // Optimizaciones de performance
  db.pragma('journal_mode = WAL');
  db.pragma('synchronous = NORMAL');
  db.pragma('foreign_keys = ON');

  initSchema();
  return db;
}

function initSchema() {
  db.exec(`
    -- Dispositivos descubiertos en la red
    CREATE TABLE IF NOT EXISTS devices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      hostname TEXT DEFAULT '',
      ip TEXT NOT NULL,
      mac TEXT NOT NULL UNIQUE,
      vendor TEXT DEFAULT '',
      device_type TEXT DEFAULT '',
      os TEXT DEFAULT '',
      ttl INTEGER DEFAULT 0,
      is_online INTEGER DEFAULT 1,
      first_seen TEXT DEFAULT (datetime('now','localtime')),
      last_seen TEXT DEFAULT (datetime('now','localtime')),
      notes TEXT DEFAULT ''
    );

    -- Conexión a switch/boca
    CREATE TABLE IF NOT EXISTS connections (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id INTEGER NOT NULL,
      switch_name TEXT DEFAULT '',
      switch_ip TEXT DEFAULT '',
      port_name TEXT DEFAULT '',
      port_index INTEGER DEFAULT 0,
      vlan INTEGER DEFAULT 0,
      speed TEXT DEFAULT '',
      duplex TEXT DEFAULT '',
      updated_at TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    );

    -- Servicios de datos y RAG
    CREATE TABLE IF NOT EXISTS services (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id INTEGER NOT NULL UNIQUE,
      has_data_service INTEGER DEFAULT 0,
      rag_origin TEXT DEFAULT '',
      provider TEXT DEFAULT '',
      service_type TEXT DEFAULT '',
      details TEXT DEFAULT '',
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    );

    -- Switches configurados para SNMP
    CREATE TABLE IF NOT EXISTS switches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      ip TEXT NOT NULL UNIQUE,
      vendor TEXT DEFAULT '',
      model TEXT DEFAULT '',
      snmp_community TEXT DEFAULT 'public',
      snmp_version INTEGER DEFAULT 1,
      enabled INTEGER DEFAULT 1,
      last_polled TEXT DEFAULT ''
    );

    -- Historial de escaneos
    CREATE TABLE IF NOT EXISTS scan_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      scan_time TEXT DEFAULT (datetime('now','localtime')),
      scan_type TEXT DEFAULT 'arp',
      devices_found INTEGER DEFAULT 0,
      devices_online INTEGER DEFAULT 0,
      new_devices INTEGER DEFAULT 0,
      duration_ms INTEGER DEFAULT 0
    );

    -- Tags extensibles clave-valor
    CREATE TABLE IF NOT EXISTS device_tags (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id INTEGER NOT NULL,
      key TEXT NOT NULL,
      value TEXT DEFAULT '',
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
      UNIQUE(device_id, key)
    );

    -- Configuración clave-valor (Telegram, alertas, etc.)
    CREATE TABLE IF NOT EXISTS settings (
      key   TEXT PRIMARY KEY,
      value TEXT DEFAULT ''
    );

    -- Bóveda de credenciales (SSH keys, passwords, SNMP)
    CREATE TABLE IF NOT EXISTS credentials (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      name        TEXT NOT NULL,
      type        TEXT NOT NULL,
      username    TEXT DEFAULT '',
      password    TEXT DEFAULT '',
      private_key TEXT DEFAULT '',
      created_at  TEXT DEFAULT (datetime('now','localtime')),
      updated_at  TEXT DEFAULT (datetime('now','localtime'))
    );

    -- Asociación credencial <-> dispositivo
    CREATE TABLE IF NOT EXISTS device_credentials (
      device_id     INTEGER NOT NULL,
      credential_id INTEGER NOT NULL,
      PRIMARY KEY (device_id, credential_id),
      FOREIGN KEY (device_id)     REFERENCES devices(id)     ON DELETE CASCADE,
      FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE CASCADE
    );

    -- Muestras de tráfico SNMP por puerto de switch
    CREATE TABLE IF NOT EXISTS port_traffic (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id   INTEGER NOT NULL,
      port_index  INTEGER NOT NULL,
      port_name   TEXT DEFAULT '',
      in_octets   INTEGER DEFAULT 0,
      out_octets  INTEGER DEFAULT 0,
      ts          TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE
    );

    -- Historial de eventos up/down por dispositivo
    CREATE TABLE IF NOT EXISTS uptime_events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id  INTEGER NOT NULL,
      event      TEXT NOT NULL CHECK(event IN ('online','offline')),
      ts         TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    );

    -- Historial de eventos ricos por dispositivo (cambios, nuevos, notas, etc.)
    CREATE TABLE IF NOT EXISTS device_events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id  INTEGER NOT NULL,
      event_type TEXT NOT NULL,
      detail     TEXT DEFAULT '',
      ts         TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    );

    -- Configuración de Power Management (WoL / Shutdown / Reboot)
    CREATE TABLE IF NOT EXISTS power_config (
      id INTEGER PRIMARY KEY CHECK (id = 1),
      ssh_user TEXT DEFAULT 'admin',
      ssh_port INTEGER DEFAULT 22,
      wol_port INTEGER DEFAULT 9,
      broadcast_addr TEXT DEFAULT '255.255.255.255',
      default_shutdown_delay INTEGER DEFAULT 0
    );

    -- Log de acciones de power (WoL / Shutdown / Reboot)
    CREATE TABLE IF NOT EXISTS power_log (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      timestamp TEXT DEFAULT (datetime('now','localtime')),
      action TEXT NOT NULL,
      device_id INTEGER,
      device_name TEXT DEFAULT '',
      ip TEXT DEFAULT '',
      status TEXT DEFAULT '',
      detail TEXT DEFAULT ''
    );

    -- Índices para performance
    CREATE INDEX IF NOT EXISTS idx_uptime_device   ON uptime_events(device_id);
    CREATE INDEX IF NOT EXISTS idx_devevents_device ON device_events(device_id);
    CREATE INDEX IF NOT EXISTS idx_port_traffic_switch ON port_traffic(switch_id, port_index);
    CREATE INDEX IF NOT EXISTS idx_devices_mac ON devices(mac);
    CREATE INDEX IF NOT EXISTS idx_devices_ip ON devices(ip);
    CREATE INDEX IF NOT EXISTS idx_devices_online ON devices(is_online);
    CREATE INDEX IF NOT EXISTS idx_connections_device ON connections(device_id);
    CREATE INDEX IF NOT EXISTS idx_services_device ON services(device_id);
    CREATE INDEX IF NOT EXISTS idx_tags_device ON device_tags(device_id);
    CREATE INDEX IF NOT EXISTS idx_power_log_timestamp ON power_log(timestamp);
  `);

  // Migraciones para DBs existentes
  runMigrations();
}

function runMigrations() {
  // ── devices columns ─────────────────────────────────────────
  const devCols = db.prepare('PRAGMA table_info(devices)').all().map(c => c.name);
  if (!devCols.includes('os'))          { db.exec("ALTER TABLE devices ADD COLUMN os          TEXT    DEFAULT ''"); console.log('[DB] +devices.os'); }
  if (!devCols.includes('ttl'))         { db.exec("ALTER TABLE devices ADD COLUMN ttl         INTEGER DEFAULT 0");  console.log('[DB] +devices.ttl'); }
  if (!devCols.includes('is_critical')) { db.exec("ALTER TABLE devices ADD COLUMN is_critical INTEGER DEFAULT 0");  console.log('[DB] +devices.is_critical'); }

  // ── settings: migrate old fixed-column schema → key-value ───
  const settingsCols = db.prepare('PRAGMA table_info(settings)').all().map(c => c.name);
  if (!settingsCols.includes('key')) {
    console.log('[DB] Migrando tabla settings al esquema key-value...');
    // Read old values before dropping
    let oldRow = null;
    try { oldRow = db.prepare('SELECT * FROM settings LIMIT 1').get(); } catch (_) {}

    db.exec('DROP TABLE settings');
    db.exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT DEFAULT \'\')');

    if (oldRow) {
      const up = db.prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
      if (oldRow.telegram_token)      up.run('telegram_token',    oldRow.telegram_token);
      if (oldRow.telegram_chat_id)    up.run('telegram_chat_id',  oldRow.telegram_chat_id);
      if (oldRow.notifications_enabled != null)
        up.run('telegram_enabled', String(oldRow.notifications_enabled));
    }
    console.log('[DB] settings migrado');
  }

  // ── port_traffic new columns ─────────────────────────────────
  const ptCols = db.prepare('PRAGMA table_info(port_traffic)').all().map(c => c.name);
  if (!ptCols.includes('port_status')) {
    db.exec("ALTER TABLE port_traffic ADD COLUMN port_status INTEGER DEFAULT 0");
    console.log('[DB] +port_traffic.port_status');
  }
  if (!ptCols.includes('speed_bps')) {
    db.exec("ALTER TABLE port_traffic ADD COLUMN speed_bps INTEGER DEFAULT 0");
    console.log('[DB] +port_traffic.speed_bps');
  }

  // ── switches new columns ─────────────────────────────────────
  const swCols = db.prepare('PRAGMA table_info(switches)').all().map(c => c.name);
  if (!swCols.includes('credential_id')) {
    db.exec('ALTER TABLE switches ADD COLUMN credential_id INTEGER DEFAULT NULL REFERENCES credentials(id) ON DELETE SET NULL');
    console.log('[DB] +switches.credential_id');
  }

  // ── ensure new tables exist if DB predates them ──────────────
  const tables = db.prepare("SELECT name FROM sqlite_master WHERE type='table'").all().map(r => r.name);

  if (!tables.includes('device_events')) {
    db.exec(`CREATE TABLE device_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id INTEGER NOT NULL,
      event_type TEXT NOT NULL,
      detail TEXT DEFAULT '',
      ts TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_devevents_device ON device_events(device_id)');
    console.log('[DB] +device_events');
  }

  if (!tables.includes('uptime_events')) {
    db.exec(`CREATE TABLE uptime_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      device_id INTEGER NOT NULL,
      event TEXT NOT NULL CHECK(event IN ('online','offline')),
      ts TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_uptime_device ON uptime_events(device_id)');
    console.log('[DB] +uptime_events');
  }

  if (!tables.includes('port_traffic')) {
    db.exec(`CREATE TABLE port_traffic (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL,
      port_index INTEGER NOT NULL,
      port_name TEXT DEFAULT '',
      in_octets INTEGER DEFAULT 0,
      out_octets INTEGER DEFAULT 0,
      ts TEXT DEFAULT (datetime('now','localtime')),
      FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_port_traffic_switch ON port_traffic(switch_id, port_index)');
    console.log('[DB] +port_traffic');
  }

  // ── L3 tables ────────────────────────────────────────────────────────────────
  if (!tables.includes('vlans')) {
    db.exec(`CREATE TABLE vlans (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL REFERENCES switches(id) ON DELETE CASCADE,
      vlan_id INTEGER NOT NULL,
      vlan_name TEXT DEFAULT '',
      tagged_ports TEXT DEFAULT '[]',
      untagged_ports TEXT DEFAULT '[]',
      updated_at TEXT DEFAULT (datetime('now','localtime')),
      UNIQUE(switch_id, vlan_id)
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_vlans_switch ON vlans(switch_id)');
    console.log('[DB] +vlans');
  }

  if (!tables.includes('interface_ips')) {
    db.exec(`CREATE TABLE interface_ips (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL REFERENCES switches(id) ON DELETE CASCADE,
      if_index INTEGER NOT NULL,
      ip_addr TEXT NOT NULL,
      netmask TEXT DEFAULT '',
      updated_at TEXT DEFAULT (datetime('now','localtime')),
      UNIQUE(switch_id, ip_addr)
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_iface_switch ON interface_ips(switch_id)');
    console.log('[DB] +interface_ips');
  }

  if (!tables.includes('arp_entries')) {
    db.exec(`CREATE TABLE arp_entries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL REFERENCES switches(id) ON DELETE CASCADE,
      ip_addr TEXT NOT NULL,
      mac_addr TEXT NOT NULL,
      if_index INTEGER DEFAULT 0,
      ts TEXT DEFAULT (datetime('now','localtime')),
      UNIQUE(switch_id, ip_addr)
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_arp_switch ON arp_entries(switch_id)');
    console.log('[DB] +arp_entries');
  }

  if (!tables.includes('routing_entries')) {
    db.exec(`CREATE TABLE routing_entries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL REFERENCES switches(id) ON DELETE CASCADE,
      dest TEXT NOT NULL,
      mask TEXT DEFAULT '',
      next_hop TEXT DEFAULT '',
      metric INTEGER DEFAULT 0,
      route_type TEXT DEFAULT '',
      ts TEXT DEFAULT (datetime('now','localtime')),
      UNIQUE(switch_id, dest, next_hop)
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_routes_switch ON routing_entries(switch_id)');
    console.log('[DB] +routing_entries');
  }

  if (!tables.includes('cdp_neighbors')) {
    db.exec(`CREATE TABLE cdp_neighbors (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      switch_id INTEGER NOT NULL REFERENCES switches(id) ON DELETE CASCADE,
      local_port TEXT DEFAULT '',
      remote_device TEXT DEFAULT '',
      remote_ip TEXT DEFAULT '',
      remote_port TEXT DEFAULT '',
      updated_at TEXT DEFAULT (datetime('now','localtime')),
      UNIQUE(switch_id, local_port, remote_device)
    )`);
    db.exec('CREATE INDEX IF NOT EXISTS idx_cdp_switch ON cdp_neighbors(switch_id)');
    console.log('[DB] +cdp_neighbors');
  }
}

function close() {
  if (db) {
    db.close();
    db = null;
  }
}

module.exports = { getDb, close };
