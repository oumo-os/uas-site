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
  if ($user['status'] === 'pending') json_error('Account is awaiting admin approval', 403);
  if ($user['status'] === 'rejected') json_error('Membership application was not approved', 403);
  if ($user['status'] === 'suspended') json_error('Account has been suspended', 403);
  if ($user['status'] !== 'active') json_error('Account is not active', 403);
  return $user;
}

function public_user(array $user): array {
  unset($user['password']);
  return $user;
}

function login(string $email, string $password): array {
  $email = mb_strtolower(trim($email));
  $ipKey = 'login-ip:' . client_ip();
  $emailKey = 'login:' . $email;
  if (!rate_limit($ipKey, 'login', 10, 900) || !rate_limit($emailKey, 'login', 5, 900)) {
    json_error('Too many failed login attempts. Try again in 15 minutes.', 429);
  }
  $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if (!$user || !password_verify($password, $user['password'])) {
    json_error('Invalid email or password', 401);
  }
  if ($user['status'] === 'pending') json_error('Your account is awaiting admin approval. You will receive a notification once approved.', 403);
  if ($user['status'] === 'rejected') json_error('Your membership application was not approved. Please contact us for more information.', 403);
  if ($user['status'] === 'suspended') json_error('Your account has been suspended. Please contact an administrator.', 403);
  if ($user['status'] !== 'active') json_error('Account is not active', 403);
  $_SESSION['user_id'] = $user['id'];
  db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
  audit_log('login', 'user', $user['id']);
  return $user;
}

function register(string $name, string $email, string $password, array $extra = []): array {
  if (!rate_limit('register-ip:' . client_ip(), 'register', 5, 900)) {
    json_error('Too many registration attempts. Try again in 15 minutes.', 429);
  }
  $email = mb_strtolower(trim($email));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('A valid email address is required', 400);
  if (mb_strlen($name) < 2) json_error('Name must be at least 2 characters', 400);
  if (!password_is_strong($password)) json_error('Password must be at least 8 characters with at least one letter and one digit', 400);
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
  $stmt->execute([$email]);
  if ($stmt->fetch()) json_error('Email already registered', 409);

  $hash = password_hash($password, PASSWORD_DEFAULT);

  try {
    db()->beginTransaction();

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

    // Per-year sequence: next number after the current max membership number
    $year = date('Y');
    $stmt = db()->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(membership_number, -4) AS UNSIGNED)), 0) + 1 FROM members WHERE membership_number LIKE 'UAS-{$year}-%'");
    $stmt->execute();
    $memberNum = 'UAS-' . $year . '-' . str_pad($stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);

    // Auto-create member record (pending until admin approval)
    db()->prepare(
      'INSERT INTO members (user_id, membership_number, category, status, joined_date)
       VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $memberNum, $extra['category'] ?? 'regular', 'pending', date('Y-m-d')]);

    db()->commit();
  } catch (Exception $e) {
    db()->rollBack();
    json_error('Registration failed', 500);
  }

  audit_log('register', 'user', $userId);
  // Do NOT create a session — user must wait for admin approval
  return ['id' => $userId, 'status' => 'pending', 'membership_number' => $memberNum];
}

function logout(): void {
  audit_log('logout', 'user', current_user_id());
  session_destroy();
}
