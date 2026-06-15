<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/users_repo.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$pdo = db_pdo();
if (!users_table_exists($pdo)) {
    header('Location: users.php');
    exit;
}

$errors = [];
$username = trim((string)($_POST['username'] ?? ''));
$displayName = trim((string)($_POST['display_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_die();

    $password = (string)($_POST['password'] ?? '');
  $password2 = (string)($_POST['password2'] ?? '');

    if ($username === '') {
        $errors[] = 'กรุณากรอกชื่อผู้ใช้';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'ชื่อผู้ใช้ต้องยาว 3-50 และใช้ได้เฉพาะ a-z A-Z 0-9 _ . -';
    }

    if ($displayName === '') {
      $errors[] = 'กรุณากรอกชื่อที่แสดง (Display name)';
    } elseif (mb_strlen($displayName) > 100) {
      $errors[] = 'ชื่อที่แสดงต้องไม่เกิน 100 ตัวอักษร';
    }

    if (strlen($password) < 6) {
        $errors[] = 'รหัสผ่านต้องอย่างน้อย 6 ตัวอักษร';
    }

    if ($password !== $password2) {
      $errors[] = 'รหัสผ่านไม่ตรงกัน';
    }

    if (count($errors) === 0) {
        if (users_username_taken($pdo, $username)) {
            $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
        } else {
          users_create($pdo, $username, $displayName, $password);
            header('Location: users.php');
            exit;
        }
    }
}

layout_head('New User');
layout_topbar('users');
?>

<main class="mx-auto max-w-3xl px-4 py-8">
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">เพิ่มผู้ใช้</h2>
      <p class="mt-1 text-sm text-slate-500">สร้างบัญชีใหม่สำหรับเข้าใช้งานระบบ</p>
    </div>
    <a href="users.php" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150">
      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
      ย้อนกลับ
    </a>
  </div>

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
      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">ชื่อที่แสดง (Display name)</label>
        <input name="display_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" placeholder="เช่น ครูสมชาย"
          class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">ชื่อผู้ใช้ (Username)</label>
        <input name="username" value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" placeholder="เช่น teacher01"
          class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        <p class="mt-1 text-xs text-slate-500">รองรับ a-z A-Z 0-9 _ . -</p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">รหัสผ่าน</label>
        <input type="password" name="password" placeholder="อย่างน้อย 6 ตัวอักษร"
          class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-slate-700">ยืนยันรหัสผ่าน</label>
        <input type="password" name="password2" placeholder="พิมพ์รหัสผ่านซ้ำ"
          class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
      </div>
    </div>

    <div class="mt-6 flex items-center justify-end gap-2">
      <a href="users.php" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">ยกเลิก</a>
      <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        บันทึก
      </button>
    </div>
  </form>
</main>

<?php layout_footer(); ?>
