<?php
// UAS Institutional Platform — Governance Engine
// Resolutions, voting, auto-apply when last vote meets quorum
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';

// ============================================================
// RESOLUTIONS
// ============================================================

/**
 * Create a governance resolution.
 * changePayload: array of change objects [{change_type, target_type, target_id, payload}]
 */
function create_resolution(array $data, int $proposedBy): int {
  // Generate code: UAS-BRD-2026-001
  $year = date('Y');
  $stmt = db()->prepare("SELECT COUNT(*) + 1 AS next_num FROM resolutions WHERE YEAR(created_at) = ?");
  $stmt->execute([$year]);
  $num = $stmt->fetch()['next_num'];
  $code = 'UAS-BRD-' . $year . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);

  $stmt = db()->prepare(
    'INSERT INTO resolutions (code, title, description, type, proposed_by, quorum, majority, voting_deadline, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    $code,
    $data['title'],
    $data['description'] ?? null,
    $data['type'],
    $proposedBy,
    $data['quorum'] ?? 0,
    $data['majority'] ?? 'simple',
    $data['voting_deadline'] ?? null,
    'draft'
  ]);
  $resolutionId = (int) db()->lastInsertId();

  // Store changes
  if (!empty($data['changes'])) {
    $ins = db()->prepare(
      'INSERT INTO resolution_changes (resolution_id, change_type, target_type, target_id, payload) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($data['changes'] as $change) {
      $ins->execute([
        $resolutionId,
        $change['change_type'],
        $change['target_type'] ?? null,
        $change['target_id'] ?? null,
        json_encode($change['payload'])
      ]);
    }
  }

  audit_log('resolution_create', 'resolution', $resolutionId, [
    'code' => $code,
    'type' => $data['type'],
    'proposed_by' => $proposedBy
  ]);

  return $resolutionId;
}

/**
 * Submit a resolution for voting.
 */
function submit_resolution(int $resolutionId, int $userId): void {
  $res = get_resolution($resolutionId);
  if (!$res) json_error('Resolution not found', 404);
  if ($res['status'] !== 'draft') json_error('Resolution cannot be submitted', 400);
  if ($res['proposed_by'] != $userId) json_error('Only proposer can submit', 403);

  db()->prepare("UPDATE resolutions SET status = 'submitted' WHERE id = ?")->execute([$resolutionId]);
  db()->prepare(
    "INSERT INTO workflow_states (object_type, object_id, state) VALUES ('resolution', ?, 'submitted')"
  )->execute([$resolutionId]);
  audit_log('resolution_submit', 'resolution', $resolutionId);
}

/**
 * Begin voting on a resolution.
 */
function begin_voting(int $resolutionId): void {
  $res = get_resolution($resolutionId);
  if (!$res) json_error('Resolution not found', 404);
  if ($res['status'] !== 'submitted') json_error('Resolution must be submitted first', 400);

  db()->prepare("UPDATE resolutions SET status = 'voting' WHERE id = ?")->execute([$resolutionId]);
  db()->prepare(
    "INSERT INTO workflow_states (object_type, object_id, state) VALUES ('resolution', ?, 'voting')"
  )->execute([$resolutionId]);
  audit_log('resolution_voting', 'resolution', $resolutionId);
}

/**
 * Cast a vote. Auto-applies when last vote meets quorum.
 */
function cast_vote(int $resolutionId, int $userId, string $value, ?string $rationale = null): void {
  if (!in_array($value, ['for', 'against', 'abstain'])) json_error('Invalid vote value');

  $res = get_resolution($resolutionId);
  if (!$res) json_error('Resolution not found', 404);
  if ($res['status'] !== 'voting') json_error('Resolution is not open for voting', 400);

  // Check not already voted
  $stmt = db()->prepare('SELECT id FROM votes WHERE resolution_id = ? AND user_id = ?');
  $stmt->execute([$resolutionId, $userId]);
  if ($stmt->fetch()) json_error('Already voted', 409);

  // Check voting deadline
  if ($res['voting_deadline'] && strtotime($res['voting_deadline']) < time()) {
    json_error('Voting deadline has passed', 400);
  }

  db()->prepare(
    'INSERT INTO votes (resolution_id, user_id, value, rationale) VALUES (?, ?, ?, ?)'
  )->execute([$resolutionId, $userId, $value, $rationale]);

  // Update counters
  $field = 'votes_' . $value;
  db()->prepare("UPDATE resolutions SET {$field} = {$field} + 1 WHERE id = ?")->execute([$resolutionId]);

  audit_log('resolution_vote', 'resolution', $resolutionId, [
    'user_id' => $userId,
    'value' => $value
  ]);

  // Cast proxy votes for active delegators
  apply_delegated_resolution_votes($resolutionId, $userId, $value, $rationale);

  // Check quorum and apply if met
  check_and_apply($resolutionId);
}

