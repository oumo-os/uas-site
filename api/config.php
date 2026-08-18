<?php
// UAS Institutional Platform — Configuration
// environment: production, development
define('ENV', getenv('UAS_ENV') ?: 'development');
define('DB_HOST', getenv('UAS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('UAS_DB_NAME') ?: 'uas_platform');
define('DB_USER', getenv('UAS_DB_USER') ?: 'root');
define('DB_PASS', getenv('UAS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Uganda Astronomical Society');
define('SITE_URL', ENV === 'production' ? 'https://astronomy.ug' : 'http://localhost/uas');
define('API_URL', SITE_URL . '/api');
define('UPLOAD_DIR', __DIR__ . '/../img/uploads/');

// Session
if (session_status() === PHP_SESSION_NONE) {
  ini_set('session.cookie_httponly', 1);
  ini_set('session.use_strict_mode', 1);
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => ENV === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// Rate limiter: tracks attempts per key within a sliding window.
// Returns true when the request is allowed; false (or errors out) when throttled.
function rate_limit(string $key, string $type, int $maxAttempts, int $windowSeconds): bool {
  $now = time();
  $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds);
  $stmt = db()->prepare('SELECT attempts, window_start FROM rate_limits WHERE rl_key = ?');
  $stmt->execute([$key]);
  $row = $stmt->fetch();
  if (!$row) {
    db()->prepare('INSERT INTO rate_limits (rl_key, rl_type, attempts, window_start) VALUES (?, ?, 1, ?)')
      ->execute([$key, $type, date('Y-m-d H:i:s', $now)]);
    return true;
  }
  if ($row['window_start'] < $windowStart) {
    db()->prepare('UPDATE rate_limits SET attempts = 1, window_start = ? WHERE rl_key = ?')
      ->execute([date('Y-m-d H:i:s', $now), $key]);
    return true;
  }
  if ((int) $row['attempts'] >= $maxAttempts) return false;
  db()->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE rl_key = ?')->execute([$key]);
  return true;
}

function client_ip(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Password policy: at least 8 characters, one letter, one digit.
function password_is_strong(string $pw): bool {
  return strlen($pw) >= 8 && preg_match('/[A-Za-z]/', $pw) && preg_match('/\d/', $pw);
}

// JSON response helper
function json_response($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function json_error(string $message, int $code = 400): void {
  json_response(['error' => $message], $code);
}

// Input helpers
function input_json(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function input(string $key, $default = null) {
  $data = input_json();
  return $data[$key] ?? $default;
}

function param(string $key, $default = null) {
  global $_GET;
  return $_GET[$key] ?? $default;
}

// Database connection (PDO singleton)
function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}

// In-app notification: insert a row for a single user (or all users matching a capability).
function notify_user(int $userId, string $type, string $title, string $body = '', ?string $link = null): void {
  db()->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)')
    ->execute([$userId, $type, $title, $body, $link]);
}

function notify_capability(string $capability, string $type, string $title, string $body = '', ?string $link = null): int {
  $stmt = db()->prepare('SELECT DISTINCT u.id FROM users u JOIN role_assignments ra ON ra.user_id = u.id AND ra.status = "active" JOIN role_capabilities rc ON rc.role_id = ra.role_id JOIN capabilities c ON c.id = rc.capability_id WHERE c.slug = ? AND u.status = "active"');
  $stmt->execute([$capability]);
  $count = 0;
  foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
    notify_user((int) $uid, $type, $title, $body, $link);
    $count++;
  }
  return $count;
}

// Event waitlist: promote the earliest waitlisted member when capacity frees up.
function promote_from_waitlist(int $eventId): ?array {
  $stmt = db()->prepare('SELECT id, capacity, date FROM events WHERE id = ?');
  $stmt->execute([$eventId]);
  $event = $stmt->fetch();
  if (!$event || !$event['capacity'] || $event['date'] < date('Y-m-d H:i:s')) return null;

  $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
  $stmt->execute([$eventId]);
  if ((int) $stmt->fetchColumn() >= (int) $event['capacity']) return null;

  $stmt = db()->prepare('SELECT * FROM event_waitlist WHERE event_id = ? ORDER BY created_at ASC, id ASC LIMIT 1');
  $stmt->execute([$eventId]);
  $next = $stmt->fetch();
  if (!$next) return null;

  $stmt = db()->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
  $stmt->execute([$eventId, $next['user_id']]);
  $existingReg = $stmt->fetch();
  if ($existingReg) {
    db()->prepare("UPDATE event_registrations SET status = 'registered', registered_at = NOW() WHERE id = ?")->execute([$existingReg['id']]);
  } else {
    db()->prepare('INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, "registered")')->execute([$eventId, $next['user_id']]);
  }
  db()->prepare('DELETE FROM event_waitlist WHERE id = ?')->execute([$next['id']]);
  audit_log('waitlist_promote', 'event', $eventId, ['user_id' => $next['user_id']]);
  $stmt = db()->prepare('SELECT title FROM events WHERE id = ?');
  $stmt->execute([$eventId]);
  $eventTitle = $stmt->fetchColumn();
  notify_user((int) $next['user_id'], 'waitlist_promote', 'Spot secured: ' . $eventTitle, 'A spot opened up and you have been auto-promoted from the waitlist to registered.', '/event/' . $eventId);
  return $next;
}
