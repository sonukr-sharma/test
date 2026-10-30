<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer & Earn - Admin Panel Settings</title>
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
    /* Custom Toggle Switch */
    .toggle-checkbox:checked {
      right: 0;
      border-color: #6339f9;
    }
    .toggle-checkbox:checked + .toggle-label {
      background-color: #6339f9;
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
      <a href="withdrawal.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-wallet text-slate-400 w-5 text-center text-base"></i>
        <span>Withdrawals</span>
      </a>
      <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-gear text-white w-5 text-center text-base"></i>
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
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Settings</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Configure your Refer & Earn platform settings and view key statistics.</p>
        </div>
      </div>
    </header>

    <!-- Main Settings Grid (6 Cards) -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">
      
      <!-- 1. Referral Settings -->
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
          <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-user-plus text-base sm:text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">Referral Settings</h3>
              <p class="text-xs text-slate-500">Set how much users earn per successful referral.</p>
            </div>
          </div>

          <!-- Fields -->
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Reward per Referral (₹)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                <input type="text" value="150" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                              <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">₹</span>
              </div>
            </div>

                        <div>
                          <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Referrals per Withdrawal</label>
                          <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                            <input type="text" value="5" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                            <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">refs</span>
                          </div>
                        </div>
                      </div>
        </div>

        <!-- Info Banner -->
        <div class="mt-5 bg-[#f3f1fe] rounded-xl p-3 flex items-start gap-2.5">
          <i class="fa-solid fa-circle-info text-[#4f6ef7] text-sm mt-0.5 shrink-0"></i>
          <p class="text-[11px] leading-relaxed text-slate-600">
            A referral will be counted when the invited user joins using your link and stays active for the set time.
          </p>
        </div>
      </div>

      <!-- 2. Withdrawal Settings -->
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
          <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-wallet text-base sm:text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">Withdrawal Settings</h3>
              <p class="text-xs text-slate-500">Set withdrawal limits and methods.</p>
            </div>
          </div>

          <!-- Fields -->
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Withdrawal Amount (₹)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                <input type="text" value="2,000" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                              <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">₹</span>
              </div>
            </div>

            <div>
                          <label class="block text-xs font-medium text-slate-700 mb-1.5">Maximum Withdrawal Amount (₹)</label>
                          <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                            <input type="text" value="50,000" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                                                      <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">₹</span>
                          </div>
                        </div>
                      </div>
        </div>

        <!-- Auto Approve Toggle -->
        <div class="mt-5 pt-3 border-t border-slate-100">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-slate-800">Auto Approve Withdrawals</span>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" class="sr-only peer">
              <div class="w-10 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
            </label>
          </div>
          <p class="text-[11px] text-slate-400 mt-1">If enabled, withdrawals will be approved automatically.</p>
        </div>
      </div>

      <!-- 3. Withdrawal API -->
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-code text-base sm:text-lg"></i>
            </div>
            <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Withdrawal API</h3>
                    <p class="text-xs text-slate-500">Configure the withdrawal callback API.</p>
            </div>
          </div>

                <!-- Inputs -->
                <div class="space-y-3.5">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Withdrawal API URL</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/70 overflow-hidden focus-within:border-indigo-500">
                      <input type="text" value="https://yourdomain.com/api/withdraw" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-copy text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Placeholder Info -->
                <div class="mt-5 bg-[#f3f1fe] rounded-xl p-3">
                  <p class="text-[11px] font-semibold text-slate-700 mb-1.5">POST payload placeholders</p>
                  <div class="space-y-1 font-mono text-[11px] text-slate-600">
                    <p><span class="text-[#6d37f7]">{amt}</span> — withdrawal amount</p>
                    <p><span class="text-[#6d37f7]">{upi}</span> — user's UPI ID</p>
                    <p><span class="text-[#6d37f7]">{txid}</span> — transaction ID <span class="text-slate-400">(optional)</span></p>
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. Bot & Links -->
            <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
              <div>
                <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-5">
                  <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-[#e5f4ff] text-[#1d9bf0] flex items-center justify-center shrink-0">
                    <i class="fa-brands fa-telegram text-xl sm:text-2xl"></i>
                  </div>
                  <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Bot & Links</h3>
                    <p class="text-xs text-slate-500">Configure your Telegram bot and links.</p>
                  </div>
                </div>

                <!-- Inputs -->
                <div class="space-y-3.5">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Bot Token</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/70 overflow-hidden focus-within:border-indigo-500">
                      <input type="password" value="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-eye text-xs"></i>
                      </button>
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Bot Web App URL</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/70 overflow-hidden focus-within:border-indigo-500">
                      <input id="botWebAppUrl" type="text" readonly class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-copy text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 5. Bonus Settings -->
            <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
              <div>
                <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-5">
                  <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-gift text-base sm:text-lg"></i>
                  </div>
                  <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Bonus Settings</h3>
                    <p class="text-xs text-slate-500">Configure signup and other bonuses.</p>
                  </div>
                </div>

                <!-- Fields -->
                <div class="space-y-3.5">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Signup Bonus (₹)</label>
                    <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                      <input type="text" value="50" class="w-full px-3.5 py-1.5 text-sm text-slate-800 bg-white focus:outline-none" />
                      <span class="px-3.5 py-1.5 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">₹</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bonus Toggles -->
              <div class="mt-5 space-y-2.5 pt-2">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-medium text-slate-700">Enable Signup Bonus</span>
                  <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" checked class="sr-only peer">
                    <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                  </label>
                </div>
              </div>
            </div>

            <!-- 6. Platform Settings -->
            <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
              <div>
                <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-6">
                  <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-gear text-base sm:text-lg"></i>
                  </div>
                  <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Platform Settings</h3>
                    <p class="text-xs text-slate-500">Control platform features and access.</p>
                  </div>
                </div>

                <!-- Toggle Rows -->
                <div class="space-y-4">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-slate-800">Allow New User Registration</span>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" checked class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>

                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-slate-800">Withdrawals</span>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" checked class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Save Changes Button -->
              <div class="mt-5 flex justify-end">
                <button class="w-full sm:w-auto justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
                  <i class="fa-solid fa-floppy-disk"></i>
                  <span>Save Changes</span>
                </button>
              </div>
            </div>

          </section>
  </main>

  <script>
    // Custom breakpoint support for very small phones (xs)
    tailwind.config = {
      theme: {
        extend: {
          screens: {
            'xs': '360px',
          }
        }
      }
    }

    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      sidebar.classList.toggle('-translate-x-full');
      backdrop.classList.toggle('hidden');
    }

        // Auto-fetch current domain for the Bot Web App URL
        const webAppUrlInput = document.getElementById('botWebAppUrl');
        if (webAppUrlInput) {
          webAppUrlInput.value = window.location.origin;
        }
      </script>
</body>
</html>