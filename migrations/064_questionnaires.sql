-- Migration 064: questionnaires (data-collection forms) attachable to events
-- and articles, plus their answers.
-- A questionnaire belongs to exactly one event or one article (nullable FKs,
-- cascade on delete). Answers accept logged-in members (user_id) and guests
-- (name + email); one response per member or per guest email (re-submits
-- overwrite), so organizers get a clean per-person dataset.
-- IDEMPOTENT: tables use IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS questionnaires (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(500) NOT NULL,
  description TEXT NULL,
  fields JSON NOT NULL,
  event_id INT NULL,
  article_id INT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS questionnaire_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  questionnaire_id INT NOT NULL,
  user_id INT NULL,
  name VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  answers JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_q_user (questionnaire_id, user_id),
  UNIQUE KEY uq_q_email (questionnaire_id, email),
  FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
