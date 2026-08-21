-- Migration 024: Membership dues tracking
-- Per-member annual dues with payment status

CREATE TABLE IF NOT EXISTS membership_dues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  period_year YEAR NOT NULL,
  amount_owed DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE NOT NULL,
  paid_date DATE NULL,
  status ENUM('pending','paid','overdue','waived') DEFAULT 'pending',
  notes TEXT,
  recorded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_member_period (member_id, period_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
