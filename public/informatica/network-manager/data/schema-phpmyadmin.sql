CREATE DATABASE IF NOT EXISTS network_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE network_monitor;

CREATE TABLE IF NOT EXISTS devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hostname VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL,
  mac VARCHAR(17) NOT NULL,
  vendor VARCHAR(255) NOT NULL DEFAULT '',
  device_type VARCHAR(255) NOT NULL DEFAULT '',
  os VARCHAR(255) NOT NULL DEFAULT '',
  ttl INT NOT NULL DEFAULT 0,
  is_online TINYINT(1) NOT NULL DEFAULT 1,
  first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_devices_mac (mac),
  KEY idx_devices_ip (ip),
  KEY idx_devices_online (is_online)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS switches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  vendor VARCHAR(255) NOT NULL DEFAULT '',
  model VARCHAR(255) NOT NULL DEFAULT '',
  snmp_community VARCHAR(255) NOT NULL DEFAULT 'public',
  snmp_version INT NOT NULL DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_polled DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_switches_ip (ip)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS connections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id INT UNSIGNED NOT NULL,
  switch_name VARCHAR(255) NOT NULL DEFAULT '',
  switch_ip VARCHAR(45) NOT NULL DEFAULT '',
  port_name VARCHAR(255) NOT NULL DEFAULT '',
  port_index INT NOT NULL DEFAULT 0,
  vlan INT NOT NULL DEFAULT 0,
  speed VARCHAR(64) NOT NULL DEFAULT '',
  duplex VARCHAR(64) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_connections_device (device_id),
  CONSTRAINT fk_connections_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id INT UNSIGNED NOT NULL,
  has_data_service TINYINT(1) NOT NULL DEFAULT 0,
  rag_origin VARCHAR(255) NOT NULL DEFAULT '',
  provider VARCHAR(255) NOT NULL DEFAULT '',
  service_type VARCHAR(255) NOT NULL DEFAULT '',
  details TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_device (device_id),
  CONSTRAINT fk_services_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scan_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  scan_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  scan_type VARCHAR(50) NOT NULL DEFAULT 'arp',
  devices_found INT NOT NULL DEFAULT 0,
  devices_online INT NOT NULL DEFAULT 0,
  new_devices INT NOT NULL DEFAULT 0,
  duration_ms INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id INT UNSIGNED NOT NULL,
  `key` VARCHAR(255) NOT NULL,
  value TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_tags (device_id, `key`),
  KEY idx_tags_device (device_id),
  CONSTRAINT fk_device_tags_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS switch_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_switch_id INT UNSIGNED NOT NULL,
  source_switch_name VARCHAR(255) NOT NULL DEFAULT '',
  source_switch_ip VARCHAR(45) NOT NULL DEFAULT '',
  source_port_index INT NOT NULL DEFAULT 0,
  source_port_name VARCHAR(255) NOT NULL DEFAULT '',
  target_device_id INT UNSIGNED NULL DEFAULT NULL,
  target_mac VARCHAR(17) NOT NULL DEFAULT '',
  target_ip VARCHAR(45) NOT NULL DEFAULT '',
  target_label VARCHAR(255) NOT NULL DEFAULT '',
  target_type VARCHAR(100) NOT NULL DEFAULT '',
  link_type VARCHAR(100) NOT NULL DEFAULT 'direct',
  confidence DECIMAL(4,2) NOT NULL DEFAULT 0.50,
  observed_mac_count INT NOT NULL DEFAULT 1,
  discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_switch_links (source_switch_id, source_port_index, target_mac, target_ip, target_label),
  KEY idx_switch_links_source (source_switch_id),
  KEY idx_switch_links_target_device (target_device_id),
  CONSTRAINT fk_switch_links_source FOREIGN KEY (source_switch_id) REFERENCES switches(id) ON DELETE CASCADE,
  CONSTRAINT fk_switch_links_target_device FOREIGN KEY (target_device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;
