-- Migration 011: Meetings
-- Governance meetings: agenda, attendance, minutes, decisions, action items.

CREATE TABLE IF NOT EXISTS meetings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  meeting_type ENUM('board','general','committee','working_group','other') DEFAULT 'board',
  description TEXT,
  scheduled_at DATETIME,
  location VARCHAR(255),
  agenda JSON NULL,          -- [{title, owner_id, duration_min}]
  minutes LONGTEXT,          -- formatted minutes text
  decisions JSON NULL,       -- [{text, assignee_id, due_date}]
  status ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meeting_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('attended','absent','excused','apology') DEFAULT 'absent',
  notes VARCHAR(500),
  UNIQUE KEY uq_meeting_user (meeting_id, user_id),
  FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Capabilities for the meetings module
INSERT INTO capabilities (slug, name, description, category) VALUES
('meetings.create', 'Create Meetings', 'Schedule meetings and draft agendas', 'governance'),
('meetings.manage', 'Manage Meetings', 'Edit meetings, change status, manage attendance', 'governance'),
('meetings.record', 'Record Minutes', 'Post minutes and decisions for a meeting', 'governance')
ON DUPLICATE KEY UPDATE name = VALUES(name);