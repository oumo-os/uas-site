<?php
// Shared social-share (Open Graph / Twitter Card) meta injector.
// Link scrapers (WhatsApp, Facebook, X, Telegram) don't run JavaScript, so
// detail pages (programme / project / event / article) get their specific
// title, description and cover image injected here, server-side.
require_once __DIR__ . '/config.php';

/**
 * Web path of the application root ('' at domain root, '/uas' under a
 * subdirectory). Derived from the filesystem layout so it works in every
 * install without configuration.
 */
function app_base(string $dir): string {
  $norm = function ($p) { return rtrim(str_replace('\\', '/', (string) $p), '/'); };
  $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
  $dirN = $norm($dir);
  $docN = $norm($doc);
  if (function_exists('realpath')) {
    $rd = realpath($dir);
    if ($rd !== false) $dirN = $norm($rd);
    $rt = realpath($doc);
    if ($rt !== false && $rt !== '') $docN = $norm($rt);
  }
  if ($docN !== '' && strpos($dirN, $docN) === 0) return substr($dirN, strlen($docN));
  return '';
}

/**
 * Head prefix injected into every slug.php-served page, BEFORE the
 * template's own base-path script (it sits right after <meta charset>).
 * - window.UAS_BASE pre-seed wins over the template's computation thanks to
 *   its `===undefined` guard (a bare slug would otherwise be mistaken for a
 *   subdirectory, breaking every API call and rewritten link on the page).
 * - The static <base> element is FIRST in document order, so it wins URL
 *   resolution over the one the template script appends.
 */
function head_prefix(string $appBase): string {
  $b = htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8');
  return '<script>window.UAS_BASE=\'' . $b . '\';</script>' . "\n"
    . '  <base href="' . $b . '/">';
}

/**
 * Serve an HTML template with the correct base (always) and fresh share
 * meta tags (when $meta is given).
 */
function serve_template(string $template, ?array $meta = null): void {
  $html = @file_get_contents($template);
  if ($html === false) {
    http_response_code(404);
    include __DIR__ . '/../404.html';
    exit;
  }
  $prefix = head_prefix(app_base(dirname(__DIR__)));
  if ($meta !== null) {
    $esc = function ($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $prefix .= "\n" . '  <meta property="og:title" content="' . $esc($meta['title']) . '">' . "\n"
      . '  <meta property="og:description" content="' . $esc($meta['description']) . '">' . "\n"
      . '  <meta property="og:image" content="' . $esc($meta['image']) . '">' . "\n"
      . '  <meta property="og:image:width" content="1200">' . "\n"
      . '  <meta property="og:image:height" content="630">' . "\n"
      . '  <meta property="og:url" content="' . $esc($meta['url']) . '">' . "\n"
      . '  <meta property="og:type" content="' . $esc($meta['type'] ?? 'website') . '">' . "\n"
      . '  <meta name="twitter:card" content="summary_large_image">' . "\n"
      . '  <meta name="twitter:title" content="' . $esc($meta['title']) . '">' . "\n"
      . '  <meta name="twitter:description" content="' . $esc($meta['description']) . '">' . "\n"
      . '  <meta name="twitter:image" content="' . $esc($meta['image']) . '">';
    // Strip existing share tags to avoid duplicates (scrapers read the first).
    $html = preg_replace('#<meta\s+(property="og:[^"]+"|name="twitter:[^"]+")\s+content="[^"]*"\s*/?>#i', '', $html);
    $html = preg_replace('#<meta\s+content="[^"]*"\s+(property="og:[^"]+"|name="twitter:[^"]+")\s*/?>#i', '', $html);
  }
  $html = preg_replace('#(<meta charset="[^"]*">)#i', '$1' . "\n  " . $prefix, $html, 1);
  echo $html;
  exit;
}

function share_abs_url($path): string {
  $path = trim((string) $path);
  if ($path === '') return SITE_URL . '/img/og-cover.jpg';
  if (preg_match('#^https?://#i', $path)) return $path;
  return SITE_URL . '/' . ltrim($path, '/');
}

function share_text($html, int $max = 200): string {
  $t = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
  if (function_exists('mb_strlen') && mb_strlen($t) > $max) {
    $t = mb_substr($t, 0, $max - 1) . '…';
  } elseif (strlen($t) > $max) {
    $t = substr($t, 0, $max - 1) . '…';
  }
  return $t;
}

/**
 * Serve an HTML template with fresh share meta tags (delegates to
 * serve_template so the base fix is always included).
 */
function serve_with_meta(string $template, array $meta): void {
  serve_template($template, $meta);
}
