<?php
// UAS Institutional Platform — Rich Sample Content
// Run: php api/seed-content.php
// Idempotent: only inserts content that does not already exist.
// Adds demo members, articles, events, programmes, projects, documents,
// finance records, assignments, calendar items, partners, links.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/governance.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/audit.php';

echo "=== UAS Sample Content Seeder ===\n";
$count = ['members' => 0, 'articles' => 0, 'events' => 0, 'programmes' => 0, 'projects' => 0, 'documents' => 0, 'finance' => 0, 'assignments' => 0, 'calendar' => 0, 'partners' => 0, 'links' => 0, 'resolutions' => 0];

function exists($table, $column, $value) {
  $stmt = db()->prepare("SELECT id FROM {$table} WHERE {$column} = ?");
  $stmt->execute([$value]);
  return $stmt->fetch();
}

function userId($email) {
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  return $stmt->fetchColumn() ?: null;
}

// ---------- 1. Extra members ----------
$extraMembers = [
  ['name' => 'Peter Okello', 'email' => 'peter@astronomy.ug', 'password' => 'starlight1', 'category' => 'student', 'institution' => 'Gulu University', 'location' => 'Gulu', 'interests' => 'variable stars, double stars', 'joined' => '2026-02-10'],
  ['name' => 'Grace Achieng', 'email' => 'grace@astronomy.ug', 'password' => 'starlight1', 'category' => 'regular', 'institution' => 'Makerere University', 'location' => 'Kampala', 'interests' => 'astrophotography, outreach', 'joined' => '2026-03-22'],
  ['name' => 'Dr. Ronald Kiiza', 'email' => 'ronald@astronomy.ug', 'password' => 'starlight1', 'category' => 'regular', 'institution' => 'Uganda National Meteorological Authority', 'location' => 'Entebbe', 'interests' => 'meteorology, climate, observing', 'joined' => '2025-11-05'],
];

$memberRoleId = null;
foreach ($extraMembers as $m) {
  if (exists('users', 'email', $m['email'])) { echo "member exists: {$m['email']}\n"; continue; }
  $hash = password_hash($m['password'], PASSWORD_DEFAULT);
  db()->prepare("INSERT INTO users (name, email, password, phone, institution, location, bio, status) VALUES (?, ?, ?, NULL, ?, ?, ?, 'active')")
    ->execute([$m['name'], $m['email'], $hash, $m['institution'], $m['location'], $m['interests'] . ' — passionate about ' . $m['interests']]);
  $uid = (int) db()->lastInsertId();
  $year = 'UAS-2026-' . str_pad((string) $uid, 4, '0', STR_PAD_LEFT);
  $n = (int) db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(membership_number, 10) AS UNSIGNED)), 0) + 1 FROM members")->fetchColumn();
  $year = 'UAS-2026-' . str_pad((string) max($n, $uid + 1), 4, '0', STR_PAD_LEFT);
  db()->prepare("INSERT INTO members (user_id, membership_number, category, status, joined_date, interests, profile_visible) VALUES (?, ?, ?, 'active', ?, ?, 1)")
    ->execute([$uid, $year, $m['category'], $m['joined'], $m['interests']]);
  if (!$memberRoleId) {
    $memberRoleId = (int) db()->query("SELECT id FROM roles WHERE title = 'Member'")->fetchColumn();
  }
  if ($memberRoleId) {
    db()->prepare("INSERT INTO role_assignments (role_id, user_id, assigned_by, status, effective_from) VALUES (?, ?, 1, 'active', ?)")
      ->execute([$memberRoleId, $uid, date('Y-m-d')]);
  }
  $count['members']++;
  echo "member: {$m['name']} ({$year})\n";
}

