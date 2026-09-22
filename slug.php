<?php
// Detail router + social-share meta injector.
// Serves programme / project / event / article detail pages under clean URLs
// (200, no redirect) with item-specific Open Graph tags injected server-side,
// because link scrapers (WhatsApp, Facebook, X, Telegram) don't run JavaScript.
// Routes here via .htaccess: /<slug>, /events/<slug|id>, /article/<id>,
// /programmes/<id>. Only publicly visible items get specific tags; anything
// else falls back to the template's generic tags (same as before).
require_once __DIR__ . '/api/share-meta.php';

function slug_404(): void {
  http_response_code(404);
  include __DIR__ . '/404.html';
  exit;
}

$type = strtolower(trim($_GET['type'] ?? 'auto'));
$slug = strtolower(trim($_GET['slug'] ?? ''));
$id = (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) ? (int) $_GET['id'] : 0;

// Fallback: derive from the request path (direct hits without rewrite params).
if (!$slug && !$id) {
  $segs = array_values(array_filter(explode('/', trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'))));
  if (count($segs) >= 2) {
    $head = strtolower($segs[0]);
    $map = ['events' => 'event', 'event' => 'event', 'articles' => 'article', 'article' => 'article',
             'programmes' => 'programme', 'programme' => 'programme'];
    if (isset($map[$head])) {
      $type = $map[$head];
      $last = end($segs);
      if (ctype_digit($last)) $id = (int) $last; else $slug = strtolower($last);
    }
  } elseif (isset($segs[0])) {
    $slug = strtolower($segs[0]);
  }
}

if (!in_array($type, ['auto', 'programme', 'project', 'event', 'article'], true)) $type = 'auto';
if ($slug !== '' && !preg_match('/^[a-z0-9-]{2,120}$/', $slug)) slug_404();

try {
  $db = db();

  // --- Programme: /<slug> or /programmes/<id> ---
  if (in_array($type, ['auto', 'programme'], true)) {
    $row = null;
    if ($type === 'programme' || $slug !== '') {
      if ($slug !== '') {
        $s = $db->prepare('SELECT id, title, slug, description, image_url, status FROM programmes WHERE slug = ? LIMIT 1');
        $s->execute([$slug]);
        $row = $s->fetch();
      } elseif ($id) {
        $s = $db->prepare('SELECT id, title, slug, description, image_url, status FROM programmes WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch();
      }
    }
    if ($row) {
      $canon = SITE_URL . '/' . ($row['slug'] ?: $row['id']);
      if ($row['status'] === 'active') {
        serve_with_meta(__DIR__ . '/programme.html', [
          'title' => $row['title'] . ' — Uganda Astronomical Society',
          'description' => share_text($row['description']) ?: 'Programme — Uganda Astronomical Society.',
          'image' => share_abs_url($row['image_url']),
          'url' => $canon,
        ]);
      }
      include __DIR__ . '/programme.html';
      exit;
    }
    if ($type === 'programme') { include __DIR__ . '/programme.html'; exit; }
  }

  // --- Project: /<slug> ---
  if (in_array($type, ['auto', 'project'], true) && $slug !== '') {
    $s = $db->prepare('SELECT id, title, slug, description, status FROM projects WHERE slug = ? LIMIT 1');
    $s->execute([$slug]);
    if ($row = $s->fetch()) {
      $canon = SITE_URL . '/' . ($row['slug'] ?: $row['id']);
      if ($row['status'] === 'published') {
        serve_with_meta(__DIR__ . '/project.html', [
          'title' => $row['title'] . ' — Uganda Astronomical Society',
          'description' => share_text($row['description']) ?: 'Project — Uganda Astronomical Society.',
          'image' => share_abs_url(null),
          'url' => $canon,
        ]);
      }
      include __DIR__ . '/project.html';
      exit;
    }
  }

  // --- Event: /events/<slug|id> ---
  if ($type === 'event') {
    $row = null;
    if ($slug !== '') {
      $s = $db->prepare('SELECT id, title, slug, description, image_url, status FROM events WHERE slug = ? LIMIT 1');
      $s->execute([$slug]);
      $row = $s->fetch();
    }
    if (!$row && ($id || ctype_digit($slug))) {
      $s = $db->prepare('SELECT id, title, slug, description, image_url, status FROM events WHERE id = ? LIMIT 1');
      $s->execute([$id ?: (int) $slug]);
      $row = $s->fetch();
    }
    if ($row && in_array($row['status'], ['published', 'cancelled', 'completed'], true)) {
      serve_with_meta(__DIR__ . '/event.html', [
        'title' => $row['title'] . ' — Uganda Astronomical Society',
        'description' => share_text($row['description']) ?: 'Event — Uganda Astronomical Society.',
        'image' => share_abs_url($row['image_url']),
        'url' => SITE_URL . '/events/' . ($row['slug'] ?: $row['id']),
      ]);
    }
    include __DIR__ . '/event.html';
    exit;
  }

  // --- Article: /article/<id> ---
  if ($type === 'article' && $id) {
    $s = $db->prepare('SELECT id, title, body, image_url, status FROM articles WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    if ($row = $s->fetch()) {
      if ($row['status'] === 'published') {
        serve_with_meta(__DIR__ . '/article.html', [
          'title' => $row['title'] . ' — Uganda Astronomical Society',
          'description' => share_text($row['body']) ?: 'Article — Uganda Astronomical Society.',
          'image' => share_abs_url($row['image_url']),
          'url' => SITE_URL . '/article/' . $row['id'],
          'type' => 'article',
        ]);
      }
      include __DIR__ . '/article.html';
      exit;
    }
    include __DIR__ . '/article.html';
    exit;
  }
} catch (Exception $e) {
  // Database unavailable: serve the plain template rather than breaking the page.
  foreach (['programme' => 'programme.html', 'project' => 'project.html', 'event' => 'event.html', 'article' => 'article.html'] as $t => $tpl) {
    if ($type === $t) { include __DIR__ . '/' . $tpl; exit; }
  }
}

slug_404();
