<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function csrf_start_session(): void
{
    start_session();
}

function csrf_token(): string
{
    csrf_start_session();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

function csrf_verify_or_die(): void
{
    csrf_start_session();

    $sent = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');

    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        echo 'Forbidden (CSRF)';
        exit;
    }
}
