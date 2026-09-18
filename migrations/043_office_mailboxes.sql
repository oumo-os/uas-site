-- Migration 043: office mailbox configs (IMAP credentials, encrypted).
-- IDEMPOTENT: CREATE TABLE IF NOT EXISTS. Run once.
-- Passwords are AES-256 encrypted with MAILBOX_KEY from api/prod-env.php
-- (server-only, never in git). Officers never see them.

CREATE TABLE IF NOT EXISTS office_mailboxes (
  office VARCHAR(50) PRIMARY KEY,
  host VARCHAR(255) NOT NULL DEFAULT 'astronomy.ug',
  port INT NOT NULL DEFAULT 993,
  username VARCHAR(255) NOT NULL,
  password_enc TEXT NOT NULL,
  use_ssl TINYINT(1) NOT NULL DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_check_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
