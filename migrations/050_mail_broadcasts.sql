-- Migration 050: mass-mail broadcasts (queued, browser-driven sending).
-- No cron needed: the composer creates the broadcast + recipient rows, then
-- the browser fires small send-chunks until done (shared hosting friendly).
-- Sending reuses send_office_email(), so SPF/DKIM/signatures behave exactly
-- like single office mail.

CREATE TABLE IF NOT EXISTS mail_broadcasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office VARCHAR(50) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  audience_type ENUM('member_class','event','project','role','group','all') NOT NULL,
  audience_ref INT NULL,
  audience_opts JSON NULL,
  created_by INT NOT NULL,
  status ENUM('draft','sending','done') DEFAULT 'draft',
  total INT NOT NULL DEFAULT 0,
  sent_count INT NOT NULL DEFAULT 0,
  fail_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_broadcast_creator (created_by, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_broadcast_recipients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  broadcast_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  user_id INT NULL,
  name VARCHAR(255) NULL,
  status ENUM('queued','sent','failed') DEFAULT 'queued',
  error VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  UNIQUE KEY uq_broadcast_email (broadcast_id, email),
  FOREIGN KEY (broadcast_id) REFERENCES mail_broadcasts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_recip_status (broadcast_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dedicated capability for mass mail (grantable later via Admin → Roles).
-- Senders also keep passing through their existing manage/approve caps.
INSERT INTO capabilities (slug, name, description, category) VALUES
  ('mail.broadcast', 'Send Mass Mail', 'Compose broadcasts to member classes, events, projects, roles and groups', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);
