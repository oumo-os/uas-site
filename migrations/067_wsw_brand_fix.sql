-- Migration 067: the Oct 10 physical day is World Space Week branding.
-- Retitle (slug and everything else untouched) and fix the two in-content
-- brand references to match. Deterministic re-runnable.

UPDATE events SET title = 'World Space Week 2026 - Physical Day' WHERE id = 26;

UPDATE events
SET description = REPLACE(description,
  'This is the flagship physical day of Uganda National Space Week 2026, held under',
  'This is the flagship physical day of World Space Week 2026 in Uganda, held under')
WHERE id = 26;

UPDATE events
SET confirm_message = REPLACE(confirm_message,
  'registered for Uganda National Space Week 2026',
  'registered for World Space Week 2026 - Physical Day')
WHERE id = 26;
