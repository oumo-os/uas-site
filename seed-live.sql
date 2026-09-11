-- ============================================================================
-- UAS LIVE BASELINE SEED — real members, real structure, grounded content
-- ============================================================================
-- One-shot import for the PRODUCTION database only. Run AFTER migrations
-- 001-032, via phpMyAdmin (Import tab). Safe to run on an empty database.
-- Run order inside this file respects foreign keys (checks stay ON).
--
-- Passwords: ALL accounts are created LOCKED ('NOT-SET-...'). Nobody can log
-- in until an admin issues a password via the admin panel (Members -> reset
-- password). Separation of duties: day-to-day administration is done through
-- the dedicated 'System Administrator' account (id 21, hidden), NOT through
-- any personal account. Bootstrap access: run the separate snippet for the
-- system administrator (kept out of git), log in, change it immediately,
-- then reset the other officers' passwords and share them out-of-band.
--
-- Conventions:
--   * Stand-in personal emails: firstname.lastname@astronomy.ug (replace with
--     real addresses when members provide them).
--   * Functional role inboxes (info@, secretary@, ...) are mailboxes to
--     create in cPanel — they are NOT user accounts and are NOT in this file.
--   * Vacant offices exist as roles with NO assignment ("open roles").
--   * No finance rows, no dues rows, no polls, no resolutions: the board
--     creates those through the app after launch.
-- ============================================================================

