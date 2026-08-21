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
 * If $userId is provided, returns only items relevant to that user.
 */
function get_pending_items(?int $userId = null): array {
  $items = [];

  if ($userId) {
    // MY ITEMS: items created by user, assigned to user, or in user's roles
    $userRoleIds = [];
    $stmt = db()->prepare('SELECT role_id FROM role_assignments WHERE user_id = ? AND status = "active"');
    $stmt->execute([$userId]);
    $userRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Articles by user
    $sql = "SELECT a.id, a.title, a.status, a.created_at, u.name AS author_name,
            'article' AS item_type, a.approver_role_id AS related_id
            FROM articles a JOIN users u ON u.id = a.author_id
            WHERE a.author_id = ? AND a.status IN ('submitted','under_review','approved')";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $items = array_merge($items, $stmt->fetchAll());

    // Events by user or where user's role approves
    $sql = "SELECT e.id, e.title, e.status, e.created_at, u.name AS author_name,
            'event' AS item_type, e.id AS related_id
            FROM events e JOIN users u ON u.id = e.created_by
            WHERE e.created_by = ? AND e.status IN ('submitted','draft')
            AND e.approval_required = 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $items = array_merge($items, $stmt->fetchAll());

    // Documents by user
    $sql = "SELECT d.id, d.title, d.status, d.created_at, u.name AS author_name,
            'document' AS item_type, d.id AS related_id
            FROM documents d JOIN users u ON u.id = d.owner_id
            WHERE d.owner_id = ? AND d.status IN ('submitted','draft')";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $items = array_merge($items, $stmt->fetchAll());

    // Resolutions by user or where user can vote
    if ($userRoleIds) {
      $placeholders = implode(',', array_fill(0, count($userRoleIds), '?'));
      $sql = "SELECT DISTINCT r.id, r.title AS name, r.status, r.created_at, u.name AS author_name,
              'resolution' AS item_type, r.id AS related_id
              FROM resolutions r
              JOIN users u ON u.id = r.proposed_by
              LEFT JOIN role_capabilities rc ON rc.role_id IN ($placeholders)
              LEFT JOIN capabilities c ON c.id = rc.capability_id AND c.slug = 'resolutions.vote'
              WHERE r.status = 'voting'
                AND (r.voting_deadline IS NULL OR r.voting_deadline > NOW())
                AND NOT EXISTS (SELECT 1 FROM delegations dg WHERE dg.delegator_id = ? AND dg.status = 'active' AND dg.scope IN ('all','resolutions'))
                AND (r.proposed_by = ? OR c.id IS NOT NULL)";
      $params = array_merge($userRoleIds, [$userId, $userId]);
      $stmt = db()->prepare($sql);
      $stmt->execute($params);
      $items = array_merge($items, $stmt->fetchAll());
    }

    // Assignments to user
    $sql = "SELECT a.id, a.title, a.status, COALESCE(a.due_date, a.created_at) AS created_at,
            u.name AS author_name, 'assignment' AS item_type, a.id AS related_id
            FROM assignments a LEFT JOIN users u ON u.id = a.assigner_id
            WHERE a.assignee_id = ? AND a.status IN ('not_started','in_progress','overdue')";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $items = array_merge($items, $stmt->fetchAll());

    // Open polls the user is eligible for and has not voted in
    $sql = "SELECT p.id, p.title, p.status, p.starts_at AS created_at, u.name AS author_name,
            'poll' AS item_type, p.id AS related_id
            FROM polls p LEFT JOIN users u ON u.id = p.created_by
            WHERE p.status = 'open'
              AND (p.ends_at IS NULL OR p.ends_at > NOW())
              AND NOT EXISTS (SELECT 1 FROM delegations dg WHERE dg.delegator_id = ? AND dg.status = 'active' AND dg.scope IN ('all','polls'))
              AND NOT EXISTS (SELECT 1 FROM poll_votes pv WHERE pv.poll_id = p.id AND pv.user_id = ?)
              AND (p.eligibility = 'all'
                OR (p.eligibility = 'members' AND EXISTS (
                  SELECT 1 FROM members m2 WHERE m2.user_id = ? AND m2.status = 'active'))
                OR (p.eligibility = 'group' AND p.eligibility_target IS NOT NULL AND EXISTS (
                  SELECT 1 FROM working_group_members wgm JOIN working_groups wg ON wg.id = wgm.group_id
                  WHERE wgm.user_id = ? AND wg.name = p.eligibility_target))
                OR (p.eligibility = 'role' AND p.eligibility_target IS NOT NULL AND EXISTS (
                  SELECT 1 FROM role_assignments ra JOIN roles r ON r.id = ra.role_id
                  WHERE ra.user_id = ? AND r.title = p.eligibility_target AND ra.status = 'active')))";
    $params = array_merge([$userId], [$userId], [$userId], [$userId], [$userId]);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = array_merge($items, $stmt->fetchAll());

  } else {
    // ORG WIDE: all pending items
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
            WHERE e.status IN ('submitted','draft')
            AND e.approval_required = 1";
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

    // Open polls awaiting votes
    $sql = "SELECT p.id, p.title, p.status, p.starts_at AS created_at, u.name AS author_name,
            'poll' AS item_type,
            CONCAT((SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id), ' votes') AS approver_role
            FROM polls p LEFT JOIN users u ON u.id = p.created_by
            WHERE p.status = 'open'
              AND (p.ends_at IS NULL OR p.ends_at > NOW())";
    $stmt = db()->prepare($sql);
    $stmt->execute();
    $items = array_merge($items, $stmt->fetchAll());
  }

  // Sort by date (oldest first)
  usort($items, fn($a, $b) => strtotime($a['created_at'] ?? 'now') - strtotime($b['created_at'] ?? 'now'));

  return $items;
}

/**
 * Institutional health metrics.
 */
function institutional_health(): array {
  close_expired_voting();
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
  $stmt = db()->prepare("SELECT COUNT(*) AS open FROM polls WHERE status = 'open' AND (ends_at IS NULL OR ends_at > NOW())");
  $stmt->execute();
  $health['polls_open'] = (int) $stmt->fetch()['open'];
  $stmt = db()->prepare("SELECT COUNT(*) AS upcoming FROM meetings WHERE status IN ('scheduled','in_progress') AND (scheduled_at IS NULL OR scheduled_at >= NOW())");
  $stmt->execute();
  $health['meetings_upcoming'] = (int) $stmt->fetch()['upcoming'];

  // Financials (snapshot: received / spent / committed / available)
  $stmt = db()->prepare("SELECT
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS spent,
    SUM(CASE WHEN type = 'commitment' THEN amount ELSE 0 END) AS committed
  FROM financial_records");
  $stmt->execute();
  $finance = $stmt->fetch();
  $finance['available'] = $finance['income'] - $finance['spent'] - $finance['committed'];
  $health['finance'] = $finance;

  // Recent memberships
  $stmt = db()->prepare("SELECT COUNT(*) AS new_this_quarter FROM members WHERE joined_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
  $stmt->execute();
  $health['new_members_quarter'] = $stmt->fetch()['new_this_quarter'];

  return $health;
}
