<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$flash = flash_get();

$totalEarnedT  = (float) $user['total_earned_t'];
$referralCount = (int) $user['referral_count'];

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$transactions = [];
$totalRows    = 0;

try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM `wallet_transactions` WHERE `user_id` = :uid');
    $stmt->execute([':uid' => $user['id']]);
    $totalRows = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT `type`, `direction`, `amount_t`, `balance_after_t`, `description`, `created_at`
           FROM `wallet_transactions`
          WHERE `user_id` = :uid
          ORDER BY `created_at` DESC, `id` DESC
          LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue(':uid', $user['id'], PDO::PARAM_INT);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('activity.php failed: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Activity - Refer & Earn</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script src="asset/webapp-auth.js"></script>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome 6 CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <!-- Google Fonts (Plus Jakarta Sans) -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #f4f3fa;
    }
    .header-bg {
      background: linear-gradient(180deg, #5925dc 0%, #7038f5 65%, #f4f3fa 100%);
    }
    .card-shadow {
      box-shadow: 0 8px 24px -4px rgba(28, 17, 65, 0.06);
    }
    /* Custom Gold T Coin */
    .t-coin {
      background: url('asset/coin.webp') center / cover no-repeat;
      box-shadow: 0 4px 8px rgba(245, 158, 11, 0.25);
      border: none;
    }
    .t-coin-inner {
      display: none;
    }
  </style>
</head>
<body class="flex justify-center min-h-screen text-slate-900 antialiased">

  <!-- Mobile App Container -->
  <div class="w-full max-w-[430px] min-h-screen bg-[#f4f3fa] relative pb-24 overflow-x-hidden shadow-2xl">

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-24 left-1/2 -translate-x-1/2 z-50 bg-slate-900/95 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-lg flex items-center gap-2 transition-all duration-300 opacity-0 pointer-events-none translate-y-3">
      <i class="fa-solid fa-circle-check text-emerald-400"></i>
      <span id="toastMsg">Notification</span>
    </div>

    <!-- Top Purple Header Background -->
    <div class="header-bg pt-5 pb-20 px-4">
      <div class="flex items-center justify-between">
        <!-- Left: Back + Title -->
        <div class="flex items-center gap-3">
          <a href="index.php" class="w-10 h-10 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center border border-white/10 active:scale-95 transition">
            <i class="fa-solid fa-arrow-left text-white text-lg"></i>
          </a>
          <div>
            <h1 class="text-white font-extrabold text-xl tracking-tight leading-tight">Activity</h1>
            <p class="text-white/85 text-xs font-medium mt-0.5">All your referral rewards</p>
          </div>
        </div>

        <!-- Right: Masked Telegram Chat ID -->
                <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm border border-white/10 rounded-full px-3 py-1.5">
                  <i class="fa-brands fa-telegram text-white text-sm"></i>
                  <span class="text-white text-xs font-bold tracking-wide">ID: <?= e(mask_telegram_id($user['telegram_id'])) ?></span>
                </div>
      </div>
    </div>

    <!-- Main Content Stack -->
    <div class="px-3.5 -mt-14 space-y-3.5">

      <!-- Activity Summary Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-xs font-semibold text-slate-500">Total Earned</div>
            <div class="flex items-center gap-2 mt-1">
              <div class="w-8 h-8 rounded-full t-coin flex items-center justify-center shrink-0"></div>
              <div class="text-2xl font-extrabold text-slate-900 leading-none"><?= e(t_format($totalEarnedT)) ?></div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs font-semibold text-slate-500">Total Referrals</div>
            <div class="text-2xl font-extrabold text-[#5925dc] leading-none mt-1"><?= e(number_format($referralCount)) ?></div>
          </div>
        </div>
      </div>

      <!-- All Activity List Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="flex items-center gap-2 mb-3">
          <i class="fa-regular fa-clock text-slate-500 text-sm"></i>
          <h3 class="font-extrabold text-sm text-slate-900">All Activity</h3>
        </div>

        <div class="divide-y divide-slate-100">
          <?php if ($transactions === []): ?>
            <div class="py-8 text-center">
              <div class="w-12 h-12 mx-auto rounded-full bg-[#f4f4f8] text-slate-400 flex items-center justify-center">
                <i class="fa-regular fa-clock text-lg"></i>
              </div>
              <p class="mt-2.5 text-xs font-semibold text-slate-500">No activity yet</p>
              <p class="mt-1 text-[11px] text-slate-400">Your referral rewards will appear here.</p>
            </div>
          <?php else: ?>
            <?php foreach ($transactions as $index => $tx): ?>
              <?php
                $style    = wallet_type_style((string) $tx['type']);
                $isCredit = ($tx['direction'] ?? 'credit') === 'credit';
                $rowClass = $index === 0 ? 'py-2.5 first:pt-0' : 'py-2.5';
                $rowClass .= $index === count($transactions) - 1 ? ' last:pb-0' : '';
              ?>
              <div class="<?= e($rowClass) ?> flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-10 h-10 rounded-full <?= e($style['bg']) ?> <?= e($style['text']) ?> flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= e($style['icon']) ?> text-sm"></i>
                  </div>
                  <div class="min-w-0">
                    <div class="text-xs font-extrabold text-slate-900"><?= e(wallet_type_label((string) $tx['type'])) ?></div>
                    <div class="text-[11px] font-medium text-slate-500 mt-0.5 truncate"><?= e($tx['description'] ?: 'Wallet update') ?></div>
                  </div>
                </div>
                <div class="text-right shrink-0 ml-2">
                  <div class="text-xs font-extrabold <?= $isCredit ? 'text-emerald-600' : 'text-rose-500' ?>">
                    <?= $isCredit ? '+' : '-' ?><?= e(t_format((float) $tx['amount_t'])) ?>
                  </div>
                  <div class="text-[10px] font-medium text-slate-400 mt-0.5"><?= e(activity_time($tx['created_at'] ?? null)) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
            <?php if ($page > 1): ?>
              <a href="activity.php?page=<?= e((string) ($page - 1)) ?>" class="text-[11px] font-bold text-[#5925dc] flex items-center gap-1">
                <i class="fa-solid fa-chevron-left text-[9px]"></i><span>Previous</span>
              </a>
            <?php else: ?>
              <span class="text-[11px] font-bold text-slate-300 flex items-center gap-1">
                <i class="fa-solid fa-chevron-left text-[9px]"></i><span>Previous</span>
              </span>
            <?php endif; ?>

            <span class="text-[10px] font-semibold text-slate-400">Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></span>

            <?php if ($page < $totalPages): ?>
              <a href="activity.php?page=<?= e((string) ($page + 1)) ?>" class="text-[11px] font-bold text-[#5925dc] flex items-center gap-1">
                <span>Next</span><i class="fa-solid fa-chevron-right text-[9px]"></i>
              </a>
            <?php else: ?>
              <span class="text-[11px] font-bold text-slate-300 flex items-center gap-1">
                <span>Next</span><i class="fa-solid fa-chevron-right text-[9px]"></i>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] bg-white rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-6 pt-2.5 pb-2 flex items-center justify-around z-50">
      <!-- Home -->
      <a href="index.php" class="flex flex-col items-center gap-1 text-[#5925dc] relative pb-1.5 w-20">
        <i class="fa-solid fa-house text-lg"></i>
        <span class="text-[11px] font-bold">Home</span>
        <span class="absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
      </a>

      <!-- Invite -->
      <a href="refer-earn.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
        <i class="fa-solid fa-share-nodes text-lg"></i>
        <span class="text-[11px] font-semibold">Invite</span>
      </a>

      <!-- Leaderboard -->
      <a href="leaderboard.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
        <i class="fa-solid fa-trophy text-lg"></i>
        <span class="text-[11px] font-semibold">Ranking</span>
      </a>

            <!-- Withdraw -->
            <a href="withdraw.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
              <i class="fa-solid fa-wallet text-lg"></i>
              <span class="text-[11px] font-semibold">Withdraw</span>
            </a>
          </nav>

  </div>

  <script>
    let toastTimeout;

    function showToast(message, isError) {
      const toast = document.getElementById('toast');
      const toastMsg = document.getElementById('toastMsg');
      const icon = toast.querySelector('i');
      toastMsg.textContent = message;
      icon.className = isError ? 'fa-solid fa-circle-exclamation text-rose-400' : 'fa-solid fa-circle-check text-emerald-400';

      toast.classList.remove('opacity-0', 'translate-y-3');
      toast.classList.add('opacity-100', 'translate-y-0');

      clearTimeout(toastTimeout);
      toastTimeout = setTimeout(() => {
        toast.classList.remove('opacity-100', 'translate-y-0');
        toast.classList.add('opacity-0', 'translate-y-3');
      }, 2000);
    }

    const serverFlash = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (serverFlash && serverFlash.message) {
      showToast(serverFlash.message, serverFlash.type === 'error');
    }

    if (window.Telegram && window.Telegram.WebApp) {
      const tg = window.Telegram.WebApp;
      tg.ready();
      tg.expand();
      tg.setHeaderColor('#5925dc');
    }
  </script>
</body>
</html>