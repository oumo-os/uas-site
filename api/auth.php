<?php
// UAS Institutional Platform — Authentication
require_once __DIR__ . '/config.php';

function current_user_id(): ?int {
  return $_SESSION['user_id'] ?? null;
}

function current_user(): ?array {
  $id = current_user_id();
  if (!$id) return null;
  $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->fetch() ?: null;
}

function is_logged_in(): bool {
  return current_user_id() !== null;
}

function require_login(): array {
  $user = current_user();
  if (!$user) json_error('Authentication required', 401);
  if ($user['status'] !== 'active') json_error('Account is not active', 403);
  return $user;
}

function login(string $email, string $password): array {
  $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user || !password_verify($password, $user['password'])) {
    json_error('Invalid credentials', 401);
  }
  if ($user['status'] !== 'active') json_error('Account is not active', 403);
  $_SESSION['user_id'] = $user['id'];
  db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
  audit_log('login', 'user', $user['id']);
  return $user;
}

function register(string $name, string $email, string $password, array $extra = []): array {
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) json_error('Email already registered', 409);

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = db()->prepare(
    'INSERT INTO users (name, email, password, phone, bio, institution, location, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    $name, $email, $hash,
    $extra['phone'] ?? null,
    $extra['bio'] ?? null,
    $extra['institution'] ?? null,
    $extra['location'] ?? null,
    'pending'  // accounts require approval
  ]);
  $userId = (int) db()->lastInsertId();

  // Auto-create member record
  $memberNum = 'UAS-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
  db()->prepare(
    'INSERT INTO members (user_id, membership_number, category, status, joined_date)
     VALUES (?, ?, ?, ?, ?)'
  )->execute([$userId, $memberNum, $extra['category'] ?? 'regular', 'active', date('Y-m-d')]);

  $_SESSION['user_id'] = $userId;
  audit_log('register', 'user', $userId);
  return current_user();
}

function logout(): void {
  audit_log('logout', 'user', current_user_id());
  session_destroy();
}