// ---------- 2. Articles ----------
$articles = [
  ['title' => 'Aurora Hunting in the Tropics: What Ugandan Skygazers Can See', 'cat' => 'educational', 'tags' => '["aurora","outreach","tropics"]', 'author' => 'grace@astronomy.ug', 'body' => "<p>Aurorae are famously a high-latitude phenomenon, but tropical skygazers still get remarkable celestial spectacles — from zodiacal light after dusk to brilliant meteors.</p><h2>Zodiacal light</h2><p>In the weeks around the equinoxes, look west after sunset or east before dawn for a soft cone of light rising from the horizon. It is sunlight scattered off interplanetary dust.</p><h2>Noctilucent clouds</h2><p>Occasionally visible near the poles of our sky at twilight, these glowing clouds form at ~80 km altitude.</p><p>Download our monthly sky map from the Library to plan your next session.</p>", 'status' => 'published', 'pub' => '2026-08-02 10:00:00', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'Announcement: Monthly Public Observing Nights at Kololo', 'cat' => 'announcement', 'tags' => '["events","observing"]', 'author' => 'malcom@astronomy.ug', 'body' => "<p>The Society is delighted to announce that public observing nights now run every first Friday of the month at Kololo Airstrip, Kampala, weather permitting.</p><p>Each session features a short talk, telescope guidance for beginners, and a featured target of the month. Entry is free for members and 5,000 UGX for guests.</p><p>Follow the Events page and RSVP to reserve a telescope slot.</p>", 'status' => 'published', 'pub' => '2026-07-18 09:00:00', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'First Light at Makerere Observatory', 'cat' => 'observing_report', 'tags' => '["telescope","makerere"]', 'author' => 'alice@astronomy.ug', 'body' => "<p>On a clear night in August, we gathered at Makerere University to commission the first telescope of our outreach programme.</p><h2>What we did</h2><p>Students aligned the instrument, calibrated the finder scope, and took turns observing Jupiter and its four Galilean moons. The session ran from 7pm to 10pm with over 40 participants.</p><h2>Key takeaways</h2><p>The event confirmed strong public appetite for observing sessions. We will run monthly public nights and partner with university physics departments for student volunteers.</p>", 'status' => 'published', 'pub' => '2026-08-14 09:40:41', 'by' => 'john@astronomy.ug'],
  ['title' => 'Uganda in the 2027 Total Solar Eclipse Path', 'cat' => 'educational', 'tags' => '["eclipse","2027"]', 'author' => 'ronald@astronomy.ug', 'body' => "<p>The total solar eclipse of 2 August 2027 will cross northern Africa and the Middle East. Uganda lies just outside the path of totality, but a substantial partial eclipse will be visible across the entire country.</p><h2>What to expect</h2><p>In Kampala, the Moon will cover roughly 45% of the Sun at maximum. Never look at the partial phases without certified eclipse glasses.</p><h2>The Society's plan</h2><p>UAS will run coordinated public viewing sessions and school outreach kits in the weeks before the event.</p>", 'status' => 'published', 'pub' => '2026-08-10 14:00:00', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'Report: School Telescope Programme Pilot', 'cat' => 'project_report', 'tags' => '["schools","telescopes","impact"]', 'author' => 'john@astronomy.ug', 'body' => "<p>The pilot phase of the School Telescope Programme delivered instruments and teacher training to three schools in Kampala and Wakiso districts.</p><h2>Outcomes</h2><ul><li>3 schools equipped with 6-inch Dobsonian telescopes</li><li>14 teachers trained in basic astronomy and instrument care</li><li>Established school astronomy clubs with 120+ learners</li></ul><p>Full rollout to 10 schools is scheduled by December 2026.</p>", 'status' => 'published', 'pub' => '2026-07-25 11:30:00', 'by' => 'cosmus@astronomy.ug'],
];

foreach ($articles as $a) {
  if (exists('articles', 'title', $a['title'])) { echo "article exists: {$a['title']}\n"; continue; }
  $aid = userId($a['author']);
  $app = userId($a['by']);
  db()->prepare("INSERT INTO articles (author_id, title, body, category, tags, status, approver_role_id, approved_by, approved_at, published_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)")
    ->execute([$aid, $a['title'], $a['body'], $a['cat'], $a['tags'], $a['status'], $app, $a['pub'], $a['pub']]);
  $count['articles']++;
  echo "article: {$a['title']}\n";
}