-- --------------------------------------------------------------------------
-- A. USERS (explicit IDs so later sections can reference them)
-- --------------------------------------------------------------------------
INSERT INTO users (id, name, email, password, institution, bio, status) VALUES
(1,  'Samuel Oumo', 'samuel.oumo@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Builds the Society website and membership tools.', 'active'),
(2,  'Obwengye Cosmus', 'cosmus.obwengye@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, NULL, 'active'),
(3,  'Christopher Byaruhanga Malcom', 'christopher.malcom@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, NULL, 'active'),
(4,  'Nsaale Ivan Kalule', 'ivan.kalule@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, NULL, 'active'),
(5,  'Shiella Splendour Aol', 'shiella.aol@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', 'Makerere University', 'Third-year Law student.', 'active'),
(6,  'Angel Uwera', 'angel.uwera@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', 'Makerere University', 'Graduate student in Agricultural Engineering.', 'active'),
(7,  'Reginald Busulwa', 'reginald.busulwa@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', 'WAVE', 'Water Resources Engineer.', 'active'),
(8,  'Kizito Mudambo', 'kizito.mudambo@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Photography and content creation.', 'active'),
(9,  'Derrick Asedri', 'derrick.asedri@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Electrical and Biomedical Engineering background.', 'active'),
(10, 'Kalyango Dan Maseke', 'dan.maseke@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, NULL, 'active'),
(11, 'Ronnie Rutogogo', 'ronnie.rutogogo@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', 'Makerere University', 'Physics and Biochemistry student.', 'active'),
(12, 'Zoora Harrison', 'zoora.harrison@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', 'StellarView', 'CEO of StellarView; applicant for Space Ecosystem Engagement lead.', 'active'),
(13, 'Raymond Anguzu', 'raymond.anguzu@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Lawyer; informal external legal support to the Society.', 'active'),
(14, 'Matovu John Baptist', 'john.matovu@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Runs the Society annual sky-viewing event.', 'active'),
(15, 'Nasasira Tony', 'tony.nasasira@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, NULL, 'active'),
(16, 'Niwazeirwe Octevious', 'octevious.niwazeirwe@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Founding Secretary (2023); Immediate Past Secretary (emeritus).', 'active'),
(17, 'Ndagire Gloria Linda', 'gloria.ndagire@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Founding Vice Chairperson (2023); emeritus member.', 'active'),
(18, 'Agaba Bruce', 'bruce.agaba@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Founding Treasurer (2023); emeritus member.', 'active'),
(19, 'Kyamanya Majda Bajwara', 'majda.kyamanya@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Founding Events Coordinator (2023); emeritus member.', 'active'),
(20, 'Sseggoma Timothy', 'timothy.sseggoma@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Founding signatory (2023); emeritus member.', 'active'),
-- Dedicated administration account (a role, not a person). Hidden everywhere.
(21, 'System Administrator', 'sysadmin@astronomy.ug', 'NOT-SET-USE-PASSWORD-RESET', NULL, 'Technical system administration account.', 'active');

-- --------------------------------------------------------------------------
-- B. MEMBERS (profile_visible=1 for officers, leads, emeritus, actives)
-- --------------------------------------------------------------------------
INSERT INTO members (user_id, membership_number, status, joined_date, approved_by, approved_at, profile_visible) VALUES
(1,  'UAS-2026-0001', 'active', CURDATE(), 1, NOW(), 1),
(2,  'UAS-2026-0002', 'active', CURDATE(), 1, NOW(), 1),
(3,  'UAS-2026-0003', 'active', CURDATE(), 1, NOW(), 1),
(4,  'UAS-2026-0004', 'active', CURDATE(), 1, NOW(), 1),
(5,  'UAS-2026-0005', 'active', CURDATE(), 1, NOW(), 1),
(6,  'UAS-2026-0006', 'active', CURDATE(), 1, NOW(), 1),
(7,  'UAS-2026-0007', 'active', CURDATE(), 1, NOW(), 1),
(8,  'UAS-2026-0008', 'active', CURDATE(), 1, NOW(), 1),
(9,  'UAS-2026-0009', 'active', CURDATE(), 1, NOW(), 1),
(10, 'UAS-2026-0010', 'active', CURDATE(), 1, NOW(), 1),
(11, 'UAS-2026-0011', 'active', CURDATE(), 1, NOW(), 1),
(12, 'UAS-2026-0012', 'active', CURDATE(), 1, NOW(), 0),
(13, 'UAS-2026-0013', 'active', CURDATE(), 1, NOW(), 1),
(14, 'UAS-2026-0014', 'active', CURDATE(), 1, NOW(), 1),
(15, 'UAS-2026-0015', 'active', CURDATE(), 1, NOW(), 0),
(16, 'UAS-2026-0016', 'active', CURDATE(), 1, NOW(), 1),
(17, 'UAS-2026-0017', 'active', CURDATE(), 1, NOW(), 1),
(18, 'UAS-2026-0018', 'active', CURDATE(), 1, NOW(), 1),
(19, 'UAS-2026-0019', 'active', CURDATE(), 1, NOW(), 1),
(20, 'UAS-2026-0020', 'active', CURDATE(), 1, NOW(), 1),
-- System administration account: deliberately hidden from all public listings
(21, 'UAS-2026-0021', 'active', CURDATE(), 1, NOW(), 0);

-- --------------------------------------------------------------------------
-- C. MEMBER CLASSES (add the two missing tiers from the Sept framework)
-- --------------------------------------------------------------------------
INSERT INTO roles (title, description, role_type, status) VALUES
('Affiliate Member', 'Affiliated individual member', 'member_class', 'active'),
('Corporate Member', 'Corporate / institutional partner member', 'member_class', 'active');

-- --------------------------------------------------------------------------
-- D. SYSTEM ADMINISTRATOR ROLE (all capabilities; holders in section G)
-- --------------------------------------------------------------------------
INSERT INTO roles (title, description, role_type, status, created_by) VALUES
('System Administrator', 'Full technical administration of the platform', 'administrative', 'active', 1);
SET @admin_role := LAST_INSERT_ID();
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT @admin_role, id, 1 FROM capabilities;

-- --------------------------------------------------------------------------
-- E. OFFICES, LEADS, EMERITUS (vacant = created but never assigned = open)
-- --------------------------------------------------------------------------
-- Board offices
INSERT INTO roles (title, description, role_type, scope, target, status, created_by) VALUES
('President', 'President of the Society', 'governance', 'committee', 'Board of Directors', 'active', 1),
('Vice President', 'Vice President of the Society (OPEN)', 'governance', 'committee', 'Board of Directors', 'active', 1),
('General Secretary', 'General Secretary of the Society', 'governance', 'committee', 'Board of Directors', 'active', 1),
('Treasurer', 'Treasurer of the Society (appointment pending formal confirmation)', 'governance', 'committee', 'Board of Directors', 'active', 1);
-- Executive offices
INSERT INTO roles (title, description, role_type, scope, target, status, created_by) VALUES
('Legal Affairs & Compliance Officer', 'Legal affairs and compliance', 'administrative', 'committee', 'Executive Management', 'active', 1),
('Programmes Officer', 'Programmes coordination', 'administrative', 'committee', 'Executive Management', 'active', 1),
('Programme Operations & Logistics Officer', 'Programme operations and logistics', 'administrative', 'committee', 'Executive Management', 'active', 1),
('PR, Media & Publicity Officer', 'Public relations, media and publicity', 'administrative', 'committee', 'Executive Management', 'active', 1),
('External Partnerships Officer', 'External partnerships', 'administrative', 'committee', 'Executive Management', 'active', 1),
('Values, Discipline & Society Culture Officer', 'Values, discipline and society culture', 'administrative', 'committee', 'Executive Management', 'active', 1),
('ICT & Internal Communications Officer', 'ICT and internal communications (OPEN)', 'administrative', 'committee', 'Executive Management', 'active', 1),
('Finance, Accountability & Resource Mobilisation Officer', 'Finance, accountability and resource mobilisation (OPEN)', 'administrative', 'committee', 'Executive Management', 'active', 1),
('Inventory, Assets & Procurement Officer', 'Inventory, assets and procurement (OPEN)', 'administrative', 'committee', 'Executive Management', 'active', 1);
-- Programme leads
INSERT INTO roles (title, description, role_type, scope, target, status, created_by) VALUES
('Programme Lead - Astronomy & Observation', 'Lead, Astronomy & Observation programme (OPEN)', 'governance', 'programme', 'Astronomy & Observation', 'active', 1),
('Programme Lead - Space Science', 'Lead, Space Science programme', 'governance', 'programme', 'Space Science', 'active', 1),
('Programme Lead - Astronomy Education & STEM', 'Lead, Astronomy Education & STEM programme (OPEN)', 'governance', 'programme', 'Astronomy Education & STEM', 'active', 1),
('Programme Lead - African & Ugandan Astronomy', 'Lead, African & Ugandan Astronomy programme (OPEN)', 'governance', 'programme', 'African & Ugandan Astronomy', 'active', 1),
('Programme Lead - Public Science & Space Culture', 'Lead, Public Science & Space Culture programme (OPEN)', 'governance', 'programme', 'Public Science & Space Culture', 'active', 1),
('Programme Lead - Space Ecosystem Engagement', 'Lead, Space Ecosystem Engagement programme (OPEN - application in progress)', 'governance', 'programme', 'Space Ecosystem Engagement', 'active', 1);
-- Emeritus
INSERT INTO roles (title, description, role_type, scope, status, created_by) VALUES
('Emeritus Board Member', 'Honorary emeritus member of the board community', 'governance', 'emeritus', 'active', 1),
('Immediate Past President', 'Immediate past President (emeritus, currently vacant)', 'governance', 'emeritus', 'active', 1),
('Immediate Past Secretary', 'Immediate past Secretary (emeritus)', 'governance', 'emeritus', 'active', 1);

-- --------------------------------------------------------------------------
-- F. CLASS LOOKUPS (IDs depend on migration history, so resolve by title)
-- --------------------------------------------------------------------------
SET @c_regular      := (SELECT id FROM roles WHERE title = 'Regular Member' AND role_type = 'member_class' LIMIT 1);
SET @c_student      := (SELECT id FROM roles WHERE title = 'Student Member' AND role_type = 'member_class' LIMIT 1);
SET @c_honorary     := (SELECT id FROM roles WHERE title = 'Honorary Member' AND role_type = 'member_class' LIMIT 1);
SET @c_affiliate    := (SELECT id FROM roles WHERE title = 'Affiliate Member' AND role_type = 'member_class' LIMIT 1);
SET @c_institutional:= (SELECT id FROM roles WHERE title = 'Institutional Member' AND role_type = 'member_class' LIMIT 1);
SET @c_corporate    := (SELECT id FROM roles WHERE title = 'Corporate Member' AND role_type = 'member_class' LIMIT 1);
SET @r_president    := (SELECT id FROM roles WHERE title = 'President' LIMIT 1);
SET @r_secretary    := (SELECT id FROM roles WHERE title = 'General Secretary' LIMIT 1);
SET @r_treasurer    := (SELECT id FROM roles WHERE title = 'Treasurer' LIMIT 1);
SET @r_legal        := (SELECT id FROM roles WHERE title = 'Legal Affairs & Compliance Officer' LIMIT 1);
SET @r_prog         := (SELECT id FROM roles WHERE title = 'Programmes Officer' LIMIT 1);
SET @r_ops          := (SELECT id FROM roles WHERE title = 'Programme Operations & Logistics Officer' LIMIT 1);
SET @r_pr           := (SELECT id FROM roles WHERE title = 'PR, Media & Publicity Officer' LIMIT 1);
SET @r_partner      := (SELECT id FROM roles WHERE title = 'External Partnerships Officer' LIMIT 1);
SET @r_values       := (SELECT id FROM roles WHERE title = 'Values, Discipline & Society Culture Officer' LIMIT 1);
SET @r_lead_space   := (SELECT id FROM roles WHERE title = 'Programme Lead - Space Science' LIMIT 1);
SET @r_emeritus     := (SELECT id FROM roles WHERE title = 'Emeritus Board Member' LIMIT 1);
SET @r_past_sec     := (SELECT id FROM roles WHERE title = 'Immediate Past Secretary' LIMIT 1);

-- --------------------------------------------------------------------------
-- G. ASSIGNMENTS (offices + Regular Member class for everyone)
-- --------------------------------------------------------------------------
INSERT INTO role_assignments (role_id, user_id, assigned_by, effective_from, status) VALUES
-- System administrators: President, General Secretary, and the dedicated
-- (hidden, non-personal) System Administrator account. Personal accounts
-- of technical staff deliberately hold NO admin rights.
(@admin_role, 2, 1, CURDATE(), 'active'),
(@admin_role, 3, 1, CURDATE(), 'active'),
(@admin_role, 21, 1, CURDATE(), 'active'),
-- Offices
(@r_president, 2, 1, CURDATE(), 'active'),
(@r_secretary, 3, 1, CURDATE(), 'active'),
(@r_treasurer, 4, 1, CURDATE(), 'active'),
(@r_legal, 5, 1, CURDATE(), 'active'),
(@r_prog, 6, 1, CURDATE(), 'active'),
(@r_ops, 7, 1, CURDATE(), 'active'),
(@r_pr, 8, 1, CURDATE(), 'active'),
(@r_partner, 9, 1, CURDATE(), 'active'),
(@r_values, 10, 1, CURDATE(), 'active'),
(@r_lead_space, 11, 1, CURDATE(), 'active'),
-- Emeritus
(@r_past_sec, 16, 1, CURDATE(), 'active'),
(@r_emeritus, 17, 1, CURDATE(), 'active'),
(@r_emeritus, 18, 1, CURDATE(), 'active'),
(@r_emeritus, 19, 1, CURDATE(), 'active'),
(@r_emeritus, 20, 1, CURDATE(), 'active'),
-- Member class: everyone is a Regular Member (no students named yet)
(@c_regular, 1, 1, CURDATE(), 'active'),
(@c_regular, 2, 1, CURDATE(), 'active'),
(@c_regular, 3, 1, CURDATE(), 'active'),
(@c_regular, 4, 1, CURDATE(), 'active'),
(@c_regular, 5, 1, CURDATE(), 'active'),
(@c_regular, 6, 1, CURDATE(), 'active'),
(@c_regular, 7, 1, CURDATE(), 'active'),
(@c_regular, 8, 1, CURDATE(), 'active'),
(@c_regular, 9, 1, CURDATE(), 'active'),
(@c_regular, 10, 1, CURDATE(), 'active'),
(@c_regular, 11, 1, CURDATE(), 'active'),
(@c_regular, 12, 1, CURDATE(), 'active'),
(@c_regular, 13, 1, CURDATE(), 'active'),
(@c_regular, 14, 1, CURDATE(), 'active'),
(@c_regular, 15, 1, CURDATE(), 'active'),
(@c_regular, 16, 1, CURDATE(), 'active'),
(@c_regular, 17, 1, CURDATE(), 'active'),
(@c_regular, 18, 1, CURDATE(), 'active'),
(@c_regular, 19, 1, CURDATE(), 'active'),
(@c_regular, 20, 1, CURDATE(), 'active'),
(@c_regular, 21, 1, CURDATE(), 'active');

-- --------------------------------------------------------------------------
-- H. LEADERSHIP COMMITTEES (drive the public About page)
-- --------------------------------------------------------------------------
INSERT INTO working_groups (name, description, type, status, created_by, term_start) VALUES
('Board of Directors', 'Governing board of the Society. President: Obwengye Cosmus (Confirmed). General Secretary: Christopher Byaruhanga Malcom (Confirmed). Treasurer: Nsaale Ivan Kalule (submitted - pending formal confirmation). Vice President: open.', 'committee', 'active', 1, CURDATE()),
('Executive Management', 'Officers running the Society day to day. Agreed: Legal Affairs & Compliance (Shiella Splendour Aol), Programmes (Angel Uwera), Operations & Logistics (Reginald Busulwa), PR Media & Publicity (Kizito Mudambo), External Partnerships (Derrick Asedri), Values Discipline & Culture (Kalyango Dan Maseke). Open: ICT & Internal Communications, Finance Accountability & Resource Mobilisation, Inventory Assets & Procurement.', 'committee', 'active', 1, CURDATE());
SET @wg_board := (SELECT id FROM working_groups WHERE name = 'Board of Directors' LIMIT 1);
SET @wg_exec  := (SELECT id FROM working_groups WHERE name = 'Executive Management' LIMIT 1);
INSERT INTO working_group_members (group_id, user_id, status, joined_date) VALUES
(@wg_board, 2, 'active', CURDATE()),
(@wg_board, 3, 'active', CURDATE()),
(@wg_board, 4, 'active', CURDATE()),
(@wg_exec, 5, 'active', CURDATE()),
(@wg_exec, 6, 'active', CURDATE()),
(@wg_exec, 7, 'active', CURDATE()),
(@wg_exec, 8, 'active', CURDATE()),
(@wg_exec, 9, 'active', CURDATE()),
(@wg_exec, 10, 'active', CURDATE());

-- --------------------------------------------------------------------------
-- I. PROGRAMMES (the six standing areas from the Sept 2026 framework)
-- --------------------------------------------------------------------------
INSERT INTO programmes (title, description, status, objectives, created_by) VALUES
('Astronomy & Observation', '<p>Practical astronomy: stargazing sessions, telescopes, astrophotography and celestial events, open to all Ugandans.</p>', 'active', 'Run regular public observing sessions. Build the Society telescope capacity. Programme Lead: to be appointed.', 1),
('Space Science', '<p>Astrophysics, planetary science and research engagement - the Society scientific core.</p>', 'active', 'Anchor a research-engaged community. Connect members with university and regional research. Programme Lead: Ronnie Rutogogo.', 1),
('Astronomy Education & STEM', '<p>School and university outreach, STEM education and hands-on workshops across Uganda.</p>', 'active', 'Take astronomy into schools and universities. Develop reusable workshop kits. Programme Lead: to be appointed.', 1),
('African & Ugandan Astronomy', '<p>Local astronomical heritage: cultural narratives, history and indigenous sky knowledge.</p>', 'active', 'Document Ugandan sky heritage. Weave culture into public programming. Programme Lead: to be appointed.', 1),
('Public Science & Space Culture', '<p>Public talks, science communication and community activities that keep space in public conversation.</p>', 'active', 'Hold regular public talks. Grow the Society public audience. Programme Lead: to be appointed.', 1),
('Space Ecosystem Engagement', '<p>Aerospace, Earth observation, satellites, robotics and the space economy - including the university and early-career pipeline.</p>', 'active', 'Map the Ugandan space ecosystem. Build the student and early-career pipeline. Programme Lead: to be appointed.', 1);
SET @p_space := (SELECT id FROM programmes WHERE title = 'Space Science' LIMIT 1);
INSERT INTO programme_members (programme_id, user_id, role_in_programme, status, joined_date) VALUES
(@p_space, 11, 'Programme Lead', 'active', CURDATE());

-- --------------------------------------------------------------------------
-- J. EVENTS (grounded; officers adjust dates/venues as plans firm up)
-- --------------------------------------------------------------------------
INSERT INTO events (programme_id, title, description, organizer_id, date, end_date, location, capacity, status, created_by) VALUES
(NULL, 'World Space Week 2026 - Uganda', '<p>Join the global celebration of space science and technology. UAS marks World Space Week (4-10 October) with public activities across Kampala.</p>', 2, '2026-10-04 09:00:00', '2026-10-10 18:00:00', 'Kampala - venues to be confirmed', 500, 'published', 3),
(NULL, 'October Public Observing Night', '<p>An evening under the stars with Society telescopes. Beginners welcome; telescopes and guidance provided.</p>', 2, '2026-10-02 19:00:00', '2026-10-02 22:00:00', 'Kampala - venue to be confirmed', 200, 'published', 3),
(NULL, 'Annual Sky-Viewing Event', '<p>The Society flagship sky-viewing gathering, coordinated with Matovu John Baptist. Date to be confirmed.</p>', 2, '2026-12-12 19:00:00', '2026-12-12 23:00:00', 'To be confirmed', 300, 'published', 3),
(NULL, 'Teachers Astronomy Bootcamp', '<p>A hands-on workshop giving teachers reusable astronomy activities for their classrooms. Details to be announced.</p>', 3, '2026-11-14 09:00:00', '2026-11-14 13:00:00', 'To be confirmed', 60, 'draft', 3),
(NULL, 'Society Planning Session', '<p>Internal planning session for officers and programme teams. Details to be announced.</p>', 3, '2027-01-16 10:00:00', '2027-01-16 13:00:00', 'To be confirmed', 40, 'draft', 3);

-- --------------------------------------------------------------------------
-- K. ARTICLES (grounded launch content)
-- --------------------------------------------------------------------------
INSERT INTO articles (author_id, title, body, category, tags, status, approved_by, approved_at, published_at) VALUES
(3, 'UAS Adopts a New Governance Framework', '<p>The Uganda Astronomical Society has formalised a new executive structure, with confirmed officers, defined programme areas and open seats for new volunteers.</p><p>Obwengye Cosmus continues as President, with Christopher Byaruhanga Malcom as General Secretary. Six standing programmes now anchor all Society activity, and recruitment is open for the remaining officer and programme-lead seats.</p>', 'announcement', '["governance","announcement"]', 'published', 3, NOW(), NOW()),
(3, 'Uganda Looks Up: World Space Week 2026', '<p>Every October, the world celebrates space science and technology during World Space Week. UAS joins the celebration with public activities across Kampala.</p><p>Details of this year venues and programme will be announced here and on the Events page.</p>', 'announcement', '["space-week","events"]', 'published', 3, NOW(), NOW()),
(3, 'The 2027 Eclipse: What Uganda Will See', '<p>On 2 August 2027 a total solar eclipse crosses northern Africa. Uganda lies just outside the path of totality, but a substantial partial eclipse will be visible across the whole country.</p><p>Never look at the partial phases without certified eclipse glasses. UAS will run public viewing guidance closer to the event.</p>', 'educational', '["eclipse","observing"]', 'published', 3, NOW(), NOW()),
(3, 'How to Join the Uganda Astronomical Society', '<p>Membership is open to everyone curious about the night sky: students, professionals, institutions and supporters.</p><p>Choose the membership class that fits you on the Join page and register online. Fees for 2026 are published per class; students join free.</p>', 'article', '["membership","join"]', 'published', 3, NOW(), NOW()),
(1, 'From WhatsApp Group to Institution: Rebuilding UAS', '<p>Real public enthusiasm sat on almost no functioning structure. Over July to September 2026 the Society converted that informality into named offices, mandates and accountable programmes.</p><p>This site is part of that rebuild: everything the Society does - members, programmes, events, finances, governance - now runs here, in the open.</p>', 'article', '["governance","history"]', 'published', 3, NOW(), NOW());

-- --------------------------------------------------------------------------
-- L. DUES SCHEDULE 2026 (signed-off rates; no dues rows = nobody billed)
-- --------------------------------------------------------------------------
INSERT INTO dues_schedule (role_id, amount, period_year, description, created_by) VALUES
(@c_student, 0, 2026, 'Students join free.', 1),
(@c_honorary, 0, 2026, 'Honorary membership carries no dues.', 1),
(@c_regular, 35000, 2026, 'Annual dues, Regular Member.', 1),
(@c_affiliate, 20000, 2026, 'Annual dues, Affiliate Member.', 1),
(@c_institutional, 100000, 2026, 'Annual dues, Institutional Member.', 1),
(@c_corporate, 150000, 2026, 'Annual dues, Corporate Member.', 1);

-- --------------------------------------------------------------------------
-- M. CURATED EXTERNAL LINKS (public News page resources)
-- --------------------------------------------------------------------------
INSERT INTO useful_links (title, url, category, description, external_organization, status) VALUES
('World Space Week Association', 'https://www.worldspaceweek.org', 'organization', 'Global coordinators of World Space Week, held 4-10 October each year.', 'World Space Week', 'active'),
('African Astronomical Society', 'https://www.africanastronomicalsociety.org', 'organization', 'Continental body advancing astronomy across Africa.', 'AfAS', 'active'),
('Space Generation Advisory Council', 'https://spacegeneration.org', 'organization', 'Global network of students and young professionals in the space sector.', 'SGAC', 'active'),
('International Astronomical Union', 'https://www.iau.org', 'organization', 'The global authority for professional astronomy.', 'IAU', 'active'),
('NASA', 'https://www.nasa.gov', 'organization', 'Missions, imagery and open science resources.', 'NASA', 'active'),
('Stellarium', 'https://stellarium.org', 'tool', 'Free planetarium software - plan what to observe before an outing.', 'Stellarium', 'active');

-- --------------------------------------------------------------------------
-- N. MANDATE-FOCUSED COMMITTEE DESCRIPTIONS
-- (process history lives in resolutions + articles below, not here)
-- --------------------------------------------------------------------------
UPDATE working_groups SET description = 'The governing board of the Society. It sets strategy, guards the constitution, approves policy and finance, and holds the executive to account through the General Secretary.' WHERE name = 'Board of Directors';
UPDATE working_groups SET description = 'The officers who run the Society day to day - programmes, operations and logistics, communications, finance, legal, partnerships, assets and culture - reporting through the General Secretary to the Board.' WHERE name = 'Executive Management';

-- --------------------------------------------------------------------------
-- O. RATIFYING RESOLUTIONS (draft: the board votes them through in-app)
-- --------------------------------------------------------------------------
INSERT INTO resolutions (code, title, description, type, status, proposed_by, quorum, majority) VALUES
('UAS-BRD-2026-001', 'Confirmation of the President', 'Under the September 2026 governance framework, Obwengye Cosmus - the founding chair and only founding officer to remain in post - is confirmed to continue as President. This resolution formally records that decision.', 'appointment', 'draft', 3, 2, 'simple'),
('UAS-BRD-2026-002', 'Appointment of the Treasurer', 'Nsaale Ivan Kalule, a founding signatory, submitted his name for Treasurer and is hereby appointed to the office with full financial responsibility.', 'appointment', 'draft', 3, 2, 'simple'),
('UAS-BRD-2026-003', 'Confirmation of Executive Officers', 'Confirms the six agreed executive officers: Legal Affairs & Compliance (Shiella Splendour Aol), Programmes (Angel Uwera), Operations & Logistics (Reginald Busulwa), PR Media & Publicity (Kizito Mudambo), External Partnerships (Derrick Asedri), Values Discipline & Culture (Kalyango Dan Maseke).', 'appointment', 'draft', 3, 2, 'simple');
SET @res_pres := (SELECT id FROM resolutions WHERE code = 'UAS-BRD-2026-001' LIMIT 1);
SET @res_treas := (SELECT id FROM resolutions WHERE code = 'UAS-BRD-2026-002' LIMIT 1);
SET @res_exec := (SELECT id FROM resolutions WHERE code = 'UAS-BRD-2026-003' LIMIT 1);
INSERT INTO resolution_changes (resolution_id, change_type, target_type, target_id, payload) VALUES
(@res_pres, 'appoint', 'role', NULL, '{"role_title": "President", "user_id": 2}'),
(@res_treas, 'appoint', 'role', NULL, '{"role_title": "Treasurer", "user_id": 4}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "Legal Affairs & Compliance Officer", "user_id": 5}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "Programmes Officer", "user_id": 6}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "Programme Operations & Logistics Officer", "user_id": 7}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "PR, Media & Publicity Officer", "user_id": 8}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "External Partnerships Officer", "user_id": 9}'),
(@res_exec, 'appoint', 'role', NULL, '{"role_title": "Values, Discipline & Society Culture Officer", "user_id": 10}');

-- --------------------------------------------------------------------------
-- P. PERSONNEL ANNOUNCEMENTS (public record of the same decisions)
-- --------------------------------------------------------------------------
INSERT INTO articles (author_id, title, body, category, tags, status, approved_by, approved_at, published_at) VALUES
(3, 'President Confirmed to Continue Leading the Society', '<p>Under the September 2026 governance framework, Obwengye Cosmus has been confirmed to continue as President of the Uganda Astronomical Society.</p><p>Cosmus founded the Society in 2023 and remains the only founding officer to continue in post through the reorganisation. A formal ratifying resolution is before the board.</p>', 'announcement', '["governance","announcement"]', 'published', 3, NOW(), NOW()),
(3, 'Treasurer Nominated as Executive Team Takes Shape', '<p>Nsaale Ivan Kalule, a founding signatory, has been nominated as Treasurer, and six executive officers have been agreed across legal, programmes, operations, publicity, partnerships and culture.</p><p>Three officer seats and five programme-lead seats remain open - members interested in serving should see the Join page.</p>', 'announcement', '["governance","announcement"]', 'published', 3, NOW(), NOW());

-- --------------------------------------------------------------------------
-- Q. OPENING FINANCES: founding dues paid + 2026 working budget
-- --------------------------------------------------------------------------
-- All 9 committee members (Board + Executive, users 2-10) paid UGX 50,000
-- founding contributions, effective 31 Aug 2026. Mirrors the in-app pay flow:
-- a paid dues row + a linked approved income record each. No receivables:
-- nobody owes anything at launch.
SET @c_regular := (SELECT id FROM roles WHERE title = 'Regular Member' AND role_type = 'member_class' LIMIT 1);
INSERT INTO membership_dues (member_id, role_id, period_year, amount_owed, amount_paid, due_date, paid_date, status, notes, recorded_by)
SELECT m.id, @c_regular, 2026, 50000, 50000, '2026-08-31', '2026-08-31', 'paid', 'Founding contribution recorded at launch', 3
FROM members m WHERE m.user_id BETWEEN 2 AND 10;
INSERT INTO financial_records (type, amount, category, description, member_id, record_date, status, recorded_by)
SELECT 'income', 50000, 'membership', 'Regular Member dues payment 2026', m.id, '2026-08-31', 'approved', 3
FROM members m WHERE m.user_id BETWEEN 2 AND 10;
UPDATE membership_dues md
JOIN financial_records fr ON fr.member_id = md.member_id AND fr.type = 'income' AND fr.record_date = '2026-08-31' AND fr.amount = 50000
SET md.payment_record_id = fr.id
WHERE md.period_year = 2026 AND md.status = 'paid' AND md.payment_record_id IS NULL;

-- 2026 working budget (active; officers adjust through the app).
-- Income: confirmed dues (9 x 50k) + a WSW sponsorship target.
-- Expenses: grounded figures (CLG ~500k per the framework discussion).
SET @ev_wsw := (SELECT id FROM events WHERE title LIKE 'World Space Week%' LIMIT 1);
SET @p_edu := (SELECT id FROM programmes WHERE title = 'Astronomy Education & STEM' LIMIT 1);
INSERT INTO budget_items (title, description, type, amount, category, programme_id, project_id, event_id, fiscal_year, status, created_by) VALUES
('Membership dues - founding contributions', 'Confirmed: 9 committee members x UGX 50,000', 'income', 450000, 'membership', NULL, NULL, NULL, 2026, 'active', 3),
('World Space Week sponsorship target', 'Target: sponsor contributions toward October activities', 'income', 5000000, 'sponsorship', NULL, NULL, @ev_wsw, 2026, 'active', 3),
('CLG registration and legal', 'Company Limited by Guarantee registration with in-group legal support', 'expense', 500000, 'administration', NULL, NULL, NULL, 2026, 'active', 3),
('World Space Week activities', 'Venues, materials and logistics for the October programme', 'expense', 2500000, 'event', NULL, NULL, @ev_wsw, 2026, 'active', 3),
('Monthly observing nights (Q4)', 'Logistics for public observing nights, Oct-Dec', 'expense', 600000, 'event', NULL, NULL, NULL, 2026, 'active', 3),
('School outreach kits', 'Reusable workshop kits for school visits', 'expense', 800000, 'outreach', @p_edu, NULL, NULL, 2026, 'active', 3),
('Teacher bootcamp workshop', 'Hands-on training for secondary school science teachers', 'expense', 750000, 'training', @p_edu, NULL, NULL, 2026, 'active', 3),
('Telescope maintenance', 'Maintenance kits and servicing', 'expense', 400000, 'equipment', NULL, NULL, NULL, 2026, 'active', 3),
('Website hosting and domain', 'Hosting, domain and site running costs', 'expense', 350000, 'communications', NULL, NULL, NULL, 2026, 'active', 3);

-- End of live baseline seed.
