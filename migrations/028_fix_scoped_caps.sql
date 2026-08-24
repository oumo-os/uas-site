-- Migration 028: Fix scoped capabilities for Programme Lead role
-- Updates existing role_capabilities entries for Programme Lead to include scope info.
-- Programme Lead caps should be scoped to the programme they manage.

-- For each Programme Lead role assignment, scope their capabilities to the programme
-- that matches the role's target (if the target programme exists).
UPDATE role_capabilities rc
JOIN roles r ON r.id = rc.role_id
JOIN programmes p ON p.title = r.target
SET rc.scope_type = 'programme', rc.scope_id = p.id
WHERE r.title = 'Programme Lead'
  AND r.scope = 'programme'
  AND r.target IS NOT NULL
  AND rc.scope_type IS NULL
  AND rc.scope_id IS NULL;

-- Also scope Education WG Lead caps to the working group's programme (if linked).
UPDATE role_capabilities rc
JOIN roles r ON r.id = rc.role_id
JOIN working_groups wg ON wg.name = r.target
SET rc.scope_type = 'programme', rc.scope_id = wg.programme_id
WHERE r.title = 'Education WG Lead'
  AND r.scope = 'working_group'
  AND r.target IS NOT NULL
  AND wg.programme_id IS NOT NULL
  AND rc.scope_type IS NULL
  AND rc.scope_id IS NULL;
