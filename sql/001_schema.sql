-- ARC Inventory — initial schema (MariaDB / MySQL)
-- Run this once in phpMyAdmin or: mysql -u root < sql/001_schema.sql

CREATE DATABASE IF NOT EXISTS arc_inventory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE arc_inventory;

-- Roles: member | admin | superuser
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  callsign VARCHAR(16) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('member', 'admin', 'superuser') NOT NULL DEFAULT 'member',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_callsign (callsign)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id VARCHAR(32) NOT NULL,
  club_id VARCHAR(64) NULL DEFAULT NULL,
  item_type VARCHAR(64) NOT NULL DEFAULT 'other',
  description VARCHAR(255) NOT NULL,
  manufacturer VARCHAR(128) NULL DEFAULT NULL,
  model VARCHAR(128) NULL DEFAULT NULL,
  serial_number VARCHAR(128) NULL DEFAULT NULL,
  location VARCHAR(128) NULL DEFAULT NULL,
  condition_note VARCHAR(128) NULL DEFAULT NULL,
  source_note VARCHAR(255) NULL DEFAULT NULL,
  notes TEXT NULL,
  status ENUM('available', 'on_loan', 'maintenance', 'sold', 'disposed') NOT NULL DEFAULT 'available',
  not_for_loan TINYINT(1) NOT NULL DEFAULT 0,
  is_kit TINYINT(1) NOT NULL DEFAULT 0,
  photo_path VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_items_public_id (public_id),
  KEY idx_items_status (status),
  KEY idx_items_not_for_loan (not_for_loan),
  FULLTEXT KEY ft_items_search (description, manufacturer, model, serial_number, location, source_note, notes, club_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kit_includes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kit_item_id INT UNSIGNED NOT NULL,
  line_label VARCHAR(255) NOT NULL,
  child_item_id INT UNSIGNED NULL DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_kit_parent FOREIGN KEY (kit_item_id) REFERENCES items(id),
  CONSTRAINT fk_kit_child FOREIGN KEY (child_item_id) REFERENCES items(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  borrower_user_id INT UNSIGNED NOT NULL,
  loaned_at DATETIME NOT NULL,
  returned_at DATETIME NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_loans_item FOREIGN KEY (item_id) REFERENCES items(id),
  CONSTRAINT fk_loans_borrower FOREIGN KEY (borrower_user_id) REFERENCES users(id),
  KEY idx_loans_active (is_active)
) ENGINE=InnoDB;

-- pending | approved | declined | expired
CREATE TABLE IF NOT EXISTS witness_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action_type ENUM('loan_out', 'loan_return') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED NOT NULL,
  witness_user_id INT UNSIGNED NOT NULL,
  loan_id INT UNSIGNED NULL DEFAULT NULL,
  status ENUM('pending', 'approved', 'declined', 'expired') NOT NULL DEFAULT 'pending',
  kit_verified TINYINT(1) NOT NULL DEFAULT 0,
  admin_override TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  resolved_at DATETIME NULL DEFAULT NULL,
  CONSTRAINT fk_wr_item FOREIGN KEY (item_id) REFERENCES items(id),
  CONSTRAINT fk_wr_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_wr_witness FOREIGN KEY (witness_user_id) REFERENCES users(id),
  CONSTRAINT fk_wr_loan FOREIGN KEY (loan_id) REFERENCES loans(id),
  KEY idx_wr_status (status),
  KEY idx_wr_witness (witness_user_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  item_id INT UNSIGNED NULL DEFAULT NULL,
  actor_user_id INT UNSIGNED NULL DEFAULT NULL,
  witness_user_id INT UNSIGNED NULL DEFAULT NULL,
  loan_id INT UNSIGNED NULL DEFAULT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_item FOREIGN KEY (item_id) REFERENCES items(id),
  CONSTRAINT fk_ledger_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_ledger_witness FOREIGN KEY (witness_user_id) REFERENCES users(id),
  CONSTRAINT fk_ledger_loan FOREIGN KEY (loan_id) REFERENCES loans(id),
  KEY idx_ledger_created (created_at),
  KEY idx_ledger_event (event_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alert_type VARCHAR(64) NOT NULL,
  callsign VARCHAR(16) NULL DEFAULT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_alerts_unread (is_read, created_at)
) ENGINE=InnoDB;

-- Per-install club branding / URLs (filled by public/install.php)
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Complete first-time setup at public/install.php after import.
