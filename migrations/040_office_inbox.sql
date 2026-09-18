-- Migration 040: office-routed contact inbox + replies.
-- Run ONCE. Do NOT re-import seed files.

ALTER TABLE contact_messages
  ADD COLUMN office VARCHAR(50) NOT NULL DEFAULT 'contact' AFTER email,
  ADD COLUMN assigned_to INT NULL AFTER status,
  ADD CONSTRAINT fk_contact_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE contact_messages
  MODIFY status ENUM('new','read','assigned','replied','archived') NOT NULL DEFAULT 'new';

CREATE TABLE IF NOT EXISTS message_replies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  sent_via ENUM('email','internal') NOT NULL DEFAULT 'internal',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_reply_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
