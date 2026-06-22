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

    $rows = students_export_form_rows($pdoSchool, (int)$currentYear['id'], (string)$currentYear['name']);

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

    // หัวคอลัมน์ตามฟอร์ม A–O (Email/Password เป็นสูตร)
    fputcsv($out, students_form_headers());

    $seq = 0;
    foreach ($rows as $r) {
        $seq++;
        $excelRow = $seq + 1; // แถวข้อมูลแรกอยู่ที่ Excel row 2

        fputcsv($out, [
            (string)$seq,                                  // A ลำดับ
            (string)($r['grade'] ?? ''),                   // B ชั้น
            (string)($r['room'] ?? ''),                    // C ห้อง
            (string)($r['roll_no'] ?? ''),                 // D เลขที่
            (string)($r['status'] ?? ''),                  // E สถานะ
            students_text_formula((string)($r['citizen_id'] ?? '')),    // F รหัสบัตรประชาชน (บังคับเป็นข้อความ)
            students_text_formula((string)($r['student_code'] ?? '')),  // G รหัสนักศึกษา (บังคับเป็นข้อความ)
            (string)($r['gender'] ?? ''),                  // H เพศ
            (string)($r['title_prefix'] ?? ''),            // I คำนำหน้า
            (string)($r['first_name'] ?? ''),              // J ชื่อ
            (string)($r['last_name'] ?? ''),               // K นามสกุล
            (string)($r['nickname'] ?? ''),                // L ชื่อเล่น
            (string)($r['birth_date'] ?? ''),              // M วัน/เดือน/ปีเกิด
            students_email_formula($excelRow),             // N Email (สูตร)
            students_password_formula($excelRow),          // O Password (สูตร)
        ]);
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    $msg = rawurlencode($e->getMessage());
    header('Location: students.php?error=' . $msg);
    exit;
}
