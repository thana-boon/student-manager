<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function students_table_name(): string
{
    $cfg = app_config();
    $t = (string)($cfg['school']['students_table'] ?? 'students');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
        throw new RuntimeException('Invalid students table name');
    }
    return $t;
}

function students_sql_ident(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new RuntimeException('Invalid SQL identifier');
    }
    return '`' . $name . '`';
}

function students_detect_columns(PDO $pdo): array
{
    $table = students_table_name();

    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmt->execute([':t' => $table]);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch data types (helps interpret ambiguous columns like class_room)
    $stmtTypes = $pdo->prepare(
        'SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmtTypes->execute([':t' => $table]);
    $typeRows = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
    $types = [];
    foreach ($typeRows as $r) {
        $cn = (string)($r['column_name'] ?? '');
        if ($cn === '') {
            continue;
        }
        $types[$cn] = strtolower((string)($r['data_type'] ?? ''));
    }

    if (!$cols || count($cols) === 0) {
        throw new RuntimeException('ไม่พบตาราง students ใน school_app (ตรวจสอบชื่อ table ใน config.php)');
    }

    $cols = array_map('strval', $cols);
    $has = array_flip($cols);

    $pick = function (array $candidates) use ($has): ?string {
        foreach ($candidates as $c) {
            if (isset($has[$c])) {
                return $c;
            }
        }
        return null;
    };

    $idCol = $pick(['id', 'student_id']);

    // student identifier/code (used for upsert on import)
    $codeCol = $pick(['student_code', 'code', 'studentcode', 'student_number', 'student_no']);

    // roll number / seat number in class
    $rollCol = $pick(['roll_no', 'rollnumber', 'seat_no', 'number_in_room', 'no', 'number', 'student_no']);
    if ($rollCol !== null && $codeCol !== null && $rollCol === $codeCol) {
        $rollCol = null;
    }

    $fullNameCol = $pick(['full_name', 'student_name', 'name', 'fullname']);
    $firstNameCol = $pick(['first_name', 'firstname', 'fname']);
    $lastNameCol = $pick(['last_name', 'lastname', 'lname', 'surname']);

    // class / room (schema varies a lot)
    $classRoomCol = $pick(['class_room', 'classroom', 'homeroom', 'class_room_name']);
    $gradeCol = $pick(['grade', 'level', 'class_level', 'class', 'class_name']);
    $roomCol = $pick(['room', 'room_no', 'section', 'room_name']);

    // If grade is a separate column and class_room is numeric, treat it as room.
    // This matches common schemas like: class_level (grade) + class_room (room) + number_in_room (roll).
    if ($gradeCol !== null && $roomCol === null && $classRoomCol !== null) {
        $tcr = $types[$classRoomCol] ?? '';
        if (in_array($tcr, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric'], true)) {
            $roomCol = $classRoomCol;
            $classRoomCol = null;
        }
    }

    $yearIdCol = $pick(['academic_year_id', 'year_id', 'ay_id']);
    $yearNameCol = $pick(['academic_year', 'academic_year_name', 'year']);

    $updatedAt = $pick(['updated_at', 'update_at', 'modified_at', 'updated']);
    $createdAt = $pick(['created_at', 'create_at', 'created']);

    if ($idCol === null) {
        throw new RuntimeException('โครงสร้างตาราง students ไม่รองรับ (ต้องมีคอลัมน์ id หรือ student_id)');
    }

    if ($yearIdCol === null && $yearNameCol === null) {
        throw new RuntimeException('ตาราง students ไม่พบคอลัมน์อ้างอิงปีการศึกษา (ต้องมี academic_year_id หรือ year/academic_year)');
    }

    if ($fullNameCol === null && ($firstNameCol === null || $lastNameCol === null)) {
        throw new RuntimeException('ตาราง students ไม่พบคอลัมน์ชื่อ (ต้องมี full_name/name หรือมีทั้ง first_name และ last_name)');
    }

    return [
        'table' => $table,
        'id' => $idCol,
        'student_code' => $codeCol,
        'roll_no' => $rollCol,
        'full_name' => $fullNameCol,
        'first_name' => $firstNameCol,
        'last_name' => $lastNameCol,

        'class_room' => $classRoomCol,
        'grade' => $gradeCol,
        'room' => $roomCol,

        'academic_year_id' => $yearIdCol,
        'academic_year_name' => $yearNameCol,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'columns' => $cols,
    ];
}

function students_columns_meta(PDO $pdo): array
{
    $table = students_table_name();

    $stmt = $pdo->prepare(
        'SELECT column_name, data_type, column_type, is_nullable, column_default, column_key, extra, character_maximum_length, numeric_precision, numeric_scale '
        . 'FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = :t '
        . 'ORDER BY ordinal_position ASC'
    );
    $stmt->execute([':t' => $table]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $name = (string)($r['column_name'] ?? '');
        if ($name === '') {
            continue;
        }

        $out[$name] = [
            'name' => $name,
            'data_type' => strtolower((string)($r['data_type'] ?? '')),
            'column_type' => strtolower((string)($r['column_type'] ?? '')),
            'is_nullable' => strtoupper((string)($r['is_nullable'] ?? '')) === 'YES',
            'default' => $r['column_default'] ?? null,
            'key' => (string)($r['column_key'] ?? ''),
            'extra' => strtolower((string)($r['extra'] ?? '')),
            'char_max_len' => isset($r['character_maximum_length']) ? (int)$r['character_maximum_length'] : null,
            'numeric_precision' => isset($r['numeric_precision']) ? (int)$r['numeric_precision'] : null,
            'numeric_scale' => isset($r['numeric_scale']) ? (int)$r['numeric_scale'] : null,
        ];
    }

    return $out;
}

function students_is_system_column(array $m, string $col): bool
{
    $col = (string)$col;
    if ($col === '') {
        return true;
    }

    $system = [
        (string)($m['id'] ?? ''),
        (string)($m['academic_year_id'] ?? ''),
        (string)($m['academic_year_name'] ?? ''),
        (string)($m['created_at'] ?? ''),
        (string)($m['updated_at'] ?? ''),
    ];
    foreach ($system as $s) {
        if ($s !== '' && $s === $col) {
            return true;
        }
    }
    return false;
}

function students_is_mapped_column(array $m, string $col): bool
{
    $mapped = [
        (string)($m['student_code'] ?? ''),
        (string)($m['roll_no'] ?? ''),
        (string)($m['class_room'] ?? ''),
        (string)($m['grade'] ?? ''),
        (string)($m['room'] ?? ''),
        (string)($m['full_name'] ?? ''),
        (string)($m['first_name'] ?? ''),
        (string)($m['last_name'] ?? ''),
    ];
    foreach ($mapped as $s) {
        if ($s !== '' && $s === $col) {
            return true;
        }
    }
    return false;
}

function students_prepare_value_for_column(string $raw, array $meta)
{
    $raw = (string)$raw;
    $v = trim($raw);

    $type = (string)($meta['data_type'] ?? '');
    $nullable = (bool)($meta['is_nullable'] ?? false);

    if ($v === '') {
        if ($nullable) {
            return null;
        }

        // For numeric/date-like columns, prefer NULL-ish semantics even if not nullable
        // (DB will reject if truly NOT NULL; this keeps consistent behavior for "clearing" values).
        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double', 'date', 'datetime', 'timestamp', 'time', 'year'], true)) {
            return null;
        }

        return '';
    }

    if (in_array($type, ['datetime', 'timestamp'], true)) {
        // Convert HTML datetime-local format to MySQL format
        $v2 = str_replace('T', ' ', $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $v2)) {
            $v2 .= ':00';
        }
        return $v2;
    }

    return $v;
}

