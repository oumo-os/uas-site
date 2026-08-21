-- Migration 026: Member classes as RBAC roles
-- 1. Seed member_class roles
-- 2. Assign roles to existing members based on category
-- 3. Drop category column from members

-- 1. Seed member_class roles
INSERT IGNORE INTO roles (title, description, role_type, status)
VALUES
  ('Regular Member', 'Standard membership class', 'member_class', 'active'),
  ('Student Member', 'Student membership class', 'member_class', 'active'),
  ('Honorary Member', 'Honorary membership class', 'member_class', 'active'),
  ('Institutional Member', 'Institutional membership class', 'member_class', 'active');

-- 2. Assign roles to existing active members
INSERT IGNORE INTO role_assignments (role_id, user_id, assigned_by, effective_from, status)
SELECT r.id, m.user_id, 1, COALESCE(m.joined_date, CURDATE()), 'active'
FROM members m
JOIN roles r ON r.role_type = 'member_class'
  AND r.title = CASE m.category
    WHEN 'regular' THEN 'Regular Member'
    WHEN 'student' THEN 'Student Member'
    WHEN 'honorary' THEN 'Honorary Member'
    WHEN 'institutional' THEN 'Institutional Member'
    ELSE 'Regular Member'
  END
WHERE m.status = 'active';

-- 3. Drop category column
ALTER TABLE members DROP COLUMN category;
