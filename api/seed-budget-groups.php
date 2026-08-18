<?php
require_once __DIR__ . '/config.php';

echo "Seeding working groups and budget items...\n";

// 1. Create working groups
$groups = [
  ['name' => 'Education Working Group', 'type' => 'working_group', 'desc' => 'Oversees astronomy education programmes in schools and universities', 'chair' => 'sarah@astronomy.ug'],
  ['name' => 'Outreach Committee', 'type' => 'committee', 'desc' => 'Plans and coordinates public observing events and community outreach', 'chair' => 'malcom@astronomy.ug'],
  ['name' => 'Technical Task Force', 'type' => 'task_force', 'desc' => 'Handles telescope maintenance, observatory operations, and technical infrastructure', 'chair' => 'john@astronomy.ug'],
  ['name' => 'Finance & Governance Committee', 'type' => 'committee', 'desc' => 'Oversees financial planning, budgets, and governance compliance', 'chair' => 'cosmus@astronomy.ug'],
];

$stmtUser = db()->prepare('SELECT id FROM users WHERE email = ?');
$stmtWg = db()->prepare('SELECT id FROM working_groups WHERE name = ?');
$stmtMem = db()->prepare('SELECT id FROM working_group_members WHERE group_id = ? AND user_id = ?');

foreach ($groups as $g) {
  $stmtUser->execute([$g['chair']]);
  $chair = $stmtUser->fetch();
  if (!$chair) continue;

  $stmtWg->execute([$g['name']]);
  if ($stmtWg->fetch()) { echo "  skip: {$g['name']}\n"; continue; }

  db()->prepare('INSERT INTO working_groups (name, description, type, chair_id, created_by, term_start) VALUES (?, ?, ?, ?, 1, CURDATE())')
    ->execute([$g['name'], $g['desc'], $g['type'], $chair['id']]);
  $wgId = (int) db()->lastInsertId();

  // Add chair as member
  db()->prepare('INSERT INTO working_group_members (group_id, user_id, role, joined_date) VALUES (?, ?, "chair", CURDATE())')
    ->execute([$wgId, $chair['id']]);

  echo "  created: {$g['name']} (chair: {$g['chair']})\n";
}

// 2. Create budget items linked to existing programmes
$progStmt = db()->prepare('SELECT id, title FROM programmes LIMIT 3');
$progStmt->execute();
$programmes = $progStmt->fetchAll();

$budgetItems = [
  ['title' => 'School Telescope Equipment', 'type' => 'expense', 'amount' => 5000000, 'cat' => 'equipment', 'desc' => 'Purchase of Dobsonian telescopes for school programme', 'prog_idx' => 0],
  ['title' => 'Teacher Training Workshops', 'type' => 'expense', 'amount' => 2000000, 'cat' => 'training', 'desc' => 'Training materials and facilitator fees for teacher bootcamps', 'prog_idx' => 0],
  ['title' => 'UNESCO Grant Application', 'type' => 'income', 'amount' => 15000000, 'cat' => 'grant', 'desc' => 'Pending UNESCO capacity-building grant for astronomy education', 'prog_idx' => 0],
  ['title' => 'Membership Dues Collection Q3', 'type' => 'income', 'amount' => 3000000, 'cat' => 'membership', 'desc' => 'Expected membership dues for Q3 2026', 'prog_idx' => null],
  ['title' => 'Public Observing Night Costs', 'type' => 'expense', 'amount' => 800000, 'cat' => 'event', 'desc' => 'Monthly public observing night logistics and refreshments', 'prog_idx' => 1],
  ['title' => 'Stargazing Event Sponsorship', 'type' => 'income', 'amount' => 2000000, 'cat' => 'sponsorship', 'desc' => 'Sponsorship from telecom company for National Stargazing Night', 'prog_idx' => 1],
];

$stmtBudget = db()->prepare('SELECT id FROM budget_items WHERE title = ?');
foreach ($budgetItems as $b) {
  $stmtBudget->execute([$b['title']]);
  if ($stmtBudget->fetch()) { echo "  skip: {$b['title']}\n"; continue; }

  $progId = ($b['prog_idx'] !== null && isset($programmes[$b['prog_idx']])) ? $programmes[$b['prog_idx']]['id'] : null;

  db()->prepare('INSERT INTO budget_items (title, description, type, amount, category, programme_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, "active", 1)')
    ->execute([$b['title'], $b['desc'], $b['type'], $b['amount'], $b['cat'], $progId]);
  echo "  created: {$b['title']}\n";
}

echo "Done.\n";
