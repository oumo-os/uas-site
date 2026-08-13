<?php
// UAS Institutional Platform — Audit Trail
require_once __DIR__ . '/config.php';

/**
 * Record an audit log entry.
 */
function audit_log(string $actionType, ?string $targetType = null, ?int $targetId = null, array $context = []): void {
  $userId = $_SESSION['user_id'] ?? null;
  $userName = null;
  if ($userId) {
    $stmt = db()->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $userName = $row['name'] ?? null;
  }

  $governanceContext = null;
  if (!empty($context['resolution_id']) || !empty($context['role_id']) || !empty($context['capability_id'])) {
    $governanceContext = array_filter([
      'resolution_id' => $context['resolution_id'] ?? null,
      'role_id' => $context['role_id'] ?? null,
      'capability_slug' => $context['capability_slug'] ?? null,
    ]);
  }

  $previousState = $context['previous_state'] ?? null;
  $newState = $context['new_state'] ?? null;
  $details = $context['details'] ?? null;

  // Store remaining context as details if not set
  $extraContext = array_diff_key($context, array_flip([
    'resolution_id', 'role_id', 'capability_id', 'capability_slug',
    'previous_state', 'new_state', 'details'
  ]));
  if (!$details && $extraContext) {
    $details = json_encode($extraContext, JSON_UNESCAPED_UNICODE);
  }

  db()->prepare(
    'INSERT INTO audit_log (user_id, user_name, action_type, target_type, target_id, governance_context, previous_state, new_state, details)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  )->execute([
    $userId,
    $userName,
    $actionType,
    $targetType,
    $targetId,
    $governanceContext ? json_encode($governanceContext) : null,
    $previousState ? json_encode($previousState) : null,
    $newState ? json_encode($newState) : null,
    $details
  ]);
}

/**
 * Query audit log with filters.
 */
function query_audit_log(array $filters = []): array {
  $where = ['1=1'];
  $params = [];

  if (!empty($filters['user_id'])) {
    $where[] = 'al.user_id = ?';
    $params[] = $filters['user_id'];
  }
  if (!empty($filters['action_type'])) {
    $where[] = 'al.action_type = ?';
    $params[] = $filters['action_type'];
  }
  if (!empty($filters['target_type'])) {
    $where[] = 'al.target_type = ?';
    $params[] = $filters['target_type'];
  }
  if (!empty($filters['target_id'])) {
    $where[] = 'al.target_id = ?';
    $params[] = $filters['target_id'];
  }
  if (!empty($filters['since'])) {
    $where[] = 'al.created_at >= ?';
    $params[] = $filters['since'];
  }

  $sql = "SELECT al.* FROM audit_log al
          WHERE " . implode(' AND ', $where) . "
          ORDER BY al.created_at DESC";

  if (!empty($filters['limit'])) {
    $sql .= " LIMIT " . (int) $filters['limit'];
    if (!empty($filters['offset'])) $sql .= " OFFSET " . (int) $filters['offset'];
  }

  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}
