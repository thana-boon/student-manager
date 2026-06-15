<?php

declare(strict_types=1);

// โหลดค่าจาก .env (ถ้ามี) แบบเบาๆ ไม่ต้องพึ่ง composer
(function (): void {
    $path = __DIR__ . '/.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // ตัด quote ครอบค่าออก ถ้ามี
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
})();

function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    return $val === false ? $default : $val;
}

return [
    // Database (XAMPP / MariaDB / MySQL)
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int)env('DB_PORT', '3306'),
        // IMPORTANT: ใช้ฐานข้อมูลนี้เท่านั้น เพื่อไม่ไปยุ่งกับ school_app
        'name' => env('DB_NAME', 'student_manager'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],

    // Existing school database (ใช้สำหรับ academic_year และ student)
    'db_school' => [
        'host' => env('DB_SCHOOL_HOST', '127.0.0.1'),
        'port' => (int)env('DB_SCHOOL_PORT', '3306'),
        'name' => env('DB_SCHOOL_NAME', 'school_app'),
        'user' => env('DB_SCHOOL_USER', 'root'),
        'pass' => env('DB_SCHOOL_PASS', ''),
        'charset' => env('DB_SCHOOL_CHARSET', 'utf8mb4'),
    ],

    'app' => [
        'name' => env('APP_NAME', 'Student Manager'),
        'timezone' => env('APP_TIMEZONE', 'Asia/Bangkok'),
    ],

    // Optional overrides for school schema
    'school' => [
        // หากตารางปีการศึกษาใน school_app ชื่อไม่ใช่ academic_years ให้แก้ตรงนี้
        'academic_years_table' => env('SCHOOL_ACADEMIC_YEARS_TABLE', 'academic_years'),

        // หากตารางนักเรียนใน school_app ชื่อไม่ใช่ students ให้แก้ตรงนี้
        'students_table' => env('SCHOOL_STUDENTS_TABLE', 'students'),
    ],
];
