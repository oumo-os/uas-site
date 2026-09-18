-- Migration 039: guest (non-member) event signups.
-- IDEMPOTENT: CREATE TABLE IF NOT EXISTS. Do NOT re-import seed files.

CREATE TABLE IF NOT EXISTS event_guests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  status ENUM('registered','cancelled','attended') NOT NULL DEFAULT 'registered',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_guest (event_id, email),
  KEY idx_guest_event (event_id, status),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
