<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/academic_years_repo.php';
require_once __DIR__ . '/includes/settings_repo.php';
require_once __DIR__ . '/includes/students_repo.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$error = '';
$errors = [];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: students.php');
    exit;
}

$localCurrentId = null;
$currentYear = null;
$mapping = null;
$student = null;

$metaByName = [];
$extraColumns = [];
$extraValues = [];

$studentCode = '';
$rollNo = '';
$classRoom = '';
$grade = '';
$room = '';
$fullName = '';
$firstName = '';
$lastName = '';

try {
    $pdoLocal = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');

    $pdoSchool = db_pdo_school();
    academic_years_require_table($pdoSchool);
    $years = academic_years_list($pdoSchool);

    if ($localCurrentId !== null) {
        foreach ($years as $y) {
            if ((int)$y['id'] === (int)$localCurrentId) {
                $currentYear = $y;
                break;
            }
        }
    }

    $mapping = students_require_table($pdoSchool);
    $student = students_get($pdoSchool, $id);

    if (!$student) {
        header('Location: students.php');
        exit;
    }

    if ($currentYear !== null && !students_row_belongs_to_year($mapping, $student, (int)$currentYear['id'], (string)$currentYear['name'])) {
        throw new RuntimeException('นักเรียนนี้ไม่ได้อยู่ในปีการศึกษาปัจจุบันของระบบนี้');
    }

    if ($mapping['student_code']) {
        $studentCode = trim((string)($student[$mapping['student_code']] ?? ''));
    }

    if (!empty($mapping['roll_no'])) {
      $rollNo = trim((string)($student[$mapping['roll_no']] ?? ''));
    }

    if (!empty($mapping['class_room'])) {
      $classRoom = trim((string)($student[$mapping['class_room']] ?? ''));
    } else {
      if (!empty($mapping['grade'])) {
        $grade = trim((string)($student[$mapping['grade']] ?? ''));
      }
      if (!empty($mapping['room'])) {
        $room = trim((string)($student[$mapping['room']] ?? ''));
      }
    }

    if ($mapping['full_name']) {
        $fullName = trim((string)($student[$mapping['full_name']] ?? ''));
    } else {
        if ($mapping['first_name']) {
            $firstName = trim((string)($student[$mapping['first_name']] ?? ''));
        }
        if ($mapping['last_name']) {
            $lastName = trim((string)($student[$mapping['last_name']] ?? ''));
        }
    }

    $metaByName = students_columns_meta($pdoSchool);

    foreach ((array)($mapping['columns'] ?? []) as $c) {
      $c = (string)$c;
      if ($c === '') {
        continue;
      }
      if ($c === 'citizen_id' || $c === 'birth_date') {
        // Rendered as dedicated fields
        continue;
      }
      if (students_is_system_column($mapping, $c)) {
        continue;
      }
      if (students_is_mapped_column($mapping, $c)) {
        continue;
      }
      // Skip auto-increment columns if any slipped through
      $extra = (string)($metaByName[$c]['extra'] ?? '');
      if ($extra !== '' && strpos($extra, 'auto_increment') !== false) {
        continue;
      }
      $extraColumns[] = $c;
    }

    foreach ($extraColumns as $c) {
      $v = $student[$c] ?? '';
      $extraValues[$c] = is_scalar($v) || $v === null ? (string)$v : '';
    }

    // Dedicated optional fields
    $v = $student['citizen_id'] ?? '';
    $extraValues['citizen_id'] = is_scalar($v) || $v === null ? (string)$v : '';
    $v = $student['birth_date'] ?? '';
    $extraValues['birth_date'] = is_scalar($v) || $v === null ? (string)$v : '';

} catch (Throwable $e) {
    $error = $e->getMessage();
}

// override from POST
$studentCode = trim((string)($_POST['student_code'] ?? $studentCode));
$rollNo = trim((string)($_POST['roll_no'] ?? $rollNo));
$classRoom = trim((string)($_POST['class_room'] ?? $classRoom));
$grade = trim((string)($_POST['grade'] ?? $grade));
$room = trim((string)($_POST['room'] ?? $room));
$fullName = trim((string)($_POST['full_name'] ?? $fullName));
$firstName = trim((string)($_POST['first_name'] ?? $firstName));
$lastName = trim((string)($_POST['last_name'] ?? $lastName));

