-- Migration 010: Rate limiting
-- Tracks failed auth attempts and submission rates per key (email / IP).

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rl_key VARCHAR(190) NOT NULL,
  rl_type VARCHAR(40) NOT NULL,
  attempts INT NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rl_key (rl_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;