function students_apply_extra_columns_for_insert(PDO $pdo, array $m, array $data, array &$cols, array &$vals, array &$params): void
{
    $extra = $data['extra'] ?? null;
    if (!is_array($extra) || count($extra) === 0) {
        return;
    }

    $metaByName = students_columns_meta($pdo);
    $allowed = array_flip(array_map('strval', (array)($m['columns'] ?? [])));

    $i = 0;
    foreach ($extra as $col => $raw) {
        $col = (string)$col;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
            continue;
        }
        if (!isset($allowed[$col])) {
            continue;
        }
        if (students_is_system_column($m, $col)) {
            continue;
        }
        if (students_is_mapped_column($m, $col)) {
            continue;
        }
        if (!isset($metaByName[$col])) {
            continue;
        }

        $param = ':x' . $i;
        $i++;

        $cols[] = students_sql_ident($col);
        $vals[] = $param;
        $params[$param] = students_prepare_value_for_column((string)$raw, $metaByName[$col]);
    }
}

function students_apply_extra_columns_for_update(PDO $pdo, array $m, array $data, array &$sets, array &$params): void
{
    $extra = $data['extra'] ?? null;
    if (!is_array($extra) || count($extra) === 0) {
        return;
    }

    $metaByName = students_columns_meta($pdo);
    $allowed = array_flip(array_map('strval', (array)($m['columns'] ?? [])));

    $i = 0;
    foreach ($extra as $col => $raw) {
        $col = (string)$col;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
            continue;
        }
        if (!isset($allowed[$col])) {
            continue;
        }
        if (students_is_system_column($m, $col)) {
            continue;
        }
        if (students_is_mapped_column($m, $col)) {
            continue;
        }
        if (!isset($metaByName[$col])) {
            continue;
        }

        $param = ':ex' . $i;
        $i++;

        $sets[] = students_sql_ident($col) . ' = ' . $param;
        $params[$param] = students_prepare_value_for_column((string)$raw, $metaByName[$col]);
    }
}

function students_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmt->execute([':t' => students_table_name()]);
    return (int)$stmt->fetchColumn() > 0;
}

function students_column_exists(PDO $pdo, string $column): bool
{
    $table = students_table_name();
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function students_add_column_if_missing(PDO $pdo, string $column, string $definitionSql): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        throw new RuntimeException('Invalid column name');
    }
    if (students_column_exists($pdo, $column)) {
        return false;
    }

    $t = students_sql_ident(students_table_name());
    $c = students_sql_ident($column);

    $pdo->exec('ALTER TABLE ' . $t . ' ADD COLUMN ' . $c . ' ' . $definitionSql);
    return true;
}

