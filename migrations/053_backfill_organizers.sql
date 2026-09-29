-- Migration 053: backfill missing event organizers.
-- Some event rows have organizer_id NULL (e.g. created before the field was
-- consistently set), which shows as "Unknown" and breaks owner checks.
-- The creator is the best-known organizer for those rows.
UPDATE events SET organizer_id = created_by
WHERE organizer_id IS NULL AND created_by IS NOT NULL;
