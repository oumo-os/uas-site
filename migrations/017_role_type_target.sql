-- Add role_type and target columns to roles table (safe re-run)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_type');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE roles ADD COLUMN role_type ENUM(''governance'',''administrative'',''working_group'',''programme'',''cohort'',''member_class'') DEFAULT ''member_class'' AFTER scope, ADD COLUMN target VARCHAR(255) NULL AFTER role_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed existing roles with explicit role_type and target
UPDATE roles SET role_type = 'governance',     target = NULL           WHERE id = 1;  -- President (board)
UPDATE roles SET role_type = 'administrative', target = NULL           WHERE id = 2;  -- General Secretary (committee)
UPDATE roles SET role_type = 'working_group',  target = 'Education Working Group'        WHERE id = 3;  -- Education WG Lead
UPDATE roles SET role_type = 'programme',      target = 'Women in Astronomy Initiative'  WHERE id = 4;  -- Programme Lead
UPDATE roles SET role_type = 'working_group',  target = 'Technical Task Force'            WHERE id = 5;  -- Head of Science
UPDATE roles SET role_type = 'member_class',   target = NULL           WHERE id = 6;  -- Member
UPDATE roles SET role_type = 'working_group',  target = 'Outreach Committee'             WHERE id = 7;  -- Outreach Coordinator
UPDATE roles SET role_type = 'administrative', target = NULL           WHERE id = 10; -- Communications Officer (committee)
