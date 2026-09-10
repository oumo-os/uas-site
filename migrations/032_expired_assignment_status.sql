-- Migration 032: Allow 'expired' status on role_assignments.
-- expire_role_assignments() (called from institutional_health()) marks past-due
-- assignments as 'expired'. Without this value in the ENUM the UPDATE fails
-- under strict SQL mode and breaks the dashboard health endpoint.

ALTER TABLE role_assignments
  MODIFY COLUMN status ENUM('active','inactive','revoked','expired') DEFAULT 'active';

-- Heal rows left with an empty status by earlier runs on non-strict servers
-- (invalid ENUM values were silently truncated to '').
UPDATE role_assignments SET status = 'expired' WHERE status = '';
