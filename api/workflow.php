<?php
// UAS Institutional Platform — Workflow Engine
// Generic lifecycle: draft → submitted → pending_review → approved → published → archived
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';

// Workflow state definitions per object type
define('WORKFLOWS', [
  'article' => ['draft', 'submitted', 'under_review', 'approved', 'published', 'rejected', 'archived'],
  'event'   => ['draft', 'submitted', 'approved', 'published', 'cancelled', 'completed'],
  'document'=> ['draft', 'submitted', 'approved', 'published', 'archived'],
  'resolution' => ['draft', 'submitted', 'voting', 'passed', 'failed', 'applied', 'rejected'],
  'programme' => ['draft', 'active', 'paused', 'completed', 'archived'],
  'project' => ['draft', 'active', 'on_hold', 'completed', 'archived'],
  'assignment' => ['not_started', 'in_progress', 'submitted', 'completed', 'overdue'],
]);

// Valid transitions per object type
define('TRANSITIONS', [
  'article' => [
    'draft' => ['submitted'],
    'submitted' => ['under_review', 'rejected'],
    'under_review' => ['approved', 'rejected'],
    'approved' => ['published'],
    'rejected' => ['draft'],  // can revise and resubmit
    'published' => ['archived'],
  ],
  'event' => [
    'draft' => ['submitted'],
    'submitted' => ['approved', 'cancelled'],
    'approved' => ['published'],
    'published' => ['cancelled', 'completed'],
  ],
  'document' => [
    'draft' => ['submitted'],
    'submitted' => ['approved'],
    'approved' => ['published'],
    'published' => ['archived'],
  ],
  'programme' => [
    'draft' => ['active'],
    'active' => ['paused', 'completed'],
    'paused' => ['active', 'archived'],
    'completed' => ['archived'],
  ],
  'project' => [
    'draft' => ['active'],
    'active' => ['on_hold', 'completed'],
    'on_hold' => ['active', 'archived'],
    'completed' => ['archived'],
  ],
]);

/**
 * Transition an object to a new workflow state.
 * Validates: transition is allowed, user has required capability.
 */
function transition(string $objectType, int $objectId, string $newState, int $userId, ?string $notes = null): void {
  // Get current state
  $current = get_current_state($objectType, $objectId);

  // Validate transition
  if (!isset(TRANSITIONS[$objectType])) {
    json_error("No workflow defined for {$objectType}");
  }
  $valid = TRANSITIONS[$objectType][$current] ?? [];
  if (!in_array($newState, $valid)) {
    json_error("Invalid transition: {$current} → {$newState} for {$objectType}");
  }

  // Check capability
  $capRequired = get_required_cap($objectType, $newState);
  if ($capRequired && !user_has_cap($userId, $capRequired)) {
    json_error("Insufficient permissions: {$capRequired}", 403);
  }

  // Record transition
  db()->prepare(
    "INSERT INTO workflow_states (object_type, object_id, state, assignee_id, notes)
     VALUES (?, ?, ?, ?, ?)"
  )->execute([$objectType, $objectId, $newState, $userId, $notes]);

  // Update object status field
  update_object_status($objectType, $objectId, $newState);

  audit_log('workflow_transition', $objectType, $objectId, [
    'from' => $current,
    'to' => $newState,
    'user_id' => $userId
  ]);
}

/**
 * Get the current (latest) state of a workflow object.
 */
function get_current_state(string $objectType, int $objectId): string {
  $stmt = db()->prepare(
    'SELECT state FROM workflow_states
     WHERE object_type = ? AND object_id = ?
     ORDER BY id DESC LIMIT 1'
  );
  $stmt->execute([$objectType, $objectId]);
  $row = $stmt->fetch();
  if ($row) return $row['state'];

  // Fallback: check object's status field
  $table = object_type_to_table($objectType);
  $stmt = db()->prepare("SELECT status FROM {$table} WHERE id = ?");
  $stmt->execute([$objectId]);
  $row = $stmt->fetch();
  return $row['status'] ?? 'draft';
}

/**
 * Get the capability required for a given transition.
 */
function get_required_cap(string $objectType, string $newState): ?string {
  $map = [
    'article' => [
      'submitted' => 'articles.submit',
      'under_review' => 'articles.review',
      'approved' => 'articles.approve',
      'published' => 'articles.publish',
    ],
    'event' => [
      'submitted' => 'events.create',
      'approved' => 'events.approve',
      'published' => 'events.publish',
      'cancelled' => 'events.cancel',
    ],
    'document' => [
      'submitted' => 'documents.upload',
      'approved' => 'documents.approve',
      'published' => 'documents.publish',
    ],
    'programme' => [
      'active' => 'programmes.approve',
    ],
    'project' => [
      'active' => 'projects.approve',
    ],
  ];
  return $map[$objectType][$newState] ?? null;
}

/**
 * Update the status field on the actual object table.
 */
function update_object_status(string $objectType, int $objectId, string $newState): void {
  $table = object_type_to_table($objectType);
  $statusMap = [
    'submitted' => 'submitted',
    'under_review' => 'submitted',
    'approved' => 'approved',
    'published' => 'published',
    'rejected' => 'rejected',
    'cancelled' => 'cancelled',
    'completed' => 'completed',
    'archived' => 'archived',
    'draft' => 'draft',
    'active' => 'active',
    'paused' => 'paused',
    'on_hold' => 'on_hold',
  ];
  $status = $statusMap[$newState] ?? $newState;

  if ($objectType === 'article') {
    if ($newState === 'published') {
      db()->prepare("UPDATE articles SET status = ?, published_at = NOW() WHERE id = ?")->execute([$status, $objectId]);
    } else {
      db()->prepare("UPDATE articles SET status = ? WHERE id = ?")->execute([$status, $objectId]);
    }
  } else {
    db()->prepare("UPDATE {$table} SET status = ? WHERE id = ?")->execute([$status, $objectId]);
  }
}