// ---------- 3. Events ----------
$events = [
  ['title' => 'National Stargazing Night 2026', 'desc' => 'Public observing session at Kololo Airstrip featuring the full moon and Saturn. Telescopes, talks, and star maps for all ages.', 'loc' => 'Kololo Airstrip, Kampala', 'date' => '2026-10-02 19:00:00', 'end' => '2026-10-02 22:00:00', 'cap' => 300, 'status' => 'published', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'September Public Observing Night', 'desc' => 'Monthly public observing night. Target of the month: Saturn and its rings.', 'loc' => 'Kololo Airstrip, Kampala', 'date' => '2026-09-04 19:30:00', 'end' => '2026-09-04 21:30:00', 'cap' => 150, 'status' => 'published', 'by' => 'malcom@astronomy.ug'],
  ['title' => 'Astronomy Education Workshop: Teachers Bootcamp', 'desc' => 'Hands-on training for secondary school science teachers covering solar system models, spectroscopes, and classroom activities.', 'loc' => 'Makerere University, Kampala', 'date' => '2026-09-19 09:00:00', 'end' => '2026-09-19 16:00:00', 'cap' => 60, 'status' => 'published', 'by' => 'sarah@astronomy.ug'],
  ['title' => 'Uganda Astronomy Week Closing Ceremony', 'desc' => 'Closing ceremony and awards for the national astronomy week.', 'loc' => 'National Theatre, Kampala', 'date' => '2026-08-09 15:00:00', 'end' => '2026-08-09 18:00:00', 'cap' => 200, 'status' => 'completed', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'Meteor Shower Watch: Perseids 2026', 'desc' => 'Cancelled due to forecast cloud cover. Join us for the next public night instead.', 'loc' => 'Mbale', 'date' => '2026-08-13 22:00:00', 'cap' => 80, 'status' => 'cancelled', 'by' => 'cosmus@astronomy.ug'],
];

foreach ($events as $e) {
  if (exists('events', 'title', $e['title'])) { echo "event exists: {$e['title']}\n"; continue; }
  $eid = userId($e['by']);
  $status = $e['status'];
  $approved = null;
  $published = null;
  if ($status === 'published') { $approved = $eid; $published = $eid; }
  if ($status === 'completed') { $approved = $eid; $published = $eid; }
  db()->prepare("INSERT INTO events (title, description, organizer_id, date, end_date, location, capacity, status, approval_required, approved_by, approved_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?)")
    ->execute([$e['title'], $e['desc'], $eid, $e['date'], $e['end'] ?? null, $e['loc'], $e['cap'], $status, $approved, $eid]);
  $count['events']++;
  echo "event: {$e['title']} ({$status})\n";
}

// ---------- 4. Programmes + projects ----------
$programmes = [
  ['title' => 'Research & Citizen Science', 'desc' => 'Supporting variable star observing, meteor counting, and collaboration with regional observatories.', 'lead' => 'cosmus@astronomy.ug', 'objectives' => "Coordinate citizen science campaigns\nPublish observing reports\nLink with regional astronomy research"],
  ['title' => 'Women in Astronomy Initiative', 'desc' => 'Mentorship, scholarships, and visibility for women pursuing physics and astronomy in Uganda.', 'lead' => 'sarah@astronomy.ug', 'objectives' => "Annual mentorship cohort\nScholarship support for STEM students\nPublic role-model profiles"],
];

$eduProgId = (int) db()->query("SELECT id FROM programmes WHERE title = 'Astronomy Education Programme'")->fetchColumn();

foreach ($programmes as $p) {
  if (exists('programmes', 'title', $p['title'])) { echo "programme exists: {$p['title']}\n"; continue; }
  $lead = userId($p['lead']);
  db()->prepare("INSERT INTO programmes (title, description, lead_id, status, objectives, created_by) VALUES (?, ?, ?, 'active', ?, 1)")
    ->execute([$p['title'], $p['desc'], $lead, $p['objectives']]);
  $count['programmes']++;
  echo "programme: {$p['title']}\n";
}

