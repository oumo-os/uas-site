-- Migration 035: article cover images + IEEE ComSoc partner event.
-- IDEMPOTENT: safe to re-run. Do NOT re-import seed-live.sql or 033/034.

-- Article covers (plain UPDATEs are naturally idempotent)
UPDATE articles SET image_url = 'img/article-moon-detail.jpg'
WHERE title LIKE '%2027 Eclipse%';

UPDATE articles SET image_url = 'img/event-spaceweek-2026.jpg'
WHERE title LIKE '%World Space Week 2026%';

-- IEEE ComSoc School Series Uganda 2026 (past partner event; INSERT only if absent)
INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, image_url, category, created_by)
SELECT NULL, 'IEEE ComSoc School Series Uganda 2026', '<p>Guest lecture on Satellite Communications by Eng. Patience Namugwanya (Space Systems Engineer, InnaLabs, Dublin), hosted by the IEEE Communications Society Uganda Chapter with IEEE SAC Uganda and IEEE Women in Engineering Uganda.</p><p><strong>Venue:</strong> Hilton Garden Inn, Kampala<br><strong>Dates:</strong> 24-28 August 2026</p>', NULL, '2026-08-24 09:00:00', '2026-08-28 17:00:00', 'Hilton Garden Inn, Kampala', 150, 'published', 'img/event-ieee-comsoc-2026.jpg', 'lecture', 3
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'IEEE ComSoc School Series Uganda 2026' AND date = '2026-08-24 09:00:00');