// extra fields override from POST
$extraPost = $_POST['extra'] ?? [];
if (is_array($extraPost)) {
  $allowed = array_flip(array_merge($extraColumns, ['citizen_id', 'birth_date']));
  foreach ($extraPost as $k => $v) {
    $k = (string)$k;
    if (!isset($allowed[$k])) {
      continue;
    }
    $extraValues[$k] = trim((string)$v);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    csrf_verify_or_die();

    if (mb_strlen($studentCode) > 50) {
        $errors[] = 'รหัสนักเรียนต้องไม่เกิน 50 ตัวอักษร';
    }

    if (mb_strlen($rollNo) > 20) {
      $errors[] = 'เลขที่ต้องไม่เกิน 20 ตัวอักษร';
    }

    if (mb_strlen($classRoom) > 50) {
      $errors[] = 'ชั้น/ห้องต้องไม่เกิน 50 ตัวอักษร';
    }
    if (mb_strlen($grade) > 50) {
      $errors[] = 'ชั้นต้องไม่เกิน 50 ตัวอักษร';
    }
    if (mb_strlen($room) > 50) {
      $errors[] = 'ห้องต้องไม่เกิน 50 ตัวอักษร';
    }

    if ($mapping !== null && (string)($mapping['full_name'] ?? '') !== '') {
        if ($fullName === '') {
            $errors[] = 'กรุณากรอกชื่อ-สกุล';
        } elseif (mb_strlen($fullName) > 255) {
            $errors[] = 'ชื่อ-สกุลต้องไม่เกิน 255 ตัวอักษร';
        }
    } else {
        if ($firstName === '' && $lastName === '') {
            $errors[] = 'กรุณากรอกชื่อ/นามสกุลอย่างน้อย 1 ช่อง';
        }
        if (mb_strlen($firstName) > 100) {
            $errors[] = 'ชื่อต้องไม่เกิน 100 ตัวอักษร';
        }
        if (mb_strlen($lastName) > 100) {
            $errors[] = 'นามสกุลต้องไม่เกิน 100 ตัวอักษร';
        }
    }

    $citizenId = trim((string)($extraValues['citizen_id'] ?? ''));
    if ($citizenId !== '' && !preg_match('/^\d{13}$/', $citizenId)) {
      $errors[] = 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก';
    }

    $birthDate = trim((string)($extraValues['birth_date'] ?? ''));
    if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
      $errors[] = 'วันเดือนปีเกิดต้องเป็นรูปแบบ YYYY-MM-DD';
    }

    if (count($errors) === 0) {
        try {
            $pdoSchool = db_pdo_school();
            $m = students_require_table($pdoSchool);

            students_update($pdoSchool, $m, $id, [
                'student_code' => $studentCode,
              'roll_no' => $rollNo,
              'class_room' => $classRoom,
              'grade' => $grade,
              'room' => $room,
                'full_name' => $fullName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'extra' => $extraValues,
            ]);

            app_log('student.update', ['id' => $id]);

            header('Location: students.php');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

layout_head('Edit Student');
layout_topbar('students');
?>

<main class="mx-auto max-w-3xl px-4 py-8">
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">แก้ไขนักเรียน</h2>
      <p class="mt-1 text-sm text-slate-500">ปีการศึกษา: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string)($currentYear['name'] ?? '-'), ENT_QUOTES) ?></span></p>
    </div>
    <a href="students.php" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
      ย้อนกลับ
    </a>
  </div>

  <?php if ($error !== ''): ?>
    <div class="mb-5 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
      <svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      <div>
        <div class="font-semibold">เกิดข้อผิดพลาด</div>
        <div class="mt-1 break-words font-mono text-xs text-rose-800/90"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (count($errors) > 0): ?>
    <div class="mb-5 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
      <svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      <div>
        <div class="font-semibold">กรุณาตรวจสอบ</div>
        <ul class="mt-1 list-disc pl-5">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <?= csrf_field() ?>

    <div class="grid gap-4">
      <?php if ($mapping !== null && ((string)($mapping['class_room'] ?? '') !== '' || (string)($mapping['grade'] ?? '') !== '' || (string)($mapping['room'] ?? '') !== '' || (string)($mapping['roll_no'] ?? '') !== '')): ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <?php if ((string)($mapping['class_room'] ?? '') !== ''): ?>
            <div>
              <label class="mb-1 block text-sm text-slate-700">ชั้น/ห้อง</label>
              <input name="class_room" value="<?= htmlspecialchars($classRoom, ENT_QUOTES) ?>" placeholder="เช่น ม.1/1"
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
            </div>
          <?php else: ?>
            <?php if ((string)($mapping['grade'] ?? '') !== ''): ?>
              <div>
                <label class="mb-1 block text-sm text-slate-700">ชั้น</label>
                <input name="grade" value="<?= htmlspecialchars($grade, ENT_QUOTES) ?>" placeholder="เช่น ม.1"
                  class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
              </div>
            <?php endif; ?>
            <?php if ((string)($mapping['room'] ?? '') !== ''): ?>
              <div>
                <label class="mb-1 block text-sm text-slate-700">ห้อง</label>
                <input name="room" value="<?= htmlspecialchars($room, ENT_QUOTES) ?>" placeholder="เช่น 1"
                  class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ((string)($mapping['roll_no'] ?? '') !== ''): ?>
            <div>
              <label class="mb-1 block text-sm text-slate-700">เลขที่</label>
              <input name="roll_no" value="<?= htmlspecialchars($rollNo, ENT_QUOTES) ?>" placeholder="เช่น 5"
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div>
        <label class="mb-1 block text-sm text-slate-700">รหัสนักเรียน (ถ้ามี)</label>
        <input name="student_code" value="<?= htmlspecialchars($studentCode, ENT_QUOTES) ?>" placeholder="เช่น 12345"
          class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm text-slate-700">เลขบัตรประชาชน (ถ้ามี)</label>
          <input name="extra[citizen_id]" value="<?= htmlspecialchars((string)($extraValues['citizen_id'] ?? ''), ENT_QUOTES) ?>" placeholder="13 หลัก"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        </div>
        <div>
          <label class="mb-1 block text-sm text-slate-700">วันเดือนปีเกิด</label>
          <input type="date" name="extra[birth_date]" value="<?= htmlspecialchars((string)($extraValues['birth_date'] ?? ''), ENT_QUOTES) ?>"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        </div>
      </div>

      <?php if ($mapping !== null && (string)($mapping['full_name'] ?? '') !== ''): ?>
        <div>
          <label class="mb-1 block text-sm text-slate-700">ชื่อ-สกุล</label>
          <input name="full_name" value="<?= htmlspecialchars($fullName, ENT_QUOTES) ?>"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        </div>
      <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm text-slate-700">ชื่อ</label>
            <input name="first_name" value="<?= htmlspecialchars($firstName, ENT_QUOTES) ?>"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-slate-700">นามสกุล</label>
            <input name="last_name" value="<?= htmlspecialchars($lastName, ENT_QUOTES) ?>"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
          </div>
        </div>
      <?php endif; ?>

      <?php if (count($extraColumns) > 0): ?>
        <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="text-sm font-semibold text-slate-900">ข้อมูลอื่นๆ (ในฐานข้อมูล)</div>
          <p class="mt-1 text-xs text-slate-600">ฟิลด์ด้านล่างถูกดึงจากคอลัมน์ที่มีอยู่ในตารางนักเรียน เพื่อให้แก้ไขข้อมูลที่ถูกเก็บไว้ได้ครบ</p>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?php foreach ($extraColumns as $col): ?>
              <?php
                $meta = $metaByName[$col] ?? [];
                $type = (string)($meta['data_type'] ?? '');
                $ctype = (string)($meta['column_type'] ?? '');
                $val = (string)($extraValues[$col] ?? '');

                $asDateTimeLocal = function (string $v): string {
                    $v = trim($v);
                    if ($v === '') {
                        return '';
                    }
                    // 2026-04-07 13:45:00 -> 2026-04-07T13:45
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?$/', $v, $m2)) {
                        return $m2[1] . 'T' . $m2[2];
                    }
                    return str_replace(' ', 'T', $v);
                };

                $enumOptions = [];
                if (strpos($ctype, 'enum(') === 0) {
                  if (preg_match_all("/'((?:\\\\'|[^'])*)'/", $ctype, $mm)) {
                        foreach ($mm[1] as $opt) {
                            $enumOptions[] = str_replace("\\'", "'", (string)$opt);
                        }
                    }
                }
              ?>

              <div>
                <label class="mb-1 block text-sm text-slate-700"><?= htmlspecialchars($col, ENT_QUOTES) ?></label>

                <?php if (count($enumOptions) > 0): ?>
                  <select name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
                    <option value="">(ว่าง)</option>
                    <?php foreach ($enumOptions as $opt): ?>
                      <option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= ($opt === $val) ? 'selected' : '' ?>><?= htmlspecialchars($opt, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif (in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext'], true)): ?>
                  <textarea name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"><?= htmlspecialchars($val, ENT_QUOTES) ?></textarea>
                <?php elseif ($type === 'date'): ?>
                  <input type="date" name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($val, ENT_QUOTES) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
                <?php elseif (in_array($type, ['datetime', 'timestamp'], true)): ?>
                  <input type="datetime-local" name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($asDateTimeLocal($val), ENT_QUOTES) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
                <?php elseif (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'], true)): ?>
                  <input type="number" name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($val, ENT_QUOTES) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
                <?php else: ?>
                  <input name="extra[<?= htmlspecialchars($col, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($val, ENT_QUOTES) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
                <?php endif; ?>

                <div class="mt-1 text-[11px] text-slate-500"><?= htmlspecialchars($type !== '' ? $type : '-', ENT_QUOTES) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
      <a href="students.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">ยกเลิก</a>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        บันทึก
      </button>
    </div>
  </form>
</main>

<?php layout_footer(); ?>
