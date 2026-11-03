CREATE DATABASE IF NOT EXISTS kerio_report
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kerio_report;

CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mac_address VARCHAR(17) NULL,
  ip_address VARCHAR(45) NOT NULL,
  hostname VARCHAR(255) NULL,
  first_seen DATETIME NULL,
  last_seen DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_devices_ip_mac (ip_address, mac_address),
  KEY idx_devices_mac (mac_address),
  KEY idx_devices_hostname (hostname)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS traffic_samples (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  sampled_at DATETIME NOT NULL,
  total_download_kb DECIMAL(20,3) NULL,
  total_upload_kb DECIMAL(20,3) NULL,
  download_delta_kb DECIMAL(20,3) NOT NULL DEFAULT 0,
  upload_delta_kb DECIMAL(20,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_traffic_device_time (device_id, sampled_at),
  KEY idx_traffic_sampled_at (sampled_at),
  CONSTRAINT fk_traffic_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS connection_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kerio_log_key VARCHAR(100) NULL,
  device_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  connection_id VARCHAR(40) NULL,
  rule_name VARCHAR(255) NULL,
  service_name VARCHAR(100) NULL,
  protocol VARCHAR(20) NULL,
  source_ip VARCHAR(45) NOT NULL,
  source_port INT UNSIGNED NULL,
  destination_host VARCHAR(255) NULL,
  destination_ip VARCHAR(45) NULL,
  destination_port INT UNSIGNED NULL,
  interface_name VARCHAR(100) NULL,
  duration_seconds INT UNSIGNED NULL,
  bytes_in BIGINT UNSIGNED NULL,
  bytes_out BIGINT UNSIGNED NULL,
  bytes_total BIGINT UNSIGNED NULL,
  raw_line TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_connection_log_key (kerio_log_key),
  KEY idx_connection_time_ip (occurred_at, source_ip),
  KEY idx_connection_device_time_bytes (device_id, occurred_at, bytes_total),
  KEY idx_connection_destination (destination_host, destination_ip),
  CONSTRAINT fk_connection_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS web_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kerio_log_key VARCHAR(100) NULL,
  device_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  source_ip VARCHAR(45) NOT NULL,
  http_method VARCHAR(12) NULL,
  url TEXT NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  response_bytes BIGINT UNSIGNED NULL,
  raw_line TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_log_key (kerio_log_key),
  KEY idx_web_time_ip (occurred_at, source_ip),
  CONSTRAINT fk_web_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sync_state (
  id TINYINT UNSIGNED NOT NULL,
  connection_cursor BIGINT NOT NULL DEFAULT -1,
  web_cursor BIGINT NOT NULL DEFAULT -1,
  last_active_hosts_sync DATETIME NULL,
  last_connection_sync DATETIME NULL,
  last_web_sync DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT chk_sync_state_singleton CHECK (id = 1)
) ENGINE=InnoDB;

INSERT INTO sync_state (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);
