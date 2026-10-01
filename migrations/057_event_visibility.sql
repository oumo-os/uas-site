-- Migration 057: per-event visibility (public vs members-only).
-- Public events behave exactly as before. Members-only events are invisible
-- to logged-out visitors everywhere: listings, search, sitemap, share
-- previews, detail (404) and guest signup. Any active logged-in member may
-- see and RSVP them; organizers and approvers keep preview access.

ALTER TABLE events ADD COLUMN visibility ENUM('public','members') NOT NULL DEFAULT 'public' AFTER status;
