-- 014_delegations.sql
-- Proxy voting (delegation) for governance resolutions and polls.
-- A voter (delegator) transfers their voting right to a delegatee (proxy holder).
-- When the delegatee casts a vote, proxy rows are inserted for each active delegator.
-- One active delegation per (delegator, scope).

CREATE TABLE delegations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delegator_id INT NOT NULL,
  delegatee_id INT NOT NULL,
  scope ENUM('all','resolutions','polls') NOT NULL DEFAULT 'all',
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  revoked_by INT NULL DEFAULT NULL,
  UNIQUE KEY uq_delegation (delegator_id, scope),
  KEY idx_delegations_delegatee (delegatee_id, status),
  KEY idx_delegations_delegator (delegator_id, status),
  CONSTRAINT fk_delegations_delegator FOREIGN KEY (delegator_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_delegations_delegatee FOREIGN KEY (delegatee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_delegations_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proxy marker on cast votes: user_id = the delegator (principal), delegated_for = the delegatee who cast.
ALTER TABLE votes ADD COLUMN delegated_for INT NULL AFTER user_id,
  ADD KEY idx_votes_delegated (delegated_for);

ALTER TABLE poll_votes ADD COLUMN delegated_for INT NULL AFTER user_id,
  ADD KEY idx_pollvotes_delegated (delegated_for);
