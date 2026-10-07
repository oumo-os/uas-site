-- Migration 065: slug history for renamed events/projects/programmes.
-- When an editor changes a slug, the old slug keeps resolving (301) to the
-- current URL instead of dying. IDEMPOTENT: table uses IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS slug_redirects (
  from_slug VARCHAR(120) NOT NULL PRIMARY KEY,
  target_type ENUM('event','project','programme') NOT NULL,
  target_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slug_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
