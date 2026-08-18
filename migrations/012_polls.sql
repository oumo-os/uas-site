-- Migration 012: Polls
-- Governance and consultation polls with eligibility, quorum, and results.

CREATE TABLE IF NOT EXISTS polls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  poll_type ENUM('governance','consultation') DEFAULT 'consultation',
  eligibility ENUM('directors','members','all') DEFAULT 'directors',
  options JSON NOT NULL,     -- ["Option A", "Option B", ...]
  quorum INT NOT NULL DEFAULT 0,       -- required voter count (0 = none)
  allow_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME,
  ends_at DATETIME,
  status ENUM('draft','open','closed') DEFAULT 'draft',
  result_option INT NULL,
  resolution_id INT NULL,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS poll_votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  poll_id INT NOT NULL,
  user_id INT NOT NULL,
  option_index INT NOT NULL,
  voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_poll_user (poll_id, user_id),
  FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Capabilities for the polls module
INSERT INTO capabilities (slug, name, description, category) VALUES
('governance.poll.manage', 'Manage Polls', 'Open, edit and close polls', 'governance'),
('governance.poll.vote', 'Vote in Polls', 'Cast votes in eligible polls', 'governance'),
('governance.poll.resolve', 'Resolve Polls', 'Finalize poll results and record outcomes', 'governance')
ON DUPLICATE KEY UPDATE name = VALUES(name);