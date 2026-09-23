-- Migration 051: project timelines (start dates + milestones).
-- milestones JSON already exists on projects (001) but was never used.
-- Shape: [{title, due_date (YYYY-MM-DD|null), done (bool)}], max 30 entries.

ALTER TABLE projects ADD COLUMN start_date DATE NULL AFTER deadline;