$projects = [
  ['title' => 'School Telescope Programme', 'desc' => 'Distribute and train teachers on donated telescopes in 10 schools across Uganda', 'prog' => 'Astronomy Education Programme', 'lead' => 'john@astronomy.ug', 'obj' => 'Equip 10 schools with telescopes and train 20 teachers', 'deadline' => '2026-12-15', 'budget' => 5000000],
  ['title' => 'Astronomy Club Network', 'desc' => 'Establish and support astronomy clubs at universities and secondary schools', 'prog' => 'Astronomy Education Programme', 'lead' => 'john@astronomy.ug', 'obj' => 'Support 15 astronomy clubs with activity kits and mentoring', 'deadline' => '2027-03-30', 'budget' => 3000000],
  ['title' => 'Variable Star Campaign', 'desc' => 'Monthly photometry runs on bright southern variables, reporting to AAVSO', 'prog' => 'Research & Citizen Science', 'lead' => 'cosmus@astronomy.ug', 'obj' => '200 reported observations in 2026', 'deadline' => '2026-12-31', 'budget' => 1200000],
  ['title' => 'Women in STEM Scholarship Fund', 'desc' => 'Raise and disburse scholarships for female physics students', 'prog' => 'Women in Astronomy Initiative', 'lead' => 'sarah@astronomy.ug', 'obj' => '5 scholarships awarded by 2027', 'deadline' => '2027-06-30', 'budget' => 15000000],
];

foreach ($projects as $pr) {
  if (exists('projects', 'title', $pr['title'])) { echo "project exists: {$pr['title']}\n"; continue; }
  $progId = (int) db()->query("SELECT id FROM programmes WHERE title = '{$pr['prog']}'")->fetchColumn();
  $lead = userId($pr['lead']);
  db()->prepare("INSERT INTO projects (programme_id, title, description, lead_id, status, objectives, deadline, budget, created_by) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, 1)")
    ->execute([$progId ?: null, $pr['title'], $pr['desc'], $lead, $pr['obj'], $pr['deadline'], $pr['budget']]);
  $count['projects']++;
  echo "project: {$pr['title']}\n";
}

// ---------- 5. Documents ----------
$documents = [
  ['title' => 'UAS Constitution (2026 Revision)', 'path' => '/docs/constitution-2026.pdf', 'cat' => 'constitution', 'vis' => 'public', 'status' => 'published', 'by' => 'cosmus@astronomy.ug'],
  ['title' => 'Astronomy Education Toolkit', 'path' => '/docs/education-toolkit.pdf', 'cat' => 'guide', 'vis' => 'public', 'status' => 'published', 'by' => 'sarah@astronomy.ug'],
  ['title' => 'Annual Financial Report FY2025/26', 'path' => '/docs/financial-2025-26.pdf', 'cat' => 'financial', 'vis' => 'internal', 'status' => 'published', 'by' => 'sarah@astronomy.ug'],
  ['title' => 'Board Meeting Minutes — July 2026', 'path' => '/docs/minutes-2026-07.pdf', 'cat' => 'minutes', 'vis' => 'internal', 'status' => 'published', 'by' => 'john@astronomy.ug'],
  ['title' => '2026 Annual Report Draft', 'path' => '/docs/annual-2026.pdf', 'cat' => 'report', 'vis' => 'public', 'status' => 'published', 'by' => 'alice@astronomy.ug'],
];

