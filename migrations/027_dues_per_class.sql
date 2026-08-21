-- Migration 027: Dues per member class role
-- Add role_id to membership_dues to link dues to a specific member_class role

ALTER TABLE membership_dues ADD COLUMN role_id INT NULL AFTER member_id;
ALTER TABLE membership_dues ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
