-- Migration 022: Dynamic polls eligibility
-- Replace hardcoded ENUM with dynamic group/role-based eligibility

-- Add eligibility_target column for group name or role title
ALTER TABLE polls ADD COLUMN eligibility_target VARCHAR(255) NULL AFTER eligibility;

-- Migrate existing 'directors' eligibility to 'members' (directors = cap holders, subset of members)
UPDATE polls SET eligibility = 'members' WHERE eligibility = 'directors';

-- Update the ENUM to remove 'directors' and add 'group'/'role'
ALTER TABLE polls MODIFY COLUMN eligibility ENUM('all','members','group','role') DEFAULT 'members';
