-- UAS Institutional Platform — Database Schema
-- Phase 1-2: Governance engine + RBAC + institutional objects + workflows
-- MySQL 5.7+ / MariaDB 10.3+

-- ============================================================
-- 1. USERS & MEMBERSHIP
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  bio TEXT,
  avatar_url VARCHAR(500),
  institution VARCHAR(255),
  location VARCHAR(255),
  status ENUM('active','suspended','pending') DEFAULT 'pending',
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  membership_number VARCHAR(50) UNIQUE,
  category ENUM('regular','student','honorary','institutional') DEFAULT 'regular',
  status ENUM('active','inactive','suspended') DEFAULT 'active',
  joined_date DATE,
  approved_by INT,
  approved_at TIMESTAMP NULL,
  profile_visible TINYINT(1) DEFAULT 0,
  interests TEXT,
  contributions TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. CAPABILITIES & ROLES (RBAC)
-- ============================================================

CREATE TABLE IF NOT EXISTS capabilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) UNIQUE NOT NULL,  -- e.g. articles.approve
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100),              -- e.g. content, governance, finance
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('active','inactive') DEFAULT 'active',
  scope VARCHAR(100),                 -- e.g. board, committee, programme
  term_start DATE,
  term_end DATE,
  created_by INT,
  resolution_id INT,                  -- which resolution created this role
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_capabilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  capability_id INT NOT NULL,
  granted_by INT,                     -- resolution or user
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_role_cap (role_id, capability_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (capability_id) REFERENCES capabilities(id) ON DELETE CASCADE,
  FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  user_id INT NOT NULL,
  assigned_by INT,
  resolution_id INT,                  -- which resolution made this appointment
  effective_from DATE NOT NULL,
  effective_to DATE,
  status ENUM('active','inactive','revoked') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_role_user_active (role_id, user_id, status),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. GOVERNANCE ENGINE
-- ============================================================

CREATE TABLE IF NOT EXISTS resolutions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,   -- e.g. UAS-BRD-2026-014
  title VARCHAR(500) NOT NULL,
  description TEXT,
  type ENUM(
    'role_create','role_modify','role_delete',
    'capability_assign','capability_revoke',
    'appointment','removal',
    'policy_adopt','policy_amend',
    'committee_create','committee_dissolve',
    'programme_create','programme_close',
    'budget_approve','financial_authorization',
    'general_poll','constitutional_amendment'
  ) NOT NULL,
  status ENUM('draft','submitted','voting','passed','failed','applied','rejected') DEFAULT 'draft',
  proposed_by INT NOT NULL,
  body_id INT,                        -- board/committee that votes
  quorum INT DEFAULT 0,               -- minimum votes required
  majority VARCHAR(20) DEFAULT 'simple', -- simple, two_thirds, unanimous
  voting_deadline TIMESTAMP NULL,
  votes_for INT DEFAULT 0,
  votes_against INT DEFAULT 0,
  votes_abstain INT DEFAULT 0,
  passed_at TIMESTAMP NULL,
  applied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS resolution_changes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resolution_id INT NOT NULL,
  change_type ENUM(
    'role_create','role_modify','role_delete',
    'cap_assign','cap_revoke',
    'appoint','remove',
    'policy_adopt','policy_amend',
    'committee_create','committee_dissolve',
    'programme_create','programme_close',
    'budget_approve','financial_auth'
  ) NOT NULL,
  target_type VARCHAR(100),           -- what object type this affects
  target_id INT,                      -- id of affected object
  payload JSON NOT NULL,              -- exact changes to apply
  applied TINYINT(1) DEFAULT 0,
  applied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resolution_id INT NOT NULL,
  user_id INT NOT NULL,
  value ENUM('for','against','abstain') NOT NULL,
  rationale TEXT,
  cast_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vote_once (resolution_id, user_id),
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. INSTITUTIONAL OBJECTS
-- ============================================================

-- Programmes
CREATE TABLE IF NOT EXISTS programmes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  lead_id INT,
  status ENUM('draft','active','paused','completed','archived') DEFAULT 'draft',
  budget DECIMAL(15,2) DEFAULT 0,
  spent DECIMAL(15,2) DEFAULT 0,
  start_date DATE,
  end_date DATE,
  objectives TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (lead_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects
CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme_id INT,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  lead_id INT,
  status ENUM('draft','active','on_hold','completed','archived') DEFAULT 'draft',
  objectives TEXT,
  milestones JSON,
  deadline DATE,
  budget DECIMAL(15,2) DEFAULT 0,
  spent DECIMAL(15,2) DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
  FOREIGN KEY (lead_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events
CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme_id INT,
  project_id INT,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  organizer_id INT,
  date DATETIME NOT NULL,
  end_date DATETIME,
  location VARCHAR(500),
  capacity INT,
  status ENUM('draft','submitted','approved','published','cancelled','completed') DEFAULT 'draft',
  approval_required TINYINT(1) DEFAULT 1,
  approved_by INT,
  approved_at TIMESTAMP NULL,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Articles / Submissions
CREATE TABLE IF NOT EXISTS articles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  title VARCHAR(500) NOT NULL,
  body LONGTEXT,
  category ENUM('article','observing_report','educational','project_report','announcement','paper') DEFAULT 'article',
  tags JSON,
  image_url VARCHAR(500),
  status ENUM('draft','submitted','under_review','approved','published','rejected','archived') DEFAULT 'draft',
  approver_role_id INT,               -- which role approves this type
  approved_by INT,
  approved_at TIMESTAMP NULL,
  published_at TIMESTAMP NULL,
  rejection_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approver_role_id) REFERENCES roles(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents
CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  file_path VARCHAR(1000) NOT NULL,
  category ENUM('paper','publication','article','guide','image','constitution','policy','minutes','resolution','report','agreement','financial','other') NOT NULL,
  visibility ENUM('public','internal','restricted') DEFAULT 'public',
  owner_id INT NOT NULL,
  version INT DEFAULT 1,
  status ENUM('draft','submitted','approved','published','archived') DEFAULT 'draft',
  approved_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Financial Records
CREATE TABLE IF NOT EXISTS financial_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('income','expense','commitment') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  category ENUM('programme','event','equipment','administration','communications','membership','outreach','other') DEFAULT 'other',
  programme_id INT,
  project_id INT,
  description TEXT,
  recorded_by INT NOT NULL,
  approved_by INT,
  approved_at TIMESTAMP NULL,
  record_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignments
CREATE TABLE IF NOT EXISTS assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  assignee_id INT,
  assigner_id INT,
  role_id INT,                        -- if assigned to a role rather than person
  due_date DATE,
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  status ENUM('not_started','in_progress','submitted','completed','overdue') DEFAULT 'not_started',
  related_type VARCHAR(100),
  related_id INT,
  completed_at TIMESTAMP NULL,
  completion_evidence TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (assigner_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Calendar Items (superset of events for internal tracking)
CREATE TABLE IF NOT EXISTS calendar_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  type ENUM('event','meeting','deadline','governance','report','election','renewal','milestone','other') NOT NULL,
  owner_id INT,
  deadline DATETIME NOT NULL,
  status ENUM('pending','in_progress','completed','overdue','cancelled') DEFAULT 'pending',
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  related_type VARCHAR(100),
  related_id INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. WORKFLOW ENGINE
-- ============================================================

-- Tracks every object's progression through its lifecycle
CREATE TABLE IF NOT EXISTS workflow_states (
  id INT AUTO_INCREMENT PRIMARY KEY,
  object_type VARCHAR(100) NOT NULL,  -- event, article, resolution, etc.
  object_id INT NOT NULL,
  state VARCHAR(50) NOT NULL,         -- draft, submitted, approved, etc.
  assignee_role_id INT,               -- who needs to act
  assignee_id INT,                    -- specific person if assigned
  entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  due_at TIMESTAMP NULL,
  notes TEXT,
  INDEX idx_ws_object (object_type, object_id),
  INDEX idx_ws_state (state),
  INDEX idx_ws_assignee (assignee_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. PARTNERS, AFFILIATES, USEFUL LINKS
-- ============================================================

CREATE TABLE IF NOT EXISTS partners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  organization VARCHAR(255) NOT NULL,
  description TEXT,
  logo_url VARCHAR(500),
  website VARCHAR(500),
  relationship_type ENUM('partner','affiliate','sponsor','institutional') DEFAULT 'partner',
  status ENUM('active','inactive') DEFAULT 'active',
  associated_programmes JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS useful_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  url VARCHAR(1000) NOT NULL,
  category VARCHAR(100),
  external_organization VARCHAR(255),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. AUDIT TRAIL
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_name VARCHAR(255),
  action_type VARCHAR(50) NOT NULL,   -- create, update, delete, approve, vote, apply
  target_type VARCHAR(100),           -- resolution, article, event, etc.
  target_id INT,
  governance_context JSON,            -- {resolution_id, role_id, capability_slug}
  previous_state JSON,
  new_state JSON,
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action_type),
  INDEX idx_audit_target (target_type, target_id),
  INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. SEED DATA — Default capabilities
-- ============================================================

INSERT INTO capabilities (slug, name, description, category) VALUES
-- Content
('articles.submit', 'Submit Articles', 'Submit articles for review', 'content'),
('articles.edit', 'Edit Articles', 'Edit own submitted articles', 'content'),
('articles.review', 'Review Articles', 'Review submitted articles', 'content'),
('articles.approve', 'Approve Articles', 'Approve articles for publication', 'content'),
('articles.publish', 'Publish Articles', 'Publish approved articles', 'content'),
('articles.delete', 'Delete Articles', 'Delete articles', 'content'),

-- Events
('events.create', 'Create Events', 'Create events', 'events'),
('events.edit', 'Edit Events', 'Edit events', 'events'),
('events.review', 'Review Events', 'Review submitted events', 'events'),
('events.approve', 'Approve Events', 'Approve events for publication', 'events'),
('events.publish', 'Publish Events', 'Publish approved events', 'events'),
('events.cancel', 'Cancel Events', 'Cancel published events', 'events'),

-- Programmes
('programmes.create', 'Create Programmes', 'Create new programmes', 'programmes'),
('programmes.manage', 'Manage Programmes', 'Manage programme details and projects', 'programmes'),
('programmes.approve', 'Approve Programmes', 'Approve programme creation', 'programmes'),

-- Projects
('projects.create', 'Create Projects', 'Create new projects', 'projects'),
('projects.manage', 'Manage Projects', 'Manage project details and tasks', 'projects'),
('projects.approve', 'Approve Projects', 'Approve project creation', 'projects'),
('projects.close', 'Close Projects', 'Close completed projects', 'projects'),

-- Members
('members.view', 'View Members', 'View member profiles', 'members'),
('members.approve', 'Approve Members', 'Approve membership applications', 'members'),
('members.manage', 'Manage Members', 'Manage member accounts', 'members'),

-- Governance
('resolutions.create', 'Create Resolutions', 'Create governance resolutions', 'governance'),
('resolutions.vote', 'Vote on Resolutions', 'Vote on resolutions', 'governance'),
('resolutions.manage', 'Manage Resolutions', 'Manage resolution lifecycle', 'governance'),
('governance.poll.create', 'Create Governance Polls', 'Create governance polls', 'governance'),

-- Documents
('documents.upload', 'Upload Documents', 'Upload documents', 'documents'),
('documents.review', 'Review Documents', 'Review submitted documents', 'documents'),
('documents.approve', 'Approve Documents', 'Approve documents for publication', 'documents'),
('documents.publish', 'Publish Documents', 'Publish approved documents', 'documents'),

-- Finance
('finance.view', 'View Finance', 'View financial records', 'finance'),
('finance.record', 'Record Finance', 'Record financial transactions', 'finance'),
('finance.approve', 'Approve Finance', 'Approve financial records', 'finance'),

-- Reports
('reports.create', 'Create Reports', 'Create reports', 'reports'),
('reports.review', 'Review Reports', 'Review reports', 'reports'),
('reports.publish', 'Publish Reports', 'Publish reports', 'reports'),

-- Administration
('roles.create', 'Create Roles', 'Create institutional roles', 'admin'),
('roles.manage', 'Manage Roles', 'Manage role assignments', 'admin'),
('admin.system', 'System Administration', 'System configuration and maintenance', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);
