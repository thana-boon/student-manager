<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/users_repo.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

csrf_verify_or_die();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: users.php');
    exit;
}

try {
    $pdo = db_pdo();
    if (users_table_exists($pdo)) {
        users_delete($pdo, $id);
    }
} catch (Throwable $e) {
    // keep it simple: redirect back
}

header('Location: users.php');
exit;
