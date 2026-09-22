-- Migration 047: project teams + event rejection reason
-- Teams live in projects (participants with roles), not in programmes.

CREATE TABLE IF NOT EXISTS project_participants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  role VARCHAR(120) DEFAULT 'Member',
  status ENUM('active','inactive') DEFAULT 'active',
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_user (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event rejection reason (articles already have one; events can be
-- cancelled but previously could not be rejected with feedback).
ALTER TABLE events ADD COLUMN rejection_reason TEXT NULL AFTER approved_at;
