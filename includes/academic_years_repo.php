<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ay_table_name(): string
{
    $cfg = app_config();
    $t = (string)($cfg['school']['academic_years_table'] ?? 'academic_years');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
        throw new RuntimeException('Invalid academic years table name');
    }
    return $t;
}

function sql_ident(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new RuntimeException('Invalid SQL identifier');
    }
    return '`' . $name . '`';
}

function ay_detect_columns(PDO $pdo): array
{
    $table = ay_table_name();

    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmt->execute([':t' => $table]);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$cols || count($cols) === 0) {
        throw new RuntimeException('ไม่พบตารางปีการศึกษาใน students_db (ตรวจสอบชื่อ table ใน config.php)');
    }

    $cols = array_map('strval', $cols);
    $has = array_flip($cols);

    $idColCandidates = ['id', 'academic_year_id', 'year_id'];
    $nameColCandidates = ['name', 'year', 'academic_year', 'title'];
    $currentColCandidates = ['is_current', 'current', 'is_active', 'active'];
    $yearNumberCandidates = ['year_be', 'year_no', 'be_year', 'academic_year_be', 'buddhist_year', 'year'];
    $updatedAtCandidates = ['updated_at', 'update_at', 'modified_at', 'updated'];
    $createdAtCandidates = ['created_at', 'create_at', 'created'];

    $pick = function (array $candidates) use ($has): ?string {
        foreach ($candidates as $c) {
            if (isset($has[$c])) {
                return $c;
            }
        }
        return null;
    };

    $idCol = $pick($idColCandidates);
    $nameCol = $pick($nameColCandidates);
    $currentCol = $pick($currentColCandidates);
    $updatedAt = $pick($updatedAtCandidates);
    $createdAt = $pick($createdAtCandidates);

    // Numeric year column (e.g. year_be). Must be a separate column from the
    // display name; otherwise we'd overwrite the title with a bare number.
    $yearNumberCol = $pick($yearNumberCandidates);
    if ($yearNumberCol !== null && $yearNumberCol === $nameCol) {
        $yearNumberCol = null;
    }

    if ($idCol === null || $nameCol === null) {
        throw new RuntimeException('โครงสร้างตารางปีการศึกษาไม่รองรับ (ต้องมี id และชื่อปีการศึกษา)');
    }

    return [
        'table' => $table,
        'id' => $idCol,
        'name' => $nameCol,
        'is_current' => $currentCol,
        'year_number' => $yearNumberCol,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'columns' => $cols,
    ];
}

function academic_years_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $stmt->execute([':t' => ay_table_name()]);
    return (int)$stmt->fetchColumn() > 0;
}

function academic_years_require_table(PDO $pdo): array
{
    if (!academic_years_table_exists($pdo)) {
        throw new RuntimeException('ไม่พบตารางปีการศึกษาในฐานข้อมูล students_db (ตรวจสอบชื่อ table ใน config.php)');
    }
    return ay_detect_columns($pdo);
}

function academic_years_list(PDO $pdo): array
{
    $m = academic_years_require_table($pdo);

    $t = sql_ident((string)$m['table']);
    $id = sql_ident((string)$m['id']);
    $name = sql_ident((string)$m['name']);
    $curCol = $m['is_current'] ? sql_ident((string)$m['is_current']) : null;
    $updated = $m['updated_at'] ? sql_ident((string)$m['updated_at']) : null;
    $created = $m['created_at'] ? sql_ident((string)$m['created_at']) : null;

    $select = "$id AS id, $name AS name";
    if ($curCol) {
        $select .= ", $curCol AS is_current";
    } else {
        $select .= ", 0 AS is_current";
    }
    if ($created) {
        $select .= ", $created AS created_at";
    } else {
        $select .= ", NULL AS created_at";
    }
    if ($updated) {
        $select .= ", $updated AS updated_at";
    } else {
        $select .= ", NULL AS updated_at";
    }

    $order = $curCol ? "$curCol DESC, $id DESC" : "$id DESC";
    $stmt = $pdo->query("SELECT $select FROM $t ORDER BY $order");
    return $stmt->fetchAll();
}

function academic_years_has_current(PDO $pdo): bool
{
    $m = academic_years_require_table($pdo);
    if (!$m['is_current']) {
        throw new RuntimeException('ตารางปีการศึกษาไม่มีคอลัมน์บอกปีปัจจุบัน');
    }
    $t = sql_ident((string)$m['table']);
    $cur = sql_ident((string)$m['is_current']);
    $stmt = $pdo->query("SELECT COUNT(*) FROM $t WHERE $cur = 1");
    return (int)$stmt->fetchColumn() > 0;
}

function academic_years_year_number_from_name(string $name): ?int
{
    // Prefer a 4-digit year (e.g. 2569 / 2026); fall back to any run of digits.
    if (preg_match('/(\d{4})/u', $name, $mm)) {
        return (int)$mm[1];
    }
    if (preg_match('/(\d+)/u', $name, $mm)) {
        return (int)$mm[1];
    }
    return null;
}

function academic_years_create(PDO $pdo, string $name): int
{
    $m = academic_years_require_table($pdo);
    $t = sql_ident((string)$m['table']);

    $cols = [sql_ident((string)$m['name'])];
    $vals = [':n'];
    $params = [':n' => $name];

    if ($m['is_current']) {
        $cols[] = sql_ident((string)$m['is_current']);
        $vals[] = '0';
    }

    // Schema may have a NOT NULL numeric year column (e.g. year_be) with no
    // default. Derive it from the entered name so it isn't silently stored as 0.
    if ($m['year_number']) {
        $yearNo = academic_years_year_number_from_name($name);
        if ($yearNo === null) {
            throw new RuntimeException('กรุณาระบุปีการศึกษาเป็นตัวเลข เช่น 2569 หรือ 2026');
        }
        $cols[] = sql_ident((string)$m['year_number']);
        $vals[] = ':yb';
        $params[':yb'] = $yearNo;
    }

    $sql = 'INSERT INTO ' . $t . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function academic_years_set_current(PDO $pdo, int $id): void
{
    $m = academic_years_require_table($pdo);
    if (!$m['is_current']) {
        throw new RuntimeException('ตารางปีการศึกษาไม่มีคอลัมน์บอกปีปัจจุบัน');
    }
    $t = sql_ident((string)$m['table']);
    $idCol = sql_ident((string)$m['id']);
    $cur = sql_ident((string)$m['is_current']);

    $pdo->beginTransaction();
    try {
        $pdo->exec("UPDATE $t SET $cur = 0");

        $stmt = $pdo->prepare("UPDATE $t SET $cur = 1 WHERE $idCol = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('ไม่พบปีการศึกษาที่เลือก');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function academic_years_delete(PDO $pdo, int $id): void
{
    $m = academic_years_require_table($pdo);
    $t = sql_ident((string)$m['table']);
    $idCol = sql_ident((string)$m['id']);

    // Delete
    $stmt = $pdo->prepare("DELETE FROM $t WHERE $idCol = :id");
    $stmt->execute([':id' => $id]);
}
