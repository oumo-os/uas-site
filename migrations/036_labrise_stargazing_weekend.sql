-- Migration 036: La Brise Stargazing Weekend (May 2026) past event.
-- IDEMPOTENT: safe to re-run. Do NOT re-import seed-live.sql or 033/034.

INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, image_url, category, created_by)
SELECT NULL, 'La Brise Stargazing Weekend', '<p>The Society''s May 2026 stargazing weekend at La Brise Resort confirmed genuine public demand for organised astronomy experiences: telescopes under dark skies, hands-on observing, and community evenings.</p><p><strong>Venue:</strong> La Brise Resort<br><strong>Dates:</strong> 16-17 May 2026</p>', 2, '2026-05-16 18:00:00', '2026-05-17 22:00:00', 'La Brise Resort', 100, 'published', 'img/labrise-campfire.jpg', 'observing', 3
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'La Brise Stargazing Weekend' AND date = '2026-05-16 18:00:00');
