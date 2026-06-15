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
        $f = $_FILES['csv'];
        $tmp = (string)($f['tmp_name'] ?? '');
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($err !== UPLOAD_ERR_OK || $tmp === '') {
            $error = 'อัปโหลดไฟล์ไม่สำเร็จ (error=' . $err . ')';
        } else {
            try {
                $pdoSchool = db_pdo_school();
                $result = students_delete_by_csv($pdoSchool, (int)$currentYear['id'], (string)$currentYear['name'], $tmp);

                app_log('student.delete_by_csv', [
                    'year_id'   => (int)$currentYear['id'],
                    'deleted'   => (int)($result['deleted'] ?? 0),
                    'not_found' => (int)($result['not_found'] ?? 0),
                    'errors'    => count((array)($result['errors'] ?? [])),
                ]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

layout_head('ลบนักเรียนด้วย CSV');
layout_topbar('students');
?>

<main class="mx-auto max-w-4xl px-4 py-8">
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">ลบนักเรียนด้วยไฟล์ CSV</h2>
      <p class="mt-1 text-sm text-slate-500">ปีการศึกษา: <span class="font-semibold text-slate-700"><?= htmlspecialchars((string)($currentYear['name'] ?? '-'), ENT_QUOTES) ?></span></p>
    </div>
    <div class="flex flex-wrap items-center justify-end gap-2">
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
      ดำเนินการเสร็จแล้ว: ลบสำเร็จ <?= (int)$result['deleted'] ?> คน, ไม่พบ <?= (int)$result['not_found'] ?> คน
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
      <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <div class="font-semibold">⚠️ คำเตือน: การลบไม่สามารถกู้คืนได้</div>
        <p class="mt-1 text-amber-800/90">ระบบจะลบนักเรียนทุกคนที่มีรหัสนักเรียนตรงกับไฟล์ที่นำเข้า ในปีการศึกษาปัจจุบันเท่านั้น</p>
      </div>

      <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        <div class="font-semibold">รูปแบบไฟล์</div>
        <p class="mt-1 text-sky-800/90">ไฟล์ CSV ต้องมีคอลัมน์ <span class="font-mono font-semibold">รหัสนักเรียน</span> เพื่อระบุว่าต้องการลบใครบ้าง</p>
        <p class="mt-1 text-xs text-sky-700/90">วิธีง่ายที่สุดคือ Export CSV จากหน้ารายชื่อนักเรียน จากนั้นเปิดด้วย Excel ลบแถวที่ไม่ต้องการลบออก แล้วบันทึกเป็น CSV UTF-8 เพื่อนำเข้าที่นี่</p>
      </div>

      <div>
        <label class="mb-1 block text-sm text-slate-700">ไฟล์ CSV</label>
        <input type="file" name="csv" accept=".csv,text/csv" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 file:mr-4 file:rounded-lg file:border file:border-slate-200 file:bg-slate-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-100" />
      </div>
    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
      <a href="students.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">ยกเลิก</a>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-rose-500 to-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-rose-500/20 hover:opacity-90 transition-opacity duration-150"
        onclick="return confirm('ยืนยันการลบนักเรียนจากไฟล์ CSV? การดำเนินการนี้ไม่สามารถกู้คืนได้')">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
        ลบนักเรียนจาก CSV
      </button>
    </div>
  </form>
</main>

<?php layout_footer(); ?>
