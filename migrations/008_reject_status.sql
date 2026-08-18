-- Migration 008: Add 'rejected' status to users and members
-- Allows admins to explicitly reject membership applications

ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','pending','rejected') DEFAULT 'pending';
ALTER TABLE members MODIFY COLUMN status ENUM('active','inactive','suspended','pending','rejected') DEFAULT 'pending';
