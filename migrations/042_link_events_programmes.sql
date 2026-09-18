-- Migration 042: link events to their programmes.
-- IDEMPOTENT: fixed SETs (safe to re-run). LIKE patterns tolerate hyphen variants.

UPDATE events e JOIN programmes p ON p.title = 'Astronomy & Observation'
SET e.programme_id = p.id
WHERE e.title IN ('October Public Observing Night', 'Annual Sky-Viewing Event', 'La Brise Stargazing Weekend')
   OR e.title LIKE 'Under the Skies%Ugandan Edition';

UPDATE events e JOIN programmes p ON p.title = 'Public Science & Space Culture'
SET e.programme_id = p.id
WHERE e.title LIKE 'World Space Week%';

UPDATE events e JOIN programmes p ON p.title = 'Astronomy Education & STEM'
SET e.programme_id = p.id
WHERE e.title = 'Teachers Astronomy Bootcamp'
   OR e.title LIKE 'Nileorbital Aerospace%High School%';

UPDATE events e JOIN programmes p ON p.title = 'Space Ecosystem Engagement'
SET e.programme_id = p.id
WHERE e.title = 'East African Astronomical Society Workshop 2026'
   OR e.title = 'IEEE ComSoc School Series Uganda 2026';
