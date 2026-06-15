<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/users_repo.php';

function session_idle_timeout_seconds(): int
{
    // Auto-logout after inactivity (30 minutes)
    return 30 * 60;
}

function session_expire_if_inactive(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $timeout = session_idle_timeout_seconds();
    if ($timeout <= 0) {
        return;
    }

    $last = $_SESSION['last_activity'] ?? null;
    if (!is_int($last)) {
        return;
    }

    if (time() - $last <= $timeout) {
        return;
    }

    // Clear all session data and rotate the session id.
    // This effectively logs the user out after inactivity.
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // If idle too long, clear session.
    session_expire_if_inactive();

    // Update activity timestamp for sliding expiration.
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['last_activity'] = time();
    }
}

function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    try {
        $pdo = db_pdo();
        if (!users_table_exists($pdo)) {
            return false;
        }

        $u = users_find_by_username($pdo, $username);
        if (!$u || !isset($u['password_hash']) || !is_string($u['password_hash'])) {
            return false;
        }

        if (!password_verify($password, $u['password_hash'])) {
            return false;
        }

        start_session();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'username' => (string)$u['username'],
            'display_name' => (string)($u['display_name'] ?? $u['username']),
            'login_at' => time(),
        ];
        $_SESSION['last_activity'] = time();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function logout(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}
