-- Migration 052: set member privileges correctly.
--
-- Audit findings (Sept 2026) that this fixes:
--  1. Ordinary members held ONLY finance.view (via class). They could not
--     submit articles, propose events, RSVP (!), or upload documents.
--  2. Office roles (Treasurer, Secretary, Programmes Officer, PR, …) held
--     titles with ZERO capabilities — only System Administrator holders
--     could operate anything.
--  3. mail.broadcast (050) was granted to no role at all.
--  4. Programme Lead roles had no scoped caps (worked only via fallbacks).
--
-- All grants are idempotent (NOT EXISTS guards) and attach to ROLES, so
-- future office holders inherit them. Everything stays revocable in-app
-- via Admin → Roles. No grants are removed here.
--
-- Conventions used: global grants have NULL scope (matching the existing
-- unique key uq_role_cap_scope); programme-scoped grants carry
-- scope_type='programme' + the programme id resolved by title.

-- --------------------------------------------------------------------------
-- 1. Baseline member capabilities → every active member_class role.
--    (Mirrors the platform's baseline: submit, propose, RSVP, upload.)
-- --------------------------------------------------------------------------
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1
FROM roles r
CROSS JOIN capabilities c
WHERE r.role_type = 'member_class' AND r.status = 'active'
  AND c.slug IN ('articles.submit', 'events.create', 'events.rsvp', 'documents.upload', 'reports.create')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- --------------------------------------------------------------------------
-- 2. mail.broadcast → System Administrator (restores the all-caps invariant;
--    seed-live granted "all capabilities", but 050 came after the seed).
-- --------------------------------------------------------------------------
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, (SELECT id FROM capabilities WHERE slug = 'mail.broadcast' LIMIT 1), 1
FROM roles r
WHERE r.title = 'System Administrator' AND r.status = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id
      AND rc.capability_id = (SELECT id FROM capabilities WHERE slug = 'mail.broadcast' LIMIT 1)
      AND rc.scope_type IS NULL
  );

-- --------------------------------------------------------------------------
-- 3. Office mandates (global). One block per office role; missing roles
--    (renamed/removed) simply match nothing — safe to rerun any time.
-- --------------------------------------------------------------------------

-- General Secretary: membership, meetings, records.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'General Secretary' AND r.status = 'active'
  AND c.slug IN ('members.view', 'members.approve', 'members.manage',
    'meetings.create', 'meetings.manage', 'meetings.record',
    'documents.upload', 'documents.review', 'documents.approve', 'documents.publish',
    'mail.broadcast')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- Treasurer (+ vacant Finance Officer): society money + dues visibility.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title IN ('Treasurer', 'Finance, Accountability & Resource Mobilisation Officer') AND r.status = 'active'
  AND c.slug IN ('finance.view', 'finance.record', 'finance.approve', 'members.view')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- Legal Affairs & Compliance Officer: records + partnerships paperwork.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'Legal Affairs & Compliance Officer' AND r.status = 'active'
  AND c.slug IN ('documents.upload', 'documents.review', 'documents.approve', 'documents.publish', 'partners.manage')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- Programmes Officer: coordinates all programmes, projects, events, reports.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'Programmes Officer' AND r.status = 'active'
  AND c.slug IN ('programmes.create', 'programmes.manage', 'programmes.approve',
    'projects.create', 'projects.manage', 'projects.approve',
    'events.create', 'events.approve', 'events.publish', 'events.manage_rsvps',
    'reports.create', 'reports.review', 'mail.broadcast')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- Programme Operations & Logistics Officer: runs event logistics + tasks.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'Programme Operations & Logistics Officer' AND r.status = 'active'
  AND c.slug IN ('events.create', 'events.manage_rsvps',
    'assignments.create', 'assignments.manage', 'calendar.manage', 'documents.upload')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- PR, Media & Publicity Officer: public comms + newsletters.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'PR, Media & Publicity Officer' AND r.status = 'active'
  AND c.slug IN ('articles.submit', 'articles.review', 'articles.approve', 'articles.publish',
    'events.publish', 'links.manage', 'mail.broadcast')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- External Partnerships Officer: partners + links.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'External Partnerships Officer' AND r.status = 'active'
  AND c.slug IN ('partners.manage', 'links.manage', 'documents.upload')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- Values, Discipline & Society Culture Officer: membership oversight.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'Values, Discipline & Society Culture Officer' AND r.status = 'active'
  AND c.slug IN ('members.view', 'members.manage')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- ICT & Internal Communications Officer: comms channel + uploads.
INSERT INTO role_capabilities (role_id, capability_id, granted_by)
SELECT r.id, c.id, 1 FROM roles r CROSS JOIN capabilities c
WHERE r.title = 'ICT & Internal Communications Officer' AND r.status = 'active'
  AND c.slug IN ('mail.broadcast', 'documents.upload')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id AND rc.scope_type IS NULL
  );

-- --------------------------------------------------------------------------
-- 4. Programme Lead roles: scoped caps on their own programme, resolved by
--    the role target (= programme title). Applies to filled AND vacant
--    lead seats, so incoming leads inherit automatically.
-- --------------------------------------------------------------------------
INSERT INTO role_capabilities (role_id, capability_id, scope_type, scope_id, granted_by)
SELECT r.id, c.id, 'programme', p.id, 1
FROM roles r
JOIN programmes p ON p.title = r.target AND p.status = 'active'
CROSS JOIN capabilities c
WHERE r.title LIKE 'Programme Lead - %' AND r.status = 'active'
  AND c.slug IN ('programmes.manage',
    'projects.create', 'projects.manage', 'projects.approve',
    'events.create', 'events.approve', 'events.publish', 'events.manage_rsvps',
    'reports.create')
  AND NOT EXISTS (
    SELECT 1 FROM role_capabilities rc
    WHERE rc.role_id = r.id AND rc.capability_id = c.id
      AND rc.scope_type = 'programme' AND rc.scope_id = p.id
  );
