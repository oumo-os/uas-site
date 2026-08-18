-- Migration 013: Programme members & outputs
-- Programme-level membership and documented outputs (spec §9).

ALTER TABLE programmes
  ADD COLUMN outputs LONGTEXT NULL AFTER objectives;

CREATE TABLE IF NOT EXISTS programme_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme_id INT NOT NULL,
  user_id INT NOT NULL,
  role_in_programme VARCHAR(120) NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  joined_date DATE NULL,
  UNIQUE KEY uq_programme_user (programme_id, user_id),
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;