-- Migration 038: programme applications (self-service join requests).
-- IDEMPOTENT: CREATE TABLE IF NOT EXISTS. Do NOT re-import seed files.

CREATE TABLE IF NOT EXISTS programme_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme_id INT NOT NULL,
  user_id INT NOT NULL,
  message TEXT,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prog_applicant (programme_id, user_id),
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