function students_ensure_optional_columns(PDO $pdo): void
{
    // User-requested optional fields.
    // Safe: only adds if missing; columns are nullable.
    students_add_column_if_missing($pdo, 'citizen_id', 'VARCHAR(13) NULL');
    students_add_column_if_missing($pdo, 'birth_date', 'DATE NULL');
}

function students_require_table(PDO $pdo): array
{
    if (!students_table_exists($pdo)) {
        throw new RuntimeException('ไม่พบตาราง students ในฐานข้อมูล school_app (ตรวจสอบชื่อ table ใน config.php)');
    }

    // Ensure optional fields exist before detecting columns/mapping.
    students_ensure_optional_columns($pdo);

    return students_detect_columns($pdo);
}

function students_year_where(array $m, int $yearId, string $yearName): array
{
    if ($m['academic_year_id']) {
        $col = students_sql_ident((string)$m['academic_year_id']);
        return ["$col = :yid", [':yid' => $yearId]];
    }

    $col = students_sql_ident((string)$m['academic_year_name']);
    return ["$col = :yname", [':yname' => $yearName]];
}

function students_display_name_from_alias_row(array $row): string
{
    $full = trim((string)($row['full_name'] ?? ''));
    if ($full !== '') {
        return $full;
    }

    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    return trim(trim($first) . ' ' . trim($last));
}

function students_class_room_from_alias_row(array $row): string
{
    $cr = trim((string)($row['class_room'] ?? ''));
    if ($cr !== '') {
        return $cr;
    }

    $grade = trim((string)($row['grade'] ?? ''));
    $room = trim((string)($row['room'] ?? ''));
    if ($grade !== '' && $room !== '') {
        return $grade . '/' . $room;
    }
    if ($grade !== '') {
        return $grade;
    }
    if ($room !== '') {
        return $room;
    }
    return '';
}

function students_grade_room_from_alias_row(array $row): array
{
    $parse = function (string $v): array {
        $v = trim($v);
        if ($v === '') {
            return ['', ''];
        }

        // normalize common separators
        $v2 = str_replace(['\\', '|'], '/', $v);

        // e.g. "ม.3/1", "ม.3-1", "ป.6/2"
        if (preg_match('/^(.+?)[\\/\-](\d{1,3})\s*$/u', $v2, $m)) {
            return [trim((string)$m[1]), trim((string)$m[2])];
        }

        // e.g. "ม.3ห้อง1"
        if (preg_match('/^(.+?)\s*ห้อง\s*(\d{1,3})\s*$/u', $v, $m)) {
            return [trim((string)$m[1]), trim((string)$m[2])];
        }

        // numeric-only often means room number
        if (preg_match('/^\d{1,3}$/', $v)) {
            return ['', $v];
        }

        return [$v, ''];
    };

    $grade = trim((string)($row['grade'] ?? ''));
    $room = trim((string)($row['room'] ?? ''));
    $cr = trim((string)($row['class_room'] ?? ''));

    if ($grade === '' && $room === '') {
        if ($cr === '') {
            return ['', ''];
        }
        return $parse($cr);
    }

    // If grade exists but room is empty, many schemas store room in class_room.
    if ($room === '' && $cr !== '') {
        [$g2, $r2] = $parse($cr);
        if ($r2 !== '' && ($g2 === '' || $grade === '' || trim($g2) === trim($grade))) {
            $room = $r2;
            if ($grade === '' && $g2 !== '') {
                $grade = $g2;
            }
        }
    }

    // Sometimes room column contains combined "grade/room".
    if ($room !== '' && $grade === '') {
        [$g2, $r2] = $parse($room);
        if ($g2 !== '' && $r2 !== '') {
            $grade = $g2;
            $room = $r2;
        }
    } elseif ($room !== '' && $grade !== '') {
        [$g2, $r2] = $parse($room);
        if ($r2 !== '' && ($g2 === '' || trim($g2) === trim($grade))) {
            $room = $r2;
        }
    }

    return [$grade, $room];
}

function students_first_last_from_alias_row(array $row): array
{
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));

    if ($first !== '' || $last !== '') {
        return [$first, $last];
    }

    $full = trim((string)($row['full_name'] ?? ''));
    if ($full === '') {
        return ['', ''];
    }

    // Best-effort split: first token as first name, remaining as last name
    $parts = preg_split('/\s+/u', $full) ?: [];
    if (count($parts) <= 1) {
        return [$full, ''];
    }

    $first = (string)array_shift($parts);
    $last = trim(implode(' ', $parts));
    return [trim($first), $last];
}

function students_parse_int_value($v): ?int
{
    $s = trim((string)$v);
    if ($s === '') {
        return null;
    }
    if (preg_match('/(\d+)/u', $s, $m)) {
        return (int)$m[1];
    }
    return null;
}

/**
 * Returns a tuple used for sorting grades.
 * Order: อ. (อนุบาล) < ป. (ประถม) < ม. (มัธยม) < other, then by numeric level.
 */
function students_grade_sort_key($grade): array
{
    $g = trim((string)$grade);
    if ($g === '') {
        return [99, 999, ''];
    }

    $group = 3;
    if (preg_match('/อนุบาล/u', $g) || preg_match('/^อ\.?/u', $g)) {
        $group = 0;
    } elseif (preg_match('/ประถม/u', $g) || preg_match('/^ป\.?/u', $g)) {
        $group = 1;
    } elseif (preg_match('/มัธยม/u', $g) || preg_match('/^ม\.?/u', $g)) {
        $group = 2;
    }

    $level = students_parse_int_value($g);
    return [$group, $level === null ? 999 : $level, $g];
}

