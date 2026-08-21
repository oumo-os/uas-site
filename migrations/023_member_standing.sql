-- Migration 023: Add member standing column
-- good_standing = dues paid, no disciplinary issues
-- restricted = overdue dues or disciplinary action

ALTER TABLE members ADD COLUMN standing ENUM('good_standing','restricted') DEFAULT 'good_standing' AFTER status;
