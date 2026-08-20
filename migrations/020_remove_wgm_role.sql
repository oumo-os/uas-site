-- Remove role column from working_group_members
-- Seed existing non-"member" roles into the roles table as RBAC roles

-- 1. Create roles for each unique (group, old_role) pair where role != 'member'
--    Title is capitalized, scope = group type, target = group name
INSERT IGNORE INTO roles (title, description, scope, role_type, target, status, created_by)
SELECT
  CONCAT(UPPER(LEFT(wgm.role, 1)), LOWER(SUBSTRING(wgm.role, 2))) AS title,
  CONCAT(wgm.role, ' of ', wg.name) AS description,
  wg.type AS scope,
  'governance' AS role_type,
  wg.name AS target,
  'active' AS status,
  1 AS created_by
FROM (
  SELECT DISTINCT group_id, role FROM working_group_members WHERE role != 'member' AND status = 'active'
) wgm
JOIN working_groups wg ON wg.id = wgm.group_id
WHERE NOT EXISTS (
  SELECT 1 FROM roles r WHERE r.title = CONCAT(UPPER(LEFT(wgm.role, 1)), LOWER(SUBSTRING(wgm.role, 2))) AND r.target = wg.name AND r.status = 'active'
);

-- 2. Assign users to their new RBAC roles
INSERT IGNORE INTO role_assignments (role_id, user_id, assigned_by, effective_from, status)
SELECT r.id, wgm.user_id, 1, CURDATE(), 'active'
FROM working_group_members wgm
JOIN working_groups wg ON wg.id = wgm.group_id
JOIN roles r ON r.title = CONCAT(UPPER(LEFT(wgm.role, 1)), LOWER(SUBSTRING(wgm.role, 2)))
  AND r.target = wg.name AND r.status = 'active'
WHERE wgm.role != 'member' AND wgm.status = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM role_assignments ra WHERE ra.role_id = r.id AND ra.user_id = wgm.user_id AND ra.status = 'active'
  );

-- 3. Drop the role column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'working_group_members' AND COLUMN_NAME = 'role');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE working_group_members DROP COLUMN role', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
