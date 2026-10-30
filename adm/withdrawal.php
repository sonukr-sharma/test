<?php
/**
 * Admin — Withdrawals dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

/* ---------------------------------------------------------------------
 |  Filters
 * ------------------------------------------------------------------- */

$allowedStatuses = ['all', 'pending', 'completed', 'rejected'];
$statusFilter    = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
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
    'total_requests'    => 0,
    'pending_count'     => 0,
    'completed_count'   => 0,
    'rejected_count'    => 0,
    'total_withdrawn_t' => 0.0,
    'pending_amount_t'  => 0.0,
];

$withdrawals = [];
$loadError   = null;
$totalRows   = 0;
$totalPages  = 1;

try {
    $stats = array_merge($stats, db()->query('SELECT * FROM `v_withdrawal_stats`')->fetch() ?: []);

    $sql = 'SELECT w.`id`, w.`amount_t`, w.`amount_inr`, w.`payout_method`, w.`upi_id`,
                   w.`bank_account_name`, w.`bank_account_number`, w.`status`,
                   w.`requested_at`, w.`processed_at`,
                   u.`id` AS `user_id`, u.`username`, u.`telegram_id`
              FROM `withdrawals` w
              JOIN `users` u ON u.`id` = w.`user_id`';

    $params = [];
    $where  = [];

    if ($statusFilter !== 'all') {
        $where[] = 'w.`status` = :status';
        $params[':status'] = $statusFilter;
    }

    if ($search !== '') {
        $where[] = '(u.`username` LIKE :q1 OR w.`upi_id` LIKE :q2 OR u.`telegram_id` LIKE :q3)';
        $like = '%' . $search . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS `filtered`');
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $sql .= ' ORDER BY w.`requested_at` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Withdrawals load failed: ' . $e->getMessage());
    $loadError = APP_DEBUG ? $e->getMessage() : 'Unable to load withdrawals right now.';
}

/* ---------------------------------------------------------------------
 |  Helpers
 * ------------------------------------------------------------------- */

function status_badge(string $status): array
{
    return match ($status) {
        'pending'    => ['bg-amber-100 text-amber-700', 'Pending'],
        'processing' => ['bg-blue-100 text-blue-700', 'Processing'],
        'completed'  => ['bg-emerald-100 text-emerald-700', 'Completed'],
        'rejected'   => ['bg-rose-100 text-rose-700', 'Rejected'],
        'cancelled'  => ['bg-slate-100 text-slate-600', 'Cancelled'],
        default      => ['bg-slate-100 text-slate-600', ucfirst($status)],
    };
}

function payout_details(array $row): array
{
    if (($row['payout_method'] ?? 'upi') === 'upi') {
        return [(string) ($row['upi_id'] ?? '—'), ''];
    }

    return [
        (string) ($row['bank_account_number'] ?? '—'),
        (string) ($row['bank_account_name'] ?? ''),
    ];
}

