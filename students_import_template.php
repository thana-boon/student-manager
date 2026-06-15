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

$headers = students_import_display_headers();
fputcsv($out, $headers);

foreach (students_import_template_rows() as $row) {
    $line = [];
    foreach ($headers as $header) {
        $line[] = (string)($row[$header] ?? '');
    }
    fputcsv($out, $line);
}

fclose($out);
exit;