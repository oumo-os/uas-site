-- Migration 045: local record of sent office mail (Sent view + threading).
-- IDEMPOTENT: CREATE TABLE IF NOT EXISTS. Run once.

CREATE TABLE IF NOT EXISTS mail_sent (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office VARCHAR(50) NOT NULL,
  to_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  message_id VARCHAR(255) NULL,
  in_reply_to VARCHAR(255) NULL,
  kind ENUM('compose','mailbox-reply','contact-reply') NOT NULL DEFAULT 'compose',
  ref_id INT NULL,
  sent_by INT NULL,
  via VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_sent_office (office, created_at),
  KEY idx_sent_msgid (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
