<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Activity - Refer & Earn</title>
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
                  <span class="text-white text-xs font-bold tracking-wide">ID: *****6789</span>
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
              <div class="text-2xl font-extrabold text-slate-900 leading-none">1,200 T</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs font-semibold text-slate-500">Total Referrals</div>
            <div class="text-2xl font-extrabold text-[#5925dc] leading-none mt-1">12</div>
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
          <!-- Item 1 -->
          <div class="py-2.5 first:pt-0 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-[#e8f8f0] text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@rahul123 joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">29 Oct 2026, 10:24 AM</div>
            </div>
          </div>

          <!-- Item 2 -->
          <div class="py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#eaf2ff] text-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@priya99 joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">28 Oct 2026, 06:12 PM</div>
            </div>
          </div>

          <!-- Item 3 -->
          <div class="py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#fff6e4] text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@amit_k joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">27 Oct 2026, 11:03 AM</div>
            </div>
          </div>

          <!-- Item 4 -->
          <div class="py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#f3e8ff] text-purple-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@sneha_21 joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">26 Oct 2026, 09:45 AM</div>
            </div>
          </div>

          <!-- Item 5 -->
          <div class="py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#ffeef2] text-rose-500 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@vivek_07 joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">25 Oct 2026, 04:30 PM</div>
            </div>
          </div>

          <!-- Item 6 -->
          <div class="py-2.5 last:pb-0 flex items-center justify-between">
            <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#e0f2fe] text-sky-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-user-plus text-sm"></i>
              </div>
              <div>
                <div class="text-xs font-extrabold text-slate-900">Referral Reward</div>
                <div class="text-[11px] font-medium text-slate-500 mt-0.5">@kiran_99 joined using your link</div>
              </div>
            </div>
            <div class="text-right">
                        <div class="text-xs font-extrabold text-emerald-600">+150 T</div>
              <div class="text-[10px] font-medium text-slate-400 mt-0.5">24 Oct 2026, 02:15 PM</div>
            </div>
          </div>
        </div>
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
</body>
</html>