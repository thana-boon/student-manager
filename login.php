<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users_repo.php';

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$needsSetup = false;
$setupHint = '';

try {
  $pdo = db_pdo();
  if (!users_table_exists($pdo)) {
    $needsSetup = true;
    $setupHint = 'ยังไม่มีตาราง users ในฐานข้อมูล';
  } elseif (users_count($pdo) === 0) {
    $needsSetup = true;
    $setupHint = 'ยังไม่มีผู้ใช้ในระบบ';
  }
} catch (Throwable $e) {
  $needsSetup = true;
  $setupHint = 'ยังเชื่อมต่อฐานข้อมูลไม่ได้: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
  } elseif ($needsSetup) {
    $error = 'ระบบยังไม่พร้อมใช้งาน กรุณาตั้งค่าผู้ใช้คนแรกก่อน';
    } elseif (!attempt_login($username, $password)) {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        header('Location: index.php');
        exit;
    }
}

$appName = (string)($config['app']['name'] ?? 'Student Manager');

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($basePath === '.' || $basePath === '/') {
  $basePath = '';
}
$faviconFile = __DIR__ . '/favicon.ico';
$v = @filemtime($faviconFile);
$faviconHref = $basePath . '/favicon.ico' . ($v ? ('?v=' . (string)$v) : '');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>เข้าสู่ระบบ • <?= htmlspecialchars($appName, ENT_QUOTES) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($faviconHref, ENT_QUOTES) ?>" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(70rem_50rem_at_15%_5%,rgba(59,130,246,.10),transparent_55%),radial-gradient(55rem_40rem_at_92%_15%,rgba(168,85,247,.09),transparent_50%),radial-gradient(60rem_35rem_at_45%_100%,rgba(16,185,129,.07),transparent_50%)]"></div>

  <div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">

      <!-- Brand -->
      <div class="mb-8 flex flex-col items-center text-center">
        <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 shadow-lg shadow-blue-500/30">
          <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
          </svg>
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900"><?= htmlspecialchars($appName, ENT_QUOTES) ?></h1>
        <p class="mt-1.5 text-sm text-slate-500">ระบบจัดการข้อมูลนักเรียน</p>
      </div>

      <!-- Card -->
      <div class="rounded-2xl border border-slate-200 bg-white/90 p-7 shadow-xl shadow-slate-200/60 backdrop-blur-sm">

        <?php if ($needsSetup): ?>
          <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <div class="flex items-center gap-2">
              <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
              <span class="text-sm font-semibold text-amber-800">ต้องตั้งค่าครั้งแรก</span>
            </div>
            <p class="mt-1.5 pl-6 text-xs text-amber-700"><?= htmlspecialchars($setupHint, ENT_QUOTES) ?></p>
            <div class="mt-3 pl-6">
              <a href="setup.php" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-50">
                ไปหน้า Setup
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
              </a>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span class="text-sm text-rose-800"><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">ชื่อผู้ใช้</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </span>
              <input name="username" autocomplete="username" value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES) ?>"
                class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
                placeholder="เช่น admin" />
            </div>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">รหัสผ่าน</label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
              </span>
              <input type="password" name="password" autocomplete="current-password"
                class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-4 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"
                placeholder="••••••••" />
            </div>
          </div>

          <button type="submit"
            class="mt-2 w-full rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-opacity duration-150 hover:opacity-90 active:opacity-80">
            เข้าสู่ระบบ
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-xs text-slate-400">© <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES) ?></p>
    </div>
  </div>
</body>
</html>
