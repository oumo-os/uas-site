<?php
// Shared social-share (Open Graph / Twitter Card) meta injector.
// Link scrapers (WhatsApp, Facebook, X, Telegram) don't run JavaScript, so
// detail pages (programme / project / event / article) get their specific
// title, description and cover image injected here, server-side.
require_once __DIR__ . '/config.php';

function share_abs_url($path): string {
  $path = trim((string) $path);
  if ($path === '') return SITE_URL . '/img/uas-emblem.png';
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
 * Serve an HTML template with fresh share meta tags.
 * Existing og:/twitter: tags are stripped first so scrapers never see
 * duplicates (most take the FIRST occurrence, which would be the generic one).
 */
function serve_with_meta(string $template, array $meta): void {
  $html = @file_get_contents($template);
  if ($html === false) {
    http_response_code(404);
    include __DIR__ . '/../404.html';
    exit;
  }
  $esc = function ($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
  $block = '<meta property="og:title" content="' . $esc($meta['title']) . '">' . "\n"
    . '  <meta property="og:description" content="' . $esc($meta['description']) . '">' . "\n"
    . '  <meta property="og:image" content="' . $esc($meta['image']) . '">' . "\n"
    . '  <meta property="og:url" content="' . $esc($meta['url']) . '">' . "\n"
    . '  <meta property="og:type" content="' . $esc($meta['type'] ?? 'website') . '">' . "\n"
    . '  <meta name="twitter:card" content="summary_large_image">' . "\n"
    . '  <meta name="twitter:title" content="' . $esc($meta['title']) . '">' . "\n"
    . '  <meta name="twitter:description" content="' . $esc($meta['description']) . '">' . "\n"
    . '  <meta name="twitter:image" content="' . $esc($meta['image']) . '">';
  // Strip existing share tags (both attribute orders) to avoid duplicates.
  $html = preg_replace('#<meta\s+(property="og:[^"]+"|name="twitter:[^"]+")\s+content="[^"]*"\s*/?>#i', '', $html);
  $html = preg_replace('#<meta\s+content="[^"]*"\s+(property="og:[^"]+"|name="twitter:[^"]+")\s*/?>#i', '', $html);
  // Insert the fresh block right after the charset declaration.
  $html = preg_replace('#(<meta charset="[^"]*">)#i', '$1' . "\n  " . $block, $html, 1);
  echo $html;
  exit;
}
