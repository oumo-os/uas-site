-- Migration 009: Missing capabilities + scoped capabilities
-- Adds 5 missing capabilities and scope columns to role_capabilities

-- 1. Insert missing capabilities
INSERT INTO capabilities (slug, name, description, category) VALUES
('events.rsvp', 'RSVP to Events', 'Register for events', 'events'),
('events.manage_rsvps', 'Manage Event RSVPs', 'Manage event registrations and waitlists', 'events'),
('partners.manage', 'Manage Partners', 'Create and manage partner organizations', 'admin'),
('links.manage', 'Manage Links', 'Create and manage useful links', 'admin'),
('assignments.manage', 'Manage Assignments', 'Create and manage task assignments', 'admin'),
('calendar.manage', 'Manage Calendar', 'Create and manage calendar items', 'admin'),
('assignments.create', 'Create Assignments', 'Create and assign tasks to members', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 2. Add scope columns to role_capabilities
-- scope_type: 'programme', 'event', 'project', NULL (global)
-- scope_id: the ID of the specific resource, NULL (global)
ALTER TABLE role_capabilities
  ADD COLUMN scope_type VARCHAR(50) DEFAULT NULL AFTER granted_by,
  ADD COLUMN scope_id INT DEFAULT NULL AFTER scope_type;

-- Update unique key to include scope
ALTER TABLE role_capabilities
  DROP INDEX uq_role_cap,
  ADD UNIQUE KEY uq_role_cap_scope (role_id, capability_id, scope_type, scope_id);

-- 3. Rebuild Board Director role capabilities (now that missing slugs exist)
-- This will be done via PHP seed script, not SQL, since the IDs are dynamic
