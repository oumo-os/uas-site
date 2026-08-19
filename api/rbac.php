<?php
// UAS Institutional Platform — RBAC (Role-Based Access Control)
// Supports global and resource-scoped capabilities
require_once __DIR__ . '/config.php';

/**
 * Get all active capabilities for a user via their active role assignments.
 * Returns array of ['slug' => ..., 'scope_type' => ..., 'scope_id' => ...] entries.
 * Global capabilities have scope_type = null.
 */
function user_capabilities(int $userId): array {
  global $UAS_CAP_CACHE;
  if (!isset($UAS_CAP_CACHE)) $UAS_CAP_CACHE = [];
  if (isset($UAS_CAP_CACHE[$userId])) return $UAS_CAP_CACHE[$userId];

  $stmt = db()->prepare("
    SELECT DISTINCT c.slug, rc.scope_type, rc.scope_id
    FROM role_assignments ra
    JOIN role_capabilities rc ON rc.role_id = ra.role_id
    JOIN capabilities c ON c.id = rc.capability_id
    WHERE ra.user_id = ? AND ra.status = 'active'
      AND (ra.effective_to IS NULL OR ra.effective_to >= CURDATE())
      AND ra.effective_from <= CURDATE()
  ");
  $stmt->execute([$userId]);
  $caps = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $UAS_CAP_CACHE[$userId] = $caps;
  return $caps;
}

/**
 * Drop the per-request capability cache for a user (after role changes).
 */
function user_capabilities_invalidate(int $userId): void {
  global $UAS_CAP_CACHE;
  if (isset($UAS_CAP_CACHE)) unset($UAS_CAP_CACHE[$userId]);
}

/**
 * Get flat list of global capability slugs (no scope).
 */
function user_global_caps(int $userId): array {
  return array_values(array_unique(array_map(fn($c) => $c['slug'], array_filter(user_capabilities($userId), fn($c) => !$c['scope_type']))));
}

/**
 * Check if a user has a specific capability (global OR scoped).
 * For global check: user_has_cap($userId, 'events.create')
 * For scoped check: user_has_cap($userId, 'events.create', 'programme', 5)
 */
function user_has_cap(int $userId, string $capability, ?string $scopeType = null, ?int $scopeId = null): bool {
  $caps = user_capabilities($userId);
  foreach ($caps as $c) {
    if ($c['slug'] !== $capability) continue;
    // Global cap matches any request
    if (!$c['scope_type']) return true;
    // Scoped cap: must match type and id
    if ($scopeType && $c['scope_type'] === $scopeType) {
      if (!$c['scope_id'] || ($scopeId && $c['scope_id'] == $scopeId)) return true;
    }
  }
  return false;
}

/**
 * Check if user has a capability for a specific resource.
 * Resolves the resource type from the table name or explicit params.
 */
function user_has_cap_for(int $userId, string $capability, string $resourceType, int $resourceId): bool {
  return user_has_cap($userId, $capability, $resourceType, $resourceId)
    || user_has_cap($userId, $capability); // fallback to global
}

/**
 * Get all active roles for a user.
 */
function user_roles(int $userId): array {
  $stmt = db()->prepare("
    SELECT r.*, ra.effective_from, ra.effective_to, ra.assigned_by, ra.resolution_id
    FROM role_assignments ra
    JOIN roles r ON r.id = ra.role_id
    WHERE ra.user_id = ? AND ra.status = 'active' AND r.status = 'active'
      AND (ra.effective_to IS NULL OR ra.effective_to >= CURDATE())
      AND ra.effective_from <= CURDATE()
  ");
  $stmt->execute([$userId]);
  return $stmt->fetchAll();
}

/**
 * Require the current user to have a capability. Returns user if authorized.
 * Optionally check scoped capability.
 */
function require_cap(string $capability, ?string $scopeType = null, ?int $scopeId = null): array {
  $user = require_login();
  if (!user_has_cap($user['id'], $capability, $scopeType, $scopeId)) {
    json_error("Insufficient permissions: {$capability}", 403);
  }
  return $user;
}

/**
 * Assign a capability to a role, optionally scoped to a resource.
 */
function assign_capability(int $roleId, int $capabilityId, int $grantedBy, ?string $scopeType = null, ?int $scopeId = null): void {
  db()->prepare(
    'INSERT IGNORE INTO role_capabilities (role_id, capability_id, granted_by, scope_type, scope_id) VALUES (?, ?, ?, ?, ?)'
  )->execute([$roleId, $capabilityId, $grantedBy, $scopeType, $scopeId]);
  audit_log('cap_assign', 'role', $roleId, [
    'capability_id' => $capabilityId,
    'granted_by' => $grantedBy,
    'scope_type' => $scopeType,
    'scope_id' => $scopeId,
  ]);
}

/**
 * Revoke a capability from a role (optionally only a specific scope).
 */
function revoke_capability(int $roleId, int $capabilityId, ?string $scopeType = null, ?int $scopeId = null): void {
  if ($scopeType !== null) {
    db()->prepare(
      'DELETE FROM role_capabilities WHERE role_id = ? AND capability_id = ? AND scope_type = ? AND (scope_id = ? OR (scope_id IS NULL AND ? IS NULL))'
    )->execute([$roleId, $capabilityId, $scopeType, $scopeId, $scopeId]);
  } else {
    db()->prepare(
      'DELETE FROM role_capabilities WHERE role_id = ? AND capability_id = ?'
    )->execute([$roleId, $capabilityId]);
  }
  audit_log('cap_revoke', 'role', $roleId, [
    'capability_id' => $capabilityId,
    'scope_type' => $scopeType,
    'scope_id' => $scopeId,
  ]);
}

/**
 * Create a role and optionally assign capabilities.
 * $caps can be: ['slug1', 'slug2'] or [['slug' => 'slug1', 'scope_type' => 'programme', 'scope_id' => 5], ...]
 */
function create_role(string $title, string $description, array $caps, int $createdBy, ?string $scope = null, ?int $resolutionId = null, ?string $roleType = null, ?string $target = null): int {
  $stmt = db()->prepare(
    'INSERT INTO roles (title, description, scope, role_type, target, created_by, resolution_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([$title, $description, $scope, $roleType ?? 'member_class', $target, $createdBy, $resolutionId]);
  $roleId = (int) db()->lastInsertId();

  $slugStmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
  foreach ($caps as $cap) {
    // Support both string slugs and arrays with scope info
    $slug = is_string($cap) ? $cap : ($cap['slug'] ?? null);
    $scopeType = is_array($cap) ? ($cap['scope_type'] ?? null) : null;
    $scopeId = is_array($cap) ? ($cap['scope_id'] ?? null) : null;

    if (!$slug) continue;
    $capId = is_numeric($slug) ? (int) $slug : null;
    if (!$capId) {
      $slugStmt->execute([$slug]);
      $capId = (int) $slugStmt->fetchColumn();
    }
    if ($capId) assign_capability($roleId, $capId, $createdBy, $scopeType, $scopeId);
  }

  audit_log('role_create', 'role', $roleId, [
    'title' => $title,
    'capabilities' => array_map(fn($c) => is_string($c) ? $c : $c['slug'], $caps),
    'scope' => $scope,
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
  user_capabilities_invalidate($userId);

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
  // Prune stale revoked rows first (reassign+revoke cycles would otherwise hit the unique key)
  db()->prepare(
    "DELETE FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = 'revoked'"
  )->execute([$roleId, $userId]);
  db()->prepare(
    "UPDATE role_assignments SET status = 'revoked' WHERE role_id = ? AND user_id = ? AND status = 'active'"
  )->execute([$roleId, $userId]);
  user_capabilities_invalidate($userId);
  audit_log('role_revoke', 'role', $roleId, ['user_id' => $userId]);
  if (function_exists('auto_revoke_delegations')) auto_revoke_delegations($userId);
}

/**
 * Explain why a user has a given authority — the governance trace.
 */
function authority_trace(int $userId): array {
  $roles = user_roles($userId);
  $trace = [];
  foreach ($roles as $role) {
    $stmt = db()->prepare('SELECT c.slug, rc.scope_type, rc.scope_id FROM role_capabilities rc JOIN capabilities c ON c.id = rc.capability_id WHERE rc.role_id = ?');
    $stmt->execute([$role['id']]);
    $caps = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
