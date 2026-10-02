-- Migration 063: cover images for projects.
-- The project create/edit endpoints accept image_url, but the column was
-- never added — every project save fails without it. IDEMPOTENT.

SET @has_img := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'image_url');
SET @sql_img := IF(@has_img = 0, 'ALTER TABLE projects ADD COLUMN image_url VARCHAR(500) NULL AFTER video_url', 'SELECT 1');
PREPARE stmt_img FROM @sql_img;
EXECUTE stmt_img;
DEALLOCATE PREPARE stmt_img;
