<?php
// UAS Institutional Platform — Seed Data
// Run: php api/seed.php
// Creates default admin user + sample governance data

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/governance.php';
require_once __DIR__ . '/audit.php';

echo "=== UAS Platform Seed ===\n";

// 1. Create admin user
$adminEmail = 'admin@astronomy.ug';
$stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$adminEmail]);
if (!$stmt->fetch()) {
  $hash = password_hash('admin123', PASSWORD_DEFAULT);
  db()->prepare("INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, 'active')")
    ->execute(['System Admin', $adminEmail, $hash]);
  $adminId = (int) db()->lastInsertId();
  echo "Created admin: {$adminEmail} (id={$adminId})\n";

  // Create admin member record
  db()->prepare("INSERT INTO members (user_id, membership_number, category, status, joined_date) VALUES (?, ?, 'regular', 'active', ?)")
    ->execute([$adminId, 'UAS-2026-0001', date('Y-m-d')]);
} else {
  $adminId = $stmt->fetch()['id'];
  echo "Admin already exists (id={$adminId})\n";
}

// Ensure admin has Board Director role
$stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
$stmt->execute(['Board Director']);
$boardRoleId = $stmt->fetchColumn();
if ($boardRoleId) {
  $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
  $stmt->execute([$boardRoleId, $adminId]);
  if (!$stmt->fetch()) {
    assign_role($boardRoleId, $adminId, $adminId);
    echo "Assigned Admin → Board Director\n";
  }
}

// 2. Create board members
$boardMembers = [
  ['name' => 'Cosmus Ngabirano', 'email' => 'cosmus@astronomy.ug', 'title' => 'President'],
  ['name' => 'Malcom Kintu', 'email' => 'malcom@astronomy.ug', 'title' => 'Vice President'],
  ['name' => 'John Mukasa', 'email' => 'john@astronomy.ug', 'title' => 'Secretary'],
  ['name' => 'Sarah Namukasa', 'email' => 'sarah@astronomy.ug', 'title' => 'Treasurer'],
];

$boardRoleId = null;
foreach ($boardMembers as $i => $bm) {
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$bm['email']]);
  $row = $stmt->fetch();
  if (!$row) {
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    db()->prepare("INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, 'active')")
      ->execute([$bm['name'], $bm['email'], $hash]);
    $userId = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO members (user_id, membership_number, category, status, joined_date) VALUES (?, ?, 'regular', 'active', ?)")
      ->execute([$userId, 'UAS-2026-' . str_pad($userId + 1, 4, '0', STR_PAD_LEFT), date('Y-m-d')]);
    echo "Created member: {$bm['name']} (id={$userId})\n";
  } else {
    $userId = $row['id'];
  }

  // Create board role if not exists
  if (!$boardRoleId) {
    $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
    $stmt->execute(['Board Director']);
    $roleRow = $stmt->fetch();
    if (!$roleRow) {
      $boardRoleId = create_role('Board Director', 'Member of the UAS Board of Directors', [], $adminId);
      // Constitutional bootstrap: board holds the full approval + governance suite
      $boardCaps = [
        'members.view', 'members.approve', 'members.manage',
        'admin.system',
        'partners.manage', 'links.manage', 'assignments.manage',
        'resolutions.create', 'resolutions.vote', 'resolutions.manage',
        'events.approve', 'events.publish', 'events.cancel', 'events.rsvp', 'events.manage_rsvps',
        'articles.review', 'articles.approve', 'articles.publish',
        'programmes.approve', 'projects.approve',
        'finance.view', 'finance.record', 'finance.approve',
        'documents.review', 'documents.approve', 'documents.publish',
        'roles.create', 'roles.manage',
        'reports.review', 'reports.approve', 'reports.publish',
      ];
      foreach ($boardCaps as $slug) {
        $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
        $stmt->execute([$slug]);
        $capRow = $stmt->fetch();
        if ($capRow) assign_capability($boardRoleId, $capRow['id'], $adminId);
      }
      echo "Created role: Board Director (id={$boardRoleId}, caps=" . count($boardCaps) . ")\n";
    } else {
      $boardRoleId = $roleRow['id'];
    }
  }

  // Assign board role
  $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
  $stmt->execute([$boardRoleId, $userId]);
  if (!$stmt->fetch()) {
    assign_role($boardRoleId, $userId, $adminId);
    echo "Assigned {$bm['name']} → Board Director\n";
  }
}

// 3. Create sample roles
$sampleRoles = [
  ['title' => 'PR Director', 'desc' => 'Manages public relations and communications', 'caps' => ['articles.review', 'articles.approve', 'articles.publish', 'events.approve', 'events.publish']],
  ['title' => 'Education WG Lead', 'desc' => 'Leads the Education Working Group', 'caps' => ['articles.approve', 'events.approve', 'programmes.create', 'programmes.manage', 'reports.create']],
  ['title' => 'Programme Lead', 'desc' => 'Oversees programme execution', 'caps' => ['programmes.manage', 'projects.create', 'projects.manage', 'projects.approve', 'events.approve', 'reports.create', 'reports.approve']],
];

foreach ($sampleRoles as $sr) {
  $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
  $stmt->execute([$sr['title']]);
  if (!$stmt->fetch()) {
    $capIds = [];
    foreach ($sr['caps'] as $slug) {
      $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
      $stmt->execute([$slug]);
      $capRow = $stmt->fetch();
      if ($capRow) $capIds[] = $capRow['id'];
    }
    $roleId = create_role($sr['title'], $sr['desc'], $capIds, $adminId);
    echo "Created role: {$sr['title']} (id={$roleId}, caps=" . count($capIds) . ")\n";
  }
}

// 4. Baseline Member role (auto-granted on membership approval)
$stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
$stmt->execute(['Member']);
if (!$stmt->fetch()) {
  $memberCaps = ['articles.submit', 'events.create', 'events.rsvp', 'documents.upload', 'reports.create'];
  $capIds = [];
  foreach ($memberCaps as $slug) {
    $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
    $stmt->execute([$slug]);
    $capRow = $stmt->fetch();
    if ($capRow) $capIds[] = $capRow['id'];
  }
  create_role('Member', 'Baseline membership role', $capIds, $adminId);
  echo "Created role: Member (caps=" . count($capIds) . ")\n";
}

// 5. Create sample programme
$stmt = db()->prepare('SELECT id FROM programmes WHERE title = ?');
$stmt->execute(['Astronomy Education Programme']);
if (!$stmt->fetch()) {
  db()->prepare("INSERT INTO programmes (title, description, lead_id, status, objectives, created_by) VALUES (?, ?, ?, 'active', ?, ?)")
    ->execute([
      'Astronomy Education Programme',
      'UAS flagship programme for astronomy education across Ugandan schools and universities',
      null,
      'Increase astronomy literacy and inspire the next generation of Ugandan astronomers',
      $adminId
    ]);
  echo "Created programme: Astronomy Education Programme\n";
}

// 5. Create sample partner
$stmt = db()->prepare('SELECT id FROM partners WHERE organization = ?');
$stmt->execute(['Ministry of Science, Technology and Innovation']);
if (!$stmt->fetch()) {
  db()->prepare("INSERT INTO partners (organization, description, relationship_type, status) VALUES (?, ?, ?, 'active')")
    ->execute([
      'Ministry of Science, Technology and Innovation',
      'Government ministry overseeing science and technology policy in Uganda',
      'institutional'
    ]);
  echo "Created partner: Ministry of STI\n";
}

echo "\n=== Seed complete ===\n";
echo "Admin login: {$adminEmail} / admin123\n";
