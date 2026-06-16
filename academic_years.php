<?php


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/academic_years_repo.php';
require_once __DIR__ . '/includes/settings_repo.php';

require_login();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

$error = '';
$flash = '';
$localCurrentId = null;

try {
  $pdo = db_pdo_school();
  academic_years_require_table($pdo);

  $pdoLocal = db_pdo();
  $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    csrf_verify_or_die();

    $action = (string)($_POST['action'] ?? '');

    try {
      $pdo = db_pdo_school();
      $pdoLocal = db_pdo();

        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('กรุณากรอกปีการศึกษา');
            }
            if (mb_strlen($name) > 20) {
                throw new RuntimeException('ปีการศึกษาต้องไม่เกิน 20 ตัวอักษร');
            }

            $id = academic_years_create($pdo, $name);

            // if no local current yet, set to newly created
            $existingLocal = settings_get_int($pdoLocal, 'current_academic_year_id');
            if ($existingLocal === null) {
              settings_set_int($pdoLocal, 'current_academic_year_id', $id);
              app_log('academic_year.set_current_local', ['id' => $id]);
            }

            app_log('academic_year.create', ['id' => $id, 'name' => $name]);
            $flash = 'เพิ่มปีการศึกษาเรียบร้อยแล้ว';
        }

        if ($action === 'set_current') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ข้อมูลไม่ถูกต้อง');
            }

          // Update is_active flag in students_db (source of truth)
          academic_years_set_current($pdo, $id);
          app_log('academic_year.set_current', ['id' => $id]);

          settings_set_int($pdoLocal, 'current_academic_year_id', $id);
          app_log('academic_year.set_current_local', ['id' => $id]);
          $flash = 'ตั้งเป็นปีปัจจุบันแล้ว';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ข้อมูลไม่ถูกต้อง');
            }

            academic_years_delete($pdo, $id);
            app_log('academic_year.delete', ['id' => $id]);
            $flash = 'ลบปีการศึกษาเรียบร้อยแล้ว';

          $current = settings_get_int($pdoLocal, 'current_academic_year_id');
          if ($current !== null && $current === $id) {
            // pick latest as local current (or clear if none)
            $rowsNow = academic_years_list($pdo);
            if (count($rowsNow) > 0) {
              $newId = (int)$rowsNow[0]['id'];
              settings_set_int($pdoLocal, 'current_academic_year_id', $newId);
              app_log('academic_year.set_current_local', ['id' => $newId, 'reason' => 'deleted_current']);
            } else {
              settings_set($pdoLocal, 'current_academic_year_id', '');
              app_log('academic_year.set_current_local', ['id' => null, 'reason' => 'deleted_current_no_rows']);
            }
          }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = [];
if ($error === '') {
    try {
      $pdo = db_pdo_school();
        $rows = academic_years_list($pdo);

    $pdoLocal = db_pdo();
    $localCurrentId = settings_get_int($pdoLocal, 'current_academic_year_id');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

layout_head('Academic Years');
layout_topbar('academic_years');
?>

<main class="mx-auto max-w-6xl px-4 py-8">
  <div class="mb-7">
    <h2 class="text-2xl font-bold tracking-tight text-slate-900">ปีการศึกษา</h2>
    <p class="mt-1 text-sm text-slate-500">กำหนดปีปัจจุบัน และเพิ่ม/ลบ ปีการศึกษา</p>
  </div>

  <?php if ($flash !== ''): ?>
    <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
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

  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h3 class="font-semibold text-slate-900">เพิ่มปีการศึกษา</h3>
    <form method="post" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create" />

      <div class="flex-1">
        <label class="mb-1.5 block text-sm font-medium text-slate-700">ปีการศึกษา</label>
        <input name="name" placeholder="เช่น 2569 หรือ 2026" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder:text-slate-400 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" />
        <p class="mt-1 text-xs text-slate-400">พิมพ์เป็นตัวเลขหรือข้อความสั้นๆ ได้</p>
      </div>

      <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-500/20 hover:opacity-90 transition-opacity duration-150 sm:mt-[1.875rem]">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        เพิ่ม
      </button>
    </form>
  </section>

  <section class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4">
      <span class="text-sm text-slate-600">ทั้งหมด <span class="font-semibold text-slate-900"><?= count($rows) ?></span> ปีการศึกษา</span>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
          <tr>
            <th class="px-5 py-3">ปีการศึกษา</th>
            <th class="px-5 py-3">สถานะ</th>
            <th class="px-5 py-3">อัปเดต</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($rows as $r): ?>
            <?php $isCurrent = ($localCurrentId !== null && (int)$r['id'] === (int)$localCurrentId); ?>
            <tr class="transition-colors duration-100 hover:bg-blue-50/30">
              <td class="px-5 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string)$r['name'], ENT_QUOTES) ?></td>
              <td class="px-5 py-3">
                <?php if ($isCurrent): ?>
                  <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    ปีปัจจุบัน
                  </span>
                <?php else: ?>
                  <span class="text-xs text-slate-400">-</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars((string)$r['updated_at'], ENT_QUOTES) ?></td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-2">
                  <?php if (!$isCurrent): ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_current" />
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                      <button class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 transition-colors duration-100">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ตั้งเป็นปัจจุบัน
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post" onsubmit="return confirm('ยืนยันลบปีการศึกษานี้?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete" />
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
              <td class="px-5 py-10 text-center text-sm text-slate-400" colspan="4">ยังไม่มีปีการศึกษา เพิ่มจากฟอร์มด้านบนได้เลย</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">
      ข้อมูลจากฐาน <span class="font-mono text-slate-500">students_db</span> · "ปีปัจจุบัน" บันทึกใน <span class="font-mono text-slate-500">student_manager</span>
    </div>
  </section>
</main>

<?php layout_footer(); ?>
