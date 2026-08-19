<?php
// UAS Institutional Platform — API Router
// Single entry point: all requests go through index.php?route=...
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/governance.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/backup.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim(str_replace('/uas/api', '', $path), '/');
$path = str_replace('/api', '', $path);
if ($path === '') $path = '/';

// CSRF guard: state-changing requests must come from our own origin.
// Headers are absent for CLI/tests, which are allowed; browsers always send
// Origin on POST, so cross-site form/fetch attacks get rejected here.
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
  $ref = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref !== '') {
    $refHost = parse_url($ref, PHP_URL_HOST);
    $ourHost = parse_url(SITE_URL, PHP_URL_HOST);
    if ($refHost && $ourHost && strcasecmp($refHost, $ourHost) !== 0) {
      json_error('Cross-origin request rejected', 403);
    }
  }
}

// CORS for dev
if (ENV === 'development') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  if ($method === 'OPTIONS') { http_response_code(204); exit; }
}

// ============================================================
// ROUTES
// ============================================================

try {
  // --- AUTH ---
  if ($path === '/auth/login' && $method === 'POST') {
    $data = input_json();
    $user = login($data['email'], $data['password']);
    json_response(['user' => public_user($user), 'capabilities' => user_capabilities($user['id'])]);
  }
  elseif ($path === '/auth/register' && $method === 'POST') {
    $data = input_json();
    $user = register($data['name'], $data['email'], $data['password'], $data);
    json_response(['user' => public_user($user)], 201);
  }
  elseif ($path === '/auth/logout' && $method === 'POST') {
    logout();
    json_response(['ok' => true]);
  }
  elseif ($path === '/auth/me') {
    $user = require_login();
    json_response(['user' => public_user($user), 'capabilities' => user_capabilities($user['id']), 'roles' => user_roles($user['id'])]);
  }
  elseif ($path === '/auth/password' && $method === 'PUT') {
    $user = require_login();
    $data = input_json();
    if (!password_verify($data['current_password'] ?? '', $user['password'])) json_error('Current password is incorrect', 400);
    $new = $data['new_password'] ?? '';
    if (!password_is_strong($new)) json_error('New password must be at least 8 characters with at least one letter and one digit', 400);
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    audit_log('password_change', 'user', $user['id']);
    json_response(['ok' => true]);
  }
  // --- ADMIN LOGIN-AS ---
  elseif ($path === '/admin/login-as' && $method === 'POST') {
    $admin = require_cap('admin.system');
    $data = input_json();
    $targetId = (int) ($data['user_id'] ?? 0);
    if (!$targetId) json_error('user_id is required', 400);
    $stmt = db()->prepare('SELECT id, name, email, status FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target || $target['status'] !== 'active') json_error('User not found or inactive', 404);
    // Log in as target user
    $_SESSION['user_id'] = $target['id'];
    $caps = user_capabilities($target['id']);
    $roles = user_roles($target['id']);
    audit_log('admin_login_as', 'user', $target['id'], ['admin_id' => $admin['id']]);
    json_response(['user' => public_user($target), 'capabilities' => $caps, 'roles' => $roles]);
  }
  // --- NOTIFICATIONS ---
  elseif ($path === '/notifications' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 50');
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    json_response(['items' => $items, 'unread' => (int) $stmt->fetchColumn()]);
  }
  elseif ($path === '/notifications/read-all' && $method === 'POST') {
    $user = require_login();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/notifications/(\d+)/read$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int) $m[1], $user['id']]);
    json_response(['ok' => true]);
  }

  // --- BACKUPS ---
  elseif ($path === '/backups' && $method === 'GET') {
    require_cap('admin.system');
    json_response(backup_list());
  }
  elseif ($path === '/backups' && $method === 'POST') {
    require_cap('admin.system');
    $name = backup_create();
    audit_log('backup_create', 'backup', 0, ['file' => $name]);
    json_response(['file' => $name], 201);
  }
  elseif (preg_match('#^/backups/([A-Za-z0-9._-]+\.sql\.gz)/download$#', $path, $m) && $method === 'GET') {
    require_cap('admin.system');
    $name = $m[1];
    if (!backup_filename_ok($name)) json_error('Invalid backup name', 400);
    $full = backup_path($name);
    if (!is_file($full)) json_error('Backup not found', 404);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
  }
  elseif (preg_match('#^/backups/([A-Za-z0-9._-]+\.sql\.gz)$#', $path, $m) && $method === 'DELETE') {
    require_cap('admin.system');
    $name = $m[1];
    if (!backup_filename_ok($name)) json_error('Invalid backup name', 400);
    $full = backup_path($name);
    if (!is_file($full)) json_error('Backup not found', 404);
    backup_delete($name);
    audit_log('backup_delete', 'backup', 0, ['file' => $name]);
    json_response(['ok' => true]);
  }

  // --- MEETINGS ---
  elseif ($path === '/meetings' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT m.*, u.name AS created_by_name,
      (SELECT COUNT(*) FROM meeting_attendance ma WHERE ma.meeting_id = m.id) AS attendees
      FROM meetings m LEFT JOIN users u ON u.id = m.created_by
      ORDER BY COALESCE(m.scheduled_at, m.created_at) DESC');
    $stmt->execute();
    $list = $stmt->fetchAll();
    foreach ($list as &$mtg) {
      $mtg['agenda'] = $mtg['agenda'] ? json_decode($mtg['agenda'], true) : [];
      $mtg['decisions'] = $mtg['decisions'] ? json_decode($mtg['decisions'], true) : [];
      $mtg['my_attendance'] = null;
      $s = db()->prepare('SELECT status FROM meeting_attendance WHERE meeting_id = ? AND user_id = ?');
      $s->execute([$mtg['id'], $user['id']]);
      $mtg['my_attendance'] = $s->fetchColumn();
    }
    json_response($list);
  }
  elseif ($path === '/meetings' && $method === 'POST') {
    $user = require_cap('meetings.create');
    $data = input_json();
    if (empty($data['title'])) json_error('Title is required', 400);
    $validTypes = ['board', 'general', 'committee', 'working_group', 'other'];
    $type = $data['meeting_type'] ?? 'board';
    if (!in_array($type, $validTypes, true)) json_error('Invalid meeting type', 400);
    db()->prepare('INSERT INTO meetings (title, meeting_type, description, scheduled_at, location, agenda, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $data['title'],
        $type,
        $data['description'] ?? null,
        $data['scheduled_at'] ?? null,
        $data['location'] ?? null,
        isset($data['agenda']) ? json_encode($data['agenda']) : null,
        $data['status'] ?? 'scheduled',
        $user['id']
      ]);
    $id = (int) db()->lastInsertId();
    audit_log('meeting_create', 'meeting', $id);
    notify_capability('meetings.manage', 'meeting_scheduled', 'Meeting scheduled: ' . $data['title'], 'A new meeting has been scheduled. Review it from the dashboard.', '/dashboard');
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/meetings/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT m.*, u.name AS created_by_name FROM meetings m LEFT JOIN users u ON u.id = m.created_by WHERE m.id = ?');
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();
    if (!$meeting) json_error('Meeting not found', 404);
    $meeting['agenda'] = $meeting['agenda'] ? json_decode($meeting['agenda'], true) : [];
    $meeting['decisions'] = $meeting['decisions'] ? json_decode($meeting['decisions'], true) : [];
    $stmt = db()->prepare('SELECT ma.*, u.name AS user_name FROM meeting_attendance ma JOIN users u ON u.id = ma.user_id WHERE ma.meeting_id = ? ORDER BY u.name');
    $stmt->execute([$id]);
    $meeting['attendance'] = $stmt->fetchAll();
    json_response($meeting);
  }
  elseif (preg_match('#^/meetings/(\d+)$#', $path, $m) && $method === 'PUT') {
    require_cap('meetings.manage');
    $data = input_json();
    $id = (int) $m[1];
    $sets = [];
    $args = [];
    foreach (['title', 'meeting_type', 'description', 'location'] as $f) {
      if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $args[] = $data[$f]; }
    }
    if (array_key_exists('scheduled_at', $data)) { $sets[] = 'scheduled_at = ?'; $args[] = $data['scheduled_at'] ?: null; }
    if (array_key_exists('agenda', $data)) { $sets[] = 'agenda = ?'; $args[] = json_encode($data['agenda']); }
    if (!$sets) json_error('Nothing to update', 400);
    $args[] = $id;
    db()->prepare('UPDATE meetings SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    audit_log('meeting_update', 'meeting', $id);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/meetings/(\d+)/status$#', $path, $m) && $method === 'POST') {
    $user = require_cap('meetings.manage');
    $data = input_json();
    $status = $data['status'] ?? '';
    if (!in_array($status, ['scheduled', 'in_progress', 'completed', 'cancelled'], true)) json_error('Invalid status', 400);
    db()->prepare('UPDATE meetings SET status = ? WHERE id = ?')->execute([$status, (int) $m[1]]);
    audit_log('meeting_status', 'meeting', (int) $m[1], ['status' => $status]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/meetings/(\d+)/attendance$#', $path, $m) && $method === 'POST') {
    require_cap('meetings.record');
    $data = input_json();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT id FROM meetings WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) json_error('Meeting not found', 404);
    foreach ($data['attendees'] ?? [] as $a) {
      if (empty($a['user_id'])) continue;
      db()->prepare('INSERT INTO meeting_attendance (meeting_id, user_id, status, notes) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)')
        ->execute([$id, (int) $a['user_id'], $a['status'] ?? 'absent', $a['notes'] ?? null]);
    }
    audit_log('meeting_attendance', 'meeting', $id);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/meetings/(\d+)/minutes$#', $path, $m) && $method === 'POST') {
    $user = require_cap('meetings.record');
    $data = input_json();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT id, title FROM meetings WHERE id = ?');
    $stmt->execute([$id]);
    $meetingRow = $stmt->fetch();
    if (!$meetingRow) json_error('Meeting not found', 404);
    $decisions = $data['decisions'] ?? [];
    db()->prepare('UPDATE meetings SET minutes = ?, decisions = ? WHERE id = ?')
      ->execute([$data['minutes'] ?? null, json_encode($decisions), $id]);
    // Action items become assignments
    $stmt = db()->prepare('INSERT INTO assignments (title, description, assignee_id, assigner_id, due_date, priority, related_type, related_id, status)
      VALUES (?, ?, ?, ?, ?, ?, "meeting", ?, "not_started")');
    foreach ($decisions as $d) {
      if (empty($d['text'])) continue;
      $assigneeId = !empty($d['assignee_id']) ? (int) $d['assignee_id'] : null;
      $text = trim($d['text']);
      $stmt->execute([
        mb_substr($text, 0, 120),
        $text,
        $assigneeId,
        $user['id'],
        $d['due_date'] ?? null,
        $d['priority'] ?? 'medium',
        $id
      ]);
      $asgId = (int) db()->lastInsertId();
      if ($assigneeId) {
        notify_user($assigneeId, 'assignment_assigned', 'Action item: ' . mb_substr($text, 0, 80), 'Assigned from meeting minutes.', '/dashboard');
      }
    }
    audit_log('meeting_minutes', 'meeting', $id);
    json_response(['ok' => true]);
  }

  // --- POLLS ---
  elseif ($path === '/polls' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT p.*, u.name AS created_by_name FROM polls p LEFT JOIN users u ON u.id = p.created_by ORDER BY p.created_at DESC');
    $stmt->execute();
    $polls = $stmt->fetchAll();
    foreach ($polls as &$p) {
      $p['options'] = json_decode($p['options'], true) ?: [];
      $p['my_vote'] = null;
      $s = db()->prepare('SELECT option_index FROM poll_votes WHERE poll_id = ? AND user_id = ?');
      $s->execute([$p['id'], $user['id']]);
      $myVote = $s->fetchColumn();
      $p['my_vote'] = $myVote !== false ? (int) $myVote : null;
      $s = db()->prepare('SELECT COUNT(*) FROM poll_votes WHERE poll_id = ?');
      $s->execute([$p['id']]);
      $p['votes'] = (int) $s->fetchColumn();
      $p['eligible'] = poll_eligible($p, $user['id']);
    }
    json_response($polls);
  }
  elseif ($path === '/polls' && $method === 'POST') {
    $user = require_cap('governance.poll.create');
    $data = input_json();
    $options = $data['options'] ?? [];
    if (empty($data['title']) || count($options) < 2) json_error('Title and at least 2 options are required', 400);
    db()->prepare('INSERT INTO polls (title, description, poll_type, eligibility, options, quorum, allow_anonymous, starts_at, ends_at, status, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['poll_type'] ?? 'consultation',
        $data['eligibility'] ?? 'directors',
        json_encode($options),
        (int) ($data['quorum'] ?? 0),
        !empty($data['allow_anonymous']) ? 1 : 0,
        $data['starts_at'] ?? null,
        $data['ends_at'] ?? null,
        'draft',
        $user['id']
      ]);
    $id = (int) db()->lastInsertId();
    audit_log('poll_create', 'poll', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/polls/(\d+)$#', $path, $m) && $method === 'GET') {
    $user = require_login();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT p.*, u.name AS created_by_name FROM polls p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?');
    $stmt->execute([$id]);
    $poll = $stmt->fetch();
    if (!$poll) json_error('Poll not found', 404);
    $poll['options'] = json_decode($poll['options'], true) ?: [];
    $poll['my_vote'] = null;
    $s = db()->prepare('SELECT option_index FROM poll_votes WHERE poll_id = ? AND user_id = ?');
    $s->execute([$id, $user['id']]);
    $myVote = $s->fetchColumn();
    $poll['my_vote'] = $myVote !== false ? (int) $myVote : null;
    $results = [];
    $s = db()->prepare('SELECT option_index, COUNT(*) AS c FROM poll_votes WHERE poll_id = ? GROUP BY option_index');
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) $results[(int) $r['option_index']] = (int) $r['c'];
    $poll['results'] = $results;
    $poll['voters'] = [];
    if (!$poll['allow_anonymous']) {
      $s = db()->prepare('SELECT pv.*, u.name AS voter_name FROM poll_votes pv JOIN users u ON u.id = pv.user_id WHERE pv.poll_id = ? ORDER BY pv.voted_at');
      $s->execute([$id]);
      $poll['voters'] = $s->fetchAll();
    }
    json_response($poll);
  }
  elseif (preg_match('#^/polls/(\d+)/open$#', $path, $m) && $method === 'POST') {
    require_cap('governance.poll.manage');
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT id, title FROM polls WHERE id = ?');
    $stmt->execute([$id]);
    $poll = $stmt->fetch();
    if (!$poll) json_error('Poll not found', 404);
    db()->prepare("UPDATE polls SET status = 'open', starts_at = COALESCE(starts_at, NOW()) WHERE id = ?")->execute([$id]);
    audit_log('poll_open', 'poll', $id);
    notify_capability('governance.poll.vote', 'poll_open', 'Poll open: ' . $poll['title'], 'Voting is now open — cast your vote from the dashboard.', '/dashboard');
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/polls/(\d+)/vote$#', $path, $m) && $method === 'POST') {
    $user = require_cap('governance.poll.vote');
    $data = input_json();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT * FROM polls WHERE id = ?');
    $stmt->execute([$id]);
    $poll = $stmt->fetch();
    if (!$poll) json_error('Poll not found', 404);
    if ($poll['status'] !== 'open') json_error('Poll is not open for voting', 400);
    if ($poll['ends_at'] && $poll['ends_at'] < date('Y-m-d H:i:s')) json_error('Poll has ended', 400);
    if (!poll_eligible($poll, $user['id'])) json_error('You are not eligible to vote in this poll', 403);
    $options = json_decode($poll['options'], true) ?: [];
    $opt = isset($data['option_index']) ? (int) $data['option_index'] : -1;
    if ($opt < 0 || $opt >= count($options)) json_error('Invalid option', 400);
    $s = db()->prepare('SELECT 1 FROM poll_votes WHERE poll_id = ? AND user_id = ?');
    $s->execute([$id, $user['id']]);
    if ($s->fetch()) json_error('You have already voted in this poll', 409);
    db()->prepare('INSERT INTO poll_votes (poll_id, user_id, option_index) VALUES (?, ?, ?)')->execute([$id, $user['id'], $opt]);
    audit_log('poll_vote', 'poll', $id, ['option' => $opt]);
    apply_delegated_poll_votes($id, $poll, $user['id'], $opt);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/polls/(\d+)/close$#', $path, $m) && $method === 'POST') {
    require_cap('governance.poll.manage');
    $id = (int) $m[1];
    $result = close_poll($id);
    $stmt = db()->prepare('SELECT title FROM polls WHERE id = ?');
    $stmt->execute([$id]);
    $title = $stmt->fetchColumn();
    audit_log('poll_close', 'poll', $id, ['result' => $result]);
    notify_capability('governance.poll.vote', 'poll_closed', 'Poll closed: ' . $title, 'Results have been finalized.', '/dashboard');
    json_response(['ok' => true, 'result' => $result]);
  }
  elseif (preg_match('#^/polls/(\d+)/resolve$#', $path, $m) && $method === 'POST') {
    require_cap('governance.poll.resolve');
    $id = (int) $m[1];
    $result = close_poll($id);
    $stmt = db()->prepare('SELECT title FROM polls WHERE id = ?');
    $stmt->execute([$id]);
    $title = $stmt->fetchColumn();
    audit_log('poll_resolve', 'poll', $id, ['result' => $result]);
    notify_capability('governance.poll.vote', 'poll_closed', 'Poll resolved: ' . $title, 'Results have been recorded.', '/dashboard');
    json_response(['ok' => true, 'result' => $result]);
  }
  elseif (preg_match('#^/polls/(\d+)$#', $path, $m) && $method === 'DELETE') {
    require_cap('governance.poll.manage');
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT status FROM polls WHERE id = ?');
    $stmt->execute([$id]);
    $poll = $stmt->fetch();
    if (!$poll) json_error('Poll not found', 404);
    if ($poll['status'] !== 'draft') json_error('Only draft polls can be deleted', 400);
    db()->prepare('DELETE FROM polls WHERE id = ?')->execute([$id]);
    audit_log('poll_delete', 'poll', $id);
    json_response(['ok' => true]);
  }

  elseif ($path === '/profile' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT m.interests, m.contributions, m.profile_visible, m.membership_number, m.category FROM members m WHERE m.user_id = ?');
    $stmt->execute([$user['id']]);
    $member = $stmt->fetch() ?: [];
    json_response(['user' => public_user($user), 'member' => $member]);
  }
  elseif ($path === '/profile' && $method === 'PUT') {
    $user = require_login();
    $data = input_json();
    $allowed = ['name', 'phone', 'institution', 'location', 'bio', 'avatar_url'];
    $sets = [];
    $args = [];
    foreach ($allowed as $f) {
      if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $args[] = $data[$f]; }
    }
    if ($sets) {
      $args[] = $user['id'];
      db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    }
    $mSets = [];
    $mArgs = [];
    foreach (['interests', 'contributions', 'profile_visible'] as $f) {
      if (array_key_exists($f, $data)) {
        $mSets[] = "$f = ?";
        $mArgs[] = $f === 'profile_visible' ? ($data[$f] ? 1 : 0) : $data[$f];
      }
    }
    if ($mSets) {
      $mArgs[] = $user['id'];
      db()->prepare('UPDATE members SET ' . implode(', ', $mSets) . ' WHERE user_id = ?')->execute($mArgs);
    }
    audit_log('profile_update', 'user', $user['id']);
    json_response(['ok' => true]);
  }

  // --- MEMBERS ---
  elseif ($path === '/members' && $method === 'GET') {
    $isPublic = isset($_GET['public']) && $_GET['public'] === '1';
    if ($isPublic) {
      $stmt = db()->prepare("SELECT m.id, m.user_id, m.category, m.membership_number, m.interests, m.joined_date, u.name, u.institution, u.location, u.avatar_url FROM members m JOIN users u ON u.id = m.user_id WHERE m.profile_visible = 1 AND m.status = 'active' AND u.status = 'active' ORDER BY u.name");
      $stmt->execute();
      json_response($stmt->fetchAll());
    }
    require_login();
    $stmt = db()->prepare('SELECT m.*, u.name, u.email, u.avatar_url, u.institution, u.location, u.status AS user_status FROM members m JOIN users u ON u.id = m.user_id ORDER BY m.joined_date DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/members' && $method === 'POST') {
    $user = require_cap('members.approve');
    $data = input_json();
    $newStatus = in_array($data['status'] ?? '', ['active', 'rejected']) ? $data['status'] : 'pending';
    db()->prepare("UPDATE members SET status = ?, approved_by = ?, approved_at = NOW() WHERE user_id = ?")
      ->execute([$newStatus, $user['id'], $data['user_id']]);
    db()->prepare("UPDATE users SET status = ? WHERE id = ?")
      ->execute([$newStatus, $data['user_id']]);

    // On approval, ensure the member holds the baseline Member role
    if ($newStatus === 'active') {
      $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
      $stmt->execute(['Member']);
      $memberRoleId = $stmt->fetchColumn();
      if (!$memberRoleId) {
        // Create baseline Member role with correct capability slugs
        $baselineSlugs = ['articles.submit', 'events.create', 'events.rsvp', 'documents.upload', 'reports.create'];
        $capIds = [];
        foreach ($baselineSlugs as $slug) {
          $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
          $stmt->execute([$slug]);
          $id = $stmt->fetchColumn();
          if ($id) $capIds[] = (int) $id;
        }
        $memberRoleId = create_role('Member', 'Baseline membership role', $capIds, $user['id']);
      }
      $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
      $stmt->execute([$memberRoleId, $data['user_id']]);
      if (!$stmt->fetch()) {
        assign_role($memberRoleId, $data['user_id'], $user['id']);
      }
    }

    audit_log('member_' . ($newStatus === 'active' ? 'approve' : 'reject'), 'member', $data['user_id'], [
      'approved_by' => $user['id'],
      'status' => $newStatus
    ]);
    if ($newStatus === 'active') {
      notify_user((int) $data['user_id'], 'membership_approved', 'Membership approved', 'Your membership application has been approved. Welcome to the society!', '/dashboard');
    } elseif ($newStatus === 'rejected') {
      notify_user((int) $data['user_id'], 'membership_rejected', 'Membership not approved', 'Your membership application was not approved. Please contact us for more information.', '/contact');
    }
    json_response(['ok' => true]);
  }
  elseif ($path === '/members/grouped' && $method === 'GET') {
    $isPublic = isset($_GET['public']) && $_GET['public'] === '1';
    $loggedIn = !empty($_SESSION['user_id']);
    if ($isPublic || !$loggedIn) {
      $stmt = db()->prepare("
        SELECT DISTINCT m.id, m.user_id, m.category, m.membership_number, m.interests, m.joined_date,
               u.name, u.institution, u.location, u.avatar_url, u.bio
        FROM members m
        JOIN users u ON u.id = m.user_id
        LEFT JOIN role_assignments ra ON ra.user_id = m.user_id AND ra.status = 'active'
        LEFT JOIN roles r ON r.id = ra.role_id AND r.status = 'active'
        WHERE m.status = 'active' AND u.status = 'active'
          AND (m.profile_visible = 1 OR r.scope IN ('board','institutional','committee','programme') OR r.scope LIKE 'programme:%' OR r.scope LIKE 'working_group:%')
        ORDER BY u.name
      ");
      $stmt->execute();
      $members = $stmt->fetchAll();
    } else {
      $stmt = db()->prepare('SELECT m.*, u.name, u.email, u.avatar_url, u.institution, u.location, u.bio, u.status AS user_status FROM members m JOIN users u ON u.id = m.user_id ORDER BY u.name');
      $stmt->execute();
      $members = $stmt->fetchAll();
    }
    $memberIds = array_map(fn($m) => (int)($m['user_id'] ?? $m['id']), $members);
    $rolesByUser = [];
    if ($memberIds) {
      $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
      $stmt = db()->prepare(
        "SELECT ra.user_id, r.id AS role_id, r.title, r.role_type, r.scope, r.target, r.description
         FROM role_assignments ra
         JOIN roles r ON r.id = ra.role_id
         WHERE ra.user_id IN ($placeholders) AND ra.status = 'active' AND r.status = 'active'"
      );
      $stmt->execute($memberIds);
      foreach ($stmt->fetchAll() as $row) {
        $uid = (int) $row['user_id'];
        if (!isset($rolesByUser[$uid])) $rolesByUser[$uid] = [];
        $rolesByUser[$uid][] = [
          'role_id' => (int) $row['role_id'],
          'title' => $row['title'],
          'role_type' => $row['role_type'],
          'scope' => $row['scope'],
          'target' => $row['target'],
          'description' => $row['description'],
        ];
      }
    }
    foreach ($members as &$m) {
      $uid = (int)($m['user_id'] ?? $m['id']);
      $m['roles'] = $rolesByUser[$uid] ?? [];
    }
    json_response($members);
  }
  elseif (preg_match('#^/members/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $userId = (int) $m[1];
    $stmt = db()->prepare('SELECT m.*, u.name, u.email, u.avatar_url, u.bio, u.institution, u.location, u.website FROM members m JOIN users u ON u.id = m.user_id WHERE m.user_id = ?');
    $stmt->execute([$userId]);
    $member = $stmt->fetch();
    if (!$member) json_error('Member not found', 404);
    $stmt = db()->prepare("SELECT r.id AS role_id, r.title, r.role_type, r.scope, r.target, r.description
      FROM role_assignments ra JOIN roles r ON r.id = ra.role_id WHERE ra.user_id = ? AND ra.status = 'active' AND r.status = 'active'");
    $stmt->execute([$userId]);
    $member['roles'] = $stmt->fetchAll();
    json_response($member);
  }
  elseif (preg_match('#^/members/(\d+)/reset-password$#', $path, $m) && $method === 'POST') {
    $user = require_cap('members.manage');
    $userId = (int) $m[1];
    $stmt = db()->prepare('SELECT id, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) json_error('User not found', 404);
    $temp = bin2hex(random_bytes(4));
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $userId]);
    audit_log('member_password_reset', 'user', $userId, ['by' => $user['id']]);
    json_response(['temp_password' => $temp]);
  }

  // --- MEMBER CSV IMPORT ---
  elseif ($path === '/members/import' && $method === 'POST') {
    $user = require_cap('members.manage');
    $data = input_json();
    $csv = $data['csv'] ?? '';
    if (!trim($csv)) json_error('CSV data is required', 400);
    $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $csv))));
    if (count($lines) < 2) json_error('CSV must contain a header row and at least one member', 400);

    $created = 0; $skipped = []; $failed = [];
    $year = date('Y');

    try {
      db()->beginTransaction();
      foreach (array_slice($lines, 1) as $i => $line) {
        $fields = str_getcsv($line);
        $name = trim($fields[0] ?? '');
        $email = strtolower(trim($fields[1] ?? ''));
        $category = trim($fields[2] ?? '') ?: 'regular';
        $institution = trim($fields[3] ?? '');

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $failed[] = "Row " . ($i + 2) . ": missing name or invalid email"; continue; }

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) { $skipped[] = $email; continue; }

        $temp = strtolower(strtok($name, ' ')) . '2026';
        db()->prepare('INSERT INTO users (name, email, password, institution, status) VALUES (?, ?, ?, ?, "active")')
          ->execute([$name, $email, password_hash($temp, PASSWORD_DEFAULT), $institution ?: null]);
        $userId = (int) db()->lastInsertId();

        $stmt = db()->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(membership_number, -4) AS UNSIGNED)), 0) + 1 FROM members WHERE membership_number LIKE 'UAS-{$year}-%'");
        $stmt->execute();
        $memberNum = 'UAS-' . $year . '-' . str_pad($stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);

        db()->prepare('INSERT INTO members (user_id, membership_number, category, status, joined_date) VALUES (?, ?, ?, "active", ?)')
          ->execute([$userId, $memberNum, $category, date('Y-m-d')]);

        $stmt = db()->prepare('SELECT id FROM roles WHERE title = ?');
        $stmt->execute(['Member']);
        $memberRoleId = $stmt->fetchColumn();
        if (!$memberRoleId) {
          $memberRoleId = create_role('Member', 'Baseline membership role', [1, 7, 40, 27, 34], $user['id']);
        }
        assign_role($memberRoleId, $userId, $user['id']);
        $created++;
      }
      db()->commit();
    } catch (Exception $e) {
      db()->rollBack();
      json_error('Import failed: ' . $e->getMessage(), 500);
    }

    audit_log('members_import', 'system', $user['id'], ['created' => $created, 'skipped' => count($skipped)]);
    json_response([
      'created' => $created,
      'skipped' => $skipped,
      'failed' => $failed,
      'note' => 'Temporary passwords are firstname + 2026 (e.g. sarah2026).'
    ], 201);
  }

  // --- ROLES ---
  elseif ($path === '/roles' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT r.*, (SELECT COUNT(*) FROM role_assignments ra WHERE ra.role_id = r.id AND ra.status = "active") AS member_count FROM roles r WHERE r.status = "active" ORDER BY r.title');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/roles' && $method === 'POST') {
    $user = require_cap('roles.create');
    $data = input_json();
    $roleId = create_role($data['title'], $data['description'] ?? '', $data['capabilities'] ?? [], $user['id'], $data['scope'] ?? null, null, $data['role_type'] ?? null, $data['target'] ?? null);
    json_response(['id' => $roleId], 201);
  }
  elseif (preg_match('#^/roles/(\d+)$#', $path, $m) && $method === 'PUT') {
    $user = require_cap('roles.manage');
    $data = input_json();
    $roleId = (int) $m[1];
    $sets = [];
    $args = [];
    foreach (['title', 'description', 'scope', 'role_type', 'target', 'status'] as $f) {
      if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $args[] = $data[$f]; }
    }
    if ($sets) {
      $args[] = $roleId;
      db()->prepare('UPDATE roles SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    }
    audit_log('role_update', 'role', $roleId, $data);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/roles/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user = require_cap('roles.manage');
    $roleId = (int) $m[1];
    db()->prepare("UPDATE roles SET status = 'inactive' WHERE id = ?")->execute([$roleId]);
    db()->prepare("UPDATE role_assignments SET status = 'inactive' WHERE role_id = ? AND status = 'active'")->execute([$roleId]);
    audit_log('role_deactivate', 'role', $roleId);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/roles/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $roleId = (int) $m[1];
    $stmt = db()->prepare('SELECT r.*, (SELECT COUNT(*) FROM role_assignments ra WHERE ra.role_id = r.id AND ra.status = \'active\') AS member_count FROM roles r WHERE r.id = ?');
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    if (!$role) json_response(['error' => 'Role not found'], 404);
    json_response($role);
  }
  elseif (preg_match('#^/roles/(\d+)/capabilities$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT c.*, rc.scope_type, rc.scope_id FROM role_capabilities rc JOIN capabilities c ON c.id = rc.capability_id WHERE rc.role_id = ? ORDER BY c.category, c.slug');
    $stmt->execute([$m[1]]);
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/roles/(\d+)/capabilities$#', $path, $m) && $method === 'POST') {
    $user = require_cap('roles.manage');
    $roleId = (int) $m[1];
    $data = input_json();
    $capSlug = $data['capability'] ?? '';
    $scopeType = $data['scope_type'] ?? null;
    $scopeId = $data['scope_id'] ? (int) $data['scope_id'] : null;
    $stmt = db()->prepare('SELECT id FROM capabilities WHERE slug = ?');
    $stmt->execute([$capSlug]);
    $capId = (int) $stmt->fetchColumn();
    if (!$capId) json_error('Capability not found', 404);
    assign_capability($roleId, $capId, $user['id'], $scopeType, $scopeId);
    json_response(['ok' => true], 201);
  }
  elseif (preg_match('#^/roles/(\d+)/capabilities/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user = require_cap('roles.manage');
    // Delete the specific role_capabilities row by ID
    $stmt = db()->prepare('DELETE FROM role_capabilities WHERE id = ? AND role_id = ?');
    $stmt->execute([(int) $m[2], (int) $m[1]]);
    audit_log('cap_revoke', 'role', (int) $m[1], ['capability_id' => (int) $m[2]]);
    json_response(['ok' => true]);
  }
  // --- ROLE ASSIGNMENTS ---
  elseif (preg_match('#^/roles/(\d+)/users$#', $path, $m) && $method === 'GET') {
    require_login();
    $roleId = (int) $m[1];
    $stmt = db()->prepare('SELECT ra.*, u.name, u.email FROM role_assignments ra JOIN users u ON u.id = ra.user_id WHERE ra.role_id = ? AND ra.status = "active" ORDER BY u.name');
    $stmt->execute([$roleId]);
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/roles/(\d+)/users$#', $path, $m) && $method === 'POST') {
    $user = require_cap('roles.manage');
    $roleId = (int) $m[1];
    $data = input_json();
    $targetUserId = (int) ($data['user_id'] ?? 0);
    if (!$targetUserId) json_error('user_id is required', 400);
    $effectiveTo = $data['effective_to'] ?? null;
    assign_role($roleId, $targetUserId, $user['id'], null, $effectiveTo);
    json_response(['ok' => true], 201);
  }
  elseif (preg_match('#^/roles/(\d+)/users/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user = require_cap('roles.manage');
    revoke_role((int) $m[1], (int) $m[2]);
    json_response(['ok' => true]);
  }
  // --- USERS (for assignment dropdowns) ---
  elseif ($path === '/users' && $method === 'GET') {
    require_cap('roles.manage');
    $stmt = db()->prepare('SELECT id, name, email, status FROM users WHERE status = "active" ORDER BY name');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/users/(\d+)/roles$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT r.*, ra.effective_from, ra.effective_to, ra.assigned_by FROM role_assignments ra JOIN roles r ON r.id = ra.role_id WHERE ra.user_id = ? AND ra.status = "active" ORDER BY r.title');
    $stmt->execute([(int) $m[1]]);
    json_response($stmt->fetchAll());
  }

  // --- CAPABILITIES ---
  elseif ($path === '/capabilities' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT * FROM capabilities ORDER BY category, slug');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }

  // --- AUTHORITY TRACE ---
  elseif (preg_match('#^/authority/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    json_response(authority_trace((int) $m[1]));
  }

  // --- GOVERNANCE: RESOLUTIONS ---
  elseif ($path === '/resolutions' && $method === 'GET') {
    require_login();
    json_response(list_resolutions($_GET));
  }
  elseif ($path === '/resolutions' && $method === 'POST') {
    $user = require_cap('resolutions.create');
    $data = input_json();
    $id = create_resolution($data, $user['id']);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/resolutions/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $res = get_resolution((int) $m[1]);
    if (!$res) json_error('Resolution not found', 404);
    json_response($res);
  }
  elseif (preg_match('#^/resolutions/(\d+)/submit$#', $path, $m) && $method === 'POST') {
    $user = require_cap('resolutions.manage');
    submit_resolution((int) $m[1], $user['id']);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/resolutions/(\d+)/vote$#', $path, $m) && $method === 'POST') {
    $user = require_cap('resolutions.vote');
    $data = input_json();
    cast_vote((int) $m[1], $user['id'], $data['value'], $data['rationale'] ?? null);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/resolutions/(\d+)/begin-voting$#', $path, $m) && $method === 'POST') {
    $user = require_cap('resolutions.manage');
    $resId = (int) $m[1];
    $stmt = db()->prepare('SELECT title, code FROM resolutions WHERE id = ?');
    $stmt->execute([$resId]);
    $res = $stmt->fetch();
    if ($res) {
      notify_capability('resolutions.vote', 'resolution_voting', 'Voting open: ' . $res['title'], 'Resolution ' . $res['code'] . ' is now in voting. Cast your vote from the dashboard.', '/dashboard');
    }
    begin_voting($resId);
    json_response(['ok' => true]);
  }
  elseif ($path === '/governance/close-expired' && $method === 'POST') {
    require_cap('resolutions.manage');
    close_expired_voting();
    json_response(['ok' => true]);
  }

  // --- RESOLUTION COMMENTS ---
  elseif (preg_match('#^/resolutions/(\d+)/comments$#', $path, $m) && $method === 'GET') {
    require_login();
    $resId = (int) $m[1];
    $stmt = db()->prepare('
      SELECT c.*, u.name AS user_name
      FROM resolution_comments c
      JOIN users u ON u.id = c.user_id
      WHERE c.resolution_id = ?
      ORDER BY c.created_at ASC
    ');
    $stmt->execute([$resId]);
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/resolutions/(\d+)/comments$#', $path, $m) && $method === 'POST') {
    $user = require_cap('resolutions.vote');
    $resId = (int) $m[1];
    $data = input_json();
    $body = trim($data['body'] ?? '');
    if (!$body) json_error('Comment body is required', 400);
    $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;
    db()->prepare('INSERT INTO resolution_comments (resolution_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)')
      ->execute([$resId, $user['id'], $parentId, $body]);
    $commentId = (int) db()->lastInsertId();
    $stmt = db()->prepare('SELECT c.*, u.name AS user_name FROM resolution_comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?');
    $stmt->execute([$commentId]);
    json_response($stmt->fetch(), 201);
  }

  // --- DELEGATIONS (proxy voting) ---
  elseif ($path === '/delegations' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT d.*, u.name AS delegatee_name FROM delegations d JOIN users u ON u.id = d.delegatee_id WHERE d.delegator_id = ? AND d.status = "active" ORDER BY d.created_at DESC');
    $stmt->execute([$user['id']]);
    $outgoing = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT d.*, u.name AS delegator_name FROM delegations d JOIN users u ON u.id = d.delegator_id WHERE d.delegatee_id = ? AND d.status = "active" ORDER BY d.created_at DESC');
    $stmt->execute([$user['id']]);
    $incoming = $stmt->fetchAll();
    json_response(['outgoing' => $outgoing, 'incoming' => $incoming]);
  }
  elseif ($path === '/delegations' && $method === 'POST') {
    $user = require_login();
    $data = input_json();
    $id = create_delegation($user['id'], (int) ($data['delegatee_id'] ?? 0), $data['scope'] ?? 'all');
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/delegations/(\d+)/revoke$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    revoke_delegation((int) $m[1], $user['id']);
    json_response(['ok' => true]);
  }

  // --- PROGRAMMES ---
  elseif ($path === '/programmes' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT p.*, u.name AS lead_name FROM programmes p LEFT JOIN users u ON u.id = p.lead_id ORDER BY p.created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/programmes' && $method === 'POST') {
    $user = require_cap('programmes.create');
    $data = input_json();
    db()->prepare('INSERT INTO programmes (title, description, lead_id, objectives, created_by) VALUES (?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['description'] ?? null, $data['lead_id'] ?? null, $data['objectives'] ?? null, $user['id']]);
    $id = (int) db()->lastInsertId();
    audit_log('programme_create', 'programme', $id);
    json_response(['id' => $id], 201);
  }

  // --- PROJECTS ---
  elseif ($path === '/projects' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT p.*, u.name AS lead_name, pr.title AS programme_title FROM projects p LEFT JOIN users u ON u.id = p.lead_id LEFT JOIN programmes pr ON pr.id = p.programme_id ORDER BY p.created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/projects' && $method === 'POST') {
    $data = input_json();
    $progId = $data['programme_id'] ?? null;
    // Check global projects.create OR scoped to this programme
    $user = current_user();
    if (!$user) json_error('Authentication required', 401);
    if ($user['status'] !== 'active') json_error('Account is not active', 403);
    if (!user_has_cap($user['id'], 'projects.create') && !($progId && user_has_cap($user['id'], 'projects.create', 'programme', (int)$progId))) {
      json_error('Insufficient permissions: projects.create', 403);
    }
    db()->prepare('INSERT INTO projects (programme_id, title, description, lead_id, objectives, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
      ->execute([$progId, $data['title'], $data['description'] ?? null, $data['lead_id'] ?? null, $data['objectives'] ?? null, $data['deadline'] ?? null, $user['id']]);
    $id = (int) db()->lastInsertId();
    audit_log('project_create', 'project', $id);
    json_response(['id' => $id], 201);
  }

  // --- EVENTS ---
  elseif ($path === '/events' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT e.*, u.name AS organizer_name, pr.title AS programme_title FROM events e LEFT JOIN users u ON u.id = e.organizer_id LEFT JOIN programmes pr ON pr.id = e.programme_id ORDER BY e.date ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/events' && $method === 'POST') {
    $data = input_json();
    $progId = $data['programme_id'] ?? null;
    // Check global events.create OR scoped to this programme
    $user = current_user();
    if (!$user) json_error('Authentication required', 401);
    if ($user['status'] !== 'active') json_error('Account is not active', 403);
    if (!user_has_cap($user['id'], 'events.create') && !($progId && user_has_cap($user['id'], 'events.create', 'programme', (int)$progId))) {
      json_error('Insufficient permissions: events.create', 403);
    }
    db()->prepare('INSERT INTO events (programme_id, project_id, title, description, organizer_id, date, end_date, location, capacity, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$progId, $data['project_id'] ?? null, $data['title'], $data['description'] ?? null, $user['id'], $data['date'], $data['end_date'] ?? null, $data['location'] ?? null, $data['capacity'] ?? null, $user['id']]);
    $id = (int) db()->lastInsertId();
    transition('event', $id, 'submitted', $user['id']);
    audit_log('event_create', 'event', $id);
    notify_capability('events.approve', 'event_submitted', 'Event awaiting approval: ' . $data['title'], 'Submitted by ' . $user['name'] . '.', '/admin?tab=events');
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/events/(\d+)/approve$#', $path, $m) && $method === 'POST') {
    $eid = (int) $m[1];
    // Check global events.approve OR scoped to the event's programme
    $user = require_login();
    $stmt = db()->prepare('SELECT programme_id FROM events WHERE id = ?');
    $stmt->execute([$eid]);
    $progId = $stmt->fetchColumn();
    if (!user_has_cap($user['id'], 'events.approve') && !($progId && user_has_cap($user['id'], 'events.approve', 'programme', (int)$progId))) {
      json_error('Insufficient permissions: events.approve', 403);
    }
    transition('event', $eid, 'approved', $user['id']);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/publish$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.publish');
    $eid = (int) $m[1];
    transition('event', $eid, 'published', $user['id']);
    $stmt = db()->prepare('SELECT organizer_id, title FROM events WHERE id = ?');
    $stmt->execute([$eid]);
    $ev = $stmt->fetch();
    if ($ev) notify_user((int) $ev['organizer_id'], 'event_published', 'Event live: ' . $ev['title'], 'Your event has been approved and is now open for RSVPs.', '/event/' . $eid);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/cancel$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.cancel');
    db()->prepare("UPDATE events SET status = 'cancelled' WHERE id = ?")->execute([(int) $m[1]]);
    audit_log('event_cancel', 'event', (int) $m[1]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/rsvps/(\d+)/attended$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $eventId = (int) $m[1];
    $regId = (int) $m[2];
    $stmt = db()->prepare('SELECT organizer_id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);
    if (!user_has_cap($user['id'], 'events.manage_rsvps') && $event['organizer_id'] != $user['id']) {
      json_error('Insufficient permissions: events.manage_rsvps', 403);
    }
    db()->prepare("UPDATE event_registrations SET status = 'attended' WHERE id = ? AND event_id = ?")->execute([$regId, $eventId]);
    audit_log('rsvp_mark_attended', 'event', $eventId, ['registration_id' => $regId]);
    json_response(['ok' => true]);
  }
  // --- PUBLIC EVENT DETAIL ---
  elseif (preg_match('#^/events/(\d+)$#', $path, $m) && $method === 'GET') {
    $eventId = (int) $m[1];
    $stmt = db()->prepare('SELECT e.*, u.name AS organiser_name, pr.title AS programme_title FROM events e JOIN users u ON u.id = e.organizer_id LEFT JOIN programmes pr ON pr.id = e.programme_id WHERE e.id = ? AND e.status IN ("published", "cancelled", "completed")');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);

    $stmt = db()->prepare('SELECT COUNT(*) AS total, SUM(IF(status="attended",1,0)) AS attended FROM event_registrations WHERE event_id = ? AND status != "cancelled"');
    $stmt->execute([$eventId]);
    $regs = $stmt->fetch();

    $is_manager = false;
    $my_rsvp = null;
    $my_waitlist = null;
    $waitlist_count = 0;
    $registrations = [];
    $user = current_user();
    if ($user && $user['status'] === 'active') {
      if (user_has_cap($user['id'], 'events.rsvp')) {
        $stmt = db()->prepare('SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$eventId, $user['id']]);
        $my_rsvp = $stmt->fetch() ?: null;
        $stmt = db()->prepare('SELECT id, created_at FROM event_waitlist WHERE event_id = ? AND user_id = ?');
        $stmt->execute([$eventId, $user['id']]);
        $my_waitlist = $stmt->fetch() ?: null;
      }
      $is_manager = user_has_cap($user['id'], 'events.manage_rsvps');
      if ($is_manager) {
        $stmt = db()->prepare('SELECT er.*, u.name, u.email FROM event_registrations er JOIN users u ON u.id = er.user_id WHERE er.event_id = ? ORDER BY er.registered_at');
        $stmt->execute([$eventId]);
        $registrations = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT w.id, w.created_at, u.name, u.email FROM event_waitlist w JOIN users u ON u.id = w.user_id WHERE w.event_id = ? ORDER BY w.created_at ASC, w.id ASC');
        $stmt->execute([$eventId]);
        $waitlist = $stmt->fetchAll();
      }
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM event_waitlist WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $waitlist_count = (int) $stmt->fetchColumn();

    json_response([
      'event' => $event,
      'stats' => ['total' => (int)$regs['total'], 'attended' => (int)$regs['attended']],
      'my_rsvp' => $my_rsvp ? ['status' => $my_rsvp['status'], 'created_at' => $my_rsvp['registered_at']] : null,
      'is_manager' => $is_manager,
      'registrations' => $registrations,
      'waitlist_count' => $waitlist_count,
      'my_waitlist' => $my_waitlist ? ['id' => $my_waitlist['id'], 'created_at' => $my_waitlist['created_at']] : null,
      'waitlist' => $waitlist ?? [],
    ]);
  }

  elseif (preg_match('#^/events/(\d+)/waitlist$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.rsvp');
    $eventId = (int) $m[1];

    $stmt = db()->prepare('SELECT id, capacity, date FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);
    if ($event['date'] < date('Y-m-d H:i:s')) json_error('Event has already taken place', 400);
    if (!$event['capacity']) json_error('This event has no capacity limit', 400);

    $stmt = db()->prepare("SELECT status FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$eventId, $user['id']]);
    $reg = $stmt->fetch();
    if ($reg && $reg['status'] === 'registered') json_error('You are already registered', 409);

    $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
    $stmt->execute([$eventId]);
    if ((int) $stmt->fetchColumn() < (int) $event['capacity']) json_error('Event has space — RSVP directly instead', 400);

    try {
      db()->prepare('INSERT INTO event_waitlist (event_id, user_id) VALUES (?, ?)')->execute([$eventId, $user['id']]);
    } catch (PDOException $e) {
      json_error('You are already on the waitlist', 409);
    }
    audit_log('waitlist_join', 'event', $eventId, ['user_id' => $user['id']]);
    json_response(['ok' => true], 201);
  }
  elseif (preg_match('#^/events/(\d+)/waitlist$#', $path, $m) && $method === 'DELETE') {
    $user = require_login();
    db()->prepare('DELETE FROM event_waitlist WHERE event_id = ? AND user_id = ?')->execute([(int) $m[1], $user['id']]);
    audit_log('waitlist_leave', 'event', (int) $m[1], ['user_id' => $user['id']]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/waitlist/(\d+)/promote$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $eventId = (int) $m[1];
    $wlId = (int) $m[2];

    $stmt = db()->prepare('SELECT organizer_id, capacity FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);
    if (!user_has_cap($user['id'], 'events.manage_rsvps') && $event['organizer_id'] != $user['id']) {
      json_error('Insufficient permissions: events.manage_rsvps', 403);
    }

    $stmt = db()->prepare('SELECT * FROM event_waitlist WHERE id = ? AND event_id = ?');
    $stmt->execute([$wlId, $eventId]);
    $wl = $stmt->fetch();
    if (!$wl) json_error('Waitlist entry not found', 404);

    $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
    $stmt->execute([$eventId]);
    if ((int) $stmt->fetchColumn() >= (int) $event['capacity']) json_error('Event is at capacity', 400);

    db()->prepare('DELETE FROM event_waitlist WHERE id = ?')->execute([$wlId]);
    $stmt = db()->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$eventId, $wl['user_id']]);
    $existingReg = $stmt->fetch();
    if ($existingReg) {
      db()->prepare("UPDATE event_registrations SET status = 'registered', registered_at = NOW() WHERE id = ?")->execute([$existingReg['id']]);
    } else {
      db()->prepare('INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, "registered")')->execute([$eventId, $wl['user_id']]);
    }
    audit_log('waitlist_promote', 'event', $eventId, ['user_id' => $wl['user_id']]);
    json_response(['ok' => true]);
  }

  elseif (preg_match('#^/events/(\d+)/rsvp$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.rsvp');
    $eventId = (int) $m[1];

    $stmt = db()->prepare('SELECT id, capacity, date FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);
    if ($event['date'] < date('Y-m-d H:i:s')) json_error('Event has already taken place', 400);

    $stmt = db()->prepare('SELECT id, status FROM event_registrations WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$eventId, $user['id']]);
    $existing = $stmt->fetch();
    if ($existing) {
      if ($existing['status'] === 'registered') json_error('Already registered for this event', 409);
      if ($event['capacity']) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
        $stmt->execute([$eventId]);
        if ((int) $stmt->fetchColumn() >= (int) $event['capacity']) json_error('Event is full — join the waitlist instead', 400);
      }
      db()->prepare("UPDATE event_registrations SET status = 'registered', registered_at = NOW() WHERE id = ?")->execute([$existing['id']]);
      audit_log('event_rsvp', 'event', $eventId, ['user_id' => $user['id']]);
      json_response(['ok' => true], 201);
    }

    if ($event['capacity']) {
      $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
      $stmt->execute([$eventId]);
      if ((int) $stmt->fetchColumn() >= (int) $event['capacity']) json_error('Event is full', 400);
    }

    db()->prepare('INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, ?)')
      ->execute([$eventId, $user['id'], 'registered']);
    audit_log('event_rsvp', 'event', $eventId, ['user_id' => $user['id']]);
    json_response(['ok' => true], 201);
  }
  elseif (preg_match('#^/events/(\d+)/rsvp$#', $path, $m) && $method === 'DELETE') {
    $user = require_login();
    $eventId = (int) $m[1];
    db()->prepare("UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'registered'")
      ->execute([$eventId, $user['id']]);
    audit_log('event_rsvp_cancel', 'event', $eventId, ['user_id' => $user['id']]);
    promote_from_waitlist($eventId);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/rsvps$#', $path, $m) && $method === 'GET') {
    $user = require_login();
    $eventId = (int) $m[1];
    $stmt = db()->prepare('SELECT organizer_id FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) json_error('Event not found', 404);
    if (!user_has_cap($user['id'], 'events.manage_rsvps') && $event['organizer_id'] != $user['id']) {
      json_error('Insufficient permissions: events.manage_rsvps', 403);
    }
    $stmt = db()->prepare("SELECT r.*, u.name, u.email FROM event_registrations r JOIN users u ON u.id = r.user_id WHERE r.event_id = ? ORDER BY r.registered_at");
    $stmt->execute([$eventId]);
    json_response($stmt->fetchAll());
  }

  // --- ARTICLES ---
  elseif ($path === '/articles' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT a.*, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id ORDER BY a.created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/articles' && $method === 'POST') {
    $user = require_cap('articles.submit');
    $data = input_json();
    $tags = $data['tags'] ?? [];
    if (is_string($tags)) {
      $decoded = json_decode($tags, true);
      $tags = is_array($decoded) ? $decoded : array_map('trim', explode(',', $tags));
    }
    db()->prepare('INSERT INTO articles (author_id, title, body, category, tags, image_url, approver_role_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
      ->execute([$user['id'], $data['title'], $data['body'] ?? null, $data['category'] ?? 'article', json_encode(array_values(array_filter($tags))), $data['image_url'] ?? null, $data['approver_role_id'] ?? null]);
    $id = (int) db()->lastInsertId();
    transition('article', $id, 'submitted', $user['id']);
    audit_log('article_create', 'article', $id);
    notify_capability('articles.approve', 'article_submitted', 'Article awaiting review: ' . $data['title'], 'Submitted by ' . $user['name'] . '.', '/admin?tab=articles');
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/articles/(\d+)/approve$#', $path, $m) && $method === 'POST') {
    $user = require_cap('articles.approve');
    $aid = (int) $m[1];
    // Stage through review if not already under review
    if (get_current_state('article', $aid) === 'submitted') {
      db()->prepare(
        "INSERT INTO workflow_states (object_type, object_id, state, assignee_id, notes)
         VALUES ('article', ?, 'under_review', ?, 'auto-staged by approver')"
      )->execute([$aid, $user['id']]);
      update_object_status('article', $aid, 'under_review');
    }
    transition('article', $aid, 'approved', $user['id']);
    $stmt = db()->prepare('SELECT author_id, title FROM articles WHERE id = ?');
    $stmt->execute([$aid]);
    $art = $stmt->fetch();
    if ($art) notify_user((int) $art['author_id'], 'article_approved', 'Article approved: ' . $art['title'], 'Your article has been approved and is ready to publish.', '/article/' . $aid);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/articles/(\d+)/publish$#', $path, $m) && $method === 'POST') {
    $user = require_cap('articles.publish');
    $aid = (int) $m[1];
    transition('article', $aid, 'published', $user['id']);
    $stmt = db()->prepare('SELECT author_id, title FROM articles WHERE id = ?');
    $stmt->execute([$aid]);
    $art = $stmt->fetch();
    if ($art) notify_user((int) $art['author_id'], 'article_published', 'Article published: ' . $art['title'], 'Your article is now live on the site.', '/article/' . $aid);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/articles/(\d+)/reject$#', $path, $m) && $method === 'POST') {
    $user = require_cap('articles.approve');
    $data = input_json();
    $reason = trim($data['reason'] ?? '');
    if (!$reason) json_error('A rejection reason is required', 400);
    db()->prepare("UPDATE articles SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?")
      ->execute([$reason, $user['id'], (int) $m[1]]);
    audit_log('article_reject', 'article', (int) $m[1], ['reason' => $reason]);
    $stmt = db()->prepare('SELECT author_id, title FROM articles WHERE id = ?');
    $stmt->execute([(int) $m[1]]);
    $art = $stmt->fetch();
    if ($art) notify_user((int) $art['author_id'], 'article_rejected', 'Article not approved: ' . $art['title'], 'Reason: ' . $reason, '/dashboard');
    json_response(['ok' => true]);
  }

  // --- DOCUMENTS ---
  elseif ($path === '/documents' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT d.*, u.name AS owner_name FROM documents d JOIN users u ON u.id = d.owner_id ORDER BY d.created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/documents' && $method === 'POST') {
    $user = require_cap('documents.upload');
    $data = input_json();
    db()->prepare('INSERT INTO documents (title, file_path, category, visibility, owner_id) VALUES (?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['file_path'], $data['category'], $data['visibility'] ?? 'public', $user['id']]);
    $id = (int) db()->lastInsertId();
    transition('document', $id, 'submitted', $user['id']);
    audit_log('document_upload', 'document', $id);
    notify_capability('documents.approve', 'document_submitted', 'Document awaiting review: ' . $data['title'], 'Uploaded by ' . $user['name'] . '.', '/admin?tab=documents');
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/documents/(\d+)/approve$#', $path, $m) && $method === 'POST') {
    $user = require_cap('documents.approve');
    transition('document', (int) $m[1], 'approved', $user['id']);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/documents/(\d+)/publish$#', $path, $m) && $method === 'POST') {
    $user = require_cap('documents.publish');
    transition('document', (int) $m[1], 'published', $user['id']);
    json_response(['ok' => true]);
  }

  // --- FILE UPLOAD ---
  elseif ($path === '/upload' && $method === 'POST') {
    $user = require_login();
    if (!isset($_FILES['file'])) json_error('No file uploaded', 400);
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_error('Upload error: ' . $file['error'], 400);

    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) json_error('File too large (max 10MB)', 400);

    $allowedTypes = [
      'image/jpeg', 'image/png', 'image/gif', 'image/webp',
      'application/pdf',
      'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'text/plain', 'text/csv',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
      json_error('File type not allowed: ' . $mimeType, 400);
    }

    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
      json_error('Failed to save file', 500);
    }

    $relativePath = '/img/uploads/' . $filename;
    audit_log('file_upload', 'file', 0, ['filename' => $file['name'], 'path' => $relativePath]);
    json_response(['url' => $relativePath, 'filename' => $file['name'], 'mime' => $mimeType], 201);
  }

  // --- FINANCE ---
  elseif ($path === '/finance' && $method === 'GET') {
    require_cap('finance.view');
    $stmt = db()->prepare('SELECT f.*, u.name AS recorded_by_name, a.name AS approved_by_name,
      p.title AS programme_name, e.title AS event_name, pr.title AS project_name,
      bi.title AS budget_item_title
      FROM financial_records f
      JOIN users u ON u.id = f.recorded_by
      LEFT JOIN users a ON a.id = f.approved_by
      LEFT JOIN programmes p ON p.id = f.programme_id
      LEFT JOIN events e ON e.id = f.event_id
      LEFT JOIN projects pr ON pr.id = f.project_id
      LEFT JOIN budget_items bi ON bi.id = f.budget_item_id
      ORDER BY f.record_date DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/finance' && $method === 'POST') {
    $user = require_cap('finance.record');
    $data = input_json();
    db()->prepare('INSERT INTO financial_records (type, amount, category, programme_id, project_id, event_id, budget_item_id, description, recorded_by, record_date, due_date, status, attachment_url, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $data['type'], $data['amount'], $data['category'] ?? 'other',
        $data['programme_id'] ?? null, $data['project_id'] ?? null,
        $data['event_id'] ?? null, $data['budget_item_id'] ?? null,
        $data['description'] ?? null, $user['id'], $data['record_date'],
        $data['due_date'] ?? null, $data['status'] ?? 'approved',
        $data['attachment_url'] ?? null, $data['notes'] ?? null
      ]);
    $id = (int) db()->lastInsertId();
    audit_log('finance_record', 'financial_record', $id);
    json_response(['id' => $id], 201);
  }

  // --- FINANCE SUMMARY (authenticated) ---
  elseif ($path === '/finance/summary' && $method === 'GET') {
    require_cap('finance.view');

    $groupBy = $_GET['group_by'] ?? 'category';
    $validGroups = ['category', 'programme', 'event', 'project'];
    if (!in_array($groupBy, $validGroups)) $groupBy = 'category';

    $stmt = db()->prepare('SELECT type, SUM(amount) as total FROM financial_records GROUP BY type');
    $stmt->execute();
    $byType = [];
    foreach ($stmt->fetchAll() as $row) $byType[$row['type']] = (float) $row['total'];

    $groupQueries = [
      'category' => 'SELECT category AS group_name, type, SUM(amount) AS total FROM financial_records GROUP BY category, type ORDER BY category',
      'programme' => 'SELECT COALESCE(p.title, \'Unassigned\') AS group_name, f.type, SUM(f.amount) AS total FROM financial_records f LEFT JOIN programmes p ON p.id = f.programme_id GROUP BY group_name, f.type ORDER BY group_name',
      'event' => 'SELECT COALESCE(e.title, \'Unassigned\') AS group_name, f.type, SUM(f.amount) AS total FROM financial_records f LEFT JOIN events e ON e.id = f.event_id GROUP BY group_name, f.type ORDER BY group_name',
      'project' => 'SELECT COALESCE(pr.title, \'Unassigned\') AS group_name, f.type, SUM(f.amount) AS total FROM financial_records f LEFT JOIN projects pr ON pr.id = f.project_id GROUP BY group_name, f.type ORDER BY group_name',
    ];
    $stmt = db()->prepare($groupQueries[$groupBy]);
    $stmt->execute();
    $byGroup = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, type, amount, category, description, record_date FROM financial_records ORDER BY record_date DESC, id DESC LIMIT 10');
    $stmt->execute();
    $recent = $stmt->fetchAll();

    json_response([
      'income' => $byType['income'] ?? 0,
      'expense' => $byType['expense'] ?? 0,
      'commitment' => $byType['commitment'] ?? 0,
      'available' => ($byType['income'] ?? 0) - ($byType['expense'] ?? 0) - ($byType['commitment'] ?? 0),
      'by_group' => $byGroup,
      'group_by' => $groupBy,
      'recent' => $recent,
    ]);
  }

  // --- BUDGET ITEMS ---
  elseif ($path === '/budget-items' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT bi.*, p.title AS programme_name, e.title AS event_name, pr.title AS project_name,
      u.name AS created_by_name,
      (SELECT COALESCE(SUM(f.amount), 0) FROM financial_records f WHERE f.budget_item_id = bi.id AND f.type = "income" AND f.status != "cancelled") AS spent_income,
      (SELECT COALESCE(SUM(f.amount), 0) FROM financial_records f WHERE f.budget_item_id = bi.id AND f.type = "expense" AND f.status != "cancelled") AS spent_expense
      FROM budget_items bi
      LEFT JOIN programmes p ON p.id = bi.programme_id
      LEFT JOIN events e ON e.id = bi.event_id
      LEFT JOIN projects pr ON pr.id = bi.project_id
      LEFT JOIN users u ON u.id = bi.created_by
      ORDER BY bi.created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/budget-items' && $method === 'POST') {
    $user = require_cap('finance.record');
    $data = input_json();
    db()->prepare('INSERT INTO budget_items (title, description, type, amount, category, programme_id, project_id, event_id, fiscal_year, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $data['title'], $data['description'] ?? null, $data['type'],
        $data['amount'], $data['category'] ?? 'other',
        $data['programme_id'] ?? null, $data['project_id'] ?? null,
        $data['event_id'] ?? null, $data['fiscal_year'] ?? date('Y'),
        $data['status'] ?? 'draft', $user['id']
      ]);
    $id = (int) db()->lastInsertId();
    audit_log('budget_item_create', 'budget_item', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/budget-items/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT bi.*, p.title AS programme_name, e.title AS event_name, pr.title AS project_name FROM budget_items bi LEFT JOIN programmes p ON p.id = bi.programme_id LEFT JOIN events e ON e.id = bi.event_id LEFT JOIN projects pr ON pr.id = bi.project_id WHERE bi.id = ?');
    $stmt->execute([(int) $m[1]]);
    $item = $stmt->fetch();
    if (!$item) json_error('Not found', 404);
    json_response($item);
  }

  // --- WORKING GROUPS ---
  elseif ($path === '/working-groups' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT wg.*, u.name AS chair_name, p.title AS programme_name,
      (SELECT COUNT(*) FROM working_group_members wgm WHERE wgm.group_id = wg.id AND wgm.status = "active") AS member_count
      FROM working_groups wg
      LEFT JOIN users u ON u.id = wg.chair_id
      LEFT JOIN programmes p ON p.id = wg.programme_id
      WHERE wg.status = "active"
      ORDER BY wg.name');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/working-groups/with-members' && $method === 'GET') {
    $isPublic = isset($_GET['public']) && $_GET['public'] === '1';
    $stmt = db()->prepare('SELECT wg.*, u.name AS chair_name, p.title AS programme_name
      FROM working_groups wg
      LEFT JOIN users u ON u.id = wg.chair_id
      LEFT JOIN programmes p ON p.id = wg.programme_id
      WHERE wg.status = "active"
      ORDER BY wg.name');
    $stmt->execute();
    $groups = $stmt->fetchAll();
    $groupIds = array_map(fn($g) => (int) $g['id'], $groups);
    $membersByGroup = [];
    if ($groupIds) {
      $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
      $stmt = db()->prepare("SELECT wgm.*, u.name AS user_name, u.email FROM working_group_members wgm JOIN users u ON u.id = wgm.user_id WHERE wgm.group_id IN ($placeholders) AND wgm.status = 'active' ORDER BY wgm.role, u.name");
      $stmt->execute($groupIds);
      foreach ($stmt->fetchAll() as $row) {
        $gid = (int) $row['group_id'];
        if (!isset($membersByGroup[$gid])) $membersByGroup[$gid] = [];
        $membersByGroup[$gid][] = $row;
      }
    }
    foreach ($groups as &$g) {
      $g['members'] = $membersByGroup[(int) $g['id']] ?? [];
    }
    json_response($groups);
  }
  elseif ($path === '/working-groups' && $method === 'POST') {
    $user = require_cap('roles.create');
    $data = input_json();
    db()->prepare('INSERT INTO working_groups (name, description, type, chair_id, programme_id, created_by, resolution_id, term_start, term_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([
        $data['name'], $data['description'] ?? null, $data['type'] ?? 'working_group',
        $data['chair_id'] ?? null, $data['programme_id'] ?? null,
        $user['id'], $data['resolution_id'] ?? null,
        $data['term_start'] ?? null, $data['term_end'] ?? null
      ]);
    $id = (int) db()->lastInsertId();
    audit_log('working_group_create', 'working_group', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/working-groups/(\d+)/members$#', $path, $m) && $method === 'GET') {
    require_login();
    $groupId = (int) $m[1];
    $stmt = db()->prepare('SELECT wgm.*, u.name AS user_name, u.email FROM working_group_members wgm JOIN users u ON u.id = wgm.user_id WHERE wgm.group_id = ? AND wgm.status = "active" ORDER BY wgm.role, u.name');
    $stmt->execute([$groupId]);
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/working-groups/(\d+)/members$#', $path, $m) && $method === 'POST') {
    $user = require_cap('roles.manage');
    $groupId = (int) $m[1];
    $data = input_json();
    $userId = (int) $data['user_id'];
    $role = $data['role'] ?? 'member';
    db()->prepare('INSERT INTO working_group_members (group_id, user_id, role, joined_date) VALUES (?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE role = VALUES(role), status = "active"')
      ->execute([$groupId, $userId, $role]);
    audit_log('working_group_member_add', 'working_group', $groupId, ['user_id' => $userId]);
    json_response(['ok' => true], 201);
  }

  // --- ASSIGNMENTS ---
  elseif ($path === '/assignments' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT a.*, u.name AS assignee_name, cr.name AS assigner_name, r.title AS role_title FROM assignments a LEFT JOIN users u ON u.id = a.assignee_id LEFT JOIN users cr ON cr.id = a.assigner_id LEFT JOIN roles r ON r.id = a.role_id ORDER BY a.due_date ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/assignments' && $method === 'POST') {
    $user = require_cap('assignments.create');
    $data = input_json();
    db()->prepare('INSERT INTO assignments (title, description, assignee_id, assigner_id, role_id, due_date, priority, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['description'] ?? null, $data['assignee_id'] ?? null, $user['id'], $data['role_id'] ?? null, $data['due_date'] ?? null, $data['priority'] ?? 'medium', $data['related_type'] ?? null, $data['related_id'] ?? null]);
    $id = (int) db()->lastInsertId();
    audit_log('assignment_create', 'assignment', $id);
    if (!empty($data['assignee_id'])) {
      notify_user((int) $data['assignee_id'], 'assignment_assigned', 'New assignment: ' . $data['title'], 'An assignment has been given to you' . (!empty($data['due_date']) ? ' (due ' . $data['due_date'] . ')' : '') . '.', '/dashboard');
    }
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/assignments/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT a.*, u.name AS assignee_name, cr.name AS assigner_name, r.title AS role_title FROM assignments a LEFT JOIN users u ON u.id = a.assignee_id LEFT JOIN users cr ON cr.id = a.assigner_id LEFT JOIN roles r ON r.id = a.role_id WHERE a.id = ?');
    $stmt->execute([(int) $m[1]]);
    $item = $stmt->fetch();
    if (!$item) json_error('Not found', 404);
    json_response($item);
  }
  elseif ($path === '/assignments/mine' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare('SELECT a.*, cr.name AS assigner_name FROM assignments a LEFT JOIN users cr ON cr.id = a.assigner_id WHERE a.assignee_id = ? ORDER BY a.due_date ASC');
    $stmt->execute([$user['id']]);
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/submissions/mine' && $method === 'GET') {
    $user = require_login();
    $stmt = db()->prepare("SELECT id, title, 'article' AS kind, category AS extra, status, rejection_reason, created_at FROM articles WHERE author_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $articles = $stmt->fetchAll();
    $stmt = db()->prepare("SELECT id, title, 'event' AS kind, NULL AS extra, status, NULL AS rejection_reason, created_at FROM events WHERE organizer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
    $stmt = db()->prepare("SELECT id, title, 'document' AS kind, category AS extra, status, NULL AS rejection_reason, created_at FROM documents WHERE owner_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $documents = $stmt->fetchAll();
    $all = array_merge($articles, $events, $documents);
    usort($all, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    json_response($all);
  }
  elseif (preg_match('#^/assignments/(\d+)/start$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $stmt = db()->prepare('UPDATE assignments SET status = "in_progress" WHERE id = ? AND assignee_id = ? AND status = "not_started"');
    $stmt->execute([(int) $m[1], $user['id']]);
    if (!$stmt->rowCount()) json_error('Assignment not found or already started', 404);
    audit_log('assignment_start', 'assignment', (int) $m[1]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/assignments/(\d+)/submit$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $data = input_json();
    $stmt = db()->prepare('UPDATE assignments SET status = "submitted", completion_evidence = ? WHERE id = ? AND assignee_id = ?');
    $stmt->execute([$data['evidence'] ?? null, (int) $m[1], $user['id']]);
    if (!$stmt->rowCount()) json_error('Assignment not found or not assigned to you', 404);
    audit_log('assignment_submit', 'assignment', (int) $m[1]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/assignments/(\d+)/complete$#', $path, $m) && $method === 'POST') {
    $user = require_login();
    $stmt = db()->prepare('SELECT assignee_id, assigner_id FROM assignments WHERE id = ?');
    $stmt->execute([(int) $m[1]]);
    $asg = $stmt->fetch();
    if (!$asg) json_error('Assignment not found', 404);
    if ($asg['assigner_id'] != $user['id'] && !user_has_cap($user['id'], 'assignments.manage')) {
      json_error('Only the assigner or a manager can complete this assignment', 403);
    }
    db()->prepare("UPDATE assignments SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $m[1]]);
    audit_log('assignment_complete', 'assignment', (int) $m[1]);
    json_response(['ok' => true]);
  }

  // --- CALENDAR ---
  elseif ($path === '/calendar' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT c.*, u.name AS owner_name FROM calendar_items c LEFT JOIN users u ON u.id = c.owner_id ORDER BY c.deadline ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/calendar' && $method === 'POST') {
    $user = require_cap('calendar.manage');
    $data = input_json();
    db()->prepare('INSERT INTO calendar_items (title, type, owner_id, deadline, priority, related_type, related_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['type'], $user['id'], $data['deadline'], $data['priority'] ?? 'medium', $data['related_type'] ?? null, $data['related_id'] ?? null, $data['notes'] ?? null]);
    $id = (int) db()->lastInsertId();
    audit_log('calendar_create', 'calendar_item', $id);
    json_response(['id' => $id], 201);
  }

  // --- PENDING ITEMS ---
  elseif ($path === '/pending' && $method === 'GET') {
    require_login();
    $scope = $_GET['scope'] ?? 'org';
    $userId = $scope === 'mine' ? $_SESSION['user_id'] : null;
    json_response(get_pending_items($userId));
  }

  // --- DASHBOARD / HEALTH ---
  elseif ($path === '/dashboard' && $method === 'GET') {
    require_login();
    $health = institutional_health();
    // Enrich with current user's member-since and last login
    $user = current_user();
    if ($user) {
      $stmt = db()->prepare('SELECT joined_date FROM members WHERE user_id = ?');
      $stmt->execute([$user['id']]);
      $health['member_since'] = $stmt->fetchColumn() ?: null;
      $stmt = db()->prepare('SELECT last_login FROM users WHERE id = ?');
      $stmt->execute([$user['id']]);
      $health['last_login'] = $stmt->fetchColumn() ?: null;
      // Upcoming events (next 3)
      $stmt = db()->prepare("SELECT e.id, e.title, e.date, e.location, e.capacity,
        (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS rsvp_count
        FROM events e WHERE e.date >= NOW() AND e.status IN ('approved','published') ORDER BY e.date ASC LIMIT 3");
      $stmt->execute();
      $health['upcoming_events'] = $stmt->fetchAll();
    }
    json_response($health);
  }

  // --- ACTIVITY FEED ---
  elseif ($path === '/activity' && $method === 'GET') {
    require_login();
    $limit = min((int)($_GET['limit'] ?? 20), 50);
    $stmt = db()->prepare("SELECT al.*, u.avatar_url FROM audit_log al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    json_response($stmt->fetchAll());
  }

  // --- PARTNERS ---
  elseif ($path === '/partners' && $method === 'GET') {
    $stmt = db()->prepare('SELECT * FROM partners WHERE status = "active" ORDER BY organization');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/partners' && $method === 'POST') {
    $user = require_cap('partners.manage');
    $data = input_json();
    db()->prepare('INSERT INTO partners (organization, website, logo_url, description, relationship_type, status) VALUES (?, ?, ?, ?, ?, ?)')
      ->execute([$data['organization'], $data['website'] ?? null, $data['logo_url'] ?? null, $data['description'] ?? null, $data['relationship_type'] ?? 'partner', $data['status'] ?? 'active']);
    $id = (int) db()->lastInsertId();
    audit_log('partner_create', 'partner', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/partners/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user = require_cap('partners.manage');
    db()->prepare('UPDATE partners SET status = "inactive" WHERE id = ?')->execute([(int) $m[1]]);
    audit_log('partner_deactivate', 'partner', (int) $m[1]);
    json_response(['ok' => true]);
  }

  // --- USEFUL LINKS ---
  elseif ($path === '/links' && $method === 'GET') {
    $stmt = db()->prepare('SELECT * FROM useful_links WHERE status = "active" ORDER BY category, title');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/links' && $method === 'POST') {
    $user = require_cap('links.manage');
    $data = input_json();
    db()->prepare('INSERT INTO useful_links (title, url, category, description, status) VALUES (?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['url'], $data['category'] ?? null, $data['description'] ?? null, $data['status'] ?? 'active']);
    $id = (int) db()->lastInsertId();
    audit_log('link_create', 'link', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/links/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user = require_cap('links.manage');
    db()->prepare('UPDATE useful_links SET status = "inactive" WHERE id = ?')->execute([(int) $m[1]]);
    audit_log('link_deactivate', 'link', (int) $m[1]);
    json_response(['ok' => true]);
  }

  // --- CONTACT ---
  elseif ($path === '/contact' && $method === 'POST') {
    $data = input_json();
    if (!empty($data['website'])) json_response(['ok' => true]); // honeypot — pretend success
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $message = trim($data['message'] ?? '');
    if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
      json_error('Name, valid email, and message are required', 400);
    }
    if (mb_strlen($message) > 5000) json_error('Message too long', 400);
    db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)')
      ->execute([$name, $email, trim($data['subject'] ?? ''), $message]);
    $n = notify_capability('admin.system', 'contact_message', 'New contact message from ' . $name, mb_substr($message, 0, 120) . (mb_strlen($message) > 120 ? '…' : ''), '/admin?tab=inbox');
    json_response(['ok' => true], 201);
  }
  elseif ($path === '/contact-messages' && $method === 'GET') {
    $user = require_cap('admin.system');
    $stmt = db()->prepare('SELECT * FROM contact_messages ORDER BY created_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/contact-messages/read-all' && $method === 'POST') {
    $user = require_cap('admin.system');
    db()->prepare("UPDATE contact_messages SET status = 'read' WHERE status = 'new'")->execute();
    json_response(['ok' => true]);
  }

  // --- SEARCH ---
  elseif ($path === '/search' && $method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) json_error('Search term must be at least 2 characters', 400);
    $like = '%' . $q . '%';
    $user = current_user();
    $loggedIn = !empty($user) && ($user['status'] ?? '') === 'active';

    $stmt = db()->prepare('SELECT a.id, a.title, a.category, a.published_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = "published" AND (a.title LIKE ? OR a.body LIKE ? OR a.tags LIKE ?) ORDER BY a.published_at DESC LIMIT 10');
    $stmt->execute([$like, $like, $like]);
    $articles = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, title, description, date, location, status FROM events WHERE status IN ("published", "cancelled") AND (title LIKE ? OR description LIKE ?) ORDER BY date LIMIT 10');
    $stmt->execute([$like, $like]);
    $events = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, title, description, status FROM programmes WHERE status = "active" AND (title LIKE ? OR description LIKE ?) LIMIT 10');
    $stmt->execute([$like, $like]);
    $programmes = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, title, description, status FROM projects WHERE status = "active" AND (title LIKE ? OR description LIKE ?) LIMIT 10');
    $stmt->execute([$like, $like]);
    $projects = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, title, category, file_path FROM documents WHERE status = "published" AND visibility = "public" AND (title LIKE ?) LIMIT 10');
    $stmt->execute([$like]);
    $documents = $stmt->fetchAll();

    if ($loggedIn) {
      $stmt = db()->prepare("SELECT m.user_id, u.name, u.institution, m.category, m.membership_number FROM members m JOIN users u ON u.id = m.user_id WHERE m.status = 'active' AND (u.name LIKE ? OR u.institution LIKE ?) LIMIT 10");
    } else {
      $stmt = db()->prepare("SELECT m.user_id, u.name, u.institution, m.category FROM members m JOIN users u ON u.id = m.user_id WHERE m.status = 'active' AND m.profile_visible = 1 AND (u.name LIKE ? OR u.institution LIKE ?) LIMIT 10");
    }
    $stmt->execute([$like, $like]);
    $members = $stmt->fetchAll();

    json_response(compact('articles', 'events', 'programmes', 'projects', 'documents', 'members'));
  }

  // --- EXPORTS (CSV) ---
  elseif ($path === '/export/members.csv' && $method === 'GET') {
    $user = require_cap('members.view');
    $stmt = db()->prepare("SELECT m.membership_number, u.name, u.email, m.category, m.status, m.joined_date, u.institution, u.location, m.interests FROM members m JOIN users u ON u.id = m.user_id ORDER BY m.joined_date");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $csv = "membership_number,name,email,category,status,joined_date,institution,location,interests\n";
    foreach ($rows as $r) {
      $cols = array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', array_values($r));
      $csv .= implode(',', $cols) . "\n";
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="members.csv"');
    echo $csv;
    exit;
  }
  elseif ($path === '/export/finance.csv' && $method === 'GET') {
    $user = require_cap('finance.view');
    $stmt = db()->prepare('SELECT f.record_date, f.type, f.amount, f.category, f.description, u.name AS recorded_by FROM financial_records f JOIN users u ON u.id = f.recorded_by ORDER BY f.record_date');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $csv = "record_date,type,amount,category,description,recorded_by\n";
    foreach ($rows as $r) {
      $cols = array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', array_values($r));
      $csv .= implode(',', $cols) . "\n";
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="finance.csv"');
    echo $csv;
    exit;
  }
  elseif ($path === '/export/audit.csv' && $method === 'GET') {
    $user = require_cap('admin.system');
    $stmt = db()->prepare('SELECT created_at, user_name, action_type, target_type, target_id, details FROM audit_log ORDER BY created_at DESC');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $csv = "created_at,user,action,target_type,target_id,details\n";
    foreach ($rows as $r) {
      $cols = array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', array_values($r));
      $csv .= implode(',', $cols) . "\n";
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit.csv"');
    echo $csv;
    exit;
  }

  // --- AUDIT LOG ---
  elseif ($path === '/audit' && $method === 'GET') {
    require_cap('admin.system');
    json_response(query_audit_log($_GET));
  }

  // --- PUBLIC: published articles, events, programmes (for website) ---
  elseif ($path === '/public/news' && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.category, a.image_url, a.published_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = "published" AND a.category = "announcement" ORDER BY a.published_at DESC');
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT id, title, description, url, category, external_organization FROM useful_links WHERE status = "active" ORDER BY category, title');
    $stmt->execute();
    $links = $stmt->fetchAll();
    json_response(['announcements' => $announcements, 'links' => $links]);
  }
  elseif ($path === '/public/gallery' && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.category, a.image_url, a.published_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = "published" AND a.image_url IS NOT NULL AND a.image_url <> "" ORDER BY a.published_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/public/articles' && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.category, a.tags, a.image_url, a.published_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = "published" ORDER BY a.published_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/public/articles/(\d+)$#', $path, $m) && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.body, a.category, a.tags, a.image_url, a.published_at, a.created_at, a.author_id, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.id = ? AND a.status = "published"');
    $stmt->execute([(int) $m[1]]);
    $article = $stmt->fetch();
    if (!$article) json_error('Article not found', 404);

    $stmt = db()->prepare('SELECT id, title, category, tags, image_url, published_at, author_id FROM articles WHERE id != ? AND status = "published" AND (category = ? OR tags LIKE ?) ORDER BY published_at DESC LIMIT 3');
    $cat = $article['category'];
    $tag = '%' . ($article['tags'] !== null ? substr($article['tags'], 0, 20) : '') . '%';
    $stmt->execute([(int) $m[1], $cat, $tag]);
    $article['related'] = $stmt->fetchAll();

    json_response($article);
  }
  elseif ($path === '/public/events' && $method === 'GET') {
    $stmt = db()->prepare("SELECT e.id, e.title, e.description, e.date, e.end_date, e.location, e.status, e.capacity,
      (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS rsvp_count
      FROM events e WHERE (e.status = 'published' AND e.date >= NOW()) OR e.status = 'cancelled' ORDER BY e.date ASC");
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/public/programmes' && $method === 'GET') {
    $stmt = db()->prepare('SELECT p.id, p.title, p.description, p.status, u.name AS lead_name FROM programmes p LEFT JOIN users u ON u.id = p.lead_id WHERE p.status = "active" ORDER BY p.title');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/public/programmes/(\d+)$#', $path, $m) && $method === 'GET') {
    $pid = (int) $m[1];
    $stmt = db()->prepare('SELECT p.*, u.name AS lead_name, u.email AS lead_email FROM programmes p LEFT JOIN users u ON u.id = p.lead_id WHERE p.id = ?');
    $stmt->execute([$pid]);
    $programme = $stmt->fetch();
    if (!$programme) json_error('Programme not found', 404);

    $requester = current_user();

    // Projects — budget/spent only for members
    if ($requester) {
      $stmt = db()->prepare('SELECT id, title, description, status, deadline, budget, spent FROM projects WHERE programme_id = ? ORDER BY title');
    } else {
      $stmt = db()->prepare('SELECT id, title, description, status, deadline FROM projects WHERE programme_id = ? ORDER BY title');
    }
    $stmt->execute([$pid]);
    $programme['projects'] = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id, title, date, end_date, location, status FROM events WHERE programme_id = ? ORDER BY date ASC');
    $stmt->execute([$pid]);
    $programme['events'] = $stmt->fetchAll();

    // Members and outputs are member-only
    if ($requester) {
      $stmt = db()->prepare('SELECT pm.*, u.name AS user_name, u.email AS user_email FROM programme_members pm JOIN users u ON u.id = pm.user_id WHERE pm.programme_id = ? AND pm.status = "active" ORDER BY u.name');
      $stmt->execute([$pid]);
      $programme['members'] = $stmt->fetchAll();
    } else {
      $programme['members'] = [];
      unset($programme['outputs']);
    }

    // Finance and programme budget are member-only
    if ($requester) {
      $stmt = db()->prepare('SELECT type, SUM(amount) AS total FROM financial_records WHERE programme_id = ? GROUP BY type');
      $stmt->execute([$pid]);
      $fin = [];
      foreach ($stmt->fetchAll() as $r) $fin[$r['type']] = (float) $r['total'];
      $programme['finance'] = [
        'income' => $fin['income'] ?? 0,
        'expense' => $fin['expense'] ?? 0,
        'balance' => ($fin['income'] ?? 0) - ($fin['expense'] ?? 0),
      ];
    } else {
      $programme['finance'] = null;
      unset($programme['budget'], $programme['spent']);
    }

    json_response($programme);
  }
  elseif ($path === '/public/documents' && $method === 'GET') {
    $stmt = db()->prepare('SELECT d.id, d.title, d.category, d.file_path, d.visibility, d.updated_at AS published_at, u.name AS owner_name FROM documents d JOIN users u ON u.id = d.owner_id WHERE d.status = "published" AND d.visibility = "public" ORDER BY d.updated_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  // --- PUBLIC FINANCE SUMMARY --- REMOVED: finance is members-only
  // Use GET /finance (requires finance.view capability) instead
  elseif ($path === '/public/finance/summary' && $method === 'GET') {
    json_error('Finance data is restricted to members', 403);
  }
  // --- MEMBER DOCUMENTS (internal + public for logged-in users) ---
  elseif ($path === '/member/documents' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT d.id, d.title, d.category, d.file_path, d.visibility, d.status, d.updated_at AS published_at, u.name AS owner_name FROM documents d JOIN users u ON u.id = d.owner_id WHERE d.status IN ("published", "approved") ORDER BY d.updated_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  // --- MEMBER ASSIGNMENTS ---
  elseif ($path === '/member/assignments' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT a.*, u.name AS assignee_name, u2.name AS assigner_name FROM assignments a LEFT JOIN users u ON u.id = a.assignee_id LEFT JOIN users u2 ON u2.id = a.assigner_id WHERE a.assignee_id = ? ORDER BY a.due_date ASC, a.created_at DESC');
    $stmt->execute([current_user_id()]);
    json_response($stmt->fetchAll());
  }
  // --- PROGRAMME MEMBERS & OUTPUTS ---
  elseif (preg_match('#^/programmes/(\d+)$#', $path, $m) && $method === 'PUT') {
    require_cap('programmes.manage');
    $data = input_json();
    $id = (int) $m[1];
    $sets = [];
    $args = [];
    foreach (['title', 'description', 'objectives', 'outputs', 'status', 'lead_id'] as $f) {
      if (array_key_exists($f, $data)) { $sets[] = "$f = ?"; $args[] = $data[$f]; }
    }
    if (array_key_exists('budget', $data)) { $sets[] = 'budget = ?'; $args[] = $data['budget'] !== null ? (float) $data['budget'] : null; }
    if (array_key_exists('spent', $data)) { $sets[] = 'spent = ?'; $args[] = $data['spent'] !== null ? (float) $data['spent'] : null; }
    if (!$sets) json_error('Nothing to update', 400);
    $args[] = $id;
    db()->prepare('UPDATE programmes SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    audit_log('programme_update', 'programme', $id);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/programmes/(\d+)/members$#', $path, $m) && $method === 'GET') {
    require_login();
    $id = (int) $m[1];
    $stmt = db()->prepare('SELECT pm.*, u.name AS user_name, u.email AS user_email FROM programme_members pm JOIN users u ON u.id = pm.user_id WHERE pm.programme_id = ? ORDER BY u.name');
    $stmt->execute([$id]);
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/programmes/(\d+)/members$#', $path, $m) && $method === 'POST') {
    require_cap('programmes.manage');
    $data = input_json();
    $id = (int) $m[1];
    if (empty($data['user_id'])) json_error('user_id is required', 400);
    db()->prepare('INSERT INTO programme_members (programme_id, user_id, role_in_programme, status, joined_date) VALUES (?, ?, ?, "active", COALESCE(?, CURDATE()))
      ON DUPLICATE KEY UPDATE role_in_programme = VALUES(role_in_programme), status = "active"')
      ->execute([$id, (int) $data['user_id'], $data['role_in_programme'] ?? null, $data['joined_date'] ?? null]);
    audit_log('programme_member_add', 'programme', $id, ['user_id' => (int) $data['user_id']]);
    notify_user((int) $data['user_id'], 'programme_member_add', 'Programme membership', 'You were added to a programme team.', '/dashboard');
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/programmes/(\d+)/members/(\d+)$#', $path, $m) && $method === 'DELETE') {
    require_cap('programmes.manage');
    db()->prepare('DELETE FROM programme_members WHERE programme_id = ? AND user_id = ?')->execute([(int) $m[1], (int) $m[2]]);
    audit_log('programme_member_remove', 'programme', (int) $m[1], ['user_id' => (int) $m[2]]);
    json_response(['ok' => true]);
  }
  // --- MEMBER CALENDAR ---
  elseif ($path === '/member/calendar' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT c.*, u.name AS owner_name FROM calendar_items c LEFT JOIN users u ON u.id = c.owner_id ORDER BY c.deadline ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }

  // --- 404 ---
  else {
    json_error('Not found', 404);
  }

} catch (PDOException $e) {
  if (ENV === 'development') {
    json_error('Database error: ' . $e->getMessage(), 500);
  } else {
    json_error('Internal server error', 500);
  }
} catch (Exception $e) {
  json_error($e->getMessage(), 500);
}