/**
 * Map object type to database table.
 */
function object_type_to_table(string $objectType): string {
  $map = [
    'article' => 'articles',
    'event' => 'events',
    'document' => 'documents',
    'programme' => 'programmes',
    'project' => 'projects',
    'assignment' => 'assignments',
    'resolution' => 'resolutions',
  ];
  return $map[$objectType] ?? $objectType . 's';
}

/**
 * Get pending items across all object types.
 * Returns items awaiting someone's action.
 */
function get_pending_items(array $filters = []): array {
  $items = [];

  // Articles pending review/approval
  $sql = "SELECT a.id, a.title, a.status, a.created_at, u.name AS author_name,
          'article' AS item_type, r.title AS approver_role
          FROM articles a
          JOIN users u ON u.id = a.author_id
          LEFT JOIN roles r ON r.id = a.approver_role_id
          WHERE a.status IN ('submitted','under_review')";
  $stmt = db()->prepare($sql);
  $stmt->execute();
  $items = array_merge($items, $stmt->fetchAll());

  // Events pending approval
  $sql = "SELECT e.id, e.title, e.status, e.created_at, u.name AS author_name,
          'event' AS item_type, r.title AS approver_role
          FROM events e
          JOIN users u ON u.id = e.created_by
          LEFT JOIN roles r ON r.id = (
            SELECT ra.role_id FROM role_assignments ra
            JOIN role_capabilities rc ON rc.role_id = ra.role_id
            JOIN capabilities c ON c.id = rc.capability_id
            WHERE c.slug = 'events.approve' AND ra.status = 'active'
            LIMIT 1
          )
          WHERE e.status IN ('submitted','draft')";
  $stmt = db()->prepare($sql);
  $stmt->execute();
  $items = array_merge($items, $stmt->fetchAll());

  // Documents pending approval
  $sql = "SELECT d.id, d.title, d.status, d.created_at, u.name AS author_name,
          'document' AS item_type, 'Documents' AS approver_role
          FROM documents d
          JOIN users u ON u.id = d.owner_id
          WHERE d.status IN ('submitted','draft')";
  $stmt = db()->prepare($sql);
  $stmt->execute();
  $items = array_merge($items, $stmt->fetchAll());

  // Resolutions awaiting votes
  $sql = "SELECT r.id, r.title AS name, r.status, r.created_at, u.name AS author_name,
          'resolution' AS item_type, CONCAT(r.votes_for, ' for / ', r.votes_against, ' against') AS approver_role
          FROM resolutions r
          JOIN users u ON u.id = r.proposed_by
          WHERE r.status = 'voting'
            AND (r.voting_deadline IS NULL OR r.voting_deadline > NOW())";
  $stmt = db()->prepare($sql);
  $stmt->execute();
  $items = array_merge($items, $stmt->fetchAll());

  // Assignments overdue or not started
  $sql = "SELECT a.id, a.title, a.status, a.due_date AS created_at, u.name AS author_name,
          'assignment' AS item_type,
          COALESCE(au.name, r.title, 'Unassigned') AS approver_role
          FROM assignments a
          LEFT JOIN users au ON au.id = a.assignee_id
          LEFT JOIN roles r ON r.id = a.role_id
          LEFT JOIN users u ON u.id = a.assigner_id
          WHERE a.status IN ('not_started','in_progress','overdue')
            AND (a.due_date IS NULL OR a.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY))";
  $stmt = db()->prepare($sql);
  $stmt->execute();
  $items = array_merge($items, $stmt->fetchAll());

  // Sort by date (oldest first)
  usort($items, fn($a, $b) => strtotime($a['created_at'] ?? 'now') - strtotime($b['created_at'] ?? 'now'));

  return $items;
}

/**
 * Institutional health metrics.
 */
function institutional_health(): array {
  $health = [];

  // Members
  $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active FROM members");
  $stmt->execute();
  $health['members'] = $stmt->fetch();

  // Programmes
  $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active FROM programmes");
  $stmt->execute();
  $health['programmes'] = $stmt->fetch();

  // Projects
  $stmt = db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active FROM projects");
  $stmt->execute();
  $health['projects'] = $stmt->fetch();

  // Events
  $stmt = db()->prepare("SELECT COUNT(*) AS upcoming FROM events WHERE date >= NOW() AND status IN ('approved','published')");
  $stmt->execute();
  $health['events'] = $stmt->fetch();

  // Pending items
  $pending = get_pending_items();
  $overdue = array_filter($pending, fn($i) => isset($i['due_date']) && strtotime($i['due_date']) < time());
  $health['pending'] = ['total' => count($pending), 'overdue' => count($overdue)];

  // Pending governance
  $stmt = db()->prepare("SELECT COUNT(*) AS total FROM resolutions WHERE status = 'voting'");
  $stmt->execute();
  $health['governance'] = $stmt->fetch();

  // Financials
  $stmt = db()->prepare("SELECT
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS spent
  FROM financial_records");
  $stmt->execute();
  $health['finance'] = $stmt->fetch();

  // Recent memberships
  $stmt = db()->prepare("SELECT COUNT(*) AS new_this_quarter FROM members WHERE joined_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
  $stmt->execute();
  $health['new_members_quarter'] = $stmt->fetch()['new_this_quarter'];

  return $health;
}