function students_list_by_year(PDO $pdo, int $yearId, string $yearName): array
{
    $m = students_require_table($pdo);
    [$where, $params] = students_year_where($m, $yearId, $yearName);

    $t = students_sql_ident((string)$m['table']);
    $id = students_sql_ident((string)$m['id']);

    $selectParts = ["$id AS id"];

    if ($m['student_code']) {
        $code = students_sql_ident((string)$m['student_code']);
        $selectParts[] = "$code AS student_code";
    } else {
        $selectParts[] = "NULL AS student_code";
    }

    if ($m['roll_no']) {
        $c = students_sql_ident((string)$m['roll_no']);
        $selectParts[] = "$c AS roll_no";
    } else {
        $selectParts[] = "NULL AS roll_no";
    }

    if ($m['class_room']) {
        $c = students_sql_ident((string)$m['class_room']);
        $selectParts[] = "$c AS class_room";
    } else {
        $selectParts[] = "NULL AS class_room";
    }

    if ($m['grade']) {
        $c = students_sql_ident((string)$m['grade']);
        $selectParts[] = "$c AS grade";
    } else {
        $selectParts[] = "NULL AS grade";
    }

    if ($m['room']) {
        $c = students_sql_ident((string)$m['room']);
        $selectParts[] = "$c AS room";
    } else {
        $selectParts[] = "NULL AS room";
    }

    if ($m['full_name']) {
        $c = students_sql_ident((string)$m['full_name']);
        $selectParts[] = "$c AS full_name";
    }
    if ($m['first_name']) {
        $c = students_sql_ident((string)$m['first_name']);
        $selectParts[] = "$c AS first_name";
    }
    if ($m['last_name']) {
        $c = students_sql_ident((string)$m['last_name']);
        $selectParts[] = "$c AS last_name";
    }

    if ($m['updated_at']) {
        $c = students_sql_ident((string)$m['updated_at']);
        $selectParts[] = "$c AS updated_at";
    } else {
        $selectParts[] = "NULL AS updated_at";
    }

    // include year cols for validation
    if ($m['academic_year_id']) {
        $c = students_sql_ident((string)$m['academic_year_id']);
        $selectParts[] = "$c AS academic_year_id";
    }
    if ($m['academic_year_name']) {
        $c = students_sql_ident((string)$m['academic_year_name']);
        $selectParts[] = "$c AS academic_year_name";
    }

    $select = implode(', ', $selectParts);
    $sql = "SELECT $select FROM $t WHERE $where";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        [$grade, $room] = students_grade_room_from_alias_row($r);
        [$firstName, $lastName] = students_first_last_from_alias_row($r);

        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'student_code' => $r['student_code'] ?? null,
            'roll_no' => $r['roll_no'] ?? null,
            'class_room' => ($cr = students_class_room_from_alias_row($r)) === '' ? null : $cr,
            'grade' => $grade === '' ? null : $grade,
            'room' => $room === '' ? null : $room,
            'first_name' => $firstName === '' ? null : $firstName,
            'last_name' => $lastName === '' ? null : $lastName,
            'display_name' => students_display_name_from_alias_row($r),
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }

    // Sort: grade (อ./ป./ม.) -> room -> roll number
    usort($out, function (array $a, array $b): int {
        $ka = students_grade_sort_key($a['grade'] ?? '');
        $kb = students_grade_sort_key($b['grade'] ?? '');
        if ($ka[0] !== $kb[0]) {
            return $ka[0] <=> $kb[0];
        }
        if ($ka[1] !== $kb[1]) {
            return $ka[1] <=> $kb[1];
        }
        if ($ka[2] !== $kb[2]) {
            return strcmp((string)$ka[2], (string)$kb[2]);
        }

        $ra = students_parse_int_value($a['room'] ?? '');
        $rb = students_parse_int_value($b['room'] ?? '');
        $ra = $ra === null ? PHP_INT_MAX : $ra;
        $rb = $rb === null ? PHP_INT_MAX : $rb;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        $na = trim((string)($a['room'] ?? ''));
        $nb = trim((string)($b['room'] ?? ''));
        if ($na !== $nb) {
            return strcmp($na, $nb);
        }

        $xa = students_parse_int_value($a['roll_no'] ?? '');
        $xb = students_parse_int_value($b['roll_no'] ?? '');
        $xa = $xa === null ? PHP_INT_MAX : $xa;
        $xb = $xb === null ? PHP_INT_MAX : $xb;
        if ($xa !== $xb) {
            return $xa <=> $xb;
        }

        $la = trim((string)($a['last_name'] ?? ''));
        $lb = trim((string)($b['last_name'] ?? ''));
        if ($la !== $lb) {
            return strcmp($la, $lb);
        }
        $fa = trim((string)($a['first_name'] ?? ''));
        $fb = trim((string)($b['first_name'] ?? ''));
        if ($fa !== $fb) {
            return strcmp($fa, $fb);
        }

        $ca = trim((string)($a['student_code'] ?? ''));
        $cb = trim((string)($b['student_code'] ?? ''));
        if ($ca !== $cb) {
            return strcmp($ca, $cb);
        }

        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    });

    return $out;
}

