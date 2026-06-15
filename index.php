<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/academic_years_repo.php';
require_once __DIR__ . '/includes/settings_repo.php';
require_once __DIR__ . '/includes/students_repo.php';
require_once __DIR__ . '/includes/users_repo.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$health = db_health_check();
$dbCfg  = $config['db'] ?? [];

$username = (string)($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? '');

// Gather stats (silent – errors are shown in the status card)
$statsYears    = 0;
$statsStudents = 0;
$statsUsers    = 0;
$currentYearName = '';

try {
    $pdoSchool = db_pdo_school();
    academic_years_require_table($pdoSchool);
    $years = academic_years_list($pdoSchool);
    $statsYears = count($years);

    $pdoLocal      = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');
    $currentYear   = null;
    if ($localCurrentId !== null) {
        foreach ($years as $y) {
            if ((int)$y['id'] === (int)$localCurrentId) {
                $currentYear = $y;
                break;
            }
        }
    }
    if ($currentYear !== null) {
        $currentYearName = (string)($currentYear['name'] ?? '');
        $statsStudents   = count(students_list_by_year($pdoSchool, (int)$currentYear['id'], $currentYearName));
    }
    if (users_table_exists($pdoLocal)) {
        $statsUsers = users_count($pdoLocal);
    }
} catch (Throwable) {
    // Stats silently fail; DB health card will surface the error.
}

layout_head('Dashboard');
layout_topbar('dashboard');
?>


<main class="mx-auto max-w-6xl px-4 py-8">

  <!-- Page header -->
  <div class="mb-7">
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">Dashboard</h2>
    <p class="mt-1 text-sm text-slate-500">
      สวัสดี <span class="font-semibold text-slate-700"><?= htmlspecialchars($username, ENT_QUOTES) ?></span>
      <span class="mx-1.5 text-slate-300">·</span>
      <?= date('j F Y') ?> เวลา <?= date('H:i') ?> น.
    </p>
  </div>

  <!-- Stat cards -->
  <div class="grid gap-4 sm:grid-cols-3">
    <a href="academic_years.php" class="group flex items-start justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-150 hover:border-blue-200 hover:shadow-md">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">ปีการศึกษา</p>
        <p class="mt-2 text-3xl font-bold text-slate-900"><?= $statsYears ?></p>
        <?php if ($currentYearName !== ''): ?>
          <p class="mt-1 text-xs text-slate-400">ปัจจุบัน: <span class="text-slate-600"><?= htmlspecialchars($currentYearName, ENT_QUOTES) ?></span></p>
        <?php else: ?>
          <p class="mt-1 text-xs text-amber-500">ยังไม่ได้ตั้งปีปัจจุบัน</p>
        <?php endif; ?>
      </div>
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-500 transition-colors duration-150 group-hover:bg-blue-100">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
      </span>
    </a>

    <a href="students.php" class="group flex items-start justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-150 hover:border-emerald-200 hover:shadow-md">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">นักเรียน</p>
        <p class="mt-2 text-3xl font-bold text-slate-900"><?= $statsStudents ?></p>
        <p class="mt-1 text-xs text-slate-400">ปีการศึกษาปัจจุบัน</p>
      </div>
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-500 transition-colors duration-150 group-hover:bg-emerald-100">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
      </span>
    </a>

    <a href="users.php" class="group flex items-start justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-150 hover:border-violet-200 hover:shadow-md">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">ผู้ใช้งานระบบ</p>
        <p class="mt-2 text-3xl font-bold text-slate-900"><?= $statsUsers ?></p>
        <p class="mt-1 text-xs text-slate-400">บัญชีผู้ใช้ทั้งหมด</p>
      </div>
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-500 transition-colors duration-150 group-hover:bg-violet-100">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
      </span>
    </a>
  </div>

  <!-- System status + Quick actions -->
  <div class="mt-6 grid gap-6 lg:grid-cols-3">

    <!-- DB Status -->
    <section class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="font-semibold text-slate-900">สถานะระบบ</h3>
          <p class="mt-0.5 text-xs text-slate-500">การเชื่อมต่อฐานข้อมูล (PDO)</p>
        </div>
        <?php if ($health['ok']): ?>
          <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            <?= htmlspecialchars($health['message'], ENT_QUOTES) ?>
          </span>
        <?php else: ?>
          <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
            <?= htmlspecialchars($health['message'], ENT_QUOTES) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Database</p>
          <p class="mt-1 text-sm text-slate-800">
            <?= htmlspecialchars((string)($dbCfg['host'] ?? ''), ENT_QUOTES) ?>:<?= htmlspecialchars((string)($dbCfg['port'] ?? ''), ENT_QUOTES) ?>
            <span class="mx-0.5 text-slate-300">/</span>
            <?= htmlspecialchars((string)($dbCfg['name'] ?? ''), ENT_QUOTES) ?>
          </p>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Driver</p>
          <p class="mt-1 text-sm text-slate-800">
            <?= htmlspecialchars((string)($health['driver'] ?? '-'), ENT_QUOTES) ?>
            <span class="mx-1 text-slate-300">·</span>
            <?= htmlspecialchars((string)($health['serverVersion'] ?? '-'), ENT_QUOTES) ?>
          </p>
        </div>
      </div>

      <?php if (!$health['ok']): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
          <p class="text-sm font-semibold text-rose-800">รายละเอียด error</p>
          <p class="mt-1 break-words font-mono text-xs text-rose-700"><?= htmlspecialchars((string)($health['error'] ?? ''), ENT_QUOTES) ?></p>
        </div>
      <?php endif; ?>
    </section>

    <!-- Quick actions -->
    <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 class="font-semibold text-slate-900">เมนูด่วน</h3>
      <p class="mt-0.5 text-xs text-slate-500">ทางลัดสำหรับงานที่ใช้บ่อย</p>

      <div class="mt-4 space-y-2">
        <a href="student_new.php" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700 transition-colors duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
          <svg class="h-4 w-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          เพิ่มนักเรียนใหม่
        </a>
        <a href="students_import.php" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700 transition-colors duration-150 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700">
          <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
          Import CSV
        </a>
        <a href="students_export.php" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700 transition-colors duration-150 hover:border-slate-300 hover:bg-slate-100">
          <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          Export CSV
        </a>
        <a href="user_new.php" class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-700 transition-colors duration-150 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700">
          <svg class="h-4 w-4 shrink-0 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
          เพิ่มผู้ใช้งานระบบ
        </a>
      </div>
    </aside>

  </div>
</main>

<?php layout_footer(); ?>
