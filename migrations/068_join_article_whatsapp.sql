-- Migration 068: link the public WhatsApp group in the How-to-Join article.
-- Inserted once, just before the sign-off (guarded by NOT LIKE so re-runs
-- and future dashboard edits never duplicate it).

UPDATE articles
SET body = REPLACE(body,
  '<p>We will see you at Kololo.</p>',
  '<p>We will see you at Kololo.</p><p>Can''t wait until the next observing night? Come and meet the community now in our public WhatsApp group: <a href="https://chat.whatsapp.com/LUvcNReQp84LQ0bkbuOCt7">UAS WhatsApp group</a>. Bring your questions — curiosity is the only entry requirement.</p>')
WHERE id = 4
  AND body NOT LIKE '%chat.whatsapp.com/LUvcNReQp84LQ0bkbuOCt7%';
