-- Migration 029: Dues schedule + finance-dues linkage
-- 1. Create dues_schedule table for configurable per-class rates
-- 2. Add member_id to financial_records to link transactions to members

-- 1. Dues schedule: one rate per member class per year
CREATE TABLE IF NOT EXISTS dues_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL COMMENT 'FK to roles — member_class role',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  period_year YEAR NOT NULL,
  description TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_year (role_id, period_year),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Link financial records to members
ALTER TABLE financial_records ADD COLUMN member_id INT NULL AFTER budget_item_id;
ALTER TABLE financial_records ADD FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL;

-- 3. Link membership_dues to financial_records
ALTER TABLE membership_dues ADD COLUMN receivable_record_id INT NULL AFTER recorded_by;
ALTER TABLE membership_dues ADD COLUMN payment_record_id INT NULL AFTER receivable_record_id;
ALTER TABLE membership_dues ADD FOREIGN KEY (receivable_record_id) REFERENCES financial_records(id) ON DELETE SET NULL;
ALTER TABLE membership_dues ADD FOREIGN KEY (payment_record_id) REFERENCES financial_records(id) ON DELETE SET NULL;
