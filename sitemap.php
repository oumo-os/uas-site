<?php
// Sitemap generator — served as /sitemap.xml via .htaccess rewrite.
// Static pages plus live published events, articles and programmes.
require_once __DIR__ . '/api/config.php';

header('Content-Type: application/xml; charset=utf-8');

$base = 'https://astronomy.ug';
$today = date('Y-m-d');
$urls = [];

$static = [
  ['', '1.0', 'weekly'],
  ['/about', '0.8', 'monthly'],
  ['/programmes', '0.8', 'monthly'],
  ['/events', '0.9', 'weekly'],
  ['/news', '0.7', 'weekly'],
  ['/knowledge', '0.8', 'weekly'],
  ['/members', '0.6', 'monthly'],
  ['/join', '0.8', 'monthly'],
  ['/ecosystem', '0.5', 'monthly'],
  ['/library', '0.5', 'monthly'],
  ['/contact', '0.5', 'monthly'],
];
foreach ($static as [$p, $pr, $freq]) {
  $urls[] = ['loc' => $base . ($p === '' ? '/' : $p), 'lastmod' => $today, 'priority' => $pr, 'freq' => $freq];
}

try {
  $pdo = db();
  $stmt = $pdo->query('SELECT id, updated_at FROM events WHERE status = "published" ORDER BY date DESC');
  foreach ($stmt->fetchAll() as $r) {
    $urls[] = ['loc' => $base . '/event/' . (int) $r['id'], 'lastmod' => substr($r['updated_at'] ?? $today, 0, 10), 'priority' => '0.7', 'freq' => 'weekly'];
  }
  $stmt = $pdo->query('SELECT id, updated_at, published_at FROM articles WHERE status = "published" ORDER BY published_at DESC');
  foreach ($stmt->fetchAll() as $r) {
    $lm = $r['updated_at'] ?? $r['published_at'] ?? $today;
    $urls[] = ['loc' => $base . '/article/' . (int) $r['id'], 'lastmod' => substr($lm, 0, 10), 'priority' => '0.7', 'freq' => 'monthly'];
  }
  $stmt = $pdo->query('SELECT id FROM programmes WHERE status = "active" ORDER BY title');
  foreach ($stmt->fetchAll() as $r) {
    $urls[] = ['loc' => $base . '/programmes/' . (int) $r['id'], 'lastmod' => $today, 'priority' => '0.7', 'freq' => 'monthly'];
  }
} catch (Exception $e) {
  // DB unavailable: still serve the static URL set.
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
  echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>'
    . '<lastmod>' . $u['lastmod'] . '</lastmod>'
    . '<changefreq>' . $u['freq'] . '</changefreq>'
    . '<priority>' . $u['priority'] . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
