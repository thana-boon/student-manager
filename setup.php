<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/users_repo.php';

// Setup page: used only when there is no user yet.
$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$error = '';
$done = false;

try {
    $pdo = db_pdo();

    if (!users_table_exists($pdo)) {
        users_create_table($pdo);
    }

    if (users_count($pdo) > 0) {
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$errors = [];
$username = trim((string)($_POST['username'] ?? ''));
$displayName = trim((string)($_POST['display_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
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

    try {
        $pdo = db_pdo();
        if (users_username_taken($pdo, $username)) {
            $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
        }

        if (count($errors) === 0) {
          users_create($pdo, $username, $displayName, $password);
            $done = true;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

layout_head('Setup');
?>

<main class="mx-auto max-w-3xl px-4 py-10">
  <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/60">
    <h1 class="text-2xl font-semibold tracking-tight">ตั้งค่าระบบครั้งแรก</h1>
    <p class="mt-1 text-sm text-slate-600">สร้างผู้ใช้คนแรก (admin) ในฐานข้อมูล <span class="font-mono">student_manager</span></p>

    <?php if ($error !== ''): ?>
      <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        <div class="font-semibold">เชื่อมต่อฐานข้อมูลไม่ได้</div>
        <div class="mt-1 break-words font-mono text-xs text-rose-800/90"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($done): ?>
      <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        สร้างผู้ใช้สำเร็จแล้ว ไปที่หน้า <a class="underline" href="login.php">Login</a>
      </div>
    <?php else: ?>
      <?php if (count($errors) > 0): ?>
        <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
          <div class="font-semibold">กรุณาตรวจสอบ</div>
          <ul class="mt-1 list-disc pl-5">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="mt-6 space-y-4">
        <?= csrf_field() ?>

        <div>
          <label class="mb-1 block text-sm text-slate-700">Display name</label>
          <input name="display_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>" placeholder="เช่น ครูสมชาย"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        </div>

        <div>
          <label class="mb-1 block text-sm text-slate-700">Username</label>
          <input name="username" value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" placeholder="เช่น admin"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm text-slate-700">Password</label>
            <input type="password" name="password" placeholder="อย่างน้อย 6 ตัวอักษร"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-slate-700">Confirm Password</label>
            <input type="password" name="password2" placeholder="พิมพ์ซ้ำ"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
          </div>
        </div>

        <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-blue-500 to-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:from-blue-400 hover:to-violet-400">
          สร้างผู้ใช้คนแรก
        </button>

        <p class="text-xs text-slate-500">เมื่อสร้างผู้ใช้แล้ว หน้านี้จะพาไป login และไม่ให้สร้างซ้ำ</p>
      </form>
    <?php endif; ?>
  </div>

  <p class="mt-6 text-center text-xs text-slate-500">Tip: ใช้หน้านี้เฉพาะตอนเริ่มต้นระบบครั้งแรกเท่านั้น</p>
</main>

<?php layout_footer(); ?>
