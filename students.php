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
$flash = '';

if ($error === '' && isset($_GET['error'])) {
  $error = (string)$_GET['error'];
}

$gradeFilter = trim((string)($_GET['grade'] ?? ''));
$roomFilter = trim((string)($_GET['room'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));

$grades = [];
$rooms = [];
$studentsAll = [];
$studentsFiltered = [];

$years = [];
$localCurrentId = null;
$currentYear = null;
$students = [];

try {
    $pdoSchool = db_pdo_school();
    academic_years_require_table($pdoSchool);
    $years = academic_years_list($pdoSchool);

    // Sort years for the dropdown: latest year first (by numeric part in name), then by id desc.
    $yearNumber = function (string $name): ?int {
      $name = trim($name);
      if ($name === '') {
        return null;
      }
      if (preg_match('/(\d{4})/u', $name, $m)) {
        return (int)$m[1];
      }
      if (preg_match('/(\d+)/u', $name, $m)) {
        return (int)$m[1];
      }
      return null;
    };

    usort($years, function (array $a, array $b) use ($yearNumber): int {
      $na = $yearNumber((string)($a['name'] ?? ''));
      $nb = $yearNumber((string)($b['name'] ?? ''));

      if ($na !== null && $nb !== null && $na !== $nb) {
        return $nb <=> $na; // desc
      }
      if ($na !== null && $nb === null) {
        return -1;
      }
      if ($na === null && $nb !== null) {
        return 1;
      }

      $ida = (int)($a['id'] ?? 0);
      $idb = (int)($b['id'] ?? 0);
      if ($ida !== $idb) {
        return $idb <=> $ida;
      }

      return strcmp((string)($b['name'] ?? ''), (string)($a['name'] ?? ''));
    });

    $pdoLocal = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');

    if ($localCurrentId !== null) {
        foreach ($years as $y) {
            if ((int)$y['id'] === (int)$localCurrentId) {
                $currentYear = $y;
                break;
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    csrf_verify_or_die();

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'set_year') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ข้อมูลไม่ถูกต้อง');
            }

            $pdoLocal = db_pdo();
            settings_set_int($pdoLocal, 'current_academic_year_id', $id);
            app_log('academic_year.set_current_local', ['id' => $id, 'from' => 'students']);

            header('Location: students.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($error === '' && $currentYear !== null) {
    try {
        $pdoSchool = db_pdo_school();
    $studentsAll = students_list_by_year($pdoSchool, (int)$currentYear['id'], (string)$currentYear['name']);

    $gSeen = [];
    $rSeen = [];
    foreach ($studentsAll as $s) {
      $g = trim((string)($s['grade'] ?? ''));
      $r = trim((string)($s['room'] ?? ''));

      if ($g !== '' && !isset($gSeen[$g])) {
        $gSeen[$g] = true;
        $grades[] = $g;
      }
      if ($r !== '' && !isset($rSeen[$r])) {
        $rSeen[$r] = true;
        $rooms[] = $r;
      }
    }
    sort($grades);
    sort($rooms);

    $studentsFiltered = array_values(array_filter($studentsAll, function (array $s) use ($gradeFilter, $roomFilter, $query): bool {
      if ($gradeFilter !== '' && trim((string)($s['grade'] ?? '')) !== $gradeFilter) {
        return false;
      }
      if ($roomFilter !== '' && trim((string)($s['room'] ?? '')) !== $roomFilter) {
        return false;
      }

      if ($query === '') {
        return true;
      }

      $hay = implode(' ', [
        (string)($s['student_code'] ?? ''),
        (string)($s['grade'] ?? ''),
        (string)($s['room'] ?? ''),
        (string)($s['roll_no'] ?? ''),
        (string)($s['first_name'] ?? ''),
        (string)($s['last_name'] ?? ''),
        (string)($s['display_name'] ?? ''),
      ]);

      return mb_stripos($hay, $query) !== false;
    }));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

layout_head('Students');
layout_topbar('students');
?>

<main class="mx-auto max-w-6xl px-4 py-8">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">นักเรียน</h2>
      <p class="mt-1 text-sm text-slate-500">จัดการข้อมูลนักเรียนจากฐาน <span class="font-mono text-slate-600">students_db</span> ตามปีการศึกษาปัจจุบัน</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <?php if ($currentYear !== null && $error === ''): ?>
        <a href="students_export.php" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          Export CSV
        </a>
        <a href="students_import.php" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
          Import CSV
        </a>
        <a href="students_delete_import.php" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition-colors duration-150">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
          ลบด้วย CSV
        </a>
        <a href="student_new.php" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          เพิ่มนักเรียน
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash !== ''): ?>
    <div class="mt-5 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?= htmlspecialchars($flash, ENT_QUOTES) ?>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
      <div class="font-semibold">เกิดข้อผิดพลาด</div>
      <div class="mt-1 break-words font-mono text-xs text-rose-800/90"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
    </div>
  <?php endif; ?>

  <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-end sm:justify-between border-b border-slate-100">
      <div>
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">ปีการศึกษา (ระบบนี้)</div>
        <div class="mt-1 text-lg font-bold text-slate-900">
          <?php if ($currentYear !== null): ?>
            <?= htmlspecialchars((string)$currentYear['name'], ENT_QUOTES) ?>
          <?php else: ?>
            <span class="text-slate-400">ยังไม่ได้ตั้งปีปัจจุบัน</span>
          <?php endif; ?>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">ต้องตั้งปีปัจจุบันก่อนเพื่อกรองรายชื่อนักเรียน</p>
      </div>

      <form method="post" class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_year" />

        <select name="id" class="min-w-44 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
          <option value="">เลือกปีการศึกษา...</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= (int)$y['id'] ?>" <?= ($localCurrentId !== null && (int)$y['id'] === (int)$localCurrentId) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$y['name'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors duration-150">ตั้งปี</button>
        <a href="academic_years.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">จัดการปีการศึกษา</a>
      </form>
    </div>
  </section>

  <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5">
      <div class="text-sm text-slate-600">
        ทั้งหมด <span class="font-semibold text-slate-900"><?= count($studentsFiltered) ?></span> คน
        <?php if (($gradeFilter !== '' || $roomFilter !== '' || $query !== '') && count($studentsAll) !== count($studentsFiltered)): ?>
          <span class="text-slate-400">(จากทั้งหมด <?= count($studentsAll) ?> คน)</span>
        <?php endif; ?>
      </div>

      <?php if ($currentYear !== null && $error === ''): ?>
        <form method="get" class="flex flex-wrap items-center gap-2">
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            </span>
            <input name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>" placeholder="ค้นหา: รหัส, ชื่อ, ชั้น/ห้อง..."
              class="w-56 rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
          </div>

          <select name="grade" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
            <option value="">ทุกชั้น</option>
            <?php foreach ($grades as $g): ?>
              <option value="<?= htmlspecialchars($g, ENT_QUOTES) ?>" <?= ($g === $gradeFilter) ? 'selected' : '' ?>>
                <?= htmlspecialchars($g, ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select name="room" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10">
            <option value="">ทุกห้อง</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= htmlspecialchars($r, ENT_QUOTES) ?>" <?= ($r === $roomFilter) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r, ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button type="submit" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">ค้นหา</button>

          <?php if ($gradeFilter !== '' || $roomFilter !== '' || $query !== ''): ?>
            <a href="students.php" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 hover:bg-slate-50 transition-colors duration-150">
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              ล้าง
            </a>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
          <tr>
            <th class="px-5 py-3">รหัสประจำตัว</th>
            <th class="px-5 py-3">ชั้น</th>
            <th class="px-5 py-3">ห้อง</th>
            <th class="px-5 py-3">เลขที่</th>
            <th class="px-5 py-3">ชื่อ</th>
            <th class="px-5 py-3">นามสกุล</th>
            <th class="px-5 py-3">อัปเดต</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($studentsFiltered as $s): ?>
            <tr class="transition-colors duration-100 hover:bg-blue-50/30">
              <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars((string)($s['student_code'] ?? '-'), ENT_QUOTES) ?></td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"><?= htmlspecialchars((string)($s['grade'] ?? '-'), ENT_QUOTES) ?></span>
              </td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"><?= htmlspecialchars((string)($s['room'] ?? '-'), ENT_QUOTES) ?></span>
              </td>
              <td class="px-5 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars((string)($s['roll_no'] ?? '-'), ENT_QUOTES) ?></td>
              <td class="px-5 py-3 font-medium text-slate-900"><?= htmlspecialchars((string)($s['first_name'] ?? '-'), ENT_QUOTES) ?></td>
              <td class="px-5 py-3 text-slate-700"><?= htmlspecialchars((string)($s['last_name'] ?? '-'), ENT_QUOTES) ?></td>
              <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars((string)($s['updated_at'] ?? ''), ENT_QUOTES) ?></td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-2">
                  <a href="student_edit.php?id=<?= (int)$s['id'] ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50 transition-colors duration-100">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                    แก้ไข
                  </a>

                  <form method="post" action="student_delete.php" onsubmit="return confirm('ยืนยันลบนักเรียนคนนี้?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                    <button class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-100 transition-colors duration-100">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                      ลบ
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if ($currentYear === null && $error === ''): ?>
            <tr>
              <td class="px-5 py-10 text-center text-sm text-slate-400" colspan="8">
                <div class="flex flex-col items-center gap-2">
                  <svg class="h-8 w-8 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                  ยังไม่ได้ตั้งปีการศึกษาปัจจุบัน เลือกปีจากด้านบนก่อน
                </div>
              </td>
            </tr>
          <?php elseif ($currentYear !== null && count($studentsAll) === 0): ?>
            <tr>
              <td class="px-5 py-10 text-center text-sm text-slate-400" colspan="8">
                <div class="flex flex-col items-center gap-2">
                  <svg class="h-8 w-8 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                  ยังไม่พบรายชื่อนักเรียนในปีนี้ · <a href="students_import.php" class="text-blue-500 hover:underline">Import CSV</a> หรือ <a href="student_new.php" class="text-blue-500 hover:underline">เพิ่มใหม่</a>
                </div>
              </td>
            </tr>
          <?php elseif ($currentYear !== null && count($studentsAll) > 0 && count($studentsFiltered) === 0): ?>
            <tr>
              <td class="px-5 py-10 text-center text-sm text-slate-400" colspan="8">ไม่พบข้อมูลตามตัวกรอง/คำค้นหา</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">
      ข้อมูลถูกอ่าน/เขียนในฐาน <span class="font-mono text-slate-500">students_db</span> (ตาราง students) · ปีปัจจุบันเก็บใน <span class="font-mono text-slate-500">student_manager</span>
    </div>
  </section>
</main>

<?php layout_footer(); ?>