$tabCounts = [
    'all'       => (int) $stats['total_requests'],
    'pending'   => (int) $stats['pending_count'],
    'completed' => (int) $stats['completed_count'],
    'rejected'  => (int) $stats['rejected_count'],
];

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$rangeLabel = 'All time';
try {
    $range = db()->query(
        'SELECT MIN(`requested_at`) AS `first_at`, MAX(`requested_at`) AS `last_at` FROM `withdrawals`'
    )->fetch();
    if (!empty($range['first_at'])) {
        $rangeLabel = date('M j, Y', strtotime((string) $range['first_at']))
            . ' - ' . date('M j, Y', strtotime((string) $range['last_at']));
    }
} catch (Throwable) {
    // keep the fallback label
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer & Earn - Withdrawals Dashboard</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome CDN -->
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
    <!-- Brand Header -->
    <div class="flex items-center justify-between px-5 py-6">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 p-0.5 shadow-md flex items-center justify-center shrink-0">
          <div class="w-full h-full rounded-full bg-amber-400 border-2 border-amber-200 flex items-center justify-center text-amber-900 font-bold text-lg">
            T
          </div>
        </div>
        <div>
          <h1 class="text-white font-semibold text-base leading-tight">Refer & Earn</h1>
          <p class="text-xs text-slate-400">Admin Panel</p>
        </div>
      </div>
      <!-- Close Button (Mobile Only) -->
      <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white p-1">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <!-- Navigation -->
    <nav class="mt-2 px-3 space-y-1.5 text-sm font-medium flex-1 overflow-y-auto">
      <a href="broadcast.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-paper-plane text-slate-400 w-5 text-center text-base"></i>
        <span>Broadcast</span>
      </a>
      <a href="withdrawal.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-wallet text-white w-5 text-center text-base"></i>
        <span>Withdrawals</span>
      </a>
      <a href="channels.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-tower-broadcast text-slate-400 w-5 text-center text-base"></i>
        <span>Channels</span>
      </a>
      <a href="devices.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-mobile-screen-button text-slate-400 w-5 text-center text-base"></i>
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
        <!-- Hamburger Button (Mobile/Tablet) -->
        <button onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden mt-0.5 sm:mt-0 w-10 h-10 rounded-xl bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] text-white hover:opacity-95 active:scale-95 shadow-md shadow-indigo-500/30 flex items-center justify-center shrink-0 transition">
          <i class="fa-solid fa-bars text-base"></i>
        </button>
        <div>
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Withdrawals</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Manage and review user withdrawal requests.</p>
        </div>
      </div>

      <!-- Signed-in admin -->
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

    <!-- Top KPI Cards Row (5 Cards) -->
    <section class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-2.5 sm:gap-5 mb-6">
      <!-- Total Withdrawal Requests -->
      <div class="bg-[#f4f1fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-purple-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#e8dffc] flex items-center justify-center text-[#7337f6] shrink-0">
          <i class="fa-solid fa-file-invoice text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Withdrawal Requests</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format((int) $stats['total_requests']) ?></p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span><?= number_format((int) $stats['pending_count']) ?> pending</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5"><?= e(t_format($stats['pending_amount_t'])) ?> awaiting review</p>
        </div>
      </div>

      <!-- Completed Withdrawals -->
      <div class="bg-[#eefbf5] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-emerald-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#d7f5e8] flex items-center justify-center text-[#18a96b] shrink-0">
          <i class="fa-solid fa-circle-check text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Completed Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format((int) $stats['completed_count']) ?></p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span><?= e(t_format($stats['total_withdrawn_t'])) ?></span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">Paid out successfully</p>
        </div>
      </div>

      <!-- Pending Withdrawals -->
      <div class="bg-[#fef9ec] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-amber-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#fdecc8] flex items-center justify-center text-amber-600 shrink-0">
          <i class="fa-solid fa-clock text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Pending Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format((int) $stats['pending_count']) ?></p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-amber-600 mt-1">
            <i class="fa-solid fa-clock text-[10px]"></i>
            <span>Needs review</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5"><?= e(t_format($stats['pending_amount_t'])) ?></p>
        </div>
      </div>

      <!-- Rejected Withdrawals -->
      <div class="bg-[#fdf2f4] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-rose-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#fce0e6] flex items-center justify-center text-[#e11d48] shrink-0">
          <i class="fa-solid fa-circle-xmark text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Rejected Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format((int) $stats['rejected_count']) ?></p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-rose-600 mt-1">
            <i class="fa-solid fa-circle-xmark text-[10px]"></i>
            <span>Declined</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">Not paid out</p>
        </div>
      </div>

      <!-- Total Amount Withdrawn -->
      <div class="bg-[#f0f6fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-blue-100/80 shadow-sm col-span-2 lg:col-span-1">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#dceafe] flex items-center justify-center text-[#2563eb] shrink-0">
          <i class="fa-solid fa-wallet text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Amount Withdrawn</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= e(t_format($stats['total_withdrawn_t'])) ?></p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span><?= e(inr_format(t_to_inr($stats['total_withdrawn_t']))) ?></span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">Across completed payouts</p>
        </div>
      </div>
    </section>

    <!-- Table Section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">

      <!-- Filter & Action Bar -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
      
        <!-- Filter Tabs -->
        <div id="filter-tabs" role="tablist" class="flex flex-wrap items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-xs font-semibold text-slate-600">
          <?php foreach (['all' => 'All', 'pending' => 'Pending', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $key => $label): ?>
            <?php $active = $statusFilter === $key; ?>
            <a href="?status=<?= e($key) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
               data-filter="<?= e($key) ?>"
               role="tab"
               aria-selected="<?= $active ? 'true' : 'false' ?>"
               class="tab-btn px-4 py-2 rounded-lg transition <?= $active ? 'bg-[#6339f9] text-white shadow-2xs' : 'hover:text-slate-900' ?>">
              <?= e($label) ?> (<?= number_format($tabCounts[$key]) ?>)
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Date Range & Export -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-medium">
            <i class="fa-regular fa-calendar text-slate-400"></i>
            <span><?= e($rangeLabel) ?></span>
            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ml-1"></i>
          </div>

          <button type="button" class="px-4 py-2 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium text-xs rounded-xl flex items-center gap-2 transition">
            <i class="fa-solid fa-download text-slate-400"></i>
            <span>Export</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-500 font-semibold">
              <th class="p-4 w-10 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </th>
              <th class="py-4 px-3">#</th>
              <th class="py-4 px-3">User</th>
              <th class="py-4 px-3">Amount</th>
              <th class="py-4 px-3">Details</th>
              <th class="py-4 px-3">Requested At</th>
              <th class="py-4 px-3">Status</th>
              <th class="py-4 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            
            <?php if ($loadError !== null): ?>
              <tr>
                <td colspan="8" class="py-10 px-4 text-center text-rose-600 font-medium">
                  <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= e($loadError) ?>
                </td>
              </tr>
            <?php elseif ($withdrawals === []): ?>
              <tr>
                <td colspan="8" class="py-10 px-4 text-center text-slate-500 font-medium">
                  <i class="fa-regular fa-folder-open mr-2"></i>No withdrawals found for this filter.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($withdrawals as $i => $w): ?>
                <?php
                  [$badgeClass, $badgeLabel] = status_badge((string) $w['status']);
                  [$detailPrimary, $detailSecondary] = payout_details($w);
                  $initial = strtoupper(substr((string) ($w['username'] ?? 'U'), 0, 1));
                  $isPending = in_array($w['status'], ['pending', 'processing'], true);
                ?>
                <tr data-status="<?= e($w['status']) ?>" class="hover:bg-slate-50/50 transition">
                  <td class="p-4 text-center">
                    <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" value="<?= (int) $w['id'] ?>" />
                  </td>
                  <td class="py-3.5 px-3 font-medium text-slate-900"><?= $i + 1 ?></td>
                  <td class="py-3.5 px-3">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                        <?= e($initial) ?>
                      </div>
                      <div>
                        <p class="font-bold text-slate-900">@<?= e($w['username'] ?? 'unknown') ?></p>
                        <p class="text-[10px] text-slate-400">ID: <?= e($w['telegram_id'] ?? '—') ?></p>
                      </div>
                    </div>
                  </td>
                  <td class="py-3.5 px-3">
                    <p class="font-bold text-slate-900"><?= e(t_format($w['amount_t'])) ?></p>
                    <p class="text-[10px] text-slate-500"><?= e(inr_format($w['amount_inr'])) ?></p>
                  </td>
                  <td class="py-3.5 px-3 <?= $detailSecondary === '' ? 'font-mono text-slate-600' : '' ?>">
                    <p class="font-medium text-slate-800"><?= e($detailPrimary) ?></p>
                    <?php if ($detailSecondary !== ''): ?>
                      <p class="text-[10px] text-slate-400"><?= e($detailSecondary) ?></p>
                    <?php endif; ?>
                  </td>
                  <td class="py-3.5 px-3 text-slate-500"><?= e(date('d M Y, H:i', strtotime((string) $w['requested_at']))) ?></td>
                  <td class="py-3.5 px-3">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold <?= e($badgeClass) ?>">
                      <?= e($badgeLabel) ?>
                    </span>
                  </td>
                  <td class="py-3.5 px-3 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <?php if ($isPending): ?>
                        <form method="post" action="withdrawal_action.php" class="inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="id" value="<?= (int) $w['id'] ?>" />
                          <input type="hidden" name="action" value="approve" />
                          <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded-lg transition shadow-2xs">
                            Approve
                          </button>
                        </form>
                        <form method="post" action="withdrawal_action.php" class="inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="id" value="<?= (int) $w['id'] ?>" />
                          <input type="hidden" name="action" value="reject" />
                          <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-[11px] rounded-lg transition">
                            Reject
                          </button>
                        </form>
                      <?php else: ?>
                        <button type="button" class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                          View
                        </button>
                      <?php endif; ?>
                      <button type="button" class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 sm:p-5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <p id="pagination-summary" class="text-xs text-slate-500">
          Showing <?= count($withdrawals) ?> of <?= number_format($totalRows) ?> withdrawals
        </p>

        <?php
          $pageUrl = static function (int $target) use ($statusFilter, $search, $perPage): string {
              $query = ['status' => $statusFilter, 'page' => $target, 'per' => $perPage];
              if ($search !== '') {
                  $query['q'] = $search;
              }
              return '?' . http_build_query($query);
          };
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
          <input type="hidden" name="status" value="<?= e($statusFilter) ?>" />
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