/**
 * Check if quorum is satisfied and auto-apply if the last vote met the threshold.
 * This is the core of the governance engine: the last qualifying vote triggers execution.
 */
function check_and_apply(int $resolutionId): void {
  $res = get_resolution($resolutionId);
  if (!$res || $res['status'] !== 'voting') return;

  $totalVotes = $res['votes_for'] + $res['votes_against'] + $res['votes_abstain'];
  $quorum = (int) $res['quorum'];

  // Vote-triggered auto-apply: the last qualifying vote decides the outcome immediately.
  if ($quorum > 0 && $totalVotes < $quorum) return;

  // Determine outcome based on majority type
  $majority = $res['majority'];
  $for = $res['votes_for'];
  $against = $res['votes_against'];
  $totalDeciding = $for + $against; // abstains don't count for majority

  $passed = false;
  switch ($majority) {
    case 'simple':
      $passed = $for > $against;
      break;
    case 'two_thirds':
      $passed = $totalDeciding > 0 && ($for / $totalDeciding) >= (2/3);
      break;
    case 'unanimous':
      $passed = $against === 0 && $for > 0;
      break;
  }

  if ($passed) {
    db()->prepare("UPDATE resolutions SET status = 'passed', passed_at = NOW() WHERE id = ?")->execute([$resolutionId]);
    db()->prepare(
      "INSERT INTO workflow_states (object_type, object_id, state) VALUES ('resolution', ?, 'passed')"
    )->execute([$resolutionId]);
    audit_log('resolution_passed', 'resolution', $resolutionId);

    // AUTO-APPLY: execute all resolution changes
    apply_resolution($resolutionId);
  } else {
    db()->prepare("UPDATE resolutions SET status = 'failed' WHERE id = ?")->execute([$resolutionId]);
    db()->prepare(
      "INSERT INTO workflow_states (object_type, object_id, state) VALUES ('resolution', ?, 'failed')"
    )->execute([$resolutionId]);
    audit_log('resolution_failed', 'resolution', $resolutionId);
  }
}

/**
 * Deadline sweep: close resolutions whose voting deadline has passed.
 * Quorum-met resolutions are decided by majority; otherwise they fail.
 * Call periodically (health check, cron, or manual management trigger).
 */
function close_expired_voting(): void {
  $stmt = db()->prepare("SELECT id FROM resolutions WHERE status = 'voting' AND voting_deadline IS NOT NULL AND voting_deadline < NOW()");
  $stmt->execute();
  foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
    check_and_apply((int) $id);
  }
}

/**
 * Apply all changes from a passed resolution.
 * This is the auto-apply worker — called automatically when quorum is met.
 */
