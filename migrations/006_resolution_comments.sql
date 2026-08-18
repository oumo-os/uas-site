-- Resolution comments / discussion thread

CREATE TABLE IF NOT EXISTS resolution_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resolution_id INT NOT NULL,
  user_id INT NOT NULL,
  parent_id INT NULL,                  -- for nested replies
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES resolution_comments(id) ON DELETE CASCADE,
  INDEX idx_rc_resolution (resolution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
