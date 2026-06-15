<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/academic_years_repo.php';
require_once __DIR__ . '/includes/settings_repo.php';
require_once __DIR__ . '/includes/students_repo.php';
require_once __DIR__ . '/includes/logger.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

try {
    $pdoLocal = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');

    if ($localCurrentId === null) {
        throw new RuntimeException('ยังไม่ได้ตั้งปีการศึกษาปัจจุบัน');
    }

    $pdoSchool = db_pdo_school();
    academic_years_require_table($pdoSchool);
    $years = academic_years_list($pdoSchool);

    $currentYear = null;
    foreach ($years as $y) {
        if ((int)$y['id'] === (int)$localCurrentId) {
            $currentYear = $y;
            break;
        }
    }

    if ($currentYear === null) {
        throw new RuntimeException('ไม่พบปีการศึกษาที่ตั้งไว้');
    }

    $rows = students_export_rows($pdoSchool, (int)$currentYear['id'], (string)$currentYear['name']);

    $filename = 'students_' . preg_replace('/[^0-9a-zA-Z_-]+/', '_', (string)$currentYear['name']) . '.csv';

    app_log('student.export_csv', ['year_id' => (int)$currentYear['id'], 'rows' => count($rows)]);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Excel-friendly UTF-8 BOM
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ส่งออกได้');
    }

    // Always export these headers
    fputcsv($out, students_csv_headers());

    foreach ($rows as $r) {
        fputcsv($out, [
            (string)($r['id'] ?? ''),
            (string)($r['student_code'] ?? ''),
            (string)($r['roll_no'] ?? ''),
            (string)($r['class_room'] ?? ''),
            (string)($r['grade'] ?? ''),
            (string)($r['room'] ?? ''),
            (string)($r['full_name'] ?? ''),
            (string)($r['first_name'] ?? ''),
            (string)($r['last_name'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    $msg = rawurlencode($e->getMessage());
    header('Location: students.php?error=' . $msg);
    exit;
}
