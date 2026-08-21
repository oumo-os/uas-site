-- Migration 025: Add website column to users table
ALTER TABLE users ADD COLUMN website VARCHAR(500) NULL AFTER location;
