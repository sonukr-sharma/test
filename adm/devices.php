<?php
/**
 * Admin — Device fingerprint review
 *
 * Lists accounts that have been seen on more devices than the configured
 * threshold, plus fingerprints shared by more than one account. Everything here
 * is advisory: nothing on this page blocks, bans or holds a withdrawal.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$threshold = device_flag_threshold();

/* ---------------------------------------------------------------------
 |  Filters
 * ------------------------------------------------------------------- */

$allowedViews = ['flagged', 'shared'];
$view = (string) ($_GET['view'] ?? 'flagged');
if (!in_array($view, $allowedViews, true)) {
    $view = 'flagged';
}

$search = trim((string) ($_GET['q'] ?? ''));

$perPage = (int) ($_GET['per'] ?? 10);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$page = max(1, (int) ($_GET['page'] ?? 1));

/* ---------------------------------------------------------------------
 |  Data
 * ------------------------------------------------------------------- */

$stats = [
    'tracked_devices' => 0,
    'tracked_users'   => 0,
    'flagged_users'   => 0,
    'shared_devices'  => 0,
];

$rows       = [];
$loadError  = null;
$totalRows  = 0;
$totalPages = 1;

try {
    $pdo = db();

    $stats['tracked_devices'] = (int) $pdo->query('SELECT COUNT(*) FROM `user_devices`')->fetchColumn();
    $stats['tracked_users']   = (int) $pdo->query('SELECT COUNT(DISTINCT `user_id`) FROM `user_devices`')->fetchColumn();
    $stats['flagged_users']   = (int) $pdo->query('SELECT COUNT(*) FROM `users` WHERE `device_flagged_at` IS NOT NULL')->fetchColumn();
    $stats['shared_devices']  = (int) $pdo->query(
        'SELECT COUNT(*) FROM (
            SELECT `fingerprint` FROM `user_devices`
             GROUP BY `fingerprint` HAVING COUNT(DISTINCT `user_id`) > 1
         ) AS `shared`'
    )->fetchColumn();

    if ($view === 'shared') {
        $where  = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(`d`.`fingerprint` LIKE :q OR `u`.`username` LIKE :q OR `u`.`telegram_id` LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = 'SELECT COUNT(DISTINCT `d`.`fingerprint`)
                       FROM `user_devices` `d`
                       JOIN `users` `u` ON `u`.`id` = `d`.`user_id`
                      WHERE ' . $whereSql;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $sql = 'SELECT `d`.`fingerprint`,
                       COUNT(DISTINCT `d`.`user_id`) AS `account_count`,
                       MIN(`d`.`first_seen_at`)      AS `first_seen_at`,
                       MAX(`d`.`last_seen_at`)       AS `last_seen_at`,
                       GROUP_CONCAT(DISTINCT `u`.`username` ORDER BY `u`.`username` SEPARATOR ", ") AS `usernames`
                  FROM `user_devices` `d`
                  JOIN `users` `u` ON `u`.`id` = `d`.`user_id`
                 WHERE ' . $whereSql . '
                 GROUP BY `d`.`fingerprint`
                HAVING COUNT(DISTINCT `d`.`user_id`) > 1
                 ORDER BY `account_count` DESC, `last_seen_at` DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } else {
        $where  = ['`u`.`device_flagged_at` IS NOT NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(`u`.`username` LIKE :q OR `u`.`telegram_id` LIKE :q OR `u`.`first_name` LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `users` `u` WHERE ' . $whereSql);
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $sql = 'SELECT `u`.`id`, `u`.`telegram_id`, `u`.`username`, `u`.`first_name`,
                       `u`.`balance_t`, `u`.`referral_count`, `u`.`status`,
                       `u`.`device_count`, `u`.`device_flagged_at`
                  FROM `users` `u`
                 WHERE ' . $whereSql . '
                 ORDER BY `u`.`device_count` DESC, `u`.`device_flagged_at` DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $loadError = 'Could not load device data.';
    error_log('adm/devices.php: ' . $e->getMessage());
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$pageUrl = static function (int $target) use ($view, $search, $perPage): string {
    $query = ['view' => $view, 'page' => $target, 'per' => $perPage];
    if ($search !== '') {
        $query['q'] = $search;
    }

    return '?' . http_build_query($query);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer &amp; Earn - Device Review</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f6f7fb;
    }
  </style>
</head>
<body class="flex flex-col lg:flex-row min-h-screen text-slate-800 antialiased overflow-x-hidden">

  <!-- Mobile Overlay Backdrop -->
  <div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 hidden lg:hidden transition-opacity"></div>

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 lg:w-60 bg-[#141627] text-slate-300 flex flex-col shrink-0 select-none transform -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen transition-transform duration-300 ease-in-out">
    <div class="flex items-center justify-between px-5 py-6">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 p-0.5 shadow-md flex items-center justify-center shrink-0">
          <div class="w-full h-full rounded-full bg-amber-400 border-2 border-amber-200 flex items-center justify-center text-amber-900 font-bold text-lg">
            T
          </div>
        </div>
        <div>
          <h1 class="text-white font-semibold text-base leading-tight">Refer &amp; Earn</h1>
          <p class="text-xs text-slate-400">Admin Panel</p>
        </div>
      </div>
      <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white p-1">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <nav class="mt-2 px-3 space-y-1.5 text-sm font-medium flex-1 overflow-y-auto">
      <a href="broadcast.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-paper-plane text-slate-400 w-5 text-center text-base"></i>
        <span>Broadcast</span>
      </a>
      <a href="withdrawal.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-wallet text-slate-400 w-5 text-center text-base"></i>
        <span>Withdrawals</span>
      </a>
      <a href="channels.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-tower-broadcast text-slate-400 w-5 text-center text-base"></i>
        <span>Channels</span>
      </a>
      <a href="devices.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-mobile-screen-button text-white w-5 text-center text-base"></i>
        <span>Devices</span>
      </a>
      <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-gear text-slate-400 w-5 text-center text-base"></i>
        <span>Settings</span>
      </a>
      <a href="account.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-user-shield text-slate-400 w-5 text-center text-base"></i>
        <span>Account</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content Area -->
  <main class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6 max-w-[1440px] mx-auto overflow-x-hidden">

    <!-- Top Header -->
    <header class="sticky top-0 z-30 flex items-center justify-between gap-4 mb-6 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-[#f6f7fb]/90 backdrop-blur-sm">
      <div class="flex items-start sm:items-center gap-3">
        <button onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden mt-0.5 sm:mt-0 w-10 h-10 rounded-xl bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] text-white hover:opacity-95 active:scale-95 shadow-md shadow-indigo-500/30 flex items-center justify-center shrink-0 transition">
          <i class="fa-solid fa-bars text-base"></i>
        </button>
        <div>
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Device Review</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Accounts seen on more than <?= $threshold ?> device<?= $threshold === 1 ? '' : 's' ?>.</p>
        </div>
      </div>

      <div class="flex items-center gap-3 shrink-0">
        <div class="hidden sm:block text-right">
          <p class="text-xs font-semibold text-slate-900"><?= e($admin['name']) ?></p>
          <p class="text-[11px] text-slate-500"><?= e(ucwords(str_replace('_', ' ', (string) $admin['role']))) ?></p>
        </div>
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] text-white flex items-center justify-center font-bold text-sm shrink-0">
          <?= e(strtoupper(substr((string) $admin['name'], 0, 1))) ?>
        </div>
        <a href="logout.php" title="Sign out" class="w-9 h-9 rounded-xl border border-slate-200 text-slate-500 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 flex items-center justify-center transition">
          <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
        </a>
      </div>
    </header>

    <?php if ($flash !== null): ?>
      <?php
        $flashType = $flash['type'] ?? 'success';
        $flashSkin = match ($flashType) {
            'error'   => ['bg-rose-50 border-rose-200 text-rose-700', 'fa-circle-exclamation'],
            'warning' => ['bg-amber-50 border-amber-200 text-amber-700', 'fa-triangle-exclamation'],
            default   => ['bg-emerald-50 border-emerald-200 text-emerald-700', 'fa-circle-check'],
        };
      ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium border <?= $flashSkin[0] ?>">
        <i class="fa-solid <?= $flashSkin[1] ?> mt-0.5 shrink-0"></i>
        <span><?= e($flash['message']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($loadError !== null): ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium border bg-rose-50 border-rose-200 text-rose-700">
        <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
        <span><?= e($loadError) ?></span>
      </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-5 mb-6">
      <div class="bg-[#f4f1fe] rounded-2xl p-3 sm:p-4 flex items-center gap-3 border border-purple-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-[#e8dffc] flex items-center justify-center text-[#7337f6] shrink-0">
          <i class="fa-solid fa-mobile-screen-button text-sm"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] text-slate-500 truncate">Devices Seen</p>
          <p class="text-base sm:text-lg font-bold text-slate-900"><?= number_format($stats['tracked_devices']) ?></p>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-3 sm:p-4 flex items-center gap-3 border border-slate-100 shadow-sm">
        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 shrink-0">
          <i class="fa-solid fa-users text-sm"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] text-slate-500 truncate">Accounts Tracked</p>
          <p class="text-base sm:text-lg font-bold text-slate-900"><?= number_format($stats['tracked_users']) ?></p>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-3 sm:p-4 flex items-center gap-3 border border-slate-100 shadow-sm">
        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
          <i class="fa-solid fa-flag text-sm"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] text-slate-500 truncate">Flagged Accounts</p>
          <p class="text-base sm:text-lg font-bold text-slate-900"><?= number_format($stats['flagged_users']) ?></p>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-3 sm:p-4 flex items-center gap-3 border border-slate-100 shadow-sm">
        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 shrink-0">
          <i class="fa-solid fa-share-nodes text-sm"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] text-slate-500 truncate">Shared Devices</p>
          <p class="text-base sm:text-lg font-bold text-slate-900"><?= number_format($stats['shared_devices']) ?></p>
        </div>
      </div>
    </section>

    <!-- Advisory notice -->
    <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium border bg-amber-50 border-amber-200 text-amber-800">
      <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
      <span>
        Fingerprints are advisory. Telegram's iOS WebView frequently produces the same fingerprint for different
        people, so a match is a review signal — never proof of abuse. Nothing here blocks or holds a withdrawal.
      </span>
    </div>

    <!-- Table -->
    <section class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-5 py-4 border-b border-slate-100">
        <div class="flex items-center gap-1.5 bg-slate-100 rounded-xl p-1 w-fit">
          <a href="?view=flagged&amp;per=<?= $perPage ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $view === 'flagged' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            Flagged Accounts
          </a>
          <a href="?view=shared&amp;per=<?= $perPage ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $view === 'shared' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
            Shared Devices
          </a>
        </div>

        <form method="get" class="flex items-center gap-2">
          <input type="hidden" name="view" value="<?= e($view) ?>" />
          <input type="hidden" name="per" value="<?= $perPage ?>" />
          <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl">
            <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs"></i>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search username or ID" class="bg-transparent text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none w-40 sm:w-52" />
          </div>
          <button type="submit" class="px-3 py-2 rounded-xl bg-[#6339f9] text-white text-xs font-semibold hover:opacity-95 transition">Search</button>
        </form>
      </div>

      <?php if ($rows === []): ?>
        <div class="px-5 py-14 text-center">
          <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
            <i class="fa-solid fa-mobile-screen-button"></i>
          </div>
          <p class="text-sm font-semibold text-slate-700">
            <?= $view === 'shared' ? 'No shared devices' : 'No flagged accounts' ?>
          </p>
          <p class="text-xs text-slate-500 mt-1">
            <?= $view === 'shared'
                ? 'No fingerprint has been seen on more than one account yet.'
                : 'No account has reached ' . $threshold . ' distinct devices yet.' ?>
          </p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100">
                <?php if ($view === 'shared'): ?>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Fingerprint</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Accounts</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Usernames</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Last Seen</th>
                <?php else: ?>
                  <th class="px-4 sm:px-5 py-3 font-semibold">User</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Devices</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Balance</th>
                  <th class="px-4 sm:px-5 py-3 font-semibold">Flagged</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php if ($view === 'shared'): ?>
                <?php foreach ($rows as $row): ?>
                  <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 sm:px-5 py-3">
                      <code class="text-[11px] text-slate-600 bg-slate-100 rounded px-1.5 py-0.5"><?= e((string) $row['fingerprint']) ?></code>
                    </td>
                    <td class="px-4 sm:px-5 py-3">
                      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700">
                        <i class="fa-solid fa-user-group text-[10px]"></i>
                        <?= (int) $row['account_count'] ?>
                      </span>
                    </td>
                    <td class="px-4 sm:px-5 py-3 text-xs text-slate-600 max-w-[280px] truncate"><?= e((string) ($row['usernames'] ?? '—')) ?></td>
                    <td class="px-4 sm:px-5 py-3 text-xs text-slate-500 whitespace-nowrap"><?= e(date('M j, Y H:i', strtotime((string) $row['last_seen_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 sm:px-5 py-3">
                      <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] text-white flex items-center justify-center font-bold text-[11px] shrink-0">
                          <?= e(strtoupper(substr((string) ($row['username'] ?: $row['first_name'] ?: '?'), 0, 1))) ?>
                        </div>
                        <div class="min-w-0">
                          <p class="text-xs font-semibold text-slate-800 truncate"><?= e((string) ($row['username'] ?: $row['first_name'] ?: 'Unknown')) ?></p>
                          <p class="text-[11px] text-slate-400">ID <?= e((string) $row['telegram_id']) ?></p>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 sm:px-5 py-3">
                      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700">
                        <i class="fa-solid fa-mobile-screen-button text-[10px]"></i>
                        <?= (int) $row['device_count'] ?>
                      </span>
                    </td>
                    <td class="px-4 sm:px-5 py-3 text-xs text-slate-600 whitespace-nowrap"><?= e(number_format((float) $row['balance_t'], 0)) ?> T</td>
                    <td class="px-4 sm:px-5 py-3 text-xs text-slate-500 whitespace-nowrap"><?= e(date('M j, Y', strtotime((string) $row['device_flagged_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 sm:px-5 py-4 border-t border-slate-100">
          <p class="text-[11px] text-slate-500">
            Showing <?= count($rows) ?> of <?= number_format($totalRows) ?> · page <?= $page ?> of <?= $totalPages ?>
          </p>

          <?php
            $windowStart = max(1, $page - 2);
            $windowEnd   = min($totalPages, $windowStart + 4);
            $windowStart = max(1, $windowEnd - 4);
          ?>

          <div class="flex items-center gap-1.5">
            <?php if ($page > 1): ?>
              <a href="<?= e($pageUrl($page - 1)) ?>" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center text-xs transition">
                <i class="fa-solid fa-chevron-left"></i>
              </a>
            <?php else: ?>
              <span class="w-8 h-8 rounded-xl border border-slate-200 text-slate-300 flex items-center justify-center text-xs">
                <i class="fa-solid fa-chevron-left"></i>
              </span>
            <?php endif; ?>

            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
              <?php if ($p === $page): ?>
                <span class="w-8 h-8 rounded-xl bg-[#6339f9] text-white font-semibold flex items-center justify-center text-xs shadow-2xs"><?= $p ?></span>
              <?php else: ?>
                <a href="<?= e($pageUrl($p)) ?>" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="<?= e($pageUrl($page + 1)) ?>" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center text-xs transition">
                <i class="fa-solid fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="w-8 h-8 rounded-xl border border-slate-200 text-slate-300 flex items-center justify-center text-xs">
                <i class="fa-solid fa-chevron-right"></i>
              </span>
            <?php endif; ?>
          </div>

          <form method="get" class="flex items-center gap-2">
            <input type="hidden" name="view" value="<?= e($view) ?>" />
            <?php if ($search !== ''): ?>
              <input type="hidden" name="q" value="<?= e($search) ?>" />
            <?php endif; ?>
            <select name="per" onchange="this.form.submit()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none">
              <?php foreach ([10, 25, 50] as $option): ?>
                <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> per page</option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      <?php endif; ?>
    </section>

  </main>

  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      sidebar.classList.toggle('-translate-x-full');
      backdrop.classList.toggle('hidden');
    }
  </script>
</body>
</html>
