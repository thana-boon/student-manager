<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/users_repo.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$pdo = null;
$tableOk = false;
$error = '';
$flash = '';

try {
    $pdo = db_pdo();
  $tableOk = users_table_exists($pdo);
  if ($tableOk) {
    users_ensure_schema($pdo);
  }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify_or_die();

    try {
        $pdo = $pdo ?: db_pdo();

        if ($_POST['action'] === 'create_table') {
            users_create_table($pdo);
          users_ensure_schema($pdo);
          $tableOk = users_table_exists($pdo);
            $flash = $tableOk ? 'สร้างตาราง users สำเร็จ' : 'สร้างตารางไม่สำเร็จ';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = [];
if ($tableOk && $pdo instanceof PDO) {
    try {
        $rows = users_list($pdo);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

layout_head('Users');
layout_topbar('users');
?>

<main class="mx-auto max-w-6xl px-4 py-8">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h2 class="text-2xl font-bold tracking-tight text-slate-900">จัดการผู้ใช้</h2>
      <p class="mt-1 text-sm text-slate-500">เพิ่ม / แก้ไข / ลบ บัญชีผู้ใช้สำหรับเข้าใช้งานระบบ</p>
    </div>

    <?php if ($tableOk): ?>
      <a href="user_new.php" class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        เพิ่มผู้ใช้
      </a>
    <?php endif; ?>
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

  <?php if (!$tableOk): ?>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/60">
      <h3 class="text-lg font-semibold">ยังไม่พบตาราง users</h3>
      <p class="mt-1 text-sm text-slate-600">กดปุ่มด้านล่างเพื่อให้ระบบสร้างตารางสำหรับเก็บชื่อผู้ใช้/รหัสผ่าน (hash) แบบอัตโนมัติ</p>

      <form method="post" class="mt-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_table" />
        <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
          สร้างตาราง users
        </button>
      </form>

      <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
        <div class="font-semibold text-slate-800">หมายเหตุ</div>
        <ul class="mt-2 list-disc pl-5">
          <li>หน้านี้จะใช้ตาราง <span class="font-mono">users</span> ในฐานข้อมูลที่ตั้งค่าใน <span class="font-mono">config.php</span></li>
          <li>หลังสร้างตารางแล้ว ให้เพิ่มผู้ใช้เพื่อใช้ล็อกอินแทน admin แบบเดิม</li>
        </ul>
      </div>
    </section>
  <?php else: ?>
    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg shadow-slate-200/60">
      <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5">
        <div class="text-sm text-slate-600">ทั้งหมด <span class="font-semibold text-slate-900"><?= count($rows) ?></span> บัญชี</div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <tr>
              <th class="px-5 py-3">ผู้ใช้</th>
              <th class="px-5 py-3">อัปเดต</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $r): ?>
              <tr class="transition-colors duration-100 hover:bg-blue-50/30">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-400 to-violet-500 text-xs font-bold text-white"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)($r['display_name'] ?? $r['username'] ?? ''), 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES) ?></span>
                    <div>
                      <div class="font-medium text-slate-900"><?= htmlspecialchars((string)($r['display_name'] ?? ''), ENT_QUOTES) ?></div>
                      <div class="text-xs text-slate-400">@<?= htmlspecialchars((string)$r['username'], ENT_QUOTES) ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars((string)$r['updated_at'], ENT_QUOTES) ?></td>
                <td class="px-5 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <a href="user_edit.php?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50 transition-colors duration-100">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                      แก้ไข
                    </a>

                    <form method="post" action="user_delete.php" onsubmit="return confirm('ยืนยันลบผู้ใช้นี้?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                      <button class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-100 transition-colors duration-100">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        ลบ
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (count($rows) === 0): ?>
              <tr>
                <td class="px-5 py-10 text-center text-sm text-slate-400" colspan="3">ยังไม่มีผู้ใช้ กด "เพิ่มผู้ใช้" เพื่อเริ่มต้น</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>

<?php layout_footer(); ?>
