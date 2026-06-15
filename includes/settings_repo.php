<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function settings_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            k VARCHAR(100) NOT NULL,
            v TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (k)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
    );
}

function settings_get(PDO $pdo, string $key): ?string
{
    settings_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT v FROM app_settings WHERE k = :k');
    $stmt->execute([':k' => $key]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string)$v;
}

function settings_set(PDO $pdo, string $key, string $value): void
{
    settings_ensure_table($pdo);
    $stmt = $pdo->prepare('INSERT INTO app_settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

function settings_get_int(PDO $pdo, string $key): ?int
{
    $v = settings_get($pdo, $key);
    if ($v === null || $v === '') {
        return null;
    }
    if (!preg_match('/^-?\d+$/', $v)) {
        return null;
    }
    return (int)$v;
}

function settings_set_int(PDO $pdo, string $key, int $value): void
{
    settings_set($pdo, $key, (string)$value);
}
