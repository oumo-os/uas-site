-- Simplify role_type to 3 values: governance, administrative, member_class
-- Remap existing data and remove 'board' as a scope value

-- 1. Remap role_type values before altering ENUM
UPDATE roles SET role_type = 'governance'     WHERE role_type = 'working_group';
UPDATE roles SET role_type = 'governance'     WHERE role_type = 'programme';
UPDATE roles SET role_type = 'member_class'   WHERE role_type = 'cohort';

-- 2. Remap scope: 'board' → 'committee'
UPDATE roles SET scope = 'committee' WHERE scope = 'board';

-- 3. Alter the ENUM column to only 3 values
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_type');
SET @current_type = (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_type');
SET @sql = IF(@col_exists AND @current_type != 'enum(''governance'',''administrative'',''member_class'')',
  'ALTER TABLE roles MODIFY COLUMN role_type ENUM(''governance'',''administrative'',''member_class'') DEFAULT ''member_class''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Fix migration 018: Board of Directors should not be a role title.
--    It is a target (committee name). Delete the incorrectly created roles.
DELETE FROM role_assignments WHERE role_id IN (SELECT id FROM roles WHERE title = 'Board of Directors' AND role_type = 'governance');
DELETE FROM role_capabilities WHERE role_id IN (SELECT id FROM roles WHERE title = 'Board of Directors' AND role_type = 'governance');
DELETE FROM roles WHERE title = 'Board of Directors' AND role_type = 'governance' AND scope = 'committee';

-- 5. Fix seed roles: Education WG Lead, Head of Science, Outreach Coordinator, Programme Lead
--    These should now be governance (already remapped above in step 1)
--    Their targets remain the same, but ensure scope is correct
UPDATE roles SET scope = 'working_group' WHERE title = 'Education WG Lead' AND scope IS NULL;
UPDATE roles SET scope = 'working_group' WHERE title = 'Head of Science' AND scope IS NULL;
UPDATE roles SET scope = 'working_group' WHERE title = 'Outreach Coordinator' AND scope IS NULL;
UPDATE roles SET scope = 'programme' WHERE title = 'Programme Lead' AND scope IS NULL;
