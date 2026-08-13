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
            $payload['capability_ids'] ?? [],
            $res['proposed_by'],
            $resolutionId
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
          if (!empty($payload['role_id']) && !empty($payload['user_id'])) {
            assign_role(
              $payload['role_id'],
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
            $payload['capability_ids'] ?? [],
            $res['proposed_by'],
            $resolutionId
          );
          db()->prepare("UPDATE roles SET scope = 'committee' WHERE id = ?")->execute([$roleId]);
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
            'INSERT INTO programmes (title, description, lead_id, status, objectives, created_by) VALUES (?, ?, ?, ?, ?, ?)'
          )->execute([
            $payload['title'],
            $payload['description'] ?? null,
            $payload['lead_id'] ?? null,
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
