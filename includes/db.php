<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function db_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = pdo_from_config(app_config()['db']);

    return $pdo;
}

function db_pdo_school(): PDO
{
    static $pdoSchool = null;
    if ($pdoSchool instanceof PDO) {
        return $pdoSchool;
    }

    $cfg = app_config()['db_school'] ?? null;
    if (!is_array($cfg)) {
        throw new RuntimeException('Missing db_school config');
    }

    $pdoSchool = pdo_from_config($cfg);
    return $pdoSchool;
}

function pdo_from_config(array $cfg): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string)$cfg['host'],
        (int)$cfg['port'],
        (string)$cfg['name'],
        (string)$cfg['charset']
    );

    return new PDO(
        $dsn,
        (string)$cfg['user'],
        (string)$cfg['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function db_health_check(): array
{
    try {
        $pdo = db_pdo();
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();

        $serverVersion = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return [
            'ok' => true,
            'message' => 'เชื่อมฐานข้อมูลได้แล้ว',
            'driver' => $driver,
            'serverVersion' => $serverVersion,
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => 'ยังเชื่อมต่อฐานข้อมูลไม่ได้',
            'error' => $e->getMessage(),
        ];
    }
}
