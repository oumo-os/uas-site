-- Set proper scope values on existing seeded roles

-- Governance roles
UPDATE roles SET scope = 'board' WHERE title = 'Board Director' AND scope IS NULL;

-- Administrative / Committee roles
UPDATE roles SET scope = 'committee' WHERE title = 'PR Director' AND scope IS NULL;

-- Working Group roles
UPDATE roles SET scope = 'working_group' WHERE title = 'Education WG Lead' AND scope IS NULL;

-- Programme roles
UPDATE roles SET scope = 'programme' WHERE title = 'Programme Lead' AND scope IS NULL;

-- Member baseline stays scope = NULL (general/institutional)
