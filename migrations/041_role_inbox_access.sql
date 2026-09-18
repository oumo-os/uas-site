-- Migration 041: grant office inbox access to roles.
-- IDEMPOTENT: CREATE TABLE IF NOT EXISTS. Run once.

CREATE TABLE IF NOT EXISTS role_inbox_access (
  role_id INT NOT NULL,
  office VARCHAR(50) NOT NULL,
  granted_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, office),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
