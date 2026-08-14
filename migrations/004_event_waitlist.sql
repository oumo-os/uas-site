-- UAS Institutional Platform — Event Waitlist
-- Run: mysql -u root uas_platform < migrations/004_event_waitlist.sql

CREATE TABLE IF NOT EXISTS event_waitlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wait_event_user (event_id, user_id),
  CONSTRAINT fk_wl_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;