-- Migration 046: Update projects table status enum for approval workflow
-- Changes status from ('draft','active','on_hold','completed','archived')
-- to ('draft','submitted','approved','published','archived') to match workflow

-- First, check if the old enum exists and update any existing values
UPDATE projects SET status = 'archived' WHERE status = 'on_hold';
UPDATE projects SET status = 'published' WHERE status = 'active';
UPDATE projects SET status = 'approved' WHERE status = 'completed';

-- Now alter the enum
ALTER TABLE projects 
MODIFY COLUMN status ENUM('draft','submitted','approved','published','archived') DEFAULT 'draft';

-- Add rejection_reason, approved_by, approved_at columns for approval workflow
ALTER TABLE projects 
ADD COLUMN rejection_reason TEXT NULL AFTER status,
ADD COLUMN approved_by INT NULL AFTER rejection_reason,
ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
ADD FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;