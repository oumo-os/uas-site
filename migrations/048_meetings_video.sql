-- Migration 048: Jitsi rooms for meetings + online/video fields for events.
-- No video is hosted: rooms live on the configured Jitsi server, and video
-- embeds are URL-only (YouTube / Vimeo, validated server-side).

-- Stable Jitsi room link per meeting (generated on demand, see POST /meetings/:id/room).
ALTER TABLE meetings ADD COLUMN meeting_url VARCHAR(500) NULL AFTER location;

-- Virtual events: online flag + room link + optional video (trailer/recording).
ALTER TABLE events ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 0 AFTER location;
ALTER TABLE events ADD COLUMN online_url VARCHAR(500) NULL AFTER is_online;
ALTER TABLE events ADD COLUMN video_url VARCHAR(500) NULL AFTER image_url;
