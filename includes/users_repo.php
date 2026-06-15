<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function users_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $stmt->execute([':t' => 'users']);
    return (int)$stmt->fetchColumn() > 0;
}

function users_create_table(PDO $pdo): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($sql);
}

function users_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
    );
    $stmt->execute([':t' => 'users', ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function users_ensure_schema(PDO $pdo): void
{
    if (!users_table_exists($pdo)) {
        users_create_table($pdo);
        return;
    }

    // Backfill / upgrade older schema
    if (!users_column_exists($pdo, 'display_name')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NOT NULL DEFAULT '' AFTER username");
        $pdo->exec('UPDATE users SET display_name = username WHERE display_name = "" OR display_name IS NULL');
        $pdo->exec('ALTER TABLE users MODIFY display_name VARCHAR(100) NOT NULL');
    }
}

function users_list(PDO $pdo): array
{
    users_ensure_schema($pdo);
    $stmt = $pdo->query('SELECT id, username, display_name, created_at, updated_at FROM users ORDER BY id DESC');
    return $stmt->fetchAll();
}

function users_get(PDO $pdo, int $id): ?array
{
    users_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, username, display_name, created_at, updated_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function users_find_by_username(PDO $pdo, string $username): ?array
{
    users_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, username, display_name, password_hash FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function users_count(PDO $pdo): int
{
    users_ensure_schema($pdo);
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    return (int)$stmt->fetchColumn();
}

function users_username_taken(PDO $pdo, string $username, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM users WHERE username = :u';
    $params = [':u' => $username];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $ignoreId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() > 0;
}

function users_create(PDO $pdo, string $username, string $displayName, string $password): int
{
    users_ensure_schema($pdo);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Cannot hash password');
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password_hash) VALUES (:u, :d, :p)');
    $stmt->execute([
        ':u' => $username,
        ':d' => $displayName,
        ':p' => $hash,
    ]);

    return (int)$pdo->lastInsertId();
}

function users_update(PDO $pdo, int $id, string $username, string $displayName): void
{
    users_ensure_schema($pdo);
    $stmt = $pdo->prepare('UPDATE users SET username = :u, display_name = :d WHERE id = :id');
    $stmt->execute([
        ':u' => $username,
        ':d' => $displayName,
        ':id' => $id,
    ]);
}

function users_update_password(PDO $pdo, int $id, string $password): void
{
    users_ensure_schema($pdo);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Cannot hash password');
    }

    $stmt = $pdo->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
    $stmt->execute([
        ':p' => $hash,
        ':id' => $id,
    ]);
}

function users_delete(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
}
