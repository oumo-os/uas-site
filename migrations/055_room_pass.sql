-- Migration 055: moderator passcodes for Jitsi rooms.
-- meet.jit.si has no API for pre-assigning moderators, so each auto-created
-- room gets a stored passcode: whoever arrives first with host rights
-- (organizer/lead signs in) sets it as the room password in Jitsi and admits
-- the rest from the lobby. Shown ONLY to privileged viewers (organizer,
-- leads, approvers, meeting managers) — never in public payloads.

ALTER TABLE meetings ADD COLUMN room_pass VARCHAR(32) NULL AFTER meeting_url;
ALTER TABLE events ADD COLUMN room_pass VARCHAR(32) NULL AFTER online_url;
