<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$rewardPerReferral = (float) setting('referral_reward_t', 150);
$signupBonusT      = (float) setting('signup_bonus_t', 50);
$totalEarnedT      = (float) $user['total_earned_t'];
$referralCount     = (int) $user['referral_count'];
$myInviteLink      = invite_link($user);

$shareText = rawurlencode('Join Refer & Earn and get ' . t_format($signupBonusT) . ' signup bonus!');
$shareUrl  = rawurlencode($myInviteLink);

$flash = flash_get();

$recentReferrals = [];
try {
    $stmt = db()->prepare(
        'SELECT `r`.`reward_t`, `r`.`status`, `r`.`credited_at`, `r`.`created_at`,
                `u`.`username`, `u`.`first_name`, `u`.`telegram_id`
           FROM `referrals` `r`
           JOIN `users` `u` ON `u`.`id` = `r`.`referee_id`
          WHERE `r`.`referrer_id` = :uid
          ORDER BY `r`.`created_at` DESC, `r`.`id` DESC
          LIMIT 5'
    );
    $stmt->execute([':uid' => $user['id']]);
    $recentReferrals = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('refer-earn.php referrals failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Invite Friends</title>
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
    /* Unique dynamic SVG / gradient banner */
    .invite-banner {
      background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 50%, #9333ea 100%);
      box-shadow: 0 10px 25px -5px rgba(91, 33, 182, 0.3);
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
        <!-- Left: Coin + Title -->
        <div class="flex items-center gap-3">
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

      <!-- Invite Banner (Image) -->
            <div class="rounded-3xl overflow-hidden shadow-lg">
              <img src="asset/refferalbanner.webp" alt="Invite Friends - Earn 150 T" class="w-full h-auto block" />
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

      <!-- Social Media Quick Share Icons Grid -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="grid grid-cols-5 gap-2 text-center">
          <!-- Telegram -->
          <a href="https://t.me/share/url?url=<?= e($shareUrl) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 group">
            <div class="w-12 h-12 rounded-2xl bg-[#e7f3ff] text-[#0088cc] flex items-center justify-center group-hover:scale-105 transition shadow-sm">
              <i class="fa-brands fa-telegram text-xl"></i>
            </div>
            <span class="text-[11px] font-semibold text-slate-700">Telegram</span>
          </a>
          <!-- WhatsApp -->
          <a href="https://wa.me/?text=<?= e($shareText . '%20' . $shareUrl) ?>" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 group">
            <div class="w-12 h-12 rounded-2xl bg-[#e8f8f0] text-[#25d366] flex items-center justify-center group-hover:scale-105 transition shadow-sm">
              <i class="fa-brands fa-whatsapp text-xl"></i>
            </div>
            <span class="text-[11px] font-semibold text-slate-700">WhatsApp</span>
          </a>
          <!-- Copy Link -->
          <button onclick="copyInviteLink()" class="flex flex-col items-center gap-1.5 group">
            <div class="w-12 h-12 rounded-2xl bg-[#efeaff] text-[#6d28d9] flex items-center justify-center group-hover:scale-105 transition shadow-sm">
              <i class="fa-solid fa-link text-lg"></i>
            </div>
            <span class="text-[11px] font-semibold text-slate-700">Copy Link</span>
          </button>
          <!-- X (Twitter) -->
          <a href="https://twitter.com/intent/tweet?url=<?= e($shareUrl) ?>&text=<?= e($shareText) ?>" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 group">
            <div class="w-12 h-12 rounded-2xl bg-[#f0f0f2] text-slate-900 flex items-center justify-center group-hover:scale-105 transition shadow-sm">
              <i class="fa-brands fa-x-twitter text-lg"></i>
            </div>
            <span class="text-[11px] font-semibold text-slate-700">X (Twitter)</span>
          </a>
          <!-- More -->
          <button onclick="shareInviteLink()" class="flex flex-col items-center gap-1.5 group">
            <div class="w-12 h-12 rounded-2xl bg-[#f4f4f8] text-slate-600 flex items-center justify-center group-hover:scale-105 transition shadow-sm">
              <i class="fa-solid fa-ellipsis text-base"></i>
            </div>
            <span class="text-[11px] font-semibold text-slate-700">More</span>
          </button>
        </div>
      </div>

      <!-- How it Works? Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="flex items-center gap-2.5 mb-3.5">
          <div class="w-8 h-8 rounded-xl bg-[#f2eeff] text-[#5925dc] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-gift text-sm"></i>
          </div>
          <h3 class="font-extrabold text-sm text-slate-900">How it Works?</h3>
        </div>

        <div class="flex items-center justify-between gap-1">
          <!-- Step 1 -->
                    <div class="flex flex-col items-center text-center gap-1.5 flex-1 relative">
                      <div class="relative">
                        <span class="absolute -top-2 -right-2 w-4 h-4 rounded-full bg-[#5925dc] text-white text-[9px] font-extrabold flex items-center justify-center shadow-sm">1</span>
                        <div class="w-10 h-10 rounded-2xl bg-[#eef0ff] text-[#5925dc] flex items-center justify-center shrink-0">
                          <i class="fa-solid fa-share-nodes text-sm"></i>
                        </div>
                      </div>
                      <div>
                        <div class="text-xs font-extrabold text-slate-900">Share Your Link</div>
                        <div class="text-[10px] font-medium text-slate-500 leading-tight mt-0.5">Send your invite link to friends</div>
                      </div>
                    </div>

          <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 shrink-0 px-1"></i>

          <!-- Step 2 -->
                    <div class="flex flex-col items-center text-center gap-1.5 flex-1 relative">
                      <div class="relative">
                        <span class="absolute -top-2 -right-2 w-4 h-4 rounded-full bg-[#5925dc] text-white text-[9px] font-extrabold flex items-center justify-center shadow-sm">2</span>
                        <div class="w-10 h-10 rounded-2xl bg-[#eaf2ff] text-blue-600 flex items-center justify-center shrink-0">
                          <i class="fa-solid fa-users text-sm"></i>
                        </div>
                      </div>
                      <div>
                        <div class="text-xs font-extrabold text-slate-900">Friend Joins</div>
                        <div class="text-[10px] font-medium text-slate-500 leading-tight mt-0.5">Your friend joins using your link</div>
                      </div>
                    </div>

          <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 shrink-0 px-1"></i>

          <!-- Step 3 -->
                    <div class="flex flex-col items-center text-center gap-1.5 flex-1 relative">
                      <div class="relative">
                        <span class="absolute -top-2 -right-2 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-extrabold flex items-center justify-center shadow-sm">3</span>
                        <div class="w-10 h-10 rounded-2xl bg-[#ffeef2] text-rose-500 flex items-center justify-center shrink-0">
                          <i class="fa-solid fa-gift text-sm"></i>
                        </div>
                      </div>
                      <div>
                        <div class="text-xs font-extrabold text-slate-900">Get Reward</div>
                        <div class="text-[10px] font-medium text-slate-500 leading-tight mt-0.5">You get <?= e(t_format($rewardPerReferral)) ?> per successful referral</div>
                      </div>
                    </div>
        </div>
      </div>

      <!-- Your Invite Stats Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="flex items-center gap-2.5 mb-3">
          <div class="w-8 h-8 rounded-xl bg-[#efeaff] text-[#6d28d9] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-chart-pie text-sm"></i>
          </div>
          <h3 class="font-extrabold text-sm text-slate-900">Your Invite Stats</h3>
        </div>

        <!-- 4-Column Stats Grid -->
        <div class="grid grid-cols-3 gap-2">
                  <!-- Total Invites -->
                  <div class="bg-[#e8f8f0] rounded-2xl p-2.5 flex flex-col justify-between border border-emerald-100/60">
                    <div class="w-7 h-7 flex items-center justify-center text-emerald-600">
                      <i class="fa-solid fa-users text-lg"></i>
                    </div>
                    <div class="mt-2">
                      <div class="text-base font-extrabold text-slate-900 leading-tight"><?= e(number_format($referralCount)) ?></div>
                      <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5 whitespace-nowrap">
                        <span>Total Invites</span>
                        <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
                      </div>
                    </div>
                  </div>

                  <!-- Per Referral -->
                  <div class="bg-[#fff6e4] rounded-2xl p-2.5 flex flex-col justify-between border border-amber-100/60">
            <div class="w-6 h-6 rounded-full t-coin flex items-center justify-center">
              <span class="text-amber-900 font-extrabold text-xs">T</span>
            </div>
            <div class="mt-2">
              <div class="text-base font-extrabold text-slate-900 leading-tight"><?= e(t_format($rewardPerReferral)) ?></div>
              <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5 whitespace-nowrap">
                <span>Per Referral</span>
                <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
              </div>
            </div>
          </div>

          <!-- Total Earned -->
          <div class="bg-[#eaf3ff] rounded-2xl p-2.5 flex flex-col justify-between border border-blue-100/60">
            <div class="w-7 h-7 flex items-center justify-center text-blue-600">
              <i class="fa-solid fa-gift text-lg"></i>
            </div>
            <div class="mt-2">
              <div class="text-base font-extrabold text-slate-900 leading-tight"><?= e(t_format($totalEarnedT)) ?></div>
              <div class="text-[10px] font-semibold text-slate-600 flex items-center gap-0.5 mt-0.5 whitespace-nowrap">
                <span>Total Earned</span>
                <i class="fa-solid fa-chevron-right text-[8px] opacity-70"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($recentReferrals !== []): ?>
        <!-- Recent Referrals Card -->
        <div class="bg-white rounded-3xl p-4 card-shadow">
          <div class="flex items-center gap-2.5 mb-3">
            <div class="w-8 h-8 rounded-xl bg-[#e8f8f0] text-emerald-600 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-user-plus text-sm"></i>
            </div>
            <h3 class="font-extrabold text-sm text-slate-900">Recent Referrals</h3>
          </div>

          <div class="divide-y divide-slate-100">
            <?php foreach ($recentReferrals as $index => $ref): ?>
              <?php
                $name = trim((string) ($ref['first_name'] ?? ''));
                if ($name === '') {
                    $name = !empty($ref['username']) ? '@' . $ref['username'] : mask_telegram_id($ref['telegram_id'] ?? null);
                }
                $status = (string) $ref['status'];
                $badge  = match ($status) {
                    'credited' => 'bg-emerald-50 text-emerald-700',
                    'reversed' => 'bg-rose-50 text-rose-700',
                    default    => 'bg-amber-50 text-amber-700',
                };
                $rowClass = $index === 0 ? 'py-2.5 first:pt-0' : 'py-2.5';
                $rowClass .= $index === count($recentReferrals) - 1 ? ' last:pb-0' : '';
              ?>
              <div class="<?= e($rowClass) ?> flex items-center justify-between">
                <div class="min-w-0">
                  <div class="text-xs font-extrabold text-slate-900 truncate"><?= e($name) ?></div>
                  <div class="text-[10px] font-medium text-slate-400 mt-0.5"><?= e(activity_time($ref['credited_at'] ?: $ref['created_at'])) ?></div>
                </div>
                <div class="text-right shrink-0 ml-2">
                  <div class="text-xs font-extrabold text-emerald-600">+<?= e(t_format((float) $ref['reward_t'])) ?></div>
                  <span class="inline-block mt-0.5 text-[9px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-full <?= e($badge) ?>"><?= e($status) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] bg-white rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-6 pt-2.5 pb-2 flex items-center justify-around z-50">
      <!-- Home -->
            <a href="index.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
        <i class="fa-solid fa-house text-lg"></i>
        <span class="text-[11px] font-semibold">Home</span>
      </a>

      <!-- Invite (Active) -->
            <a href="refer-earn.php" class="flex flex-col items-center gap-1 text-[#5925dc] relative pb-1.5 w-20">
        <i class="fa-solid fa-share-nodes text-lg"></i>
        <span class="text-[11px] font-bold">Invite</span>
        <span class="absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
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
          icon.className = 'fa-solid fa-check text-emerald-600 text-base';
          showToast('Invite link copied!');
          setTimeout(() => {
            icon.className = 'fa-regular fa-copy text-base';
          }, 1500);
        });
      }

      function shareInviteLink() {
        const link = document.getElementById('inviteLink').innerText;
        const text = 'Join Refer & Earn and get <?= e(t_format($signupBonusT)) ?> signup bonus!';

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