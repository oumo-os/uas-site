<?php
// UAS Institutional Platform — Configuration
// Server-only secret overrides (never committed to git). Needed because
// LiteSpeed/cPanel SetEnv cannot reliably carry values containing '#'.
if (is_file(__DIR__ . '/prod-env.php')) require __DIR__ . '/prod-env.php';
// environment: production, development
if (!defined('ENV')) define('ENV', getenv('UAS_ENV') ?: 'development');
if (!defined('DB_HOST')) define('DB_HOST', getenv('UAS_DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('UAS_DB_NAME') ?: 'uas_platform');
if (!defined('DB_USER')) define('DB_USER', getenv('UAS_DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('UAS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Uganda Astronomical Society');
// Production URL is fixed; in development derive from the request host so the
// app works identically under /uas, a vhost root (e.g. http://uas.local/), etc.
// (CLI has no host — falls back to the legacy local URL.)
if (ENV === 'production') {
  define('SITE_URL', 'https://astronomy.ug');
} else {
  $devScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $devHost = $_SERVER['HTTP_HOST'] ?? 'localhost/uas';
  define('SITE_URL', $devScheme . '://' . $devHost);
}
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

// Gallery helpers: every image attached to content (covers + inline rich-text
// images) feeds the photo gallery, captioned with trimmed owning content.
function trim_text($html, int $len = 140): string {
  $t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
  $t = trim(preg_replace('/\s+/', ' ', $t));
  if (mb_strlen($t) > $len) $t = mb_substr($t, 0, $len) . '…';
  return $t;
}

// Normalize a stored image ref to a gallery src: absolute http(s) URLs pass
// through, site refs become /img/... paths. Anything else is rejected.
function gallery_src($v): ?string {
  if (!is_string($v)) return null;
  $v = trim($v);
  if ($v === '') return null;
  if (preg_match('#^https?://#i', $v)) {
    return preg_match('#^https?://[^\s<>"\'\\\\]+$#i', $v) ? $v : null;
  }
  $v = ltrim($v, '/');
  if (strpos($v, 'img/') !== 0) $v = 'img/' . $v;
  if (!preg_match('#^img/[^\s<>"\'\\\\]+$#', $v)) return null;
  return '/' . $v;
}

// All <img> sources embedded in rich-text HTML, normalized + deduped.
function gallery_inline_srcs($html): array {
  $out = [];
  if (!is_string($html) || stripos($html, '<img') === false) return $out;
  if (preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', $html, $m)) {
    foreach ($m[1] as $s) {
      $n = gallery_src($s);
      if ($n && !in_array($n, $out, true)) $out[] = $n;
    }
  }
  return $out;
}

// Image URLs stored on content (covers, galleries): only same-site uploads
// (/img/...) or http(s) URLs are allowed. Anything else becomes NULL, which
// blocks javascript:/data: payloads from reaching <img src> output.
function clean_image_url($v): ?string {
  if (!is_string($v)) return null;
  $v = trim($v);
  if ($v === '' || strlen($v) > 500) return null;
  if (strpos($v, '/img/') === 0) return $v;
  if (preg_match('#^https?://[^\s<>"\']+$#i', $v)) return $v;
  return null;
}

// ---- Video meetings & embeds (nothing is hosted here) ----
if (!defined('JITSI_DOMAIN')) define('JITSI_DOMAIN', 'https://meet.jit.si');

// URL allowlist for link/redirect fields (Jitsi rooms, online venues).
function clean_link_url($v): ?string {
  if (!is_string($v)) return null;
  $v = trim($v);
  if ($v === '' || strlen($v) > 500) return null;
  if (preg_match('#^https?://[^\s<>"\']+$#i', $v)) return $v;
  return null;
}

/**
 * Convert a YouTube / Vimeo URL into a privacy-enhanced embed URL.
 * Returns null for anything else (including raw iframes and file URLs) —
 * videos are linked, never uploaded or proxied.
 */
function video_embed_url($url): ?string {
  if (!is_string($url)) return null;
  $url = trim($url);
  if ($url === '' || strlen($url) > 1000) return null;
  // YouTube: watch?v=, youtu.be/, /shorts/, /live/, /embed/
  if (preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?.*v=|shorts/|live/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#i', $url, $m)) {
    return 'https://www.youtube-nocookie.com/embed/' . $m[1];
  }
  // Vimeo: vimeo.com/<id> (channels/groups/on-demand paths included)
  if (preg_match('#^(?:https?://)?(?:www\.)?vimeo\.com/(?:.*?/)?(\d{5,12})(?:[/?#]|$)#i', $url, $m)) {
    return 'https://player.vimeo.com/video/' . $m[1];
  }
  return null;
}

/**
 * Strip dangerous markup from rich-text fields while preserving the editor's
 * formatting. Scripts/styles/event handlers go; iframes survive ONLY from
 * the video allowlist (YouTube-nocookie, Vimeo player) and Jitsi rooms.
 */
function sanitize_rich_html($html): ?string {
  if ($html === null) return null;
  if (!is_string($html)) return '';
  $html = preg_replace('#<(script|style|object|embed|form|input|button|link|meta)[^>]*>.*?</\\1>#is', '', $html);
  $html = preg_replace('#<(script|style|object|embed|form|input|button|link|meta)[^>]*/?>#i', '', $html);
  // Event-handler attributes (onclick=, onerror=, …) on any tag.
  $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
  // javascript:/data:/vbscript: URLs anywhere.
  $html = preg_replace('#\b(href|src|action|xlink:href)\s*=\s*("|\')(javascript|data|vbscript):.*?\2#is', '$1=$2#$2', $html);
  // Iframes: keep only the media allowlist, drop the rest entirely.
  $html = preg_replace_callback('#<iframe\b[^>]*>#i', function ($m) {
    $tag = $m[0];
    if (!preg_match('#\bsrc\s*=\s*("|\')([^"\']+)#i', $tag, $s)) return '';
    $src = $s[2];
    $ok = preg_match('#^https://www\.youtube-nocookie\.com/embed/#', $src)
      || preg_match('#^https://player\.vimeo\.com/video/#', $src)
      || preg_match('#^https://meet\.jit\.si/#', $src);
    if (!$ok) return '';
    // Rebuild a minimal safe iframe: sandboxed, no fullscreen abuse beyond playback.
    $id = preg_match('#\bid\s*=\s*("|\')([^"\']+)#i', $tag, $im) ? $im[2] : null;
    $safe = '<iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
    if ($id !== null) $safe .= ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
    return $safe . ' style="width:100%;height:360px;border:0;border-radius:8px" allow="camera; microphone; fullscreen; display-capture; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
  }, $html);
  // Remove stray closing iframe tags left behind by stripped opens.
  $html = preg_replace('#</iframe>#i', '', $html);
  // NOTE: closing tags of kept iframes were removed above; re-close them.
  $html = preg_replace('#(<iframe\b[^>]*style="[^"]*"[^>]*>)#i', '$1</iframe>', $html);
  return $html;
}

/**
 * Validate project milestones: [{title, due_date|null, done}].
 * Titles are plain text (stripped), max 30 entries.
 */
function clean_milestones($v): array {
  if (!is_array($v)) return [];
  $out = [];
  foreach (array_slice(array_values($v), 0, 30) as $m) {
    if (!is_array($m)) continue;
    $title = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($m['title'] ?? ''))));
    if ($title === '') continue;
    $due = $m['due_date'] ?? null;
    if ($due !== null && $due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = null;
    $out[] = ['title' => mb_substr($title, 0, 200), 'due_date' => ($due === '' ? null : $due), 'done' => !empty($m['done'])];
  }
  return $out;
}

// Role inbox offices (must match the directory on contact.html).
function office_list(): array {
  return [
    'info' => 'info@astronomy.ug',
    'contact' => 'contact@astronomy.ug',
    'membership' => 'membership@astronomy.ug',
    'programmes' => 'programmes@astronomy.ug',
    'partnerships' => 'partnerships@astronomy.ug',
    'publicity' => 'publicity@astronomy.ug',
    'secretary' => 'secretary@astronomy.ug',
    'legal' => 'legal@astronomy.ug',
    'finance' => 'finance@astronomy.ug',
  ];
}

// Office inboxes a user may triage. System admins see every office;
// others see offices granted to their active roles.
function user_inbox_offices(int $userId): array {
  if (user_has_cap($userId, 'admin.system')) return array_keys(office_list());
  $stmt = db()->prepare("SELECT DISTINCT ria.office FROM role_inbox_access ria JOIN role_assignments ra ON ra.role_id = ria.role_id WHERE ra.user_id = ? AND ra.status = 'active'");
  $stmt->execute([$userId]);
  $offices = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
  return array_values(array_intersect($offices, array_keys(office_list())));
}

// ---- Slugs ----
function slugify(string $text): string {
  $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  $text = trim($text, '-');
  $text = substr($text, 0, 80);
  return $text !== '' ? $text : 'item';
}
function slugExists(string $slug, string $table, ?int $excludeId = null): bool {
  $allowed = ['programmes','projects','events'];
  if (!in_array($table, $allowed, true)) return false;
  $sql = "SELECT id FROM `$table` WHERE slug = ?";
  $params = [$slug];
  if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return (bool) $stmt->fetchColumn();
}
function rootSlugExists(string $slug, ?int $excludeProgId = null, ?int $excludeProjId = null): bool {
  if (slugExists($slug, 'programmes', $excludeProgId)) return true;
  if (slugExists($slug, 'projects', $excludeProjId)) return true;
  return false;
}
function makeSlug(string $title, string $table, ?int $excludeId = null): string {
  $base = slugify($title);
  $slug = $base;
  $i = 2;
  $isRoot = in_array($table, ['programmes','projects'], true);
  while ($isRoot ? rootSlugExists($slug, $table==='programmes'?$excludeId:null, $table==='projects'?$excludeId:null) : slugExists($slug, $table, $excludeId)) {
    $slug = $base . '-' . $i++;
    if (strlen($slug) > 120) $slug = substr($base, 0, 100) . '-' . $i;
  }
  return $slug;
}

// ---- Office mailbox (IMAP) helpers ----
// Passwords are AES-256-CBC encrypted with MAILBOX_KEY from api/prod-env.php
// (server-only). The key never leaves the server; officers never see passwords.
function mailbox_key(): ?string {
  if (!defined('MAILBOX_KEY') || strlen((string) MAILBOX_KEY) < 16) return null;
  return (string) MAILBOX_KEY;
}

function mailbox_encrypt(string $plain): ?string {
  $key = mailbox_key();
  if (!$key || !function_exists('openssl_encrypt')) return null;
  $iv = random_bytes(16);
  $ct = openssl_encrypt($plain, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
  return $ct === false ? null : base64_encode($iv . $ct);
}

function mailbox_decrypt(string $stored): ?string {
  $key = mailbox_key();
  if (!$key || !function_exists('openssl_decrypt')) return null;
  $raw = base64_decode($stored, true);
  if ($raw === false || strlen($raw) <= 16) return null;
  $pt = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($raw, 0, 16));
  return $pt === false ? null : $pt;
}

// Open an office mailbox over IMAP+SSL. Returns [connection, null] or [null, error].
// Error strings are safe to show (never include credentials).
function mailbox_open(string $office): array {
  if (!function_exists('imap_open')) return [null, 'PHP IMAP extension missing'];
  $offices = office_list();
  if (!isset($offices[$office])) return [null, 'Unknown office'];
  try {
    $stmt = db()->prepare('SELECT * FROM office_mailboxes WHERE office = ? AND enabled = 1');
    $stmt->execute([$office]);
    $cfg = $stmt->fetch();
  } catch (Exception $e) { return [null, 'Mailbox store unavailable — import migration 043']; }
  if (!$cfg) return [null, 'Mailbox not configured'];
  $pass = mailbox_decrypt($cfg['password_enc']);
  if ($pass === null) return [null, 'Cannot decrypt credentials (MAILBOX_KEY?)'];
  $host = $cfg['host'] ?: 'astronomy.ug';
  $port = (int) ($cfg['port'] ?: 993);
  $flags = $cfg['use_ssl'] ? '/imap/ssl' : '/imap/notls';
  $box = '{' . $host . ':' . $port . $flags . '}INBOX';
  if (function_exists('imap_timeout')) {
    imap_timeout(IMAP_OPENTIMEOUT, 10);
    imap_timeout(IMAP_READTIMEOUT, 15);
  }
  $mbox = @imap_open($box, $cfg['username'], $pass, 0, 1);
  if (!$mbox) return [null, 'IMAP login failed: ' . imap_last_error()];
  return [$mbox, null];
}

// Display names per office for the From header (envelope + addresses unchanged).
function office_names(): array {
  return [
    'info' => 'UAS Information',
    'contact' => 'UAS General',
    'membership' => 'Membership Team',
    'programmes' => 'Programmes Team',
    'partnerships' => 'Partnerships Team',
    'publicity' => 'Publicity Team',
    'secretary' => 'Secretariat',
    'legal' => 'Legal Team',
    'finance' => 'Finance Team',
  ];
}

// Full sender string, e.g. Finance Team <finance@astronomy.ug>.
function office_from(string $office): string {
  $list = office_list();
  $names = office_names();
  $addr = $list[$office] ?? $list['contact'];
  $name = $names[$office] ?? 'UAS';
  return $name . ' <' . $addr . '>';
}

// Forced institutional signature appended server-side to every outgoing
// office email: author name, governance/administrative roles, committees,
// then the standard organisation block.
function mail_signature(int $userId, string $userName): string {
  $p = mail_signature_parts($userId, $userName);
  $lines = [$p['name']];
  if ($p['roles'] !== '') $lines[] = $p['roles'];
  if ($p['committees'] !== '') $lines[] = $p['committees'];
  $lines[] = 'Uganda Astronomical Society';
  $lines[] = 'https://astronomy.ug';
  return implode("\n", $lines);
}

// Structured signature parts for the styled HTML signature block.
function mail_signature_parts(int $userId, string $userName): array {
  $parts = ['name' => $userName, 'roles' => '', 'committees' => ''];
  try {
    $roles = [];
    foreach (user_roles($userId) as $r) {
      if (in_array($r['role_type'] ?? '', ['governance', 'administrative'], true)) $roles[] = $r['title'];
    }
    $roles = array_values(array_unique($roles));
    if ($roles) $parts['roles'] = implode(', ', $roles);
    $stmt = db()->prepare("SELECT wg.name FROM working_group_members wgm JOIN working_groups wg ON wg.id = wgm.group_id WHERE wgm.user_id = ? AND wgm.status = 'active' AND wg.status = 'active' AND wg.type = 'committee' ORDER BY wg.name");
    $stmt->execute([$userId]);
    $committees = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($committees) $parts['committees'] = implode(', ', $committees);
  } catch (Exception $e) { /* roles unavailable — name + org block still apply */ }
  return $parts;
}

// Styled HTML signature block (matches the plain-text signature content).
// Logo is embedded via CID (see mail_mime) so it displays offline.
function mail_signature_html(int $userId, string $userName): string {
  $p = mail_signature_parts($userId, $userName);
  $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:16px;padding-top:12px;border-top:1px solid #d8dde5;font-family:Arial,Helvetica,sans-serif;">'
    . '<tr>'
    . '<td style="vertical-align:top;padding-right:12px;"><img src="cid:uas-emblem" width="72" alt="UAS" style="display:block;border:0;"></td>'
    . '<td style="vertical-align:top;">'
    . '<div style="font-size:14px;font-weight:bold;color:#1a2332;">' . $h($p['name']) . '</div>';
  if ($p['roles'] !== '') {
    $html .= '<div style="font-size:12px;color:#4a5568;padding-top:2px;">' . $h($p['roles']) . '</div>';
  }
  if ($p['committees'] !== '') {
    $html .= '<div style="font-size:12px;color:#4a5568;">' . $h($p['committees']) . '</div>';
  }
  $html .= '<div style="font-size:12px;color:#1b4965;font-weight:bold;padding-top:6px;">Uganda Astronomical Society</div>'
    . '<div style="font-size:12px;"><a href="https://astronomy.ug" style="color:#1b4965;">astronomy.ug</a></div>'
    . '</td></tr>'
    . '</table>';
  return $html;
}

// Build a multipart/alternative body: identical plain-text part plus a
// styled HTML part (plain paragraphs + signature block). Returns
// [headers_suffix, body] where headers_suffix holds Content-Type lines.
function mail_mime(string $textBody, string $htmlSigBlock): array {
  $boundary = 'uas_' . bin2hex(random_bytes(12));
  $htmlBody = '';
  foreach (preg_split('/\r\n|\r|\n/', $textBody) as $para) {
    $para = trim($para);
    if ($para === '') continue;
    // Strip the plain signature tail (from the — separator); HTML sig replaces it.
    if ($para === '—') break;
    $htmlBody .= '<p style="font-size:14px;line-height:1.65;color:#1a2332;margin:0 0 12px;">'
      . htmlspecialchars($para, ENT_QUOTES, 'UTF-8') . '</p>';
  }
  // Drop any signature lines that followed the separator in plain text.
  $headers = 'MIME-Version: 1.0' . "\r\n"
    . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
  $alt = '--' . $boundary . "\r\n"
    . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
    . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
    . $textBody . "\r\n\r\n"
    . '--' . $boundary . "\r\n"
    . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
    . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
    . '<div style="font-family:Arial,Helvetica,sans-serif;">' . $htmlBody . $htmlSigBlock . '</div>' . "\r\n\r\n"
    . '--' . $boundary . '--';
  // Embed the emblem so it displays without remote fetching.
  $logoData = @is_file(__DIR__ . '/../img/uas-emblem-mail.png') ? @file_get_contents(__DIR__ . '/../img/uas-emblem-mail.png') : false;
  if ($logoData === false || $logoData === '') {
    $body = $alt;
  } else {
    $rel = 'uas_rel_' . bin2hex(random_bytes(8));
    $headers = 'MIME-Version: 1.0' . "\r\n"
      . 'Content-Type: multipart/related; boundary="' . $rel . '"' . "\r\n";
    $body = '--' . $rel . "\r\n"
      . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n\r\n"
      . $alt . "\r\n\r\n"
      . '--' . $rel . "\r\n"
      . 'Content-Type: image/png; name="uas-emblem.png"' . "\r\n"
      . 'Content-Transfer-Encoding: base64' . "\r\n"
      . 'Content-ID: <uas-emblem>' . "\r\n"
      . 'Content-Disposition: inline; filename="uas-emblem.png"' . "\r\n\r\n"
      . chunk_split(base64_encode($logoData)) . "\r\n"
      . '--' . $rel . '--';
  }
  // Dot-stuff for SMTP DATA transparency.
  $stuffed = [];
  foreach (preg_split('/\r\n|\r|\n/', $raw ?? $body) as $ln) {
    $stuffed[] = (isset($ln[0]) && $ln[0] === '.') ? '.' . $ln : $ln;
  }
  $body = implode("\r\n", $stuffed);
  return [$headers, $body];
}

// Observe-only SMTP probe for diagnostics: connects, reads the greeting and
// EHLO capabilities per transport. Never authenticates, never sends.
function smtp_probe(string $office): array {
  $out = ['office' => $office, 'transports' => []];
  try {
    $offices = office_list();
    if (!isset($offices[$office])) { $out['error'] = 'unknown office'; return $out; }
    $stmt = db()->prepare('SELECT host, port FROM office_mailboxes WHERE office = ? AND enabled = 1');
    $stmt->execute([$office]);
    $cfg = $stmt->fetch();
    if (!$cfg) { $out['error'] = 'mailbox not configured'; return $out; }
    $host = $cfg['host'] ?: 'astronomy.ug';
    $port = (int) ($cfg['port'] ?: 465);
    $deadline = time() + 20;
    foreach (['ssl://' . $host . ':' . $port, 'tcp://127.0.0.1:25', 'tcp://127.0.0.1:587'] as $target) {
      $t = ['target' => $target, 'lines' => []];
      $fp = @stream_socket_client($target, $errno, $errstr, 3);
      if (!$fp) { $t['lines'][] = ['!', 'connect failed' . ($errstr ? ": $errstr" : '')]; $out['transports'][] = $t; continue; }
      stream_set_timeout($fp, 4);
      $read = function () use ($fp, $deadline) {
        $resp = ''; $n = 0;
        while (time() < $deadline && $n++ < 20) {
          $line = @fgets($fp, 512);
          if ($line === false || $line === '') break;
          $resp .= $line;
          if (preg_match('/^\d{3} /', $line)) break;
        }
        return $resp;
      };
      $g = $read();
      $t['lines'][] = ['S', trim($g) !== '' ? trim($g) : '(no greeting)'];
      if (strpos($g, '220') === 0) {
        @fwrite($fp, "EHLO astronomy.ug\r\n");
        $e = $read();
        $t['lines'][] = ['S', trim($e) !== '' ? trim($e) : '(no EHLO response)'];
        @fwrite($fp, "QUIT\r\n");
      }
      @stream_set_blocking($fp, false);
      @fclose($fp);
      $out['transports'][] = $t;
      if (time() >= $deadline) break;
    }
  } catch (Throwable $e) {
    $out['error'] = 'probe failed: ' . $e->getMessage();
  }
  return $out;
}

// Record a sent office email for the Sent view + threading. Silently skips
// when migration 045 is not yet imported.
function record_sent(string $office, string $to, string $subject, string $body, ?string $msgId, string $kind, ?int $refId, ?int $userId, ?string $via, ?string $inReplyTo = null): void {
  try {
    db()->prepare('INSERT INTO mail_sent (office, to_email, subject, body, message_id, in_reply_to, kind, ref_id, sent_by, via) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$office, $to, $subject, $body, $msgId, $inReplyTo, $kind, $refId, $userId, $via]);
  } catch (Exception $e) { /* table missing — skip */ }
}

// Decode a possibly MIME-encoded header to UTF-8.
function mailbox_text(string $s): string {
  if (function_exists('imap_mime_header_decode')) {
    $out = '';
    foreach (@imap_mime_header_decode($s) ?: [] as $p) {
      $t = $p->text ?? '';
      $cs = strtoupper($p->charset ?? 'UTF-8');
      if ($cs !== 'UTF-8' && $cs !== 'DEFAULT' && function_exists('mb_convert_encoding')) {
        $t = @mb_convert_encoding($t, 'UTF-8', $cs) ?: $t;
      }
      $out .= $t;
    }
    return $out;
  }
  return function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($s) : $s;
}

// Find the first text/plain part (skipping attachments); else first text/html.
// Returns [body, is_html].
function mailbox_body($mbox, int $uid): array {
  $struct = @imap_fetchstructure($mbox, $uid, FT_UID);
  if (!$struct) return ['', false];
  $found = [null, null]; // [plain_part, html_part]
  $walk = function ($parts, $prefix = '') use (&$walk, &$found) {
    foreach ($parts as $i => $p) {
      $num = $prefix === '' ? (string) ($i + 1) : $prefix . '.' . ($i + 1);
      if (!empty($p->parts)) { $walk($p->parts, $num); continue; }
      $type = strtolower($p->type == 0 ? ($p->subtype ?? '') : '');
      $disp = strtolower($p->disposition ?? '');
      if ($disp === 'attachment') continue;
      if ($type === 'plain' && $found[0] === null) $found[0] = [$num, $p];
      if ($type === 'html' && $found[1] === null) $found[1] = [$num, $p];
    }
  };
  if (!empty($struct->parts)) { $walk($struct->parts); }
  else {
    $type = strtolower($struct->type == 0 ? ($struct->subtype ?? '') : '');
    if ($type === 'plain') $found[0] = ['1', $struct];
    elseif ($type === 'html') $found[1] = ['1', $struct];
  }
  foreach ([[$found[0], false], [$found[1], true]] as [$hit, $isHtml]) {
    if (!$hit) continue;
    [$num, $p] = $hit;
    $raw = @imap_fetchbody($mbox, $uid, $num, FT_UID | FT_PEEK);
    if ($raw === false) continue;
    switch ($p->encoding ?? 0) {
      case 3: $raw = base64_decode($raw); break;
      case 4: $raw = quoted_printable_decode($raw); break;
    }
    $cs = 'UTF-8';
    foreach ((array) ($p->parameters ?? []) as $param) {
      if (strtolower($param->attribute ?? '') === 'charset') $cs = strtoupper($param->value);
    }
    if ($cs !== 'UTF-8' && function_exists('mb_convert_encoding')) $raw = @mb_convert_encoding($raw, 'UTF-8', $cs) ?: $raw;
    $raw = substr($raw, 0, 200000);
    if ($isHtml) $raw = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', '', $raw))));
    return [$raw, $isHtml];
  }
  return ['', false];
}
// Send an office reply through the host mail system. Returns handoff
// status (true = accepted by MTA, NOT proof of inbox delivery — needs SPF).
function send_office_email(string $office, string $to, string $subject, string $body, ?string &$via = null, ?string &$msgId = null, array $extraHeaders = [], int $userId = 0, string $userName = ''): bool {
  // Preferred path: authenticated SMTP as the office itself, so the
  // envelope sender matches From (no "on behalf of", SPF/DKIM align).
  $fromHeader = office_from($office);
  [$mimeHeaders, $mimeBody] = mail_mime($body, mail_signature_html($userId, $userName));
  $msgId = bin2hex(random_bytes(12)) . '@astronomy.ug';
  [$ok, $note] = smtp_office_email($office, $to, $subject, $body, $msgId, $extraHeaders, $fromHeader, $mimeHeaders, $mimeBody);
  if ($ok) { $via = 'smtp'; return true; }
  $msgId = null; // sendmail path generates its own Message-ID server-side
  $via = 'sendmail-fallback(' . $note . ')';
  $list = office_list();
  $from = $list[$office] ?? $list['contact'];
  $headers = "From: $fromHeader\r\nReply-To: $from\r\n" . $mimeHeaders . "X-Mailer: UAS-Platform";
  // Envelope sender = office address so SPF/DKIM align (no "on behalf of").
  // Falls back to server default if the host rejects custom senders.
  // -odb queues in background so a slow remote MTA can't kill the request.
  $params = '-f' . $from . ' -odb';
  $sent = @mail($to, $subject, $mimeBody, $headers, $params);
  if (!$sent) $sent = @mail($to, $subject, $mimeBody, $headers);
  if (!$sent) $via = 'failed';
  return $sent;
}

// Authenticated SMTP submission using the office mailbox credentials.
// Tries transports in order: office host over SSL, then localhost
// submission (same machine, no auth needed). Returns [sent, note] —
// the note names the failing step across all transports for diagnostics.
function smtp_office_email(string $office, string $to, string $subject, string $body, ?string $msgId = null, array $extraHeaders = [], ?string $fromHeader = null, ?string $mimeHeaders = null, ?string $mimeBody = null): array {
  try {
    $offices = office_list();
    if (!isset($offices[$office])) return [false, 'unknown office'];
    $stmt = db()->prepare('SELECT * FROM office_mailboxes WHERE office = ? AND enabled = 1');
    $stmt->execute([$office]);
    $cfg = $stmt->fetch();
    if (!$cfg) return [false, 'mailbox not configured'];
    $pass = mailbox_decrypt($cfg['password_enc']);
    if ($pass === null || $pass === '') return [false, 'cannot decrypt credentials'];
    $from = $offices[$office];
    $host = $cfg['host'] ?: 'astronomy.ug';
    @set_time_limit(90);
    $subj = function_exists('mb_encode_mimeheader')
      ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n") : $subject;
    $lines = preg_split('/\r\n|\r|\n/', $body);
    $stuffed = [];
    foreach ($lines as $ln) {
      $stuffed[] = (isset($ln[0]) && $ln[0] === '.') ? '.' . $ln : $ln;
    }
    if ($msgId === null) $msgId = bin2hex(random_bytes(12)) . '@astronomy.ug';
    $extra = '';
    foreach ($extraHeaders as $k => $v) $extra .= $k . ': ' . $v . "\r\n";
    if ($fromHeader === null) $fromHeader = 'UAS <' . $from . '>';
    if ($mimeHeaders === null || $mimeBody === null) {
      $mimeHeaders = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n";
      $mimeBody = implode("\r\n", $stuffed);
    }
    $payload = 'From: ' . $fromHeader . "\r\n"
      . 'Reply-To: ' . $from . "\r\n"
      . 'To: ' . $to . "\r\n"
      . 'Subject: ' . $subj . "\r\n"
      . 'Date: ' . date('r') . "\r\n"
      . 'Message-ID: <' . $msgId . '>' . "\r\n"
      . $extra
      . $mimeHeaders
      . 'X-Mailer: UAS-Platform' . "\r\n"
      . "\r\n" . $mimeBody;

    // One submission attempt over a single transport. $deadline bounds the
    // whole cascade so slow hosts fail gracefully instead of killing PHP.
    $globalDeadline = time() + 22;
    $attempt = function (string $target, bool $implicitTls, ?array $auth, string $label, array $creds, int $deadline) use ($from, $to, $payload) {
      $fp = @stream_socket_client($target, $errno, $errstr, 3);
      if (!$fp) return [false, $label . ': connect failed' . ($errstr ? " ($errstr)" : '')];
      stream_set_timeout($fp, 5);
      $deadline = min($deadline, time() + 18);
      $talk = function (string $cmd) use ($fp, $deadline) {
        if ($cmd !== '') @fwrite($fp, $cmd . "\r\n");
        $resp = '';
        $n = 0;
        while (time() < $deadline && $n++ < 60) {
          $line = @fgets($fp, 512);
          if ($line === false || $line === '') break;
          $resp .= $line;
          if (preg_match('/^\d{3} /', $line)) break;
        }
        return $resp;
      };
      $close = function () use ($fp) {
        @fwrite($fp, "QUIT\r\n");
        // Non-blocking close: fclose() on TLS streams can otherwise hang
        // waiting for close_notify and kill the request AFTER sending.
        @stream_set_blocking($fp, false);
        @fclose($fp);
      };
      $greet = $talk('');
      if (strpos($greet, '220') !== 0) { $close(); return [false, $label . ': no greeting']; }
      $ehlo = $talk('EHLO astronomy.ug');
      $isLocal = strpos($target, '127.0.0.1') !== false;
      if (!$implicitTls && stripos($ehlo, 'STARTTLS') !== false && function_exists('stream_socket_enable_crypto')) {
        $r = $talk('STARTTLS');
        if (strpos($r, '220') === 0 && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
          $ehlo = $talk('EHLO astronomy.ug');
        } elseif (!$isLocal) { $close(); return [false, $label . ': STARTTLS failed']; }
      }
      if ($auth) {
        $r = $talk('AUTH LOGIN');
        if (strpos($r, '334') !== 0) { $close(); return [false, $label . ': AUTH not offered']; }
        $r = $talk(base64_encode($auth[0]));
        if (strpos($r, '334') !== 0) { $close(); return [false, $label . ': username rejected']; }
        $r = $talk(base64_encode($auth[1]));
        if (strpos($r, '235') !== 0) { $close(); return [false, $label . ': password rejected']; }
      }
      $send = function () use ($talk, $from, $to, $payload) {
        $r = $talk('MAIL FROM:<' . $from . '>');
        if (strpos($r, '250') !== 0) return [false, 'sender rejected: ' . trim(preg_replace('/\s+/', ' ', $r))];
        $r = $talk('RCPT TO:<' . $to . '>');
        if (strpos($r, '250') !== 0 && strpos($r, '251') !== 0) return [false, 'recipient rejected: ' . trim(preg_replace('/\s+/', ' ', $r))];
        $r = $talk('DATA');
        if (strpos($r, '354') !== 0) return [false, 'DATA rejected: ' . trim(preg_replace('/\s+/', ' ', $r))];
        $r = $talk($payload . "\r\n.");
        return strpos($r, '250') === 0 ? [true, 'accepted'] : [false, 'not accepted: ' . trim(preg_replace('/\s+/', ' ', $r))];
      };
      [$sent, $note] = $send();
      if (!$sent && $auth === null && $isLocal && $creds) {
        // Localhost refused anonymous submission — retry with mailbox login.
        $r = $talk('AUTH LOGIN');
        if (strpos($r, '334') === 0) {
          $r = $talk(base64_encode($creds[0]));
          if (strpos($r, '334') === 0) $r = $talk(base64_encode($creds[1]));
          if (strpos($r, '235') === 0) {
            [$sent, $note] = $send();
            if ($sent) { $close(); return [true, $label . '-auth: accepted']; }
            $note .= ' (after auth)';
          } else { $note = 'auth rejected: ' . trim(preg_replace('/\s+/', ' ', $r)); }
        } else { $note .= ' (AUTH not offered)'; }
      }
      $close();
      return $sent ? [true, $label . ': accepted'] : [false, $label . ' (' . $note . ')'];
    };

    $creds = [$cfg['username'], $pass];
    // NOTE: $cfg['port'] is the IMAP port — SMTP uses its own standard ports.
    $candidates = [
      ['ssl://' . $host . ':465', true, $creds, 'smtp-465'],
      ['tcp://' . $host . ':587', false, $creds, 'smtp-587'],
      ['tcp://127.0.0.1:25', false, $creds, 'smtp-localhost'],
      ['tcp://127.0.0.1:587', false, $creds, 'smtp-localhost587'],
    ];
    $notes = [];
    $authBroken = false;
    foreach ($candidates as [$target, $tls, $auth, $label]) {
      if (time() >= $globalDeadline) { $notes[] = $label . ' (skipped: time budget exhausted)'; continue; }
      if ($authBroken && $auth !== null) { $notes[] = $label . ' (skipped: credentials rejected elsewhere)'; continue; }
      [$sent, $note] = $attempt($target, $tls, $auth, $label, $creds, $globalDeadline);
      if ($sent) return [true, $note];
      $notes[] = $note;
      if ($auth !== null && (stripos($note, 'password rejected') !== false || stripos($note, 'username rejected') !== false)) {
        $authBroken = true; // same credentials everywhere — don't retry them
      }
    }
    return [false, implode('; ', $notes)];
  } catch (Throwable $e) {
    return [false, 'exception: ' . $e->getMessage()];
  }
}

// Recompress an uploaded image in place: max $maxW px wide (1600 default,
// 256 for avatars), JPEG q80, PNG level 6 (alpha preserved), WebP q80.
// GIFs pass through untouched (animation). Returns [width, height,
// compressed] or null when GD is unavailable or the file is unreadable.
function compress_uploaded_image(string $path, string $mime, int $maxW = 1600): ?array {
  if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) return null;
  $info = @getimagesize($path);
  if (!$info || $info[0] <= 0 || $info[1] <= 0) return null;
  $w = $info[0];
  $h = $info[1];
  if ($mime === 'image/gif') return [$w, $h, false];
  $size = @filesize($path);
  if ($w <= $maxW && $size !== false && $size <= 400 * 1024 && ($mime === 'image/jpeg' || $mime === 'image/webp')) {
    return [$w, $h, false];
  }
  if ($mime === 'image/jpeg') {
    $src = @imagecreatefromjpeg($path);
  } elseif ($mime === 'image/png') {
    $src = @imagecreatefrompng($path);
  } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
    $src = @imagecreatefromwebp($path);
  } else {
    return null;
  }
  if (!$src) return null;
  $nw = $w;
  $nh = $h;
  $dst = $src;
  if ($w > $maxW) {
    $nw = $maxW;
    $nh = (int) round($h * $maxW / $w);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') {
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
  } elseif ($mime === 'image/png') {
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
  }
  $ok = false;
  if ($mime === 'image/jpeg') {
    $ok = imagejpeg($dst, $path, 80);
  } elseif ($mime === 'image/png') {
    $ok = imagepng($dst, $path, 6);
  } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
    $ok = imagewebp($dst, $path, 80);
  }
  if ($dst !== $src) imagedestroy($dst);
  imagedestroy($src);
  if (!$ok) return null;
  clearstatcache(true, $path);
  return [$nw, $nh, true];
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

// ---- Mass mail broadcasts ----
// Who may send to many at once: dedicated mail.broadcast cap, or officers
// who already review/manage content or members. The sending office must
// additionally be one of the user's own inboxes (checked at the endpoints).
function can_broadcast(int $userId): bool {
  foreach (['mail.broadcast', 'members.manage', 'programmes.manage', 'events.approve', 'projects.approve', 'admin.system'] as $c) {
    if (user_has_cap($userId, $c)) return true;
  }
  return false;
}

function broadcast_member_classes(): array {
  return ['Regular Member', 'Student Member', 'Honorary Member', 'Institutional Member', 'Affiliate Member', 'Corporate Member'];
}

/**
 * Resolve a broadcast audience to recipients: [['email'=>, 'user_id'=>?int, 'name'=>], ...]
 * Deduped by email. Member lists require an active membership row (this also
 * keeps service accounts out); participation audiences resolve through
 * registrations/seats, which are real people by construction.
 */
function broadcast_audience(string $type, $ref, array $opts = []): array {
  $out = [];
  $add = function ($email, $uid, $name) use (&$out) {
    $email = strtolower(trim((string) $email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    if (isset($out[$email])) return;
    $out[$email] = ['email' => $email, 'user_id' => $uid ? (int) $uid : null, 'name' => trim((string) $name) ?: null];
  };
  switch ($type) {
    case 'member_class': {
      $class = trim((string) $ref);
      if (!in_array($class, broadcast_member_classes(), true)) json_error('Unknown member class', 400);
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM users u JOIN members m ON m.user_id = u.id AND m.status = 'active' JOIN role_assignments ra ON ra.user_id = u.id AND ra.status = 'active' JOIN roles r ON r.id = ra.role_id AND r.role_type = 'member_class' AND r.status = 'active' WHERE u.status = 'active' AND r.title = ? ORDER BY u.name");
      $stmt->execute([$class]);
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      break;
    }
    case 'all': {
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM users u JOIN members m ON m.user_id = u.id AND m.status = 'active' WHERE u.status = 'active' ORDER BY u.name");
      $stmt->execute();
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      break;
    }
    case 'event': {
      $eid = (int) $ref;
      $chk = db()->prepare('SELECT id FROM events WHERE id = ?');
      $chk->execute([$eid]);
      if (!$chk->fetch()) json_error('Event not found', 404);
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM event_registrations er JOIN users u ON u.id = er.user_id WHERE er.event_id = ? AND er.status IN ('registered','attended') AND u.status = 'active' ORDER BY u.name");
      $stmt->execute([$eid]);
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      if (!empty($opts['waitlist'])) {
        $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM event_waitlist w JOIN users u ON u.id = w.user_id WHERE w.event_id = ? AND u.status = 'active' ORDER BY w.created_at, w.id");
        $stmt->execute([$eid]);
        foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      }
      if (!empty($opts['guests'])) {
        try {
          $stmt = db()->prepare("SELECT name, email FROM event_guests WHERE event_id = ? AND status = 'registered' ORDER BY created_at");
          $stmt->execute([$eid]);
          foreach ($stmt->fetchAll() as $r) $add($r['email'], null, $r['name']);
        } catch (Exception $e) { /* guests table not imported yet */ }
      }
      break;
    }
    case 'project': {
      $pid = (int) $ref;
      $chk = db()->prepare('SELECT id FROM projects WHERE id = ?');
      $chk->execute([$pid]);
      if (!$chk->fetch()) json_error('Project not found', 404);
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM project_participants pp JOIN users u ON u.id = pp.user_id WHERE pp.project_id = ? AND pp.status = 'active' AND u.status = 'active' ORDER BY u.name");
      $stmt->execute([$pid]);
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      break;
    }
    case 'role': {
      $rid = (int) $ref;
      $chk = db()->prepare('SELECT id FROM roles WHERE id = ? AND status = "active"');
      $chk->execute([$rid]);
      if (!$chk->fetch()) json_error('Role not found', 404);
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM role_assignments ra JOIN users u ON u.id = ra.user_id WHERE ra.role_id = ? AND ra.status = 'active' AND u.status = 'active' ORDER BY u.name");
      $stmt->execute([$rid]);
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      break;
    }
    case 'group': {
      $gid = (int) $ref;
      $chk = db()->prepare('SELECT id FROM working_groups WHERE id = ?');
      $chk->execute([$gid]);
      if (!$chk->fetch()) json_error('Group not found', 404);
      $stmt = db()->prepare("SELECT u.id, u.name, u.email FROM working_group_members wgm JOIN users u ON u.id = wgm.user_id WHERE wgm.group_id = ? AND wgm.status = 'active' AND u.status = 'active' ORDER BY u.name");
      $stmt->execute([$gid]);
      foreach ($stmt->fetchAll() as $r) $add($r['email'], $r['id'], $r['name']);
      break;
    }
    default:
      json_error('Unknown audience type', 400);
  }
  return array_values($out);
}

function broadcast_label(string $type, $ref): string {
  try {
    switch ($type) {
      case 'member_class': return (string) $ref;
      case 'all': return 'All active members';
      case 'event': {
        $s = db()->prepare('SELECT title FROM events WHERE id = ?');
        $s->execute([(int) $ref]);
        return 'Event: ' . ($s->fetchColumn() ?: ('#' . (int) $ref));
      }
      case 'project': {
        $s = db()->prepare('SELECT title FROM projects WHERE id = ?');
        $s->execute([(int) $ref]);
        return 'Project: ' . ($s->fetchColumn() ?: ('#' . (int) $ref));
      }
      case 'role': {
        $s = db()->prepare('SELECT title FROM roles WHERE id = ?');
        $s->execute([(int) $ref]);
        return 'Role: ' . ($s->fetchColumn() ?: ('#' . (int) $ref));
      }
      case 'group': {
        $s = db()->prepare('SELECT name FROM working_groups WHERE id = ?');
        $s->execute([(int) $ref]);
        return 'Group: ' . ($s->fetchColumn() ?: ('#' . (int) $ref));
      }
    }
  } catch (Exception $e) { /* fall through */ }
  return $type . ' #' . (int) $ref;
}

// Event waitlist: promote the earliest waitlisted member when capacity frees up.
// Guest headcount for an event (0 when migration 039 not yet imported).
function event_guest_count(int $eventId): int {
  try {
    $stmt = db()->prepare("SELECT COUNT(*) FROM event_guests WHERE event_id = ? AND status = 'registered'");
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn();
  } catch (Exception $e) { return 0; }
}

// Total registered headcount: member RSVPs + guest signups.
function event_headcount(int $eventId): int {
  $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
  $stmt->execute([$eventId]);
  return (int) $stmt->fetchColumn() + event_guest_count($eventId);
}

function promote_from_waitlist(int $eventId): ?array {
  $stmt = db()->prepare('SELECT id, capacity, date FROM events WHERE id = ?');
  $stmt->execute([$eventId]);
  $event = $stmt->fetch();
  if (!$event || !$event['capacity'] || $event['date'] < date('Y-m-d H:i:s')) return null;

  $stmt = db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status = 'registered'");
  $stmt->execute([$eventId]);
  if (event_guest_count($eventId) + (int) $stmt->fetchColumn() >= (int) $event['capacity']) return null;

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
