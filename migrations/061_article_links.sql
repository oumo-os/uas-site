-- Migration 061: attach projects and events to article compositions.
-- An article may reference one project and one event (e.g. a report about
-- a workshop, or a project write-up). Displayed as linked cards on the
-- article page, with a reciprocal "Related reading" list on project pages.
-- IDEMPOTENT: columns are added only if absent. No foreign keys by design:
-- deleting a project/event must never block on articles, and display code
-- simply omits links whose target is gone.

SET @has_project := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'project_id');
SET @sql_project := IF(@has_project = 0, 'ALTER TABLE articles ADD COLUMN project_id INT NULL AFTER approver_role_id', 'SELECT 1');
PREPARE stmt_project FROM @sql_project; EXECUTE stmt_project; DEALLOCATE PREPARE stmt_project;

SET @has_event := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'event_id');
SET @sql_event := IF(@has_event = 0, 'ALTER TABLE articles ADD COLUMN event_id INT NULL AFTER project_id', 'SELECT 1');
PREPARE stmt_event FROM @sql_event; EXECUTE stmt_event; DEALLOCATE PREPARE stmt_event;
