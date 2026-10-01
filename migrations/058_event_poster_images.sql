-- Migration 058: link optimized event posters to their events.
-- The posters were re-exported from the source files in images/ with
-- descriptive names and web-sized dimensions (see img/event-*-poster.jpg).

UPDATE events SET image_url = 'img/event-under-the-skies-poster.jpg'
  WHERE slug = 'under-the-skies-ugandan-edition';
UPDATE events SET image_url = 'img/event-ieee-comsoc-poster.jpg'
  WHERE slug = 'ieee-comsoc-school-series-uganda-2026';
UPDATE events SET image_url = 'img/event-eass-workshop-poster.jpg'
  WHERE slug = 'east-african-astronomical-society-workshop-2026';
UPDATE events SET image_url = 'img/event-nileorbital-poster-blue.jpg'
  WHERE slug = 'nileorbital-aerospace-high-school-online-workshop';
UPDATE events SET image_url = 'img/event-world-space-week-poster.jpg'
  WHERE slug = 'world-space-week-2026-uganda';
