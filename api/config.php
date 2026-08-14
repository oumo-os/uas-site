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
  session_start();
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

// Event waitlist: promote the earliest waitlisted member when capacity frees up.
// Reuses a cancelled registration row if one exists; otherwise inserts a new one.
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
  return $next;
}
