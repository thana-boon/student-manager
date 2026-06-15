<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function app_log(string $action, array $meta = []): void
{
    try {
        // best-effort logging; never break the request
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $who = null;
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $who = [
                'username' => (string)($_SESSION['user']['username'] ?? ''),
                'display_name' => (string)($_SESSION['user']['display_name'] ?? ''),
            ];
        }

        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'who' => $who,
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'meta' => $meta,
        ];

        $dir = __DIR__ . '/../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/app.log';
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // ignore
    }
}
