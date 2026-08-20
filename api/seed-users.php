<?php
// Seed additional users with different roles for testing
// Run: php api/seed-users.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/audit.php';

echo "=== Seeding Additional Users ===\n";

$users = [
  [
    'name' => 'Dr. Patricia Owino',
    'email' => 'patricia@astronomy.ug',
    'password' => 'password123',
    'category' => 'regular',
    'institution' => 'Makerere University',
    'location' => 'Kampala',
    'role' => 'Programme Lead',
    'role_desc' => 'Oversees programme execution and project delivery',
    'role_scope' => 'programme',
    'role_type' => 'governance',
    'role_target' => 'General Programme',
  ],
  [
    'name' => 'James Okello',
    'email' => 'james@astronomy.ug',
    'password' => 'password123',
    'category' => 'regular',
    'institution' => 'Uganda National Meteorological Authority',
    'location' => 'Entebbe',
    'role' => 'Communications Officer',
    'role_desc' => 'Manages public communications and media relations',
    'role_scope' => 'committee',
    'role_type' => 'administrative',
  ],
  [
    'name' => 'Nansubuga Florence',
    'email' => 'florence@astronomy.ug',
    'password' => 'password123',
    'category' => 'regular',
    'institution' => 'Makerere University',
    'location' => 'Kampala',
    'role' => 'Education WG Lead',
    'role_desc' => 'Leads the Education Working Group',
    'role_scope' => 'working_group',
    'role_type' => 'governance',
    'role_target' => 'Education Working Group',
  ],
  [
    'name' => 'Brian Mugisha',
    'email' => 'brian@astronomy.ug',
    'password' => 'password123',
    'category' => 'student',
    'institution' => 'Kyambogo University',
    'location' => 'Kampala',
    'role' => null, // just a Member
    'role_desc' => null,
  ],
  [
    'name' => 'Alice Nakamya',
    'email' => 'alice@astronomy.ug',
    'password' => 'password123',
    'category' => 'regular',
    'institution' => 'Uganda Astronomical Society',
    'location' => 'Kampala',
    'role' => null, // existing article author, just Member
    'role_desc' => null,
  ],
];

// Role capabilities mapping
$roleCaps = [
  'Programme Lead' => ['programmes.manage', 'projects.create', 'projects.manage', 'projects.approve', 'events.approve', 'reports.create', 'reports.approve'],
  'Communications Officer' => ['articles.submit', 'articles.review', 'events.create', 'events.rsvp', 'documents.upload'],
  'Education WG Lead' => ['articles.approve', 'events.approve', 'programmes.create', 'programmes.manage', 'reports.create'],
];

$adminId = 1; // System Admin

foreach ($users as $u) {
  // Check if user exists
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$u['email']]);
  $row = $stmt->fetch();

  if ($row) {
    $userId = $row['id'];
    echo "User {$u['email']} already exists (id={$userId})\n";
  } else {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    db()->prepare("INSERT INTO users (name, email, password, status, institution, location) VALUES (?, ?, ?, 'active', ?, ?)")
      ->execute([$u['name'], $u['email'], $hash, $u['institution'], $u['location']]);
    $userId = (int) db()->lastInsertId();

    // Create member record
    $memNum = 'UAS-2026-' . str_pad($userId + 10, 4, '0', STR_PAD_LEFT);
    db()->prepare("INSERT INTO members (user_id, membership_number, category, status, joined_date, profile_visible) VALUES (?, ?, ?, 'active', ?, 1)")
      ->execute([$userId, $memNum, $u['category'], date('Y-m-d')]);

    echo "Created: {$u['name']} ({$u['email']}) id={$userId}\n";
  }

  // Assign role if specified
  if ($u['role']) {
    // Get or create role
    $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
    $stmt->execute([$u['role']]);
    $roleRow = $stmt->fetch();

    if (!$roleRow) {
      $capIds = [];
      foreach (($roleCaps[$u['role']] ?? []) as $slug) {
        $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
        $stmt->execute([$slug]);
        $capId = $stmt->fetchColumn();
        if ($capId) $capIds[] = (int) $capId;
      }
      $roleId = create_role($u['role'], $u['role_desc'], $capIds, $adminId, $u['role_scope'] ?? null, null, $u['role_type'] ?? null, $u['role_target'] ?? null);
      echo "  Created role: {$u['role']} (id={$roleId})\n";
    } else {
      $roleId = $roleRow['id'];
    }

    // Check if already assigned
    $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
    $stmt->execute([$roleId, $userId]);
    if (!$stmt->fetch()) {
      assign_role($roleId, $userId, $adminId);
      echo "  Assigned {$u['name']} → {$u['role']}\n";
    } else {
      echo "  {$u['name']} already has {$u['role']}\n";
    }

    // Also ensure baseline Member role
    $stmt = db()->prepare('SELECT id FROM roles WHERE title = "Member"');
    $stmt->execute();
    $memberRoleId = $stmt->fetchColumn();
    if ($memberRoleId) {
      $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
      $stmt->execute([$memberRoleId, $userId]);
      if (!$stmt->fetch()) {
        assign_role($memberRoleId, $userId, $adminId);
      }
    }
  } else {
    // Ensure at least baseline Member role
    $stmt = db()->prepare('SELECT id FROM roles WHERE title = "Member"');
    $stmt->execute();
    $memberRoleId = $stmt->fetchColumn();
    if ($memberRoleId) {
      $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
      $stmt->execute([$memberRoleId, $userId]);
      if (!$stmt->fetch()) {
        assign_role($memberRoleId, $userId, $adminId);
        echo "  Assigned {$u['name']} → Member\n";
      }
    }
  }
}

echo "\n=== All test users ===\n";
$stmt = db()->query("
  SELECT u.name, u.email, u.status,
    GROUP_CONCAT(r.title SEPARATOR ', ') as roles
  FROM users u
  LEFT JOIN role_assignments ra ON ra.user_id = u.id AND ra.status = 'active'
  LEFT JOIN roles r ON r.id = ra.role_id AND r.status = 'active'
  WHERE u.status = 'active'
  GROUP BY u.id
  ORDER BY u.id
");
while ($row = $stmt->fetch()) {
  echo "  {$row['name']} <{$row['email']}> — {$row['roles']}\n";
}
echo "\n=== Done ===\n";
