<?php
// Root slug router: astronomy.ug/<slug> -> programme or project detail
// Preserves clean URL in address bar (200, no redirect) by including the target template.
require_once __DIR__ . '/api/config.php';
$slug = $_GET['slug'] ?? '';
if (!$slug) {
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
  $slug = trim($path, '/');
  $slug = explode('/', $slug)[0];
}
$slug = strtolower(trim($slug));
if (!preg_match('/^[a-z0-9-]{2,120}$/', $slug)) {
  http_response_code(404);
  include __DIR__ . '/404.html';
  exit;
}
// Try programme first
try {
  $stmt = db()->prepare('SELECT id FROM programmes WHERE slug = ? LIMIT 1');
  $stmt->execute([$slug]);
  if ($id = $stmt->fetchColumn()) {
    $_GET['slug'] = $slug;
    $_GET['id'] = $id;
    include __DIR__ . '/programme.html';
    exit;
  }
  $stmt = db()->prepare('SELECT id FROM projects WHERE slug = ? LIMIT 1');
  $stmt->execute([$slug]);
  if ($id = $stmt->fetchColumn()) {
    $_GET['slug'] = $slug;
    $_GET['id'] = $id;
    include __DIR__ . '/project.html';
    exit;
  }
} catch (Exception $e) {
  // fall through to 404
}
http_response_code(404);
include __DIR__ . '/404.html';
