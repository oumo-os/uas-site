-- Migration 049: video embeds (URL-only) for articles, programmes and projects.
-- Events already got video_url in 048. Values store NORMALIZED embed URLs
-- (youtube-nocookie / vimeo player) or NULL — see video_embed_url().

ALTER TABLE articles ADD COLUMN video_url VARCHAR(500) NULL AFTER image_url;
ALTER TABLE programmes ADD COLUMN video_url VARCHAR(500) NULL AFTER image_url;
ALTER TABLE projects ADD COLUMN video_url VARCHAR(500) NULL;
