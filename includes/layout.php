<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function layout_head(string $title): void
{
    $config = app_config();
    $appName = (string)($config['app']['name'] ?? 'Student Manager');
    $fullTitle = $title . ' • ' . $appName;

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '';
    }
    $faviconFile = __DIR__ . '/../favicon.ico';
    $v = @filemtime($faviconFile);
    $faviconHref = $basePath . '/favicon.ico' . ($v ? ('?v=' . (string)$v) : '');

    echo "<!doctype html>\n";
    echo "<html lang=\"th\">\n";
    echo "<head>\n";
    echo "  <meta charset=\"utf-8\" />\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n";
    echo '  <title>' . htmlspecialchars($fullTitle, ENT_QUOTES) . "</title>\n";
    echo '  <link rel="icon" href="' . htmlspecialchars($faviconHref, ENT_QUOTES) . '" />' . "\n";
    echo "  <script src=\"https://cdn.tailwindcss.com\"></script>\n";
    echo "  <style>\n";
    echo "    [x-cloak]{display:none!important}\n";
    echo "    .nav-scroll::-webkit-scrollbar{display:none}\n";
    echo "    .nav-scroll{-ms-overflow-style:none;scrollbar-width:none}\n";
    echo "  </style>\n";
    echo "</head>\n";
    echo "<body class=\"min-h-screen bg-slate-50 text-slate-900 antialiased\">\n";
    echo "  <div class=\"fixed inset-0 -z-10 bg-[radial-gradient(70rem_50rem_at_15%_5%,rgba(59,130,246,.08),transparent_55%),radial-gradient(55rem_40rem_at_92%_15%,rgba(168,85,247,.07),transparent_50%),radial-gradient(60rem_35rem_at_45%_100%,rgba(16,185,129,.06),transparent_50%)]\"></div>\n";
}

function layout_topbar(string $active = ''): void
{
    $config = app_config();
    $appName = (string)($config['app']['name'] ?? 'Student Manager');

    $username = '';
    $initial  = '';
    if (is_logged_in()) {
        $username = (string)($_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? '');
        $initial  = mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');
    }

    $navItems = [
        'dashboard'      => ['label' => 'Dashboard',   'href' => 'index.php'],
        'academic_years' => ['label' => 'ปีการศึกษา', 'href' => 'academic_years.php'],
        'students'       => ['label' => 'นักเรียน',    'href' => 'students.php'],
        'users'          => ['label' => 'ผู้ใช้',      'href' => 'users.php'],
    ];

    $navIcons = [
        'dashboard'      => '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>',
        'academic_years' => '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>',
        'students'       => '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>',
        'users'          => '<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
    ];

    echo "<header class=\"sticky top-0 z-40 px-3 pt-3\">\n";
    echo "  <div class=\"mx-auto max-w-6xl\">\n";
    echo "    <div class=\"flex items-center justify-between gap-2 rounded-2xl border border-white/70 bg-white/80 px-3 py-2 shadow-lg shadow-slate-200/50 backdrop-blur-xl sm:px-4\">\n";

    // Brand / logo
    echo "      <a href=\"index.php\" class=\"flex items-center gap-2.5 shrink-0 min-w-0\">\n";
    echo "        <span class=\"flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 shadow-sm\">\n";
    echo "          <svg class=\"h-4 w-4 text-white\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5\"/></svg>\n";
    echo "        </span>\n";
    echo '        <span class="hidden truncate text-sm font-semibold tracking-tight text-slate-800 md:block">' . htmlspecialchars($appName, ENT_QUOTES) . "</span>\n";
    echo "      </a>\n";

    // Nav links (scrollable on mobile)
    echo "      <nav class=\"nav-scroll flex items-center gap-0.5 overflow-x-auto\">\n";
    foreach ($navItems as $key => $item) {
        $isActive = ($active === $key);
        $icon = $navIcons[$key] ?? '';
        if ($isActive) {
            $cls = 'inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 ring-1 ring-blue-200/80';
        } else {
            $cls = 'inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors duration-150';
        }
        echo '        <a class="' . $cls . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES) . '">'
            . $icon
            . '<span class="hidden sm:inline">' . htmlspecialchars($item['label'], ENT_QUOTES) . '</span>'
            . "</a>\n";
    }
    echo "      </nav>\n";

    // User area
    if (is_logged_in()) {
        echo "      <div class=\"flex shrink-0 items-center gap-2\">\n";
        if ($username !== '') {
            echo "        <div class=\"hidden items-center gap-2 sm:flex\">\n";
            echo "          <span class=\"flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-[11px] font-bold text-white\">"
                . htmlspecialchars($initial, ENT_QUOTES) . "</span>\n";
            echo '          <span class="max-w-[10rem] truncate text-sm text-slate-700">' . htmlspecialchars($username, ENT_QUOTES) . "</span>\n";
            echo "        </div>\n";
        }
        echo "        <a href=\"logout.php\" class=\"inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 transition-colors duration-150\">\n";
        echo "          <svg class=\"h-3.5 w-3.5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9\"/></svg>\n";
        echo "          <span class=\"hidden sm:inline\">ออกจากระบบ</span>\n";
        echo "        </a>\n";
        echo "      </div>\n";
    }

    echo "    </div>\n";
    echo "  </div>\n";
    echo "</header>\n";
}

function layout_footer(): void
{
    $config = app_config();
    $appName = (string)($config['app']['name'] ?? 'Student Manager');
    $year = (int)date('Y');

    echo "<footer class=\"mx-auto max-w-6xl px-4 pb-8 pt-6\">\n";
    echo "  <div class=\"flex flex-col items-center justify-between gap-2 border-t border-slate-200 pt-5 sm:flex-row\">\n";
    echo "    <p class=\"text-xs text-slate-400\">© " . $year . ' ' . htmlspecialchars($appName, ENT_QUOTES) . " · Student Management System</p>\n";
    echo "    <p class=\"text-xs text-slate-300\">Powered by PHP &amp; Tailwind CSS</p>\n";
    echo "  </div>\n";
    echo "</footer>\n";
    echo "</body>\n</html>\n";
}
