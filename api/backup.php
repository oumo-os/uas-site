<?php
// UAS Backup utilities — pure-PHP dump (no mysqldump dependency).
// Backups are gzip-compressed SQL files in data/backups/.

define('BACKUP_DIR', __DIR__ . '/../data/backups/');
define('BACKUP_KEEP', 20);

function backup_dir(): string {
  if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
  return BACKUP_DIR;
}

// Only allow safe backup filenames (blocks path traversal).
function backup_filename_ok(string $name): bool {
  return (bool) preg_match('/^[A-Za-z0-9._-]+\.sql(\.gz)?$/', $name);
}

function backup_path(string $name): string {
  return backup_dir() . $name;
}

function backup_list(): array {
  $out = [];
  foreach (glob(backup_dir() . '*.sql.gz') ?: [] as $f) {
    $out[] = [
      'file' => basename($f),
      'size' => filesize($f),
      'created_at' => date('Y-m-d H:i:s', filemtime($f)),
    ];
  }
  usort($out, fn($a, $b) => strcmp($b['file'], $a['file']));
  return $out;
}

// Dump the whole database to a gzipped SQL file. Returns the filename.
function backup_create(): string {
  $pdo = db();
  $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

  $sql = "-- UAS Institutional Platform backup\n";
  $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
  $sql .= "-- Tables: " . implode(', ', $tables) . "\n\n";
  $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

  foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $sql .= $row[1] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `{$table}`");
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
      $cols = [];
      foreach ($row as $v) {
        if ($v === null) { $cols[] = 'NULL'; }
        else { $cols[] = $pdo->quote((string) $v); }
      }
      $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $cols) . ");\n";
    }
    $sql .= "\n";
  }

  $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

  // Trim old backups first
  $existing = array_map('basename', glob(backup_dir() . '*.sql.gz') ?: []);
  rsort($existing);
  foreach (array_slice($existing, BACKUP_KEEP - 1) as $old) {
    @unlink(backup_dir() . $old);
  }

  $name = 'uas_platform-' . date('Ymd-His') . '.sql.gz';
  file_put_contents(backup_path($name), gzencode($sql, 6));
  return $name;
}

function backup_delete(string $name): void {
  @unlink(backup_path($name));
}