foreach ($documents as $d) {
  if (exists('documents', 'title', $d['title'])) { echo "document exists: {$d['title']}\n"; continue; }
  $owner = userId($d['by']);
  db()->prepare("INSERT INTO documents (title, file_path, category, visibility, owner_id, status, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([$d['title'], $d['path'], $d['cat'], $d['vis'], $owner, $d['status'], userId('cosmus@astronomy.ug')]);
  $count['documents']++;
  echo "document: {$d['title']}\n";
}

// ---------- 6. Finance ----------
$finance = [
  ['type' => 'income', 'amount' => 500000, 'cat' => 'membership', 'desc' => 'Membership fees — Q2 2026', 'by' => 'sarah@astronomy.ug'],
  ['type' => 'income', 'amount' => 2000000, 'cat' => 'other', 'desc' => 'IAU OAD micro-grant for education programme', 'by' => 'sarah@astronomy.ug'],
  ['type' => 'expense', 'amount' => 250000, 'cat' => 'equipment', 'desc' => 'Telescope maintenance kits', 'by' => 'sarah@astronomy.ug'],
  ['type' => 'expense', 'amount' => 180000, 'cat' => 'other', 'desc' => 'July public night — generator fuel and permits', 'by' => 'sarah@astronomy.ug'],
  ['type' => 'income', 'amount' => 320000, 'cat' => 'event', 'desc' => 'Public night guest entry fees — July', 'by' => 'sarah@astronomy.ug'],
];

foreach ($finance as $f) {
  $stmt = db()->prepare('SELECT id FROM financial_records WHERE description = ?');
  $stmt->execute([$f['desc']]);
  if ($stmt->fetch()) { echo "finance exists: {$f['desc']}\n"; continue; }
  $rec = userId($f['by']);
  db()->prepare("INSERT INTO financial_records (type, amount, category, description, recorded_by, record_date) VALUES (?, ?, ?, ?, ?, CURDATE())")
    ->execute([$f['type'], $f['amount'], $f['cat'], $f['desc'], $rec]);
  $count['finance']++;
  echo "finance: {$f['desc']}\n";
}

// ---------- 7. Assignments ----------
$assignments = [
  ['title' => 'Compile star party safety checklist', 'desc' => 'Consolidate safety rules for public observing events', 'assignee' => 'grace@astronomy.ug', 'due' => '2026-08-22', 'prio' => 'medium'],
  ['title' => 'Draft school outreach lesson plan', 'desc' => 'One-hour lesson on phases of the Moon for P6/P7', 'assignee' => 'peter@astronomy.ug', 'due' => '2026-08-30', 'prio' => 'high'],
  ['title' => 'Review eclipse viewing safety materials', 'desc' => 'Check IAU 2027 eclipse safety factsheet translation', 'assignee' => 'ronald@astronomy.ug', 'due' => '2026-08-01', 'prio' => 'urgent'],
];

foreach ($assignments as $a) {
  $stmt = db()->prepare('SELECT id FROM assignments WHERE title = ?');
  $stmt->execute([$a['title']]);
  if ($stmt->fetch()) { echo "assignment exists: {$a['title']}\n"; continue; }
  $assignee = userId($a['assignee']);
  $status = $a['due'] < date('Y-m-d') ? 'overdue' : 'not_started';
  db()->prepare("INSERT INTO assignments (title, description, assignee_id, assigner_id, due_date, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([$a['title'], $a['desc'], $assignee, userId('john@astronomy.ug'), $a['due'], $a['prio'], $status]);
  $count['assignments']++;
  echo "assignment: {$a['title']}\n";
}

// ---------- 8. Calendar ----------
$calendar = [
  ['title' => 'Monthly Board Meeting', 'type' => 'meeting', 'when' => '2026-09-07 17:00:00'],
  ['title' => 'Membership renewal deadline', 'type' => 'renewal', 'when' => '2026-09-30 23:59:00'],
  ['title' => 'Astronomy Week planning milestone', 'type' => 'milestone', 'when' => '2026-10-15 12:00:00'],
];

foreach ($calendar as $c) {
  $stmt = db()->prepare('SELECT id FROM calendar_items WHERE title = ?');
  $stmt->execute([$c['title']]);
  if ($stmt->fetch()) { echo "calendar exists: {$c['title']}\n"; continue; }
  db()->prepare("INSERT INTO calendar_items (title, type, owner_id, deadline) VALUES (?, ?, 1, ?)")
    ->execute([$c['title'], $c['type'], $c['when']]);
  $count['calendar']++;
  echo "calendar: {$c['title']}\n";
}

// ---------- 9. Partners & links ----------
$partners = [
  ['org' => 'Ministry of Science, Technology and Innovation', 'desc' => 'Government ministry overseeing science and technology policy in Uganda', 'rel' => 'institutional', 'web' => 'https://mosti.go.ug'],
  ['org' => 'UNESCO', 'desc' => 'Education, science and culture agency — supports astronomy education programmes', 'rel' => 'institutional', 'web' => 'https://unesco.org'],
  ['org' => 'International Astronomical Union (OAD)', 'desc' => 'Global astronomy advocacy and development network', 'rel' => 'sponsor', 'web' => 'https://astro4dev.org'],
  ['org' => 'Makerere University Physics Department', 'desc' => 'Academic partner for outreach and training', 'rel' => 'partner', 'web' => 'https://cns.mak.ac.ug'],
  ['org' => 'Galileo Telescopes Ltd', 'desc' => 'Equipment supplier for school telescope rollout', 'rel' => 'partner', 'web' => null],
];

foreach ($partners as $p) {
  $stmt = db()->prepare('SELECT id FROM partners WHERE organization = ?');
  $stmt->execute([$p['org']]);
  if ($stmt->fetch()) { echo "partner exists: {$p['org']}\n"; continue; }
  db()->prepare("INSERT INTO partners (organization, website, description, relationship_type, status) VALUES (?, ?, ?, ?, 'active')")
    ->execute([$p['org'], $p['web'], $p['desc'], $p['rel']]);
  $count['partners']++;
  echo "partner: {$p['org']}\n";
}

$links = [
  ['title' => 'International Astronomical Union', 'url' => 'https://www.iau.org', 'cat' => 'Organizations', 'desc' => 'The global astronomy body'],
  ['title' => 'American Association of Variable Star Observers', 'url' => 'https://www.aavso.org', 'cat' => 'Research', 'desc' => 'Citizen science variable star reporting'],
  ['title' => 'Clear Sky Chart', 'url' => 'https://www.cleardarksky.com', 'cat' => 'Tools', 'desc' => 'Weather forecasting for astronomers'],
  ['title' => 'Stellarium', 'url' => 'https://stellarium.org', 'cat' => 'Tools', 'desc' => 'Free planetarium software'],
  ['title' => 'NASA Image of the Day', 'url' => 'https://apod.nasa.gov', 'cat' => 'Education', 'desc' => 'Astronomy picture of the day'],
  ['title' => 'African Astronomical Society', 'url' => 'https://www.africanastronomicalsociety.org', 'cat' => 'Organizations', 'desc' => 'Continental astronomy community'],
];

foreach ($links as $l) {
  $stmt = db()->prepare('SELECT id FROM useful_links WHERE title = ?');
  $stmt->execute([$l['title']]);
  if ($stmt->fetch()) { echo "link exists: {$l['title']}\n"; continue; }
  db()->prepare("INSERT INTO useful_links (title, url, category, description, status) VALUES (?, ?, ?, ?, 'active')")
    ->execute([$l['title'], $l['url'], $l['cat'], $l['desc']]);
  $count['links']++;
  echo "link: {$l['title']}\n";
}

// ---------- 10. A sample resolution: appoint Grace as Communications Officer ----------
$resTitle = 'Create Communications Officer role and appoint Grace Achieng';
$stmt = db()->prepare('SELECT id FROM resolutions WHERE title = ?');
$stmt->execute([$resTitle]);
if (!$stmt->fetch()) {
  $chair = userId('cosmus@astronomy.ug');
  $rid = create_resolution([
    'title' => $resTitle,
    'description' => 'Creates the Communications Officer portfolio and appoints Grace Achieng to manage public communications.',
    'type' => 'role_create',
    'quorum' => 3,
    'voting_deadline' => date('Y-m-d H:i:s', strtotime('+14 days')),
    'changes' => [
      ['change_type' => 'role_create', 'payload' => ['title' => 'Communications Officer', 'description' => 'Manages public communications, social media and newsletters', 'capabilities' => ['articles.submit', 'articles.review', 'events.create', 'events.rsvp']]],
      ['change_type' => 'appoint', 'target_type' => 'role', 'payload' => ['user_id' => userId('grace@astronomy.ug'), 'role_title' => 'Communications Officer']],
    ],
  ], $chair);
  db()->prepare("UPDATE resolutions SET status = 'voting' WHERE id = ?")->execute([$rid]);
  // Vote to quorum (3) — the 3rd qualifying vote auto-applies; no need for further votes
  foreach (['cosmus@astronomy.ug', 'malcom@astronomy.ug', 'john@astronomy.ug'] as $voter) {
    cast_vote($rid, userId($voter), 'for', 'Board decision');
  }
  $count['resolutions']++;
  echo "resolution: {$resTitle}\n";
} else {
  echo "resolution exists: {$resTitle}\n";
}

echo "\n=== Done. Inserted: " . json_encode($count) . " ===\n";
