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
