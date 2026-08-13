<?php
// UAS Institutional Platform — RBAC (Role-Based Access Control)
require_once __DIR__ . '/config.php';

/**
 * Get all active capabilities for a user via their active role assignments.
 * Returns array of capability slug strings.
 */
function user_capabilities(int $userId): array {
  static $cache = [];
  if (isset($cache[$userId])) return $cache[$userId];

  $stmt = db()->prepare(`
    SELECT DISTINCT c.slug
    FROM role_assignments ra
    JOIN role_capabilities rc ON rc.role_id = ra.role_id
    JOIN capabilities c ON c.id = rc.capability_id
    WHERE ra.user_id = ? AND ra.status = 'active'
      AND (ra.effective_to IS NULL OR ra.effective_to >= CURDATE())
      AND ra.effective_from <= CURDATE()
  `);
  $stmt->execute([$userId]);
  $caps = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $cache[$userId] = $caps;
  return $caps;
}

/**
 * Check if a user has a specific capability.
 */
function user_has_cap(int $userId, string $capability): bool {
  return in_array($capability, user_capabilities($userId));
}

/**
 * Get all active roles for a user.
 */
function user_roles(int $userId): array {
  $stmt = db()->prepare(`
    SELECT r.*, ra.effective_from, ra.effective_to, ra.assigned_by, ra.resolution_id
    FROM role_assignments ra
    JOIN roles r ON r.id = ra.role_id
    WHERE ra.user_id = ? AND ra.status = 'active' AND r.status = 'active'
      AND (ra.effective_to IS NULL OR ra.effective_to >= CURDATE())
      AND ra.effective_from <= CURDATE()
  `);
  $stmt->execute([$userId]);
  return $stmt->fetchAll();
}

/**
 * Require the current user to have a capability. Returns user if authorized.
 */
function require_cap(string $capability): array {
  $user = require_login();
  if (!user_has_cap($user['id'], $capability)) {
    json_error("Insufficient permissions: {$capability}", 403);
  }
  return $user;
}

/**
 * Assign a capability to a role.
 */
function assign_capability(int $roleId, int $capabilityId, int $grantedBy): void {
  db()->prepare(
    'INSERT IGNORE INTO role_capabilities (role_id, capability_id, granted_by) VALUES (?, ?, ?)'
  )->execute([$roleId, $capabilityId, $grantedBy]);
  audit_log('cap_assign', 'role', $roleId, [
    'capability_id' => $capabilityId,
    'granted_by' => $grantedBy
  ]);
}

/**
 * Revoke a capability from a role.
 */
function revoke_capability(int $roleId, int $capabilityId): void {
  db()->prepare(
    'DELETE FROM role_capabilities WHERE role_id = ? AND capability_id = ?'
  )->execute([$roleId, $capabilityId]);
  audit_log('cap_revoke', 'role', $roleId, ['capability_id' => $capabilityId]);
}

/**
 * Create a role and optionally assign capabilities.
 */
function create_role(string $title, string $description, array $capIds, int $createdBy, ?int $resolutionId = null): int {
  $stmt = db()->prepare(
    'INSERT INTO roles (title, description, created_by, resolution_id) VALUES (?, ?, ?, ?)'
  );
  $stmt->execute([$title, $description, $createdBy, $resolutionId]);
  $roleId = (int) db()->lastInsertId();

  foreach ($capIds as $capId) {
    assign_capability($roleId, $capId, $createdBy);
  }

  audit_log('role_create', 'role', $roleId, [
    'title' => $title,
    'capabilities' => $capIds,
    'resolution_id' => $resolutionId
  ]);
  return $roleId;
}

/**
 * Assign a role to a user.
 */
function assign_role(int $roleId, int $userId, int $assignedBy, ?int $resolutionId = null, ?string $effectiveTo = null): void {
  // Deactivate any existing active assignment for this role+user
  db()->prepare(
    "UPDATE role_assignments SET status = 'inactive' WHERE role_id = ? AND user_id = ? AND status = 'active'"
  )->execute([$roleId, $userId]);

  db()->prepare(
    'INSERT INTO role_assignments (role_id, user_id, assigned_by, resolution_id, effective_from, effective_to, status)
     VALUES (?, ?, ?, ?, CURDATE(), ?, ?)'
  )->execute([$roleId, $userId, $assignedBy, $resolutionId, $effectiveTo, 'active']);

  audit_log('role_assign', 'role', $roleId, [
    'user_id' => $userId,
    'assigned_by' => $assignedBy,
    'resolution_id' => $resolutionId
  ]);
}

/**
 * Revoke a role from a user.
 */
function revoke_role(int $roleId, int $userId): void {
  db()->prepare(
    "UPDATE role_assignments SET status = 'revoked' WHERE role_id = ? AND user_id = ? AND status = 'active'"
  )->execute([$roleId, $userId]);
  audit_log('role_revoke', 'role', $roleId, ['user_id' => $userId]);
}

/**
 * Explain why a user has a given authority — the governance trace.
 */
function authority_trace(int $userId): array {
  $roles = user_roles($userId);
  $trace = [];
  foreach ($roles as $role) {
    $caps = user_capabilities($userId);
    $resolutionId = $role['resolution_id'];
    $resolution = null;
    if ($resolutionId) {
      $stmt = db()->prepare('SELECT code, title, passed_at FROM resolutions WHERE id = ?');
      $stmt->execute([$resolutionId]);
      $resolution = $stmt->fetch();
    }
    $trace[] = [
      'role' => $role['title'],
      'scope' => $role['scope'],
      'effective_from' => $role['effective_from'],
      'effective_to' => $role['effective_to'],
      'capabilities' => $caps,
      'source' => $resolution ? [
        'type' => 'resolution',
        'code' => $resolution['code'],
        'title' => $resolution['title'],
        'passed_at' => $resolution['passed_at']
      ] : [
        'type' => 'direct_assignment',
        'assigned_by' => $role['assigned_by']
      ]
    ];
  }
  return $trace;
}