function students_get(PDO $pdo, int $id): ?array
{
    $m = students_require_table($pdo);

    $t = students_sql_ident((string)$m['table']);
    $idCol = students_sql_ident((string)$m['id']);

    $stmt = $pdo->prepare("SELECT * FROM $t WHERE $idCol = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function students_row_belongs_to_year(array $m, array $row, int $yearId, string $yearName): bool
{
    if ($m['academic_year_id']) {
        $v = (int)($row[$m['academic_year_id']] ?? 0);
        return $v === $yearId;
    }

    $v = (string)($row[$m['academic_year_name']] ?? '');
    return trim($v) === trim($yearName);
}

function students_find_by_code(PDO $pdo, int $yearId, string $yearName, string $studentCode): ?array
{
    $m = students_require_table($pdo);
    if (!$m['student_code']) {
        return null;
    }

    [$whereYear, $paramsYear] = students_year_where($m, $yearId, $yearName);

    $t = students_sql_ident((string)$m['table']);
    $codeCol = students_sql_ident((string)$m['student_code']);

    $stmt = $pdo->prepare("SELECT * FROM $t WHERE $whereYear AND $codeCol = :code LIMIT 1");
    $params = $paramsYear;
    $params[':code'] = $studentCode;
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function students_create(PDO $pdo, array $m, int $yearId, string $yearName, array $data): int
{
    $t = students_sql_ident((string)$m['table']);

    $cols = [];
    $vals = [];
    $params = [];

    // year
    if ($m['academic_year_id']) {
        $cols[] = students_sql_ident((string)$m['academic_year_id']);
        $vals[] = ':yid';
        $params[':yid'] = $yearId;
    } else {
        $cols[] = students_sql_ident((string)$m['academic_year_name']);
        $vals[] = ':yname';
        $params[':yname'] = $yearName;
    }

    // code
    if ($m['student_code']) {
        $code = trim((string)($data['student_code'] ?? ''));
        $cols[] = students_sql_ident((string)$m['student_code']);
        $vals[] = ':code';
        $params[':code'] = $code === '' ? null : $code;
    }

    // roll
    if ($m['roll_no']) {
        $roll = trim((string)($data['roll_no'] ?? ''));
        $cols[] = students_sql_ident((string)$m['roll_no']);
        $vals[] = ':roll';
        $params[':roll'] = $roll === '' ? null : $roll;
    }

    // class/room
    $classRoom = trim((string)($data['class_room'] ?? ''));
    $grade = trim((string)($data['grade'] ?? ''));
    $room = trim((string)($data['room'] ?? ''));

    if ($m['class_room']) {
        if ($classRoom === '' && ($grade !== '' || $room !== '')) {
            $classRoom = ($grade !== '' && $room !== '') ? ($grade . '/' . $room) : ($grade !== '' ? $grade : $room);
        }
        $cols[] = students_sql_ident((string)$m['class_room']);
        $vals[] = ':class_room';
        $params[':class_room'] = $classRoom === '' ? null : $classRoom;
    } else {
        if ($classRoom !== '' && ($grade === '' && $room === '') && (strpos($classRoom, '/') !== false)) {
            [$g, $r] = array_pad(explode('/', $classRoom, 2), 2, '');
            $grade = trim((string)$g);
            $room = trim((string)$r);
        }

        if ($m['grade']) {
            $cols[] = students_sql_ident((string)$m['grade']);
            $vals[] = ':grade';
            $params[':grade'] = $grade === '' ? null : $grade;
        }
        if ($m['room']) {
            $cols[] = students_sql_ident((string)$m['room']);
            $vals[] = ':room';
            $params[':room'] = $room === '' ? null : $room;
        }
    }

    // name
    if ($m['full_name']) {
        $full = trim((string)($data['full_name'] ?? ''));
        $cols[] = students_sql_ident((string)$m['full_name']);
        $vals[] = ':full';
        $params[':full'] = $full;
    } else {
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        if ($m['first_name']) {
            $cols[] = students_sql_ident((string)$m['first_name']);
            $vals[] = ':first';
            $params[':first'] = $first;
        }
        if ($m['last_name']) {
            $cols[] = students_sql_ident((string)$m['last_name']);
            $vals[] = ':last';
            $params[':last'] = $last;
        }
    }

    // extra columns (all other columns not in the core mapping)
    students_apply_extra_columns_for_insert($pdo, $m, $data, $cols, $vals, $params);

    $sql = 'INSERT INTO ' . $t . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function students_update(PDO $pdo, array $m, int $id, array $data): void
{
    $t = students_sql_ident((string)$m['table']);
    $idCol = students_sql_ident((string)$m['id']);

    $sets = [];
    $params = [':id' => $id];

    if ($m['student_code'] && array_key_exists('student_code', $data)) {
        $sets[] = students_sql_ident((string)$m['student_code']) . ' = :code';
        $code = trim((string)$data['student_code']);
        $params[':code'] = $code === '' ? null : $code;
    }

    if ($m['roll_no'] && array_key_exists('roll_no', $data)) {
        $sets[] = students_sql_ident((string)$m['roll_no']) . ' = :roll';
        $roll = trim((string)$data['roll_no']);
        $params[':roll'] = $roll === '' ? null : $roll;
    }

    if ($m['class_room'] && array_key_exists('class_room', $data)) {
        $sets[] = students_sql_ident((string)$m['class_room']) . ' = :class_room';
        $classRoom = trim((string)$data['class_room']);
        $params[':class_room'] = $classRoom === '' ? null : $classRoom;
    }

    if (!$m['class_room']) {
        if ($m['grade'] && array_key_exists('grade', $data)) {
            $sets[] = students_sql_ident((string)$m['grade']) . ' = :grade';
            $grade = trim((string)$data['grade']);
            $params[':grade'] = $grade === '' ? null : $grade;
        }
        if ($m['room'] && array_key_exists('room', $data)) {
            $sets[] = students_sql_ident((string)$m['room']) . ' = :room';
            $room = trim((string)$data['room']);
            $params[':room'] = $room === '' ? null : $room;
        }
    }

    if ($m['full_name'] && array_key_exists('full_name', $data)) {
        $sets[] = students_sql_ident((string)$m['full_name']) . ' = :full';
        $params[':full'] = trim((string)$data['full_name']);
    }

    if (!$m['full_name']) {
        if ($m['first_name'] && array_key_exists('first_name', $data)) {
            $sets[] = students_sql_ident((string)$m['first_name']) . ' = :first';
            $params[':first'] = trim((string)$data['first_name']);
        }
        if ($m['last_name'] && array_key_exists('last_name', $data)) {
            $sets[] = students_sql_ident((string)$m['last_name']) . ' = :last';
            $params[':last'] = trim((string)$data['last_name']);
        }
    }

    // extra columns (all other columns not in the core mapping)
    students_apply_extra_columns_for_update($pdo, $m, $data, $sets, $params);

    if (count($sets) === 0) {
        return;
    }

    $sql = 'UPDATE ' . $t . ' SET ' . implode(', ', $sets) . ' WHERE ' . $idCol . ' = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function students_delete(PDO $pdo, array $m, int $id): void
{
    $t = students_sql_ident((string)$m['table']);
    $idCol = students_sql_ident((string)$m['id']);
    $stmt = $pdo->prepare('DELETE FROM ' . $t . ' WHERE ' . $idCol . ' = :id');
    $stmt->execute([':id' => $id]);
}

function students_normalize_csv_header(string $h): string
{
    $h = trim(mb_strtolower($h));
    $h = preg_replace('/^\xEF\xBB\xBF/u', '', $h) ?? $h;
    $h = preg_replace('/^\p{Cf}+/u', '', $h) ?? $h;
    $h = str_replace([' ', '-', '.'], ['_', '_', '_'], $h);

    $map = [
        'studentcode' => 'student_code',
        'student_code' => 'student_code',
        'code' => 'student_code',
        'student_no' => 'student_code',
        'student_number' => 'student_code',
        'รหัสนักเรียน' => 'student_code',
        'รหัสประจำตัว' => 'student_code',

        'name' => 'full_name',
        'full_name' => 'full_name',
        'student_name' => 'full_name',
        'ชื่อ' => 'full_name',
        'ชื่อสกุล' => 'full_name',

        'first_name' => 'first_name',
        'firstname' => 'first_name',
        'ชื่อจริง' => 'first_name',

        'last_name' => 'last_name',
        'lastname' => 'last_name',
        'นามสกุล' => 'last_name',

        'roll_no' => 'roll_no',
        'rollnumber' => 'roll_no',
        'seat_no' => 'roll_no',
        'number_in_room' => 'roll_no',
        'no' => 'roll_no',
        'number' => 'roll_no',
        'เลขที่' => 'roll_no',

        'class_room' => 'class_room',
        'classroom' => 'class_room',
        'homeroom' => 'class_room',
        'ชั้นห้อง' => 'class_room',

        'grade' => 'grade',
        'level' => 'grade',
        'class' => 'grade',
        'ชั้น' => 'grade',

        'room' => 'room',
        'room_no' => 'room',
        'section' => 'room',
        'ห้อง' => 'room',

        'id' => 'id',
        'student_id' => 'id',

        'citizen_id' => 'citizen_id',
        'national_id' => 'citizen_id',
        'id_card' => 'citizen_id',
        'เลขบัตรประชาชน' => 'citizen_id',
        'บัตรประชาชน' => 'citizen_id',

        'birth_date' => 'birth_date',
        'birthdate' => 'birth_date',
        'dob' => 'birth_date',
        'date_of_birth' => 'birth_date',
        'วันเดือนปีเกิด' => 'birth_date',
        'วันเกิด' => 'birth_date',
    ];

    return $map[$h] ?? $h;
}

function students_normalize_birth_date(string $v): string
{
    $v = trim($v);
    if ($v === '') {
        return '';
    }

    // already yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }

    // dd/mm/yyyy or d/m/yyyy
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $y = $m[3];
        return $y . '-' . $mo . '-' . $d;
    }

    return $v;
}

function students_import_csv(PDO $pdo, int $yearId, string $yearName, string $tmpFile, bool $updateExisting): array
{
    $m = students_require_table($pdo);

    $fh = fopen($tmpFile, 'rb');
    if ($fh === false) {
        throw new RuntimeException('ไม่สามารถอ่านไฟล์ที่อัปโหลดได้');
    }

    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        throw new RuntimeException('ไฟล์ CSV ว่าง หรืออ่านไม่สำเร็จ');
    }

    $keys = [];
    foreach ($header as $h) {
        $keys[] = students_normalize_csv_header((string)$h);
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $rowNo = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rowNo++;

        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue;
        }

        $data = [];
        foreach ($keys as $i => $k) {
            $data[$k] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }

        $studentCode = trim((string)($data['student_code'] ?? ''));
        $rollNo = trim((string)($data['roll_no'] ?? ''));
        $classRoom = trim((string)($data['class_room'] ?? ''));
        $grade = trim((string)($data['grade'] ?? ''));
        $room = trim((string)($data['room'] ?? ''));
        $fullName = trim((string)($data['full_name'] ?? ''));
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        $citizenId = trim((string)($data['citizen_id'] ?? ''));
        $birthDate = students_normalize_birth_date((string)($data['birth_date'] ?? ''));

        if ($fullName === '' && ($first !== '' || $last !== '')) {
            $fullName = trim($first . ' ' . $last);
        }
        if ($first === '' && $last === '' && $fullName !== '') {
            [$first, $last] = students_first_last_from_alias_row(['full_name' => $fullName]);
        }

        $hasName = $fullName !== '' || $first !== '' || $last !== '';
        if (!$hasName) {
            $skipped++;
            continue;
        }

        try {
            if ($updateExisting) {
                // Prefer match by student_code if available
                if ($m['student_code'] && $studentCode !== '') {
                    $existing = students_find_by_code($pdo, $yearId, $yearName, $studentCode);
                    if ($existing) {
                        $u = [];
                        if ($studentCode !== '') { $u['student_code'] = $studentCode; }
                        if ($rollNo !== '') { $u['roll_no'] = $rollNo; }
                        if ($classRoom !== '') { $u['class_room'] = $classRoom; }
                        if ($grade !== '') { $u['grade'] = $grade; }
                        if ($room !== '') { $u['room'] = $room; }
                        if ($fullName !== '') { $u['full_name'] = $fullName; }
                        if ($first !== '') { $u['first_name'] = $first; }
                        if ($last !== '') { $u['last_name'] = $last; }

                        $extra = [];
                        if ($citizenId !== '') { $extra['citizen_id'] = $citizenId; }
                        if ($birthDate !== '') { $extra['birth_date'] = $birthDate; }
                        if (count($extra) > 0) { $u['extra'] = $extra; }

                        students_update($pdo, $m, (int)($existing[$m['id']] ?? 0), $u);
                        $updated++;
                        continue;
                    }
                }

                // Fallback match by id if present
                $idFromCsv = (int)($data['id'] ?? 0);
                if ($idFromCsv > 0) {
                    $existing = students_get($pdo, $idFromCsv);
                    if ($existing && students_row_belongs_to_year($m, $existing, $yearId, $yearName)) {
                        $u = [];
                        if ($studentCode !== '') { $u['student_code'] = $studentCode; }
                        if ($rollNo !== '') { $u['roll_no'] = $rollNo; }
                        if ($classRoom !== '') { $u['class_room'] = $classRoom; }
                        if ($grade !== '') { $u['grade'] = $grade; }
                        if ($room !== '') { $u['room'] = $room; }
                        if ($fullName !== '') { $u['full_name'] = $fullName; }
                        if ($first !== '') { $u['first_name'] = $first; }
                        if ($last !== '') { $u['last_name'] = $last; }

                        $extra = [];
                        if ($citizenId !== '') { $extra['citizen_id'] = $citizenId; }
                        if ($birthDate !== '') { $extra['birth_date'] = $birthDate; }
                        if (count($extra) > 0) { $u['extra'] = $extra; }

                        students_update($pdo, $m, $idFromCsv, $u);
                        $updated++;
                        continue;
                    }
                }
            }

            students_create($pdo, $m, $yearId, $yearName, [
                'student_code' => $studentCode,
                'roll_no' => $rollNo,
                'class_room' => $classRoom,
                'grade' => $grade,
                'room' => $room,
                'full_name' => $fullName,
                'first_name' => $first,
                'last_name' => $last,
                'extra' => [
                    'citizen_id' => $citizenId,
                    'birth_date' => $birthDate,
                ],
            ]);
            $inserted++;
        } catch (Throwable $e) {
            $errors[] = 'แถว ' . $rowNo . ': ' . $e->getMessage();
        }
    }

    fclose($fh);

    return [
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'mapping' => $m,
    ];
}

function students_csv_headers(): array
{
    return ['id', 'student_code', 'roll_no', 'class_room', 'grade', 'room', 'full_name', 'first_name', 'last_name'];
}

function students_delete_by_csv(PDO $pdo, int $yearId, string $yearName, string $tmpFile): array
{
    $m = students_require_table($pdo);

    $fh = fopen($tmpFile, 'rb');
    if ($fh === false) {
        throw new RuntimeException('ไม่สามารถอ่านไฟล์ที่อัปโหลดได้');
    }

    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        throw new RuntimeException('ไฟล์ CSV ว่าง หรืออ่านไม่สำเร็จ');
    }

    $keys = [];
    foreach ($header as $h) {
        $keys[] = students_normalize_csv_header((string)$h);
    }

    if (!in_array('student_code', $keys, true) && !in_array('id', $keys, true)) {
        fclose($fh);
        throw new RuntimeException('ไฟล์ CSV ต้องมีคอลัมน์ "รหัสนักเรียน" หรือ "id" เพื่อระบุนักเรียนที่ต้องการลบ');
    }

    $deleted = 0;
    $notFound = 0;
    $errors = [];
    $rowNo = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rowNo++;

        if (count($row) === 1 && trim((string)$row[0]) === '') {
            continue;
        }

        $data = [];
        foreach ($keys as $i => $k) {
            $data[$k] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }

        $studentCode = trim((string)($data['student_code'] ?? ''));
        $idFromCsv = (int)($data['id'] ?? 0);

        if ($studentCode === '' && $idFromCsv <= 0) {
            $errors[] = 'แถว ' . $rowNo . ': ไม่พบรหัสนักเรียนหรือ id';
            continue;
        }

        try {
            $existing = null;

            if ($m['student_code'] && $studentCode !== '') {
                $existing = students_find_by_code($pdo, $yearId, $yearName, $studentCode);
            }

            if ($existing === null && $idFromCsv > 0) {
                $candidate = students_get($pdo, $idFromCsv);
                if ($candidate !== null && students_row_belongs_to_year($m, $candidate, $yearId, $yearName)) {
                    $existing = $candidate;
                }
            }

            if ($existing === null) {
                $notFound++;
                continue;
            }

            students_delete($pdo, $m, (int)($existing[$m['id']] ?? 0));
            $deleted++;
        } catch (Throwable $e) {
            $errors[] = 'แถว ' . $rowNo . ': ' . $e->getMessage();
        }
    }

    fclose($fh);

    return [
        'deleted'   => $deleted,
        'not_found' => $notFound,
        'errors'    => $errors,
    ];
}

