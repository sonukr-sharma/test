<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$leaderboard = [];
$myRank      = null;

try {
    $stmt = db()->query(
        'SELECT `user_id`, `username`, `avatar_url`, `referral_count`, `total_earned_t`, `rank_position`
           FROM `v_leaderboard`
          ORDER BY `rank_position` ASC
          LIMIT 50'
    );
    $leaderboard = $stmt->fetchAll();

    foreach ($leaderboard as $row) {
        if ((int) $row['user_id'] === (int) $user['id']) {
            $myRank = $row;
            break;
        }
    }

    if ($myRank === null) {
        $stmt = db()->prepare(
            'SELECT `user_id`, `username`, `avatar_url`, `referral_count`, `total_earned_t`, `rank_position`
               FROM `v_leaderboard`
              WHERE `user_id` = :uid
              LIMIT 1'
        );
        $stmt->execute([':uid' => $user['id']]);
        $myRank = $stmt->fetch() ?: null;
    }
} catch (Throwable $e) {
    error_log('leaderboard.php failed: ' . $e->getMessage());
}

$podium = array_slice($leaderboard, 0, 3);
$rest   = array_slice($leaderboard, 3, 7);

$first  = $podium[0] ?? null;
$second = $podium[1] ?? null;
$third  = $podium[2] ?? null;

/**
 * Display name for a leaderboard row.
 *
 * @param array<string, mixed>|null $row
 */
function lb_name(?array $row): string
{
    if ($row === null) {
        return '—';
    }

    $name = trim((string) ($row['username'] ?? ''));

    return $name !== '' ? $name : 'user_' . (int) $row['user_id'];
}

/**
 * Avatar URL with a generated placeholder fallback.
 *
 * @param array<string, mixed>|null $row
 */
function lb_avatar(?array $row, string $initials = 'U'): string
{
    $url = trim((string) ($row['avatar_url'] ?? ''));

    return $url !== '' ? $url : 'https://placehold.co/100x100/e2e8f0/334155?text=' . rawurlencode($initials);
}

/**
 * Two-letter initials for the placeholder avatar.
 *
 * @param array<string, mixed>|null $row
 */
