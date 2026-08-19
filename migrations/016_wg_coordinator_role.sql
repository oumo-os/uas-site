-- Add 'coordinator' to working group member role ENUM
ALTER TABLE working_group_members MODIFY COLUMN role ENUM('chair','member','secretary','advisor','coordinator') DEFAULT 'member';
