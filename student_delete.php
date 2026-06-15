<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/academic_years_repo.php';
require_once __DIR__ . '/includes/settings_repo.php';
require_once __DIR__ . '/includes/students_repo.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students.php');
    exit;
}

csrf_verify_or_die();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: students.php');
    exit;
}

try {
    $pdoLocal = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');

    $pdoSchool = db_pdo_school();
    academic_years_require_table($pdoSchool);
    $years = academic_years_list($pdoSchool);

    $currentYear = null;
    if ($localCurrentId !== null) {
        foreach ($years as $y) {
            if ((int)$y['id'] === (int)$localCurrentId) {
                $currentYear = $y;
                break;
            }
        }
    }

    $m = students_require_table($pdoSchool);
    $row = students_get($pdoSchool, $id);

    if (!$row) {
        header('Location: students.php');
        exit;
    }

    if ($currentYear !== null && !students_row_belongs_to_year($m, $row, (int)$currentYear['id'], (string)$currentYear['name'])) {
        throw new RuntimeException('ไม่อนุญาตให้ลบ: นักเรียนไม่ได้อยู่ในปีการศึกษาปัจจุบันของระบบนี้');
    }

    students_delete($pdoSchool, $m, $id);
    app_log('student.delete', ['id' => $id]);
} catch (Throwable $e) {
    // best-effort: store message in query string
    $msg = rawurlencode($e->getMessage());
    header('Location: students.php?error=' . $msg);
    exit;
}

header('Location: students.php');
exit;
