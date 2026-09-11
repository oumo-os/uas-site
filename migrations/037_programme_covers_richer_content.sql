-- Migration 037: programme covers + richer inline imagery on key content.
-- IDEMPOTENT: UPDATEs only touch rows that lack the image (safe to re-run).
-- All photos are genuine UAS member photography; captions stay factual.

-- Programme covers (only where unset)
UPDATE programmes SET image_url = 'img/labrise-telescope-day.jpg', category = 'observing'
WHERE title = 'Astronomy & Observation' AND image_url IS NULL;

UPDATE programmes SET image_url = 'img/article-observatory.jpg', category = 'research'
WHERE title = 'Space Science' AND image_url IS NULL;

UPDATE programmes SET image_url = 'img/outreach-young-engineers.jpg', category = 'education'
WHERE title = 'Astronomy Education & STEM' AND image_url IS NULL;

UPDATE programmes SET image_url = 'img/home-outreach.jpg', category = 'heritage'
WHERE title = 'African & Ugandan Astronomy' AND image_url IS NULL;

UPDATE programmes SET image_url = 'img/labrise-campfire.jpg', category = 'community'
WHERE title = 'Public Science & Space Culture' AND image_url IS NULL;

UPDATE programmes SET image_url = 'img/event-nileorbital-2026.jpg', category = 'ecosystem'
WHERE title = 'Space Ecosystem Engagement' AND image_url IS NULL;

-- La Brise weekend: inline photos of the observing setup
UPDATE events SET description = CONCAT(description,
  '<h3>On the ground</h3><p>Telescopes ready before dark at La Brise Resort.</p>',
  '<p><img src="/img/labrise-telescope-day.jpg" alt="UAS telescope prepared for the evening session"></p>',
  '<p>Sharing the eyepiece through the night.</p>',
  '<p><img src="/img/home-observing.jpg" alt="Telescopes lined up for the night session"></p>')
WHERE title = 'La Brise Stargazing Weekend'
  AND description NOT LIKE '%labrise-telescope-day.jpg%';

-- Eclipse article: member-photographed crescent moon
UPDATE articles SET body = CONCAT(body,
  '<h3>Captured by members</h3><p>Crescent moon photographed by a UAS member.</p>',
  '<p><img src="/img/crescent-moon.jpg" alt="Crescent moon photographed by a UAS member"></p>')
WHERE title LIKE '%2027 Eclipse%'
  AND body NOT LIKE '%crescent-moon.jpg%';

-- October observing night: the shared-eyepiece moment
UPDATE events SET description = CONCAT(description,
  '<p><img src="/img/eclipse-night-observing.jpg" alt="Sharing the eyepiece at a UAS observing night"></p>')
WHERE title = 'October Public Observing Night'
  AND description NOT LIKE '%eclipse-night-observing.jpg%';
