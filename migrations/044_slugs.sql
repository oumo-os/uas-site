-- 044: human-readable slugs for clean URLs
-- programmes & projects at root: astronomy.ug/<slug>
-- events at: astronomy.ug/events/<slug>
ALTER TABLE programmes ADD COLUMN slug VARCHAR(120) UNIQUE NULL AFTER title;
ALTER TABLE projects ADD COLUMN slug VARCHAR(120) UNIQUE NULL AFTER title;
ALTER TABLE events ADD COLUMN slug VARCHAR(120) UNIQUE NULL AFTER title;
-- backfill known programmes with clean slugs (idempotent)
UPDATE programmes SET slug = 'astronomy-observation' WHERE title = 'Astronomy & Observation' AND slug IS NULL;
UPDATE programmes SET slug = 'space-science' WHERE title = 'Space Science' AND slug IS NULL;
UPDATE programmes SET slug = 'astronomy-education-stem' WHERE title = 'Astronomy Education & STEM' AND slug IS NULL;
UPDATE programmes SET slug = 'african-ugandan-astronomy' WHERE title = 'African & Ugandan Astronomy' AND slug IS NULL;
UPDATE programmes SET slug = 'public-science-space-culture' WHERE title = 'Public Science & Space Culture' AND slug IS NULL;
UPDATE programmes SET slug = 'space-ecosystem-engagement' WHERE title = 'Space Ecosystem Engagement' AND slug IS NULL;
-- backfill events (examples; remaining titles will generate on write)
UPDATE events SET slug = 'world-space-week-2026-uganda' WHERE title LIKE 'World Space Week%' AND slug IS NULL;
UPDATE events SET slug = 'october-public-observing-night' WHERE title = 'October Public Observing Night' AND slug IS NULL;
UPDATE events SET slug = 'annual-sky-viewing-event' WHERE title = 'Annual Sky-Viewing Event' AND slug IS NULL;
UPDATE events SET slug = 'under-the-skies-ugandan-edition' WHERE title LIKE 'Under the Skies%' AND slug IS NULL;
UPDATE events SET slug = 'teachers-astronomy-bootcamp' WHERE title = 'Teachers Astronomy Bootcamp' AND slug IS NULL;
UPDATE events SET slug = 'society-planning-session' WHERE title = 'Society Planning Session' AND slug IS NULL;
UPDATE events SET slug = 'la-brise-stargazing-weekend' WHERE title = 'La Brise Stargazing Weekend' AND slug IS NULL;
UPDATE events SET slug = 'east-african-astronomical-society-workshop-2026' WHERE title LIKE 'East African Astronomical Society Workshop%' AND slug IS NULL;
UPDATE events SET slug = 'nileorbital-aerospace-high-school-online-workshop' WHERE title LIKE 'Nileorbital Aerospace%' AND slug IS NULL;
UPDATE events SET slug = 'ieee-comsoc-school-series-uganda-2026' WHERE title LIKE 'IEEE ComSoc%' AND slug IS NULL;
