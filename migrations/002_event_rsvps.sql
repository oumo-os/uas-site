-- UAS Institutional Platform — Migration 002: Event RSVPs
-- Run: mysql -u root uas_platform < migrations/002_event_rsvps.sql

CREATE TABLE IF NOT EXISTS event_registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('registered','attended','cancelled') DEFAULT 'registered',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_user (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Capabilities for event participation
INSERT IGNORE INTO capabilities (slug, name, description)
VALUES
  ('events.rsvp', 'RSVP to events', 'Register interest/attendance for an event'),
  ('events.manage_rsvps', 'Manage event registrations', 'View and manage attendee registrations');
