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

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim(str_replace('/uas/api', '', $path), '/');

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
    if (strlen($new) < 8) json_error('New password must be at least 8 characters', 400);
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    audit_log('password_change', 'user', $user['id']);
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
    $newStatus = $data['status'] === 'active' ? 'active' : 'pending';
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
        $baseline = [1, 7, 40, 27, 34]; // articles.submit, events.create, events.rsvp, documents.upload, reports.create
        $memberRoleId = create_role('Member', 'Baseline membership role', $baseline, $user['id']);
      }
      $stmt = db()->prepare('SELECT id FROM role_assignments WHERE role_id = ? AND user_id = ? AND status = "active"');
      $stmt->execute([$memberRoleId, $data['user_id']]);
      if (!$stmt->fetch()) {
        assign_role($memberRoleId, $data['user_id'], $user['id']);
      }
    }

    audit_log('member_approve', 'member', $data['user_id'], [
      'approved_by' => $user['id'],
      'status' => $newStatus
    ]);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/members/(\d+)$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT m.*, u.name, u.email, u.avatar_url, u.bio, u.institution, u.location, u.website FROM members m JOIN users u ON u.id = m.user_id WHERE m.user_id = ?');
    $stmt->execute([$m[1]]);
    $member = $stmt->fetch();
    if (!$member) json_error('Member not found', 404);
    json_response($member);
  }

  // --- ROLES ---
  elseif ($path === '/roles' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT * FROM roles WHERE status = "active" ORDER BY title');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/roles' && $method === 'POST') {
    $user = require_cap('roles.create');
    $data = input_json();
    $roleId = create_role($data['title'], $data['description'] ?? '', $data['capability_ids'] ?? [], $user['id']);
    json_response(['id' => $roleId], 201);
  }
  elseif (preg_match('#^/roles/(\d+)/capabilities$#', $path, $m) && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT c.* FROM role_capabilities rc JOIN capabilities c ON c.id = rc.capability_id WHERE rc.role_id = ?');
    $stmt->execute([$m[1]]);
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
    $user = require_login();
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
    begin_voting((int) $m[1]);
    json_response(['ok' => true]);
  }
  elseif ($path === '/governance/close-expired' && $method === 'POST') {
    require_cap('resolutions.manage');
    close_expired_voting();
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
    $user = require_cap('projects.create');
    $data = input_json();
    db()->prepare('INSERT INTO projects (programme_id, title, description, lead_id, objectives, deadline, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['programme_id'] ?? null, $data['title'], $data['description'] ?? null, $data['lead_id'] ?? null, $data['objectives'] ?? null, $data['deadline'] ?? null, $user['id']]);
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
    $user = require_cap('events.create');
    $data = input_json();
    db()->prepare('INSERT INTO events (programme_id, project_id, title, description, organizer_id, date, end_date, location, capacity, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['programme_id'] ?? null, $data['project_id'] ?? null, $data['title'], $data['description'] ?? null, $user['id'], $data['date'], $data['end_date'] ?? null, $data['location'] ?? null, $data['capacity'] ?? null, $user['id']]);
    $id = (int) db()->lastInsertId();
    transition('event', $id, 'submitted', $user['id']);
    audit_log('event_create', 'event', $id);
    json_response(['id' => $id], 201);
  }
  elseif (preg_match('#^/events/(\d+)/approve$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.approve');
    transition('event', (int) $m[1], 'approved', $user['id']);
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/events/(\d+)/publish$#', $path, $m) && $method === 'POST') {
    $user = require_cap('events.publish');
    transition('event', (int) $m[1], 'published', $user['id']);
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
    db()->prepare("UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'registered'")
      ->execute([(int) $m[1], $user['id']]);
    audit_log('event_rsvp_cancel', 'event', (int) $m[1], ['user_id' => $user['id']]);
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
    db()->prepare('INSERT INTO articles (author_id, title, body, category, tags, image_url, approver_role_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
      ->execute([$user['id'], $data['title'], $data['body'] ?? null, $data['category'] ?? 'article', json_encode($data['tags'] ?? []), $data['image_url'] ?? null, $data['approver_role_id'] ?? null]);
    $id = (int) db()->lastInsertId();
    transition('article', $id, 'submitted', $user['id']);
    audit_log('article_create', 'article', $id);
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
    json_response(['ok' => true]);
  }
  elseif (preg_match('#^/articles/(\d+)/publish$#', $path, $m) && $method === 'POST') {
    $user = require_cap('articles.publish');
    transition('article', (int) $m[1], 'published', $user['id']);
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

  // --- FINANCE ---
  elseif ($path === '/finance' && $method === 'GET') {
    require_cap('finance.view');
    $stmt = db()->prepare('SELECT f.*, u.name AS recorded_by_name, a.name AS approved_by_name FROM financial_records f JOIN users u ON u.id = f.recorded_by LEFT JOIN users a ON a.id = f.approved_by ORDER BY f.record_date DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/finance' && $method === 'POST') {
    $user = require_cap('finance.record');
    $data = input_json();
    db()->prepare('INSERT INTO financial_records (type, amount, category, programme_id, project_id, description, recorded_by, record_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['type'], $data['amount'], $data['category'] ?? 'other', $data['programme_id'] ?? null, $data['project_id'] ?? null, $data['description'] ?? null, $user['id'], $data['record_date']]);
    $id = (int) db()->lastInsertId();
    audit_log('finance_record', 'financial_record', $id);
    json_response(['id' => $id], 201);
  }

  // --- ASSIGNMENTS ---
  elseif ($path === '/assignments' && $method === 'GET') {
    require_login();
    $stmt = db()->prepare('SELECT a.*, u.name AS assignee_name, cr.name AS assigner_name, r.title AS role_title FROM assignments a LEFT JOIN users u ON u.id = a.assignee_id LEFT JOIN users cr ON cr.id = a.assigner_id LEFT JOIN roles r ON r.id = a.role_id ORDER BY a.due_date ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif ($path === '/assignments' && $method === 'POST') {
    $user = require_login();
    $data = input_json();
    db()->prepare('INSERT INTO assignments (title, description, assignee_id, assigner_id, role_id, due_date, priority, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$data['title'], $data['description'] ?? null, $data['assignee_id'] ?? null, $user['id'], $data['role_id'] ?? null, $data['due_date'] ?? null, $data['priority'] ?? 'medium', $data['related_type'] ?? null, $data['related_id'] ?? null]);
    $id = (int) db()->lastInsertId();
    audit_log('assignment_create', 'assignment', $id);
    json_response(['id' => $id], 201);
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
    $user = require_login();
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
    json_response(get_pending_items());
  }

  // --- DASHBOARD / HEALTH ---
  elseif ($path === '/dashboard' && $method === 'GET') {
    require_login();
    json_response(institutional_health());
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
  elseif ($path === '/public/articles' && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.category, a.tags, a.image_url, a.published_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.status = "published" ORDER BY a.published_at DESC');
    $stmt->execute();
    json_response($stmt->fetchAll());
  }
  elseif (preg_match('#^/public/articles/(\d+)$#', $path, $m) && $method === 'GET') {
    $stmt = db()->prepare('SELECT a.id, a.title, a.body, a.category, a.tags, a.image_url, a.published_at, a.approved_by, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id WHERE a.id = ? AND a.status = "published"');
    $stmt->execute([(int) $m[1]]);
    $article = $stmt->fetch();
    if (!$article) json_error('Article not found', 404);
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
    $stmt = db()->prepare('SELECT p.*, u.name AS lead_name, u.email AS lead_email FROM programmes p LEFT JOIN users u ON u.id = p.lead_id WHERE p.id = ?');
    $stmt->execute([(int) $m[1]]);
    $programme = $stmt->fetch();
    if (!$programme) json_error('Programme not found', 404);
    $stmt = db()->prepare('SELECT id, title, description, status, deadline, budget, spent FROM projects WHERE programme_id = ? ORDER BY title');
    $stmt->execute([(int) $m[1]]);
    $programme['projects'] = $stmt->fetchAll();
    json_response($programme);
  }
  elseif ($path === '/public/documents' && $method === 'GET') {
    $stmt = db()->prepare('SELECT d.id, d.title, d.category, d.file_path, d.visibility, d.updated_at AS published_at, u.name AS owner_name FROM documents d JOIN users u ON u.id = d.owner_id WHERE d.status = "published" AND d.visibility = "public" ORDER BY d.updated_at DESC');
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
