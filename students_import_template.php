<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/students_repo.php';

require_login();

$filename = 'students_import_template.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'wb');
if ($out === false) {
    throw new RuntimeException('ไม่สามารถสร้างไฟล์ตัวอย่างได้');
}

$headers = students_form_headers();
fputcsv($out, $headers);

$seq = 0;
foreach (students_import_template_rows() as $row) {
    $seq++;
    $excelRow = $seq + 1; // แถวข้อมูลแรกอยู่ที่ Excel row 2

    $line = [];
    foreach ($headers as $header) {
        if ($header === 'ลำดับ') {
            $line[] = (string)$seq;
        } elseif ($header === 'Email') {
            $line[] = students_email_formula($excelRow);
        } elseif ($header === 'Password') {
            $line[] = students_password_formula($excelRow);
        } else {
            $line[] = (string)($row[$header] ?? '');
        }
    }
    fputcsv($out, $line);
}

fclose($out);
exit;