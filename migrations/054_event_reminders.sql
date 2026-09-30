-- Migration 054: event reminder log (guest confirmations + 6h-before mails).
-- Guests receive the online join link by email (signup confirmation + a
-- reminder when the join window approaches). One row per (event, email,
-- kind) makes every sender idempotent — safe to retry or cron repeatedly.

CREATE TABLE IF NOT EXISTS event_reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  user_id INT NULL,
  name VARCHAR(255) NULL,
  kind ENUM('signup_confirm','before_6h') NOT NULL,
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_reminder (event_id, email, kind),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_reminder_lookup (event_id, kind, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
