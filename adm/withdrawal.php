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
      <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-gear text-slate-400 w-5 text-center text-base"></i>
        <span>Settings</span>
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
    </header>

    <!-- Top KPI Cards Row (5 Cards) -->
    <section class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-2.5 sm:gap-5 mb-6">
      <!-- Total Withdrawal Requests -->
      <div class="bg-[#f4f1fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-purple-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#e8dffc] flex items-center justify-center text-[#7337f6] shrink-0">
          <i class="fa-solid fa-file-invoice text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Withdrawal Requests</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">428</p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>12%</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">+46 this month</p>
        </div>
      </div>

      <!-- Completed Withdrawals -->
      <div class="bg-[#eefbf5] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-emerald-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#d7f5e8] flex items-center justify-center text-[#18a96b] shrink-0">
          <i class="fa-solid fa-circle-check text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Completed Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">286</p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>18%</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">+52 this month</p>
        </div>
      </div>

      <!-- Pending Withdrawals -->
      <div class="bg-[#fef9ec] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-amber-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#fdecc8] flex items-center justify-center text-amber-600 shrink-0">
          <i class="fa-solid fa-clock text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Pending Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">32</p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>5%</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">₹ 48,200 T</p>
        </div>
      </div>

      <!-- Rejected Withdrawals -->
      <div class="bg-[#fdf2f4] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-rose-100/80 shadow-sm">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#fce0e6] flex items-center justify-center text-[#e11d48] shrink-0">
          <i class="fa-solid fa-circle-xmark text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Rejected Withdrawals</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">110</p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>3%</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">+12 this month</p>
        </div>
      </div>

      <!-- Total Amount Withdrawn -->
      <div class="bg-[#f0f6fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-blue-100/80 shadow-sm col-span-2 lg:col-span-1">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#dceafe] flex items-center justify-center text-[#2563eb] shrink-0">
          <i class="fa-solid fa-wallet text-base sm:text-xl"></i>
        </div>
        <div class="min-w-0">
          <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Amount Withdrawn</p>
          <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5">286,500 T</p>
          <div class="flex items-center gap-1 text-[10px] sm:text-xs font-semibold text-emerald-600 mt-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>20%</span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-slate-500 mt-0.5">+47,200 T this month</p>
        </div>
      </div>
    </section>

    <!-- Table Section -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">

      <!-- Filter & Action Bar -->
      <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
      
        <!-- Filter Tabs -->
        <div id="filter-tabs" role="tablist" class="flex flex-wrap items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-xs font-semibold text-slate-600">
          <button type="button" data-filter="all" class="tab-btn px-4 py-2 rounded-lg bg-[#6339f9] text-white shadow-2xs transition">
            All (428)
          </button>
          <button type="button" data-filter="pending" class="tab-btn px-4 py-2 rounded-lg hover:text-slate-900 transition">
            Pending (32)
          </button>
          <button type="button" data-filter="completed" class="tab-btn px-4 py-2 rounded-lg hover:text-slate-900 transition">
            Completed (286)
          </button>
          <button type="button" data-filter="rejected" class="tab-btn px-4 py-2 rounded-lg hover:text-slate-900 transition">
            Rejected (110)
          </button>
        </div>

        <!-- Date Range & Export -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-medium">
            <i class="fa-regular fa-calendar text-slate-400"></i>
            <span>Oct 1, 2026 - Oct 29, 2026</span>
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
            
            <!-- Row 1 -->
            <tr data-status="pending" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">1</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    R
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@rahul123</p>
                    <p class="text-[10px] text-slate-400">ID: 100245</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">2,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 2,000</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">rahul@okaxis</td>
              <td class="py-3.5 px-3 text-slate-500">29 Oct 2026, 14:32</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                  Pending
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded-lg transition shadow-2xs">
                    Approve
                  </button>
                  <button class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-[11px] rounded-lg transition">
                    Reject
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 2 -->
            <tr data-status="pending" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">2</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    P
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@priya99</p>
                    <p class="text-[10px] text-slate-400">ID: 100238</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">1,500 T</p>
                <p class="text-[10px] text-slate-500">₹ 1,500</p>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-medium text-slate-800">HDFC **** 6521</p>
                <p class="text-[10px] text-slate-400">Priya Sharma</p>
              </td>
              <td class="py-3.5 px-3 text-slate-500">29 Oct 2026, 13:10</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                  Pending
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded-lg transition shadow-2xs">
                    Approve
                  </button>
                  <button class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-[11px] rounded-lg transition">
                    Reject
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 3 -->
            <tr data-status="completed" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">3</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    A
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@amit_kumar</p>
                    <p class="text-[10px] text-slate-400">ID: 100221</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">3,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 3,000</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">9876543210</td>
              <td class="py-3.5 px-3 text-slate-500">28 Oct 2026, 20:45</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                  Completed
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 4 -->
            <tr data-status="completed" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">4</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    N
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@neha_123</p>
                    <p class="text-[10px] text-slate-400">ID: 100208</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">2,500 T</p>
                <p class="text-[10px] text-slate-500">₹ 2,500</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">neha123@ybl</td>
              <td class="py-3.5 px-3 text-slate-500">28 Oct 2026, 18:20</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                  Completed
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 5 -->
            <tr data-status="rejected" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">5</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    R
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@rohit_tech</p>
                    <p class="text-[10px] text-slate-400">ID: 100197</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">1,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 1,000</p>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-medium text-slate-800">SBI **** 4412</p>
                <p class="text-[10px] text-slate-400">Rohit Kumar</p>
              </td>
              <td class="py-3.5 px-3 text-slate-500">27 Oct 2026, 16:12</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700">
                  Rejected
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 6 -->
            <tr data-status="completed" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">6</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    S
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@sahil_dev</p>
                    <p class="text-[10px] text-slate-400">ID: 100185</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">5,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 5,000</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">sahil@paytm</td>
              <td class="py-3.5 px-3 text-slate-500">27 Oct 2026, 11:30</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                  Completed
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 7 -->
            <tr data-status="pending" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">7</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    K
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@kavya02</p>
                    <p class="text-[10px] text-slate-400">ID: 100172</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">2,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 2,000</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">9123456789</td>
              <td class="py-3.5 px-3 text-slate-500">26 Oct 2026, 22:10</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                  Pending
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded-lg transition shadow-2xs">
                    Approve
                  </button>
                  <button class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-[11px] rounded-lg transition">
                    Reject
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 8 -->
            <tr data-status="completed" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">8</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    D
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@deepak_coder</p>
                    <p class="text-[10px] text-slate-400">ID: 100168</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">1,500 T</p>
                <p class="text-[10px] text-slate-500">₹ 1,500</p>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-medium text-slate-800">ICICI **** 9900</p>
                <p class="text-[10px] text-slate-400">Deepak</p>
              </td>
              <td class="py-3.5 px-3 text-slate-500">26 Oct 2026, 19:45</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                  Completed
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 9 -->
            <tr data-status="pending" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">9</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    V
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@vijayk</p>
                    <p class="text-[10px] text-slate-400">ID: 100154</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">3,500 T</p>
                <p class="text-[10px] text-slate-500">₹ 3,500</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">vijay@okicici</td>
              <td class="py-3.5 px-3 text-slate-500">25 Oct 2026, 15:20</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                  Pending
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-[11px] rounded-lg transition shadow-2xs">
                    Approve
                  </button>
                  <button class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-[11px] rounded-lg transition">
                    Reject
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Row 10 -->
            <tr data-status="rejected" class="hover:bg-slate-50/50 transition">
              <td class="p-4 text-center">
                <input type="checkbox" class="w-4 h-4 rounded accent-[#6339f9]" />
              </td>
              <td class="py-3.5 px-3 font-medium text-slate-900">10</td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0 flex items-center justify-center font-bold text-slate-600 text-xs">
                    S
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">@simran_0 7</p>
                    <p class="text-[10px] text-slate-400">ID: 100149</p>
                  </div>
                </div>
              </td>
              <td class="py-3.5 px-3">
                <p class="font-bold text-slate-900">2,000 T</p>
                <p class="text-[10px] text-slate-500">₹ 2,000</p>
              </td>
              <td class="py-3.5 px-3 font-mono text-slate-600">9988776555</td>
              <td class="py-3.5 px-3 text-slate-500">25 Oct 2026, 12:05</td>
              <td class="py-3.5 px-3">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700">
                  Rejected
                </span>
              </td>
              <td class="py-3.5 px-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-[11px] rounded-lg transition">
                    View
                  </button>
                  <button class="p-1.5 text-slate-400 hover:text-slate-600 ml-1">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                  </button>
                </div>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 sm:p-5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <p id="pagination-summary" class="text-xs text-slate-500">Showing 1 to 10 of 428 withdrawals</p>
        
        <div class="flex items-center gap-1.5">
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button class="w-8 h-8 rounded-xl bg-[#6339f9] text-white font-semibold flex items-center justify-center text-xs shadow-2xs">
            1
          </button>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            2
          </button>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            3
          </button>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            4
          </button>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            5
          </button>
          <span class="px-1 text-slate-400">...</span>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            43
          </button>
          <button class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center text-xs transition">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>

        <div class="flex items-center gap-2">
          <select class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none">
            <option>10 per page</option>
            <option>25 per page</option>
            <option>50 per page</option>
          </select>
        </div>
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

    // Filter tabs
    const tabButtons = document.querySelectorAll('#filter-tabs .tab-btn');
    const rows = document.querySelectorAll('tbody tr[data-status]');
    const summary = document.querySelector('#pagination-summary');

    function applyFilter(filter) {
      let visible = 0;
      rows.forEach((row) => {
        const match = filter === 'all' || row.dataset.status === filter;
        row.classList.toggle('hidden', !match);
        if (match) visible++;
      });

      tabButtons.forEach((btn) => {
        const active = btn.dataset.filter === filter;
        btn.classList.toggle('bg-[#6339f9]', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('shadow-2xs', active);
        btn.classList.toggle('hover:text-slate-900', !active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      if (summary) {
        const total = btnCount(filter);
        summary.textContent = `Showing 1 to ${visible} of ${total} withdrawals`;
      }
    }

    function btnCount(filter) {
      const btn = document.querySelector(`#filter-tabs .tab-btn[data-filter="${filter}"]`);
      const match = btn && btn.textContent.match(/\((\d+)\)/);
      return match ? match[1] : rows.length;
    }

    tabButtons.forEach((btn) => {
      btn.addEventListener('click', () => applyFilter(btn.dataset.filter));
    });

    applyFilter('all');
  </script>
</body>
</html>