function lb_initials(?array $row): string
{
    $name = lb_name($row);
    $name = ltrim($name, '@');

    return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'U', 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer & Earn - Leaderboard</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome 6 CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <!-- Google Fonts (Plus Jakarta Sans) -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #ebe9f5;
    }
    .header-bg {
      background: linear-gradient(180deg, #5925dc 0%, #7038f5 75%, #f4f3fa 100%);
    }
    .btn-purple {
      background: linear-gradient(135deg, #6b34f5 0%, #561fdb 100%);
      box-shadow: 0 4px 14px -2px rgba(91, 36, 224, 0.45);
    }
    .banner-bg {
      background: radial-gradient(circle at 78% 50%, #8b5cf6 0%, #5b21d6 52%, #4315a8 100%);
    }
    .card-shadow {
      box-shadow: 0 8px 24px -4px rgba(28, 17, 65, 0.06);
    }
    /* Custom 3D Gold T Coin */
        .t-coin {
          background: url('asset/coin.webp') center / cover no-repeat;
          box-shadow: 0 2px 5px rgba(245, 158, 11, 0.25);
          border: none;
        }
        .t-coin-inner {
          display: none;
        }
  </style>
</head>
<body class="flex justify-center min-h-screen text-slate-900 antialiased sm:py-6">

  <!-- Mobile App Container (Same 430px dimensions) -->
  <div class="w-full max-w-[430px] min-h-screen sm:min-h-[860px] bg-[#f4f3fa] relative pb-24 overflow-hidden">

    <!-- Toast Notification -->
        <div id="toast" class="fixed bottom-24 left-1/2 -translate-x-1/2 z-50 bg-slate-900/95 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-lg flex items-center gap-2 transition-all duration-300 opacity-0 pointer-events-none translate-y-3">
      <i class="fa-solid fa-circle-check text-emerald-400"></i>
      <span id="toastMsg">Notification</span>
    </div>

    <!-- Top Purple Header Background -->
    <div class="header-bg pt-5 pb-16 px-4">
      <div class="flex items-center justify-between">
        <!-- Left: Coin + Title -->
        <div class="flex items-center gap-3">
          <!-- Gold T Coin -->
          <div class="w-12 h-12 rounded-full t-coin flex items-center justify-center shrink-0">
            <div class="w-9 h-9 rounded-full t-coin-inner flex items-center justify-center">
              <span class="text-amber-900 font-extrabold text-xl drop-shadow-[0_1px_1px_rgba(255,255,255,0.6)]">T</span>
            </div>
          </div>
          <div>
            <h1 class="text-white font-extrabold text-xl tracking-tight leading-tight">Refer & Earn</h1>
            <p class="text-white/85 text-xs font-medium mt-0.5">Invite Friends <span class="mx-0.5">•</span> Earn Rewards</p>
          </div>
        </div>

        <!-- Right: Masked Telegram Chat ID -->
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm border border-white/15 rounded-full px-3 py-1.5">
                    <i class="fa-brands fa-telegram text-white text-sm"></i>
                    <span class="text-white text-xs font-bold tracking-wide">ID: <?= e(mask_telegram_id($user['telegram_id'])) ?></span>
                  </div>
                </div>
      </div>
    </div>

    <!-- Main Rounded Sheet Wrapper -->
    <div class="bg-[#f8f7fc] rounded-t-[30px] -mt-10 pt-3.5 px-3.5 space-y-3.5 relative z-10">

      <!-- Leaderboard Hero Banner -->
            <div class="rounded-2xl overflow-hidden shadow-lg">
              <img src="asset/leaderboard-page.webp" alt="Leaderboard - Top users with highest referrals" class="w-full h-auto block" />
            </div>

      <!-- Top 3 Podium Cards -->
            <div class="grid grid-cols-3 gap-2.5 pt-8 items-end">

        <!-- 2nd Place Card -->
        <div class="bg-[#eff5ff] border border-blue-200/70 rounded-2xl p-2.5 pt-6 flex flex-col items-center relative card-shadow">
          <!-- Silver #2 Badge -->
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 w-8 h-8 flex items-center justify-center">
            <svg viewBox="0 0 40 40" class="w-8 h-8 drop-shadow">
              <circle cx="20" cy="20" r="16" fill="url(#silverGrad)" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="4 2" />
              <circle cx="20" cy="20" r="12" fill="url(#silverInner)" stroke="#94a3b8" stroke-width="1" />
              <text x="20" y="25" text-anchor="middle" fill="#ffffff" font-size="14" font-weight="800">2</text>
              <defs>
                <linearGradient id="silverGrad" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#e2e8f0" />
                  <stop offset="100%" stop-color="#64748b" />
                </linearGradient>
                <linearGradient id="silverInner" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#94a3b8" />
                  <stop offset="100%" stop-color="#64748b" />
                </linearGradient>
              </defs>
            </svg>
          </div>

          <!-- Avatar -->
          <div class="w-14 h-14 rounded-full p-0.5 bg-gradient-to-b from-slate-300 to-slate-400 shadow-sm">
            <img src="<?= e(lb_avatar($second, lb_initials($second))) ?>"
                 onerror="this.src='https://placehold.co/100x100/e2e8f0/475569?text=<?= e(lb_initials($second)) ?>'"
                 alt="<?= e(lb_name($second)) ?>"
                 class="w-full h-full rounded-full object-cover" />
          </div>

          <div class="mt-2 text-xs font-extrabold text-slate-900 truncate max-w-full"><?= e(lb_name($second)) ?></div>
          <div class="text-[10px] font-medium text-slate-500 mt-0.5"><?= e(number_format((int) ($second['referral_count'] ?? 0))) ?> Referrals</div>

          <!-- Reward Pill -->
          <div class="mt-2.5 w-full bg-[#e1ecff] rounded-xl py-1.5 px-2 flex items-center justify-center gap-1.5">
            <div class="w-4 h-4 rounded-full t-coin flex items-center justify-center shrink-0">
              <span class="text-amber-900 font-extrabold text-[9px]">T</span>
            </div>
            <span class="text-[11px] font-extrabold text-[#432ad8] whitespace-nowrap"><?= e(t_format((float) ($second['total_earned_t'] ?? 0))) ?></span>
          </div>
        </div>

        <!-- 1st Place Card (Elevated & Highlighted) -->
        <div class="bg-[#fff9e5] border-2 border-amber-200/90 rounded-2xl p-2.5 pt-7 pb-3.5 flex flex-col items-center relative shadow-md -translate-y-2">
          <!-- Gold #1 Crown -->
          <div class="absolute -top-6 left-1/2 -translate-x-1/2 w-11 h-10 flex items-center justify-center">
            <svg viewBox="0 0 54 44" class="w-11 h-10 drop-shadow-md">
              <path d="M6 34 L2 12 L15 21 L27 6 L39 21 L52 12 L48 34 Z" fill="url(#crownGold)" stroke="#d97706" stroke-width="1.5" stroke-linejoin="round" />
              <rect x="6" y="34" width="42" height="6" rx="3" fill="#d97706" />
              <circle cx="2" cy="10" r="2.5" fill="#fbbf24" />
              <circle cx="27" cy="4" r="3" fill="#fbbf24" />
              <circle cx="52" cy="10" r="2.5" fill="#fbbf24" />
              <text x="27" y="30" text-anchor="middle" fill="#ffffff" font-size="15" font-weight="800">1</text>
              <defs>
                <linearGradient id="crownGold" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#fde047" />
                  <stop offset="100%" stop-color="#f59e0b" />
                </linearGradient>
              </defs>
            </svg>
          </div>

          <!-- Avatar -->
          <div class="w-16 h-16 rounded-full p-0.5 bg-gradient-to-b from-amber-300 to-amber-500 shadow">
            <img src="<?= e(lb_avatar($first, lb_initials($first))) ?>"
                 onerror="this.src='https://placehold.co/100x100/fef3c7/92400e?text=<?= e(lb_initials($first)) ?>'"
                 alt="<?= e(lb_name($first)) ?>"
                 class="w-full h-full rounded-full object-cover" />
          </div>

          <div class="mt-2 text-xs font-extrabold text-slate-900 truncate max-w-full"><?= e(lb_name($first)) ?></div>
          <div class="text-[10px] font-medium text-slate-500 mt-0.5"><?= e(number_format((int) ($first['referral_count'] ?? 0))) ?> Referrals</div>

          <!-- Reward Pill -->
          <div class="mt-2.5 w-full bg-[#ffedb8] rounded-xl py-1.5 px-2 flex items-center justify-center gap-1.5">
            <div class="w-4 h-4 rounded-full t-coin flex items-center justify-center shrink-0">
              <span class="text-amber-900 font-extrabold text-[9px]">T</span>
            </div>
            <span class="text-xs font-extrabold text-[#432ad8] whitespace-nowrap"><?= e(t_format((float) ($first['total_earned_t'] ?? 0))) ?></span>
          </div>
        </div>

        <!-- 3rd Place Card -->
        <div class="bg-[#fff1f4] border border-rose-200/70 rounded-2xl p-2.5 pt-6 flex flex-col items-center relative card-shadow">
          <!-- Bronze #3 Badge -->
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 w-8 h-8 flex items-center justify-center">
            <svg viewBox="0 0 40 40" class="w-8 h-8 drop-shadow">
              <circle cx="20" cy="20" r="16" fill="url(#bronzeGrad)" stroke="#fdba74" stroke-width="2" stroke-dasharray="4 2" />
              <circle cx="20" cy="20" r="12" fill="url(#bronzeInner)" stroke="#ea580c" stroke-width="1" />
              <text x="20" y="25" text-anchor="middle" fill="#ffffff" font-size="14" font-weight="800">3</text>
              <defs>
                <linearGradient id="bronzeGrad" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#fdba74" />
                  <stop offset="100%" stop-color="#c2410c" />
                </linearGradient>
                <linearGradient id="bronzeInner" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#ea580c" />
                  <stop offset="100%" stop-color="#9a3412" />
                </linearGradient>
              </defs>
            </svg>
          </div>

          <!-- Avatar -->
          <div class="w-14 h-14 rounded-full p-0.5 bg-gradient-to-b from-amber-600/60 to-orange-700/60 shadow-sm">
            <img src="<?= e(lb_avatar($third, lb_initials($third))) ?>"
                 onerror="this.src='https://placehold.co/100x100/ffe4e6/9f1239?text=<?= e(lb_initials($third)) ?>'"
                 alt="<?= e(lb_name($third)) ?>"
                 class="w-full h-full rounded-full object-cover" />
          </div>

          <div class="mt-2 text-xs font-extrabold text-slate-900 truncate max-w-full"><?= e(lb_name($third)) ?></div>
          <div class="text-[10px] font-medium text-slate-500 mt-0.5"><?= e(number_format((int) ($third['referral_count'] ?? 0))) ?> Referrals</div>

          <!-- Reward Pill -->
          <div class="mt-2.5 w-full bg-[#ffe2e8] rounded-xl py-1.5 px-2 flex items-center justify-center gap-1.5">
            <div class="w-4 h-4 rounded-full t-coin flex items-center justify-center shrink-0">
              <span class="text-amber-900 font-extrabold text-[9px]">T</span>
            </div>
            <span class="text-[11px] font-extrabold text-[#432ad8] whitespace-nowrap"><?= e(t_format((float) ($third['total_earned_t'] ?? 0))) ?></span>
          </div>
        </div>

      </div>

      <!-- Leaderboard Rankings Table Card -->
      <div class="bg-white rounded-3xl p-3 card-shadow">
        <!-- Table Column Header -->
        <div class="bg-[#f4f3fa] rounded-xl px-3.5 py-2 grid grid-cols-12 items-center text-[11px] font-semibold text-slate-500">
          <div class="col-span-1">#</div>
          <div class="col-span-5">User</div>
          <div class="col-span-3 text-center">Referrals</div>
          <div class="col-span-3 text-right">Earned</div>
        </div>

        <!-- Ranks 4 to 10 List -->
        <div id="leaderboardRows" class="divide-y divide-slate-100 mt-1">
          <?php if ($rest === []): ?>
            <div class="py-8 text-center">
              <div class="w-12 h-12 mx-auto rounded-full bg-[#f4f4f8] text-slate-400 flex items-center justify-center">
                <i class="fa-solid fa-trophy text-lg"></i>
              </div>
              <p class="mt-2.5 text-xs font-semibold text-slate-500">No rankings yet</p>
              <p class="mt-1 text-[11px] text-slate-400">Invite friends to climb the leaderboard.</p>
            </div>
          <?php else: ?>
            <?php foreach ($rest as $row): ?>
              <?php $initials = lb_initials($row); ?>
              <div class="py-2.5 px-2.5 grid grid-cols-12 items-center">
                <div class="col-span-1 text-xs font-bold text-slate-600"><?= e((string) (int) $row['rank_position']) ?></div>
                <div class="col-span-5 flex items-center gap-2.5 min-w-0">
                  <img src="<?= e(lb_avatar($row, $initials)) ?>"
                       onerror="this.src='https://placehold.co/80x80/e2e8f0/334155?text=<?= e($initials) ?>'"
                       alt="<?= e(lb_name($row)) ?>" class="w-9 h-9 rounded-full object-cover shrink-0 border border-slate-200" />
                  <span class="text-xs font-bold text-slate-900 truncate"><?= e(lb_name($row)) ?></span>
                </div>
                <div class="col-span-3 text-center text-xs font-semibold text-slate-600"><?= e(number_format((int) $row['referral_count'])) ?></div>
                <div class="col-span-3 flex items-center justify-end gap-1.5">
                  <div class="w-4 h-4 rounded-full t-coin flex items-center justify-center shrink-0">
                    <span class="text-amber-900 font-extrabold text-[9px]">T</span>
                  </div>
                  <span class="text-xs font-extrabold text-[#5925dc] whitespace-nowrap"><?= e(t_format((float) $row['total_earned_t'])) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Current User Highlight Bar -->
        <div class="mt-2 bg-[#f1edff] border border-purple-200/70 rounded-2xl py-2.5 px-3 grid grid-cols-12 items-center">
          <div class="col-span-2 text-xs font-extrabold text-[#5925dc]">#<?= e((string) (int) ($myRank['rank_position'] ?? 0)) ?></div>
          <div class="col-span-4 flex items-center gap-2.5 min-w-0 -ml-2">
            <img src="<?= e(lb_avatar($myRank, 'YOU')) ?>"
                 onerror="this.src='https://placehold.co/80x80/ddd6fe/5b21b6?text=YOU'"
                 alt="You" class="w-9 h-9 rounded-full object-cover shrink-0 border-2 border-white shadow-sm" />
            <span class="text-xs font-extrabold text-[#5925dc] truncate">You</span>
          </div>
          <div class="col-span-3 text-center text-xs font-semibold text-slate-600"><?= e(number_format((int) ($myRank['referral_count'] ?? $user['referral_count']))) ?></div>
          <div class="col-span-3 flex items-center justify-end gap-1.5">
            <div class="w-4 h-4 rounded-full t-coin flex items-center justify-center shrink-0">
              <span class="text-amber-900 font-extrabold text-[9px]">T</span>
            </div>
            <span class="text-xs font-extrabold text-[#5925dc] whitespace-nowrap"><?= e(t_format((float) ($myRank['total_earned_t'] ?? $user['total_earned_t']))) ?></span>
          </div>
        </div>

      </div>

    </div>

    <!-- Bottom Navigation Bar (Leaderboard Active) -->
    <nav class="fixed sm:absolute bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] bg-white rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-6 pt-2.5 pb-2 flex items-center justify-around z-40">
      <!-- Home -->
            <a href="index.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20 relative">
        <i class="fa-solid fa-house text-lg"></i>
        <span class="text-[11px] font-semibold">Home</span>
        <span class="nav-indicator hidden absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
            </a>

      <!-- Invite -->
            <a href="refer-earn.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20 relative">
        <i class="fa-solid fa-share-nodes text-lg"></i>
        <span class="text-[11px] font-semibold">Invite</span>
        <span class="nav-indicator hidden absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
            </a>

      <!-- Leaderboard (Active) -->
            <a href="leaderboard.php" class="flex flex-col items-center gap-1 text-[#5925dc] relative pb-1.5 w-20">
        <i class="fa-solid fa-trophy text-lg"></i>
        <span class="text-[11px] font-bold">Ranking</span>
        <span class="nav-indicator absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
            </a>

            <!-- Withdraw -->
                  <a href="withdraw.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
              <i class="fa-solid fa-wallet text-lg"></i>
              <span class="text-[11px] font-semibold">Withdraw</span>
            </a>
          </nav>

  </div>

  <!-- Interactive Scripts -->
  <script>
    let toastTimeout;

    function showToast(message) {
      const toast = document.getElementById('toast');
      const toastMsg = document.getElementById('toastMsg');
      toastMsg.textContent = message;

          toast.classList.remove('opacity-0', 'translate-y-3');
      toast.classList.add('opacity-100', 'translate-y-0');

      clearTimeout(toastTimeout);
      toastTimeout = setTimeout(() => {
        toast.classList.remove('opacity-100', 'translate-y-0');
            toast.classList.add('opacity-0', 'translate-y-3');
      }, 2000);
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
