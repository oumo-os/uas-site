-- Migration 034: remove duplicate events from double-applied seed / migration 033.
-- ONE-SHOT. Do NOT re-import seed-live.sql or 033 on production afterwards.
--
-- Verify first (optional):
--   SELECT id, title, date, image_url FROM events ORDER BY date, id;

-- 1. Drop rows containing literal backslash-u escape text
--    (from a seed import where \u2014 / \u2019 were written literally, not as UTF-8).
DELETE FROM events
WHERE title LIKE '%u2014%' OR title LIKE '%u2019%'
   OR location LIKE '%u2019%' OR description LIKE '%u2019%';

-- 2a. Where two rows share (title, date) and only one carries an image, drop the imageless copy.
DELETE e1 FROM events e1
JOIN events e2 ON e2.title = e1.title AND e2.date = e1.date
  AND e2.image_url IS NOT NULL AND e1.image_url IS NULL
WHERE NOT EXISTS (SELECT 1 FROM event_registrations r WHERE r.event_id = e1.id)
  AND NOT EXISTS (SELECT 1 FROM event_waitlist w WHERE w.event_id = e1.id);

-- 2b. Remaining exact (title, date) duplicates: keep the newest id, drop the older,
--     provided nothing (registrations / waitlist) references the older row.
DELETE e1 FROM events e1
JOIN events e2 ON e2.title = e1.title AND e2.date = e1.date AND e2.id > e1.id
WHERE NOT EXISTS (SELECT 1 FROM event_registrations r WHERE r.event_id = e1.id)
  AND NOT EXISTS (SELECT 1 FROM event_waitlist w WHERE w.event_id = e1.id);
