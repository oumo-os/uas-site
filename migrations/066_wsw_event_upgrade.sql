-- Migration 066: upgrade event 26 (Uganda National Space Week 2026).
-- Title case, trimmed venue, full flagship description, confirm message
-- with WhatsApp group + contact, tags, plus the pre-event questionnaire.
-- Precedence on conflict: Malcolm’s draft event is final truth, over the
-- flyer and the older run-of-show doc. Deterministic re-runnable.

UPDATE events SET title = 'Uganda National Space Week 2026',
  location = 'COSIS Block A, Makerere University',
  description = '<p>On <strong>Saturday, 10 October 2026</strong>, COSIS Block A at Makerere University becomes the meeting point for everyone building Uganda''s space future — engineers and astronomers, students and startups, the curious and the convinced. This is the flagship physical day of Uganda National Space Week 2026, held under the theme <strong>“Rocket Revolution: Engineering Uganda''s Space Future.”</strong></p>
<p>Big ideas. Real people. A stronger ecosystem. The day runs on a simple operating principle: short presentations, protected transition time, and a programme designed to connect people and capabilities — not simply to deliver talks.</p>
<h2>Explore. Connect. Build.</h2>
<p><strong>Explore</strong> — hands-on exhibits, demos and real projects, from Earth observation and GIS to rocket systems, robotics and space medicine. <strong>Connect</strong> — meet students, experts and space innovators from across the ecosystem. <strong>Build</strong> — join the founding conversation of the Inter-University Amateur Aerospace &amp; Rocket Club.</p>
<h2>Day at a glance</h2>
<ul>
<li><strong>09:00 – Networking.</strong> Early conversations; student and team mapping.</li>
<li><strong>10:15 – Welcome &amp; orientation.</strong> Housekeeping, programme overview and safety.</li>
<li><strong>10:30 – Earth &amp; Space for Uganda.</strong> Earth observation, GIS, satellites and geospatial intelligence.</li>
<li><strong>11:30 – Rocket Revolution I.</strong> Aerospace engineering and rocket systems with NOA''s Quest, Captain Simon and KSI.</li>
<li><strong>12:30 – Exhibition, lunch &amp; networking.</strong> Stalls stay active; no formal stage programme.</li>
<li><strong>13:30 – Rocket Revolution II: Beyond Earth.</strong> Robotics, planetary exploration, human exploration and space medicine with StellarView and invited contributors.</li>
<li><strong>14:30 – Space-STEAM Innovation Showcase.</strong> Live demos and audience interaction with StepQuiz, Bracelex and student innovators.</li>
<li><strong>15:30 – Future of Uganda''s Space Economy.</strong> Panel on capability, institutions, industry and research.</li>
<li><strong>16:15 – Inter-University Aerospace &amp; Rocketry Forum.</strong> The founding conversation: mapping capabilities and next steps.</li>
<li><strong>17:00 – Close.</strong></li>
</ul>
<h2>Who should come</h2>
<p>Students — secondary and university — educators, engineers, researchers, entrepreneurs, media, and anyone who has ever looked up and wondered. No background needed; curiosity counts as a qualification. Exhibitors each run a simple display: who they are, what they''re building, what they need, and how to join.</p>
<h2>Good to know</h2>
<p>The programme starts at 09:00 sharp — arrive in good time. Presenters: please arrive 45 minutes before your slot, with slide decks ready to copy to the event laptop before 10:00. Exhibitors: please keep displays up through the exhibition windows — the networking depends on it.</p>
<p>Join the public UAS WhatsApp group for updates and community: <a href="https://chat.whatsapp.com/LUvcNReQp84LQ0bkbuOCt7">UAS WhatsApp group</a>. Questions? Reach out on WhatsApp: 0776 889343.</p>
<p>See. Learn. Build. Together.</p>',
  confirm_message = 'You''re registered for Uganda National Space Week 2026 — see you Saturday 10 Oct at COSIS Block A, Makerere University. Programme runs 09:00–17:00; arrive in good time ahead of the 09:00 start. Join the UAS WhatsApp group for updates: https://chat.whatsapp.com/LUvcNReQp84LQ0bkbuOCt7 — questions? WhatsApp 0776 889343.',
  tags = '["space-week", "makerere", "outreach", "observing", "stem"]'
WHERE id = 26;

INSERT INTO questionnaires (title, description, fields, event_id, article_id, created_by)
SELECT 'Before Saturday — a few quick questions', 'Helps us prepare badges and seating. Takes a minute.', '[{"label": "How did you hear about this event?", "type": "select", "required": true, "options": ["WhatsApp", "UAS WhatsApp group", "X (Twitter)", "Facebook", "Instagram", "Friend or colleague", "University or school notice", "UAS website", "Other"]}, {"label": "Which best describes you?", "type": "select", "required": true, "options": ["Secondary school student", "University student", "Educator or teacher", "Engineer or technical professional", "Researcher", "Entrepreneur or startup", "Media", "Space enthusiast", "Other"]}, {"label": "University, school or organisation", "type": "text", "required": false, "options": []}, {"label": "Phone or WhatsApp number for day-of updates", "type": "text", "required": false, "options": []}, {"label": "Which part of the day interests you most?", "type": "select", "required": false, "options": ["Morning sessions", "Rocket Revolution talks", "Exhibitions and demos", "Space economy panel", "Inter-university forum"]}, {"label": "Anything we should know \u2014 access needs, dietary notes, expectations?", "type": "textarea", "required": false, "options": []}]', 26, NULL, 3
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM questionnaires WHERE event_id = 26);
