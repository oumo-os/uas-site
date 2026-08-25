-- Migration 031: Detail page overhaul — add image, category, tags to events and programmes

-- Events: image, category, tags for rich detail pages
ALTER TABLE events ADD COLUMN image_url VARCHAR(500) AFTER location;
ALTER TABLE events ADD COLUMN category VARCHAR(100) AFTER image_url;
ALTER TABLE events ADD COLUMN tags JSON AFTER category;

-- Programmes: image, category for rich detail pages
ALTER TABLE programmes ADD COLUMN image_url VARCHAR(500) AFTER description;
ALTER TABLE programmes ADD COLUMN category VARCHAR(100) AFTER image_url;