function apply_resolution(int $resolutionId): void {
  $stmt = db()->prepare('SELECT * FROM resolution_changes WHERE resolution_id = ? AND applied = 0');
  $stmt->execute([$resolutionId]);
  $changes = $stmt->fetchAll();

  $res = get_resolution($resolutionId);

  foreach ($changes as $change) {
    $payload = json_decode($change['payload'], true);
    $changeType = $change['change_type'];

    try {
      switch ($changeType) {
        case 'role_create':
          $roleId = create_role(
            $payload['title'],
            $payload['description'] ?? '',
            $payload['capabilities'] ?? $payload['capability_ids'] ?? [],
            $res['proposed_by'],
            $payload['scope'] ?? null,
            $resolutionId,
            $payload['role_type'] ?? null,
            $payload['target'] ?? null
          );
          // Auto-assign if specified
          if (!empty($payload['assign_to_user_id'])) {
            assign_role($roleId, $payload['assign_to_user_id'], $res['proposed_by'], $resolutionId);
          }
          break;

        case 'role_modify':
          if (!empty($payload['role_id'])) {
            // Update role fields
            $updates = [];
            $params = [];
            foreach (['title', 'description', 'scope', 'status'] as $field) {
              if (isset($payload[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $payload[$field];
              }
            }
            if ($updates) {
              $params[] = $payload['role_id'];
              db()->prepare("UPDATE roles SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            }
            // Replace capabilities if specified
            if (isset($payload['capability_ids'])) {
              db()->prepare('DELETE FROM role_capabilities WHERE role_id = ?')->execute([$payload['role_id']]);
              foreach ($payload['capability_ids'] as $capId) {
                assign_capability($payload['role_id'], $capId, $res['proposed_by']);
              }
            }
          }
          break;

        case 'role_delete':
          if (!empty($payload['role_id'])) {
            db()->prepare("UPDATE roles SET status = 'inactive' WHERE id = ?")->execute([$payload['role_id']]);
            db()->prepare("UPDATE role_assignments SET status = 'inactive' WHERE role_id = ?")->execute([$payload['role_id']]);
          }
          break;

        case 'cap_assign':
          if (!empty($payload['role_id']) && !empty($payload['capability_id'])) {
            assign_capability($payload['role_id'], $payload['capability_id'], $res['proposed_by']);
          }
          break;

        case 'cap_revoke':
          if (!empty($payload['role_id']) && !empty($payload['capability_id'])) {
            revoke_capability($payload['role_id'], $payload['capability_id']);
          }
          break;

        case 'appoint':
          $targetRoleId = $payload['role_id'] ?? null;
          if (!$targetRoleId && !empty($payload['role_title'])) {
            $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
            $stmt->execute([$payload['role_title']]);
            $targetRoleId = (int) $stmt->fetchColumn() ?: null;
          }
          if ($targetRoleId && !empty($payload['user_id'])) {
            assign_role(
              $targetRoleId,
              $payload['user_id'],
              $res['proposed_by'],
              $resolutionId,
              $payload['effective_to'] ?? null
            );
          }
          break;

        case 'remove':
          if (!empty($payload['role_id']) && !empty($payload['user_id'])) {
            revoke_role($payload['role_id'], $payload['user_id']);
          }
          break;

        case 'committee_create':
          // Committees are roles with scope='committee'
          $roleId = create_role(
            $payload['title'] . ' Committee',
            $payload['description'] ?? '',
            $payload['capabilities'] ?? $payload['capability_ids'] ?? [],
            $res['proposed_by'],
            'committee',
            $resolutionId,
            'administrative'
          );
          if (!empty($payload['chair_user_id'])) {
            assign_role($roleId, $payload['chair_user_id'], $res['proposed_by'], $resolutionId);
          }
          break;

        case 'committee_dissolve':
          if (!empty($payload['role_id'])) {
            db()->prepare("UPDATE roles SET status = 'inactive' WHERE id = ?")->execute([$payload['role_id']]);
            db()->prepare("UPDATE role_assignments SET status = 'inactive' WHERE role_id = ?")->execute([$payload['role_id']]);
          }
          break;

        case 'programme_create':
          db()->prepare(
            'INSERT INTO programmes (title, description, status, objectives, created_by) VALUES (?, ?, ?, ?, ?)'
          )->execute([
            $payload['title'],
            $payload['description'] ?? null,
            'active',
            $payload['objectives'] ?? null,
            $res['proposed_by']
          ]);
          break;

        default:
          audit_log('resolution_change_unknown', 'resolution_change', $change['id'], [
            'change_type' => $changeType
          ]);
      }

      // Mark change as applied
      db()->prepare("UPDATE resolution_changes SET applied = 1, applied_at = NOW() WHERE id = ?")->execute([$change['id']]);

      audit_log('resolution_change_applied', 'resolution_change', $change['id'], [
        'change_type' => $changeType,
        'resolution_id' => $resolutionId
      ]);

    } catch (Exception $e) {
      audit_log('resolution_change_failed', 'resolution_change', $change['id'], [
        'change_type' => $changeType,
        'resolution_id' => $resolutionId,
        'error' => $e->getMessage()
      ]);
    }
  }

  // Mark resolution as applied
  db()->prepare("UPDATE resolutions SET status = 'applied', applied_at = NOW() WHERE id = ? AND status = 'passed'")->execute([$resolutionId]);
  db()->prepare(
    "INSERT INTO workflow_states (object_type, object_id, state) VALUES ('resolution', ?, 'applied')"
  )->execute([$resolutionId]);
  audit_log('resolution_applied', 'resolution', $resolutionId);
}

/**
 * Get a resolution with all related data.
 */
function get_resolution(int $id): ?array {
  $stmt = db()->prepare('SELECT * FROM resolutions WHERE id = ?');
  $stmt->execute([$id]);
  $res = $stmt->fetch();
  if (!$res) return null;

  // Get changes
  $stmt = db()->prepare('SELECT * FROM resolution_changes WHERE resolution_id = ? ORDER BY id');
  $stmt->execute([$id]);
  $res['changes'] = $stmt->fetchAll();

  // Get votes
  $stmt = db()->prepare("
    SELECT v.*, u.name AS voter_name
    FROM votes v JOIN users u ON u.id = v.user_id
    WHERE v.resolution_id = ?
    ORDER BY v.cast_at
  ");
  $stmt->execute([$id]);
  $res['votes'] = $stmt->fetchAll();

  return $res;
}

/**
 * List resolutions with filters.
 */
function list_resolutions(array $filters = []): array {
  $where = ['1=1'];
  $params = [];

  if (!empty($filters['status'])) {
    $where[] = 'r.status = ?';
    $params[] = $filters['status'];
  }
  if (!empty($filters['type'])) {
    $where[] = 'r.type = ?';
    $params[] = $filters['type'];
  }
  if (!empty($filters['proposed_by'])) {
    $where[] = 'r.proposed_by = ?';
    $params[] = $filters['proposed_by'];
  }

  $sql = "SELECT r.*, u.name AS proposer_name
          FROM resolutions r
          LEFT JOIN users u ON u.id = r.proposed_by
          WHERE " . implode(' AND ', $where) . "
          ORDER BY r.created_at DESC";

  if (!empty($filters['limit'])) {
    $sql .= " LIMIT " . (int) $filters['limit'];
    if (!empty($filters['offset'])) $sql .= " OFFSET " . (int) $filters['offset'];
  }

  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

// ============================================================
// POLLS
// ============================================================

/**
 * Whether a user may vote in a poll (eligibility rule).
 */
function poll_eligible(array $poll, int $userId): bool {
  $elig = $poll['eligibility'] ?? 'members';
  if ($elig === 'all') return true;

  // Check active membership (required for members, group, role)
  $stmt = db()->prepare('SELECT 1 FROM members WHERE user_id = ? AND status = "active"');
  $stmt->execute([$userId]);
  $isMember = (bool) $stmt->fetch();

  if ($elig === 'members') return $isMember;
  if (!$isMember) return false;

  $target = $poll['eligibility_target'] ?? null;
  if (!$target) return true;

  if ($elig === 'group') {
    $stmt = db()->prepare('SELECT 1 FROM working_group_members wgm JOIN working_groups wg ON wg.id = wgm.group_id WHERE wgm.user_id = ? AND wg.name = ?');
    $stmt->execute([$userId, $target]);
    return (bool) $stmt->fetch();
  }

  if ($elig === 'role') {
    $stmt = db()->prepare('SELECT 1 FROM role_assignments ra JOIN roles r ON r.id = ra.role_id WHERE ra.user_id = ? AND r.title = ? AND ra.status = "active" AND r.status = "active"');
    $stmt->execute([$userId, $target]);
    return (bool) $stmt->fetch();
  }

  return false;
}

/**
 * Close a poll: tally votes, respect quorum (required vote count) and ties.
 * Returns the winning option index, or null (no quorum / tied).
 */
function close_poll(int $pollId): ?int {
  $stmt = db()->prepare('SELECT * FROM polls WHERE id = ?');
  $stmt->execute([$pollId]);
  $poll = $stmt->fetch();
  if (!$poll) return null;

  $stmt = db()->prepare('SELECT option_index, COUNT(*) AS c FROM poll_votes WHERE poll_id = ? GROUP BY option_index ORDER BY c DESC, option_index ASC');
  $stmt->execute([$pollId]);
  $rows = $stmt->fetchAll();

  $total = 0;
  $result = null;
  if ($rows) {
    foreach ($rows as $r) $total += (int) $r['c'];
    $top = (int) $rows[0]['option_index'];
    $topCount = (int) $rows[0]['c'];
    $ties = count(array_filter($rows, fn($r) => (int) $r['c'] === $topCount));
    if (($poll['quorum'] <= 0 || $total >= (int) $poll['quorum']) && $ties === 1) {
      $result = $top;
    }
  }

  db()->prepare("UPDATE polls SET status = 'closed', result_option = ? WHERE id = ?")->execute([$result, $pollId]);
  return $result;
}

// ============================================================
// DELEGATIONS (proxy voting)
// ============================================================

const DELEGATION_SCOPES = ['all', 'resolutions', 'polls'];

/**
 * Does a user hold the voting right for a delegation scope?
 */
function delegation_right(int $userId, string $scope): bool {
  if ($scope === 'resolutions') return user_has_cap($userId, 'resolutions.vote');
  if ($scope === 'polls') {
    return user_has_cap($userId, 'governance.poll.vote')
      || user_has_cap($userId, 'resolutions.vote')
      || (bool) db()->query("SELECT 1 FROM members WHERE user_id = {$userId} AND status = 'active'")->fetchColumn();
  }
  return delegation_right($userId, 'resolutions') && delegation_right($userId, 'polls');
}

/**
 * Active delegations where $userId is the delegator, keyed by scope.
 */
function delegator_scopes(int $userId): array {
  $stmt = db()->prepare('SELECT scope FROM delegations WHERE delegator_id = ? AND status = "active"');
  $stmt->execute([$userId]);
  return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Active delegators (principals) for a delegatee and a voting scope.
 */
function active_delegators(int $delegateeId, string $scope): array {
  $scopes = $scope === 'all' ? ['all'] : ['all', $scope];
  $in = implode(',', array_fill(0, count($scopes), '?'));
  $stmt = db()->prepare("SELECT delegator_id FROM delegations WHERE delegatee_id = ? AND status = 'active' AND scope IN ({$in})");
  $stmt->execute(array_merge([$delegateeId], $scopes));
  return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Create or replace the delegator's active delegation for a scope.
 */
function create_delegation(int $delegatorId, int $delegateeId, string $scope): int {
  if (!in_array($scope, DELEGATION_SCOPES, true)) json_error('Invalid delegation scope', 400);
  if ($delegatorId === $delegateeId) json_error('You cannot delegate to yourself', 400);
  if (!delegation_right($delegatorId, $scope)) json_error('You do not hold the voting right being delegated', 403);
  if (!delegation_right($delegateeId, $scope)) json_error('The delegatee does not hold that voting right', 400);

  // Upsert: reactivate an existing row (active or revoked) with the new delegatee,
  // or insert a fresh one. The (delegator_id, scope) key is unique regardless of status.
  $stmt = db()->prepare("UPDATE delegations SET delegatee_id = ?, status = 'active', revoked_at = NULL, revoked_by = NULL, created_at = NOW() WHERE delegator_id = ? AND scope = ?");
  $stmt->execute([$delegateeId, $delegatorId, $scope]);
  if ($stmt->rowCount() === 0) {
    db()->prepare('INSERT INTO delegations (delegator_id, delegatee_id, scope) VALUES (?, ?, ?)')
      ->execute([$delegatorId, $delegateeId, $scope]);
  }
  $stmt = db()->prepare('SELECT id FROM delegations WHERE delegator_id = ? AND scope = ?');
  $stmt->execute([$delegatorId, $scope]);
  $id = (int) $stmt->fetchColumn();
  audit_log('delegation_create', 'delegation', $id, ['delegator_id' => $delegatorId, 'delegatee_id' => $delegateeId, 'scope' => $scope]);

  $stmt = db()->prepare('SELECT name FROM users WHERE id = ?');
  $stmt->execute([$delegatorId]);
  $delegatorName = $stmt->fetchColumn();
  notify_user($delegateeId, 'delegation_assigned', $delegatorName . ' delegated ' . ($scope === 'all' ? 'all their votes' : $scope . ' votes') . ' to you', 'You may now cast votes on their behalf from the dashboard.', '/dashboard');

  return $id;
}

/**
 * Revoke a delegation (delegator only).
 */
function revoke_delegation(int $delegationId, int $userId): void {
  $stmt = db()->prepare('SELECT * FROM delegations WHERE id = ?');
  $stmt->execute([$delegationId]);
  $d = $stmt->fetch();
  if (!$d) json_error('Delegation not found', 404);
  if ($d['delegator_id'] != $userId) json_error('Only the delegator can revoke this delegation', 403);
  db()->prepare("UPDATE delegations SET status = 'revoked', revoked_at = NOW(), revoked_by = ? WHERE id = ? AND status = 'active'")
    ->execute([$userId, $delegationId]);
  audit_log('delegation_revoke', 'delegation', $delegationId, ['delegator_id' => $d['delegator_id']]);
}

/**
 * Revoke delegations whose delegator or delegatee no longer holds the voting right
 * (called after role/capability revocation). Purely a cleanup guard — cast-time
 * validation is the authoritative check.
 */
function auto_revoke_delegations(int $userId): void {
  $stmt = db()->prepare("SELECT id, delegator_id, delegatee_id, scope FROM delegations WHERE status = 'active' AND (delegator_id = ? OR delegatee_id = ?)");
  $stmt->execute([$userId, $userId]);
  foreach ($stmt->fetchAll() as $d) {
    if (!delegation_right((int) $d['delegator_id'], $d['scope']) || !delegation_right((int) $d['delegatee_id'], $d['scope'])) {
      db()->prepare("UPDATE delegations SET status = 'revoked', revoked_at = NOW(), revoked_by = NULL WHERE id = ? AND status = 'active'")->execute([$d['id']]);
      audit_log('delegation_auto_revoke', 'delegation', (int) $d['id'], ['user_id' => $userId]);
    }
  }
}

/**
 * Insert proxy vote rows for a delegatee's active delegators on a resolution.
 * Returns the number of proxy votes inserted (counters already incremented).
 */
function apply_delegated_resolution_votes(int $resolutionId, int $delegateeId, string $value, ?string $rationale): int {
  $delegators = active_delegators($delegateeId, 'resolutions');
  if (!$delegators) return 0;

  $have = db()->prepare('SELECT user_id FROM votes WHERE resolution_id = ? AND user_id IN (' . implode(',', array_fill(0, count($delegators), '?')) . ')');
  $have->execute(array_merge([$resolutionId], $delegators));
  $voted = $have->fetchAll(PDO::FETCH_COLUMN);
  $eligible = array_values(array_diff($delegators, $voted));

  $ins = db()->prepare('INSERT INTO votes (resolution_id, user_id, value, rationale, delegated_for) VALUES (?, ?, ?, NULL, ?)');
  $n = 0;
  foreach ($eligible as $uid) {
    if (!user_has_cap((int) $uid, 'resolutions.vote')) continue;
    $ins->execute([$resolutionId, $uid, $value, $delegateeId]);
    $n++;
  }
  if ($n > 0) {
    $field = 'votes_' . $value;
    db()->prepare("UPDATE resolutions SET {$field} = {$field} + ? WHERE id = ?")->execute([$n, $resolutionId]);
    audit_log('resolution_proxy_votes', 'resolution', $resolutionId, ['delegatee_id' => $delegateeId, 'count' => $n, 'value' => $value]);
  }
  return $n;
}

/**
 * Insert proxy vote rows for a delegatee's active delegators on a poll.
 * Returns the number of proxy votes inserted.
 */
function apply_delegated_poll_votes(int $pollId, array $poll, int $delegateeId, int $optionIndex): int {
  $delegators = active_delegators($delegateeId, 'polls');
  if (!$delegators) return 0;

  $have = db()->prepare('SELECT user_id FROM poll_votes WHERE poll_id = ? AND user_id IN (' . implode(',', array_fill(0, count($delegators), '?')) . ')');
  $have->execute(array_merge([$pollId], $delegators));
  $voted = $have->fetchAll(PDO::FETCH_COLUMN);
  $eligible = array_values(array_diff($delegators, $voted));

  $ins = db()->prepare('INSERT INTO poll_votes (poll_id, user_id, option_index, delegated_for) VALUES (?, ?, ?, ?)');
  $n = 0;
  foreach ($eligible as $uid) {
    if (!poll_eligible($poll, (int) $uid)) continue;
    $ins->execute([$pollId, $uid, $optionIndex, $delegateeId]);
    $n++;
  }
  if ($n > 0) {
    audit_log('poll_proxy_votes', 'poll', $pollId, ['delegatee_id' => $delegateeId, 'count' => $n, 'option' => $optionIndex]);
  }
  return $n;
}
