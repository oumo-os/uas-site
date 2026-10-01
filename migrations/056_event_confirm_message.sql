-- Migration 056: organizer-drafted confirmation message per event.
-- Shown to members right after they sign up (RSVP card) and appended to the
-- signup-confirmation and reminder emails they and guests receive. Plain
-- text, stripped of markup on write. Visible to any logged-in viewer of the
-- event detail (it is post-signup guidance, not a secret) but never to
-- public guests pre-signup.

ALTER TABLE events ADD COLUMN confirm_message TEXT NULL AFTER room_pass;
