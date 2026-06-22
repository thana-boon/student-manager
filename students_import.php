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
$result = null;

$localCurrentId = null;
$currentYear = null;

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
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    csrf_verify_or_die();

    if ($currentYear === null) {
        $error = 'กรุณาตั้งปีการศึกษาปัจจุบันก่อน';
    } elseif (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
        $error = 'ไม่พบไฟล์ที่อัปโหลด';
    } else {
        $updateExisting = (string)($_POST['update_existing'] ?? '') === '1';

        $f = $_FILES['csv'];
        $tmp = (string)($f['tmp_name'] ?? '');
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($err !== UPLOAD_ERR_OK || $tmp === '') {
            $error = 'อัปโหลดไฟล์ไม่สำเร็จ (error=' . $err . ')';
        } else {
            try {
                // กันไฟล์ใหญ่ทำงานเกิน timeout (เผื่อไว้ ปกติ import เร็วเพราะใช้ transaction แล้ว)
                @set_time_limit(300);

                $pdoSchool = db_pdo_school();
                $result = students_import_csv($pdoSchool, (int)$currentYear['id'], (string)$currentYear['name'], $tmp, $updateExisting);

                app_log('student.import_csv', [
                    'year_id' => (int)$currentYear['id'],
                    'update_existing' => $updateExisting,
                    'inserted' => (int)($result['inserted'] ?? 0),
                    'updated' => (int)($result['updated'] ?? 0),
                    'skipped' => (int)($result['skipped'] ?? 0),
                    'errors' => count((array)($result['errors'] ?? [])),
                ]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

layout_head('Import Students');
layout_topbar('students');
?>

<main class="mx-auto max-w-4xl px-4 py-8">
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">Import นักเรียน (CSV)</h2>
      <p class="mt-1 text-sm text-slate-500">ปีการศึกษา: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string)($currentYear['name'] ?? '-'), ENT_QUOTES) ?></span></p>
    </div>
    <div class="flex flex-wrap items-center justify-end gap-2">
      <a href="students_import_template.php" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 transition-colors duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        ดาวน์โหลดไฟล์ตัวอย่าง
      </a>
      <a href="students.php" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        ย้อนกลับ
      </a>
    </div>
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

  <?php if ($result !== null && $error === ''): ?>
    <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Import เสร็จแล้ว: เพิ่ม <?= (int)$result['inserted'] ?>, อัปเดต <?= (int)$result['updated'] ?>, ข้าม <?= (int)$result['skipped'] ?>
    </div>

    <?php $errs = (array)($result['errors'] ?? []); ?>
    <?php if (count($errs) > 0): ?>
      <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        <div class="font-semibold">พบข้อผิดพลาดบางแถว</div>
        <ul class="mt-2 list-disc pl-5 text-xs">
          <?php foreach (array_slice($errs, 0, 20) as $e): ?>
            <li class="break-words font-mono"><?= htmlspecialchars((string)$e, ENT_QUOTES) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($errs) > 20): ?>
          <div class="mt-2 text-xs text-rose-700/80">แสดงแค่ 20 รายการแรก</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <?= csrf_field() ?>

    <div class="grid gap-4">
      <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        <div class="font-semibold">รูปแบบไฟล์ที่แนะนำ</div>
        <p class="mt-1 text-sky-800/90">กดปุ่มดาวน์โหลดไฟล์ตัวอย่าง แล้วเปิดด้วย Excel เพื่อกรอกข้อมูลได้เลย จากนั้นบันทึกเป็น CSV UTF-8 ก่อนนำเข้า</p>
        <p class="mt-2 text-xs text-sky-700/90">หัวคอลัมน์ตามฟอร์มใหม่: ลำดับ, ชั้น, ห้อง, เลขที่, สถานะ, รหัสบัตรประชาชน, รหัสนักศึกษา, เพศ, คำนำหน้า, ชื่อ, นามสกุล, ชื่อเล่น, วัน/เดือน/ปีเกิด (ช่อง Email/Password เป็นสูตร ระบบจะข้ามให้ตอนนำเข้า) — ยังรองรับหัวแบบเดิมเพื่อใช้กับไฟล์เก่าได้</p>
      </div>

      <div>
        <label class="mb-1 block text-sm text-slate-700">ไฟล์ CSV</label>
        <input type="file" name="csv" accept=".csv,text/csv" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 file:mr-4 file:rounded-lg file:border file:border-slate-200 file:bg-slate-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-100" />
        <p class="mt-2 text-xs text-slate-500">แนะนำให้กดดาวน์โหลดไฟล์ตัวอย่างเพื่อดูหัวคอลัมน์ที่ถูกต้อง แล้วบันทึกเป็น CSV UTF-8 ก่อนนำเข้า</p>
      </div>

      <label class="flex items-center gap-3 text-sm text-slate-700">
        <input type="checkbox" name="update_existing" value="1" class="h-4 w-4 rounded border-slate-300 bg-white text-blue-600" />
        Import ซ้ำเพื่ออัปเดตข้อมูลเดิม (จับคู่ด้วย student_code ก่อน ถ้ามี)
      </label>
    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
      <a href="students.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">ยกเลิก</a>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        เริ่ม Import
      </button>
    </div>
  </form>
</main>

<?php layout_footer(); ?>
