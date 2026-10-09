-- Migration 069: drop Kololo as the implied permanent venue.
-- UAS has no permanent physical address yet, so public copy must not name
-- one. Two published articles referenced it; both are reworded to point at
-- the Events page instead. Guarded to no-op on re-run.

UPDATE articles
SET body = REPLACE(body,
  '<p>We will see you at Kololo.</p>',
  '<p>We will see you under the stars.</p>')
WHERE id = 4 AND body LIKE '%We will see you at Kololo.%';

UPDATE articles
SET body = REPLACE(body,
  'we set up at Kololo and look up together.',
  'we set up our telescopes and look up together — the venue is announced on the Events page.')
WHERE id = 9 AND body LIKE '%set up at Kololo%';
