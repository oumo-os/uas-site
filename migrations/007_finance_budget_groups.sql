-- Enhanced financial records + budget items + working groups

-- 1. Extend financial_records type enum
ALTER TABLE financial_records
  MODIFY COLUMN type ENUM('income','expense','commitment','payable','receivable','budget_item') NOT NULL;

-- 2. Extend financial_records category with more options
ALTER TABLE financial_records
  MODIFY COLUMN category ENUM(
    'programme','event','equipment','administration','communications',
    'membership','outreach','donation','grant','sponsorship',
    'training','publication','travel','utilities','other'
  ) DEFAULT 'other';

-- 3. Add event_id and budget_item_id to financial_records
ALTER TABLE financial_records
  ADD COLUMN event_id INT NULL AFTER project_id,
  ADD COLUMN budget_item_id INT NULL AFTER event_id,
  ADD COLUMN due_date DATE NULL AFTER record_date,
  ADD COLUMN status ENUM('draft','pending','approved','paid','cancelled') DEFAULT 'approved' AFTER due_date,
  ADD COLUMN attachment_url VARCHAR(500) NULL AFTER status,
  ADD COLUMN notes TEXT NULL AFTER attachment_url,
  ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;

-- 4. Create budget_items table
CREATE TABLE IF NOT EXISTS budget_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT,
  type ENUM('income','expense') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  category ENUM(
    'programme','event','equipment','administration','communications',
    'membership','outreach','donation','grant','sponsorship',
    'training','publication','travel','utilities','other'
  ) DEFAULT 'other',
  programme_id INT,
  project_id INT,
  event_id INT,
  fiscal_year YEAR DEFAULT (YEAR(CURDATE())),
  status ENUM('draft','active','closed','cancelled') DEFAULT 'draft',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create working_groups / committees table
CREATE TABLE IF NOT EXISTS working_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  type ENUM('committee','working_group','task_force','sub_committee') DEFAULT 'working_group',
  status ENUM('active','inactive','dissolved') DEFAULT 'active',
  chair_id INT,
  programme_id INT,
  created_by INT,
  resolution_id INT,
  term_start DATE,
  term_end DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (chair_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Working group membership
CREATE TABLE IF NOT EXISTS working_group_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('chair','member','secretary','advisor') DEFAULT 'member',
  status ENUM('active','inactive') DEFAULT 'active',
  joined_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wg_member (group_id, user_id),
  FOREIGN KEY (group_id) REFERENCES working_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
