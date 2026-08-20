-- Governance committee roles for the about page
-- Creates roles with role_type='governance' and scope='committee' for each committee
-- Run this after 017_role_type_target.sql

-- Board of Directors (institutional, no specific target)
INSERT IGNORE INTO roles (title, description, scope, role_type, target, status, created_by)
SELECT 'Board of Directors', 'Governing board of UAS', 'committee', 'governance', NULL, 'active', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE title = 'Board of Directors' AND role_type = 'governance');

-- Advisory Board
INSERT IGNORE INTO roles (title, description, scope, role_type, target, status, created_by)
SELECT 'Advisory Board', 'External advisors to UAS', 'committee', 'governance', 'Advisory Board', 'active', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE title = 'Advisory Board' AND role_type = 'governance');

-- Finance & Governance Committee
INSERT IGNORE INTO roles (title, description, scope, role_type, target, status, created_by)
SELECT 'Finance & Governance Committee', 'Oversees financial and governance matters', 'committee', 'governance', 'Finance & Governance Committee', 'active', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE title = 'Finance & Governance Committee' AND role_type = 'governance');

-- Ensure President role is governance-scoped to Board of Directors
UPDATE roles SET scope = 'committee', target = 'Board of Directors' WHERE id = 1 AND role_type = 'governance';
