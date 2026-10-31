<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$rewardPerReferral = (float) setting('referral_reward_t', 150);
$minWithdrawalT    = (float) setting('min_withdrawal_t', 2000);
$balanceT          = (float) $user['balance_t'];
$totalEarnedT      = (float) $user['total_earned_t'];
$referralCount     = (int) $user['referral_count'];
$myInviteLink      = invite_link($user);

$recentActivity = [];
try {
    $stmt = db()->prepare(
        'SELECT `type`, `direction`, `amount_t`, `description`, `created_at`
           FROM `wallet_transactions`
          WHERE `user_id` = :uid
          ORDER BY `created_at` DESC, `id` DESC
          LIMIT 3'
    );
    $stmt->execute([':uid' => $user['id']]);
    $recentActivity = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('index.php recent activity failed: ' . $e->getMessage());
}

$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer & Earn</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script src="asset/webapp-auth.js"></script>
  <script src="asset/device-guard.js"></script>
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
    .btn-purple {
      background: linear-gradient(135deg, #6b34f5 0%, #5821db 100%);
      box-shadow: 0 6px 16px -3px rgba(91, 36, 224, 0.45);
    }
    .banner-bg {
      background: radial-gradient(circle at 80% 50%, #7c4dff 0%, #5620d8 55%, #4516b5 100%);
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
<body class=" flex justify-center min-h-screen text-slate-900 antialiased">

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
                  <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm border border-white/10 rounded-full px-3 py-1.5">
                    <i class="fa-brands fa-telegram text-white text-sm"></i>
                    <span class="text-white text-xs font-bold tracking-wide">ID: <?= e(mask_telegram_id($user['telegram_id'])) ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Main Content Stack -->
    <div class="px-3.5 -mt-14 space-y-3.5">

      <!-- Your Wallet Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-1.5 text-slate-800 font-bold text-sm">
              <span>Your Wallet</span>
              <i class="fa-regular fa-circle-question text-slate-400 text-xs cursor-pointer"></i>
            </div>

            <div class="flex items-center gap-3 mt-2">
              <!-- Coin -->
              <div class="w-11 h-11 rounded-full t-coin flex items-center justify-center shrink-0">
                <div class="w-8 h-8 rounded-full t-coin-inner flex items-center justify-center">
                  <span class="text-amber-900 font-extrabold text-lg">T</span>
                </div>
              </div>
              <div>
                <div class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none"><?= e(t_format($balanceT, false)) ?></div>
                <div class="text-xs font-semibold text-slate-500 mt-1">≈ <?= e(inr_format(t_to_inr($balanceT))) ?></div>
              </div>
            </div>
          </div>

          <!-- Cashout Button -->
                    <a href="withdraw.php" class="btn-purple text-white font-bold text-sm px-4 py-3.5 rounded-2xl flex items-center gap-2.5 active:scale-95 transition">
            <i class="fa-solid fa-wallet text-base"></i>
            <span>Cashout</span>
            <i class="fa-solid fa-chevron-right text-xs opacity-80"></i>
                    </a>
        </div>

        <!-- Minimum Cashout Bar -->
        <div class="mt-4 bg-[#f5f3ff] rounded-xl px-3 py-2.5 flex items-center justify-between text-xs">
          <div class="flex items-center gap-1.5">
            <i class="fa-solid fa-crown text-amber-400 text-sm"></i>
            <span class="text-slate-600 font-medium">Minimum cashout: <strong class="text-[#5925dc] font-bold"><?= e(t_format($minWithdrawalT)) ?> (<?= e(inr_format(t_to_inr($minWithdrawalT))) ?>)</strong></span>
          </div>
          <a href="#" class="text-[#5925dc] font-bold flex items-center gap-1 hover:underline">
            <span>How it works?</span>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
          </a>
        </div>
      </div>

      <!-- Responsive Stats Grid -->
            <div class="grid grid-cols-3 gap-2">
              <!-- Total Invites -->
                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-2.5 flex flex-col justify-between shadow-sm">
                <div class="w-7 h-7 flex items-center justify-center text-emerald-600">
                  <i class="fa-solid fa-users text-lg"></i>
                </div>
                <div class="mt-2">
                  <div class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight"><?= e(number_format($referralCount)) ?></div>
                  <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5">
                    <span>Total Invites</span>
                    <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
                  </div>
                </div>
              </div>

              <!-- Per Referral -->
                    <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-2xl p-2.5 flex flex-col justify-between shadow-sm">
                <div class="w-6 h-6 rounded-full t-coin flex items-center justify-center">
                  <span class="text-amber-900 font-extrabold text-xs">T</span>
                </div>
                <div class="mt-2">
                  <div class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight"><?= e(t_format($rewardPerReferral)) ?></div>
                  <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5">
                    <span>Per Referral</span>
                    <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
                  </div>
                </div>
              </div>

              <!-- Total Earned -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-2.5 flex flex-col justify-between shadow-sm">
                <div class="w-7 h-7 flex items-center justify-center text-blue-600">
                  <i class="fa-solid fa-gift text-lg"></i>
                </div>
                <div class="mt-2">
                  <div class="text-sm sm:text-base font-extrabold text-slate-900 leading-tight"><?= e(t_format($totalEarnedT)) ?></div>
                  <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5">
                    <span>Total Earned</span>
                    <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
                  </div>
                </div>
              </div>
            </div>

      <!-- Promo Banner -->
            <div class="rounded-2xl overflow-hidden shadow-lg">
              <img src="asset/refer-earn.webp" alt="Invite Friends - Earn 150 T per Referral" class="w-full h-auto block" />
            </div>

      <!-- Your Invite Link Card -->
            <div class="bg-white rounded-3xl p-4 card-shadow">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-2xl bg-[#f2eeff] text-[#6d28d9] flex items-center justify-center shrink-0">
                  <i class="fa-solid fa-link text-base"></i>
                </div>
                <div>
                  <h3 class="font-extrabold text-sm text-slate-900 leading-tight">Your Invite Link</h3>
                  <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Share this link with your friends and earn <span class="text-[#5925dc] font-bold"><?= e(t_format($rewardPerReferral)) ?></span> for each successful referral
                  </p>
                </div>
              </div>

              <!-- Link Input Box -->
              <div class="bg-[#f4f4f8] rounded-2xl px-3.5 py-3 flex items-center justify-between border border-slate-200/80 mb-3">
                <span id="inviteLink" class="text-xs font-semibold text-slate-800 truncate select-all"><?= e($myInviteLink) ?></span>
                <button onclick="copyInviteLink()" class="text-slate-500 hover:text-[#5925dc] ml-2 active:scale-90 transition p-1" title="Copy Link">
                  <i id="copyIcon" class="fa-regular fa-copy text-base"></i>
                </button>
              </div>

              <!-- Share Invite Link Button -->
              <button onclick="shareInviteLink()" class="btn-purple w-full text-white font-bold text-sm py-3.5 rounded-2xl flex items-center justify-center gap-2.5 active:scale-[0.98] transition">
                <i class="fa-solid fa-share-nodes text-base"></i>
                <span>Share Invite Link</span>
              </button>
            </div>

      <!-- How to Earn? Card -->
      <div class="bg-white rounded-2xl p-4 card-shadow">
        <h3 class="font-extrabold text-sm text-slate-900 mb-3">How to Earn?</h3>

        <div class="flex items-center justify-between gap-1">
          <!-- Step 1 -->
          <div class="flex items-center gap-2 flex-1">
            <div class="w-9 h-9 rounded-full bg-[#eef0ff] text-[#5925dc] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-link text-sm"></i>
            </div>
            <div>
              <div class="text-xs font-extrabold text-[#5925dc]">1</div>
              <div class="text-[10px] font-medium text-slate-600 leading-tight">Share your<br />invite link</div>
            </div>
          </div>

          <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>

          <!-- Step 2 -->
          <div class="flex items-center gap-2 flex-1">
            <div class="w-9 h-9 rounded-full bg-[#eaf2ff] text-blue-600 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-users text-sm"></i>
            </div>
            <div>
              <div class="text-xs font-extrabold text-[#5925dc]">2</div>
              <div class="text-[10px] font-medium text-slate-600 leading-tight">Friend joins<br />using your link</div>
            </div>
          </div>

          <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>

          <!-- Step 3 -->
          <div class="flex items-center gap-2 flex-1">
            <div class="w-9 h-9 rounded-full bg-[#ffeef2] text-rose-500 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-gift text-sm"></i>
            </div>
            <div>
              <div class="text-xs font-extrabold text-[#5925dc]">3</div>
              <div class="text-[10px] font-medium text-slate-600 leading-tight">You get<br /><?= e(t_format($rewardPerReferral)) ?> reward</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity Card -->
      <div class="bg-white rounded-2xl p-4 card-shadow">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <i class="fa-regular fa-clock text-slate-500 text-sm"></i>
            <h3 class="font-extrabold text-sm text-slate-900">Recent Activity</h3>
          </div>
          <a href="activity.php" class="text-xs font-bold text-[#5925dc] flex items-center gap-1 hover:underline">
            <span>View All</span>
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
          </a>
        </div>

        <div class="divide-y divide-slate-100">
          <?php if ($recentActivity === []): ?>
            <div class="py-6 text-center">
              <div class="w-12 h-12 mx-auto rounded-full bg-[#f4f4f8] text-slate-400 flex items-center justify-center">
                <i class="fa-regular fa-clock text-lg"></i>
              </div>
              <p class="mt-2.5 text-xs font-semibold text-slate-500">No activity yet</p>
              <p class="mt-1 text-[11px] text-slate-400">Share your invite link to start earning.</p>
            </div>
          <?php else: ?>
            <?php foreach ($recentActivity as $index => $tx): ?>
              <?php
                $style     = wallet_type_style((string) $tx['type']);
                $isCredit  = ($tx['direction'] ?? 'credit') === 'credit';
                $rowClass  = $index === 0 ? 'py-2.5 first:pt-0' : 'py-2.5';
                $rowClass .= $index === count($recentActivity) - 1 ? ' last:pb-0' : '';
              ?>
              <div class="<?= e($rowClass) ?> flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full <?= e($style['bg']) ?> <?= e($style['text']) ?> flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= e($style['icon']) ?> text-sm"></i>
                  </div>
                  <div>
                    <div class="text-xs font-extrabold text-slate-900"><?= e(wallet_type_label((string) $tx['type'])) ?></div>
                    <div class="text-[11px] font-medium text-slate-500 mt-0.5"><?= e($tx['description'] ?: 'Wallet update') ?></div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-xs font-extrabold <?= $isCredit ? 'text-emerald-600' : 'text-rose-500' ?>">
                    <?= $isCredit ? '+' : '-' ?><?= e(t_format((float) $tx['amount_t'])) ?>
                  </div>
                  <div class="text-[10px] font-medium text-slate-400 mt-0.5"><?= e(activity_time($tx['created_at'] ?? null)) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] bg-white rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-6 pt-2.5 pb-2 flex items-center justify-around z-50">
      <!-- Home (Active) -->
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

  <!-- Copy Link Script -->
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

      function copyInviteLink() {
        const linkText = document.getElementById('inviteLink').innerText;
        const icon = document.getElementById('copyIcon');
        navigator.clipboard.writeText(linkText).then(() => {
          icon.className = 'fa-solid fa-check text-emerald-600 text-sm';
          showToast('Invite link copied!');
          setTimeout(() => {
            icon.className = 'fa-regular fa-copy text-sm';
          }, 1500);
        });
      }

      function shareInviteLink() {
        const link = document.getElementById('inviteLink').innerText;
        const text = 'Join Refer & Earn and get <?= e(t_format((float) setting('signup_bonus_t', 50))) ?> signup bonus!';

        if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.openTelegramLink) {
          window.Telegram.WebApp.openTelegramLink(
            'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(text)
          );
          return;
        }

        if (navigator.share) {
          navigator.share({ title: 'Refer & Earn', text: text, url: link }).catch(() => {});
          return;
        }

        copyInviteLink();
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