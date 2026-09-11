-- Migration 033: Add event flyer images and partner events

-- Update existing events with flyer images
UPDATE events SET image_url = 'img/event-spaceweek-2026.jpg', category = 'conference'
WHERE title LIKE '%World Space Week 2026%';

UPDATE events SET image_url = 'img/event-nightwatch.jpg', category = 'observing'
WHERE title LIKE '%October Public Observing%';

UPDATE events SET image_url = 'img/event-workshop.jpg', category = 'workshop'
WHERE title LIKE '%Teachers Astronomy%';

-- Add Under the Skies Ugandan Edition (Dec 21, King's Park Arena)
INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, image_url, category, created_by)
VALUES (
  NULL,
  'Under the Skies — Ugandan Edition',
  '<p>Star gazing with telescopes, planetarium shows, water rocket launch demos, and space trivia. A public astronomy evening organised in partnership with Leo Sky Africa and NLL (Stare Lions League).</p><p><strong>Venue:</strong> King''s Park Arena<br><strong>Fee:</strong> UGX 30,000</p>',
  2,
  '2026-12-21 17:00:00',
  '2026-12-21 22:00:00',
  'King''s Park Arena, Kampala',
  300,
  'published',
  'img/event-undertheskies.jpg',
  'observing',
  3
);

-- Add EASS Workshop as a past/partner event
INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, image_url, category, created_by)
VALUES (
  NULL,
  'East African Astronomical Society Workshop 2026',
  '<p>The EAASW-2026 bootcamp: "Bridging the Gap: Using Astronomy as a driver for Regional Development". Organised by the East African Astronomical Society with DARA, QAD, AfAS, and SEISMIC.</p><p><strong>Venue:</strong> Kenyatta University, Kenya</p>',
  NULL,
  '2026-08-03 09:00:00',
  '2026-08-07 17:00:00',
  'Kenyatta University, Kenya',
  200,
  'published',
  'img/event-eass-2026.jpg',
  'conference',
  3
);

-- Add Nile Orbital High School Online Workshop as a past/partner event
INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, image_url, category, created_by)
VALUES (
  NULL,
  'Nileorbital Aerospace — High School Online Workshop',
  '<p>Online space programme for high school students (ages 13–19) covering satellite systems, Earth observation, and rocket dynamics. In partnership with Nileorbital Aerospace.</p><p><strong>Dates:</strong> 24–28 August 2026<br><strong>Sessions:</strong> 5–7 PM EAT, 2-hour sessions<br><strong>Investment:</strong> UGX 100,000</p>',
  NULL,
  '2026-08-24 17:00:00',
  '2026-08-28 19:00:00',
  'Online (Zoom)',
  100,
  'published',
  'img/event-nileorbital-2026.jpg',
  'workshop',
  3
);