function students_import_display_headers(): array
{
    return ['รหัสนักเรียน', 'ชั้น', 'ห้อง', 'เลขที่', 'ชื่อจริง', 'นามสกุล', 'เลขบัตรประชาชน', 'วันเดือนปีเกิด'];
}

function students_import_template_rows(): array
{
    return [
        [
            'รหัสนักเรียน' => '6271',
            'ชั้น' => 'ม.1',
            'ห้อง' => '1',
            'เลขที่' => '1',
            'ชื่อจริง' => 'ธีรภาณุ',
            'นามสกุล' => 'สุดล้ำเลิศ',
            'เลขบัตรประชาชน' => '',
            'วันเดือนปีเกิด' => '',
        ],
        [
            'รหัสนักเรียน' => '6555',
            'ชั้น' => 'ม.1',
            'ห้อง' => '1',
            'เลขที่' => '2',
            'ชื่อจริง' => 'อรวีรัชญ์',
            'นามสกุล' => 'มูลทรัพย์',
            'เลขบัตรประชาชน' => '',
            'วันเดือนปีเกิด' => '',
        ],
    ];
}

function students_export_rows(PDO $pdo, int $yearId, string $yearName): array
{
    $m = students_require_table($pdo);
    [$where, $params] = students_year_where($m, $yearId, $yearName);

    $t = students_sql_ident((string)$m['table']);
    $idCol = students_sql_ident((string)$m['id']);

    $selectParts = ["$idCol AS id"];

    if ($m['student_code']) {
        $c = students_sql_ident((string)$m['student_code']);
        $selectParts[] = "$c AS student_code";
    } else {
        $selectParts[] = "NULL AS student_code";
    }

    if ($m['roll_no']) {
        $c = students_sql_ident((string)$m['roll_no']);
        $selectParts[] = "$c AS roll_no";
    } else {
        $selectParts[] = "NULL AS roll_no";
    }

    if ($m['class_room']) {
        $c = students_sql_ident((string)$m['class_room']);
        $selectParts[] = "$c AS class_room";
    } else {
        $selectParts[] = "NULL AS class_room";
    }

    if ($m['grade']) {
        $c = students_sql_ident((string)$m['grade']);
        $selectParts[] = "$c AS grade";
    } else {
        $selectParts[] = "NULL AS grade";
    }

    if ($m['room']) {
        $c = students_sql_ident((string)$m['room']);
        $selectParts[] = "$c AS room";
    } else {
        $selectParts[] = "NULL AS room";
    }

    if ($m['full_name']) {
        $c = students_sql_ident((string)$m['full_name']);
        $selectParts[] = "$c AS full_name";
    }
    if ($m['first_name']) {
        $c = students_sql_ident((string)$m['first_name']);
        $selectParts[] = "$c AS first_name";
    }
    if ($m['last_name']) {
        $c = students_sql_ident((string)$m['last_name']);
        $selectParts[] = "$c AS last_name";
    }

    $select = implode(', ', $selectParts);

    $stmt = $pdo->prepare("SELECT $select FROM $t WHERE $where ORDER BY $idCol ASC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
