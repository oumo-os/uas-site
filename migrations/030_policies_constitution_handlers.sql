-- Migration 030: Policies table, constitutional amendments table, capability seeds,
-- and constitution_update change type for the governance engine.

-- 1. Policies table — stores board-adopted policies
CREATE TABLE IF NOT EXISTS policies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  status ENUM('draft','active','archived') DEFAULT 'draft',
  effective_date DATE,
  resolution_id INT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Constitutional amendments table — stores approved amendments
CREATE TABLE IF NOT EXISTS constitutional_amendments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amendment_number INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  text TEXT NOT NULL,
  status ENUM('proposed','active','superseded') DEFAULT 'proposed',
  effective_date DATE,
  resolution_id INT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add constitution_update to resolution_changes.change_type ENUM
ALTER TABLE resolution_changes
  MODIFY COLUMN change_type ENUM(
    'role_create','role_modify','role_delete',
    'cap_assign','cap_revoke',
    'appoint','remove',
    'policy_adopt','policy_amend',
    'committee_create','committee_dissolve',
    'programme_create','programme_close',
    'budget_approve','financial_auth',
    'constitution_update'
  ) NOT NULL;

-- 4. Seed capabilities for policies and constitution management
INSERT INTO capabilities (slug, name, description, category) VALUES
  ('policies.create', 'Create Policies', 'Create governance policies', 'governance'),
  ('policies.manage', 'Manage Policies', 'Manage and archive governance policies', 'governance'),
  ('constitution.manage', 'Manage Constitution', 'Propose and manage constitutional amendments', 'governance')
ON DUPLICATE KEY UPDATE name = VALUES(name);
