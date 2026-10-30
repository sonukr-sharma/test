<?php
/**
 * Admin â€” Broadcast dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$stats = [
    'total_broadcasts' => 0,
    'total_recipients' => 0,
    'delivered_count'  => 0,
    'failed_count'     => 0,
];

$history   = [];
$loadError = null;

try {
    $stats = array_merge($stats, db()->query('SELECT * FROM `v_broadcast_stats`')->fetch() ?: []);

    $history = db()->query(
        'SELECT `id`, `title`, `media_type`, `audience`, `status`,
                `total_recipients`, `delivered_count`, `failed_count`, `sent_at`
           FROM `broadcasts`
          ORDER BY COALESCE(`sent_at`, `created_at`) DESC
          LIMIT 20'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('Broadcast load failed: ' . $e->getMessage());
    $loadError = APP_DEBUG ? $e->getMessage() : 'Unable to load broadcasts right now.';
}

$totalRecipients = (int) $stats['total_recipients'];
$delivered       = (int) $stats['delivered_count'];
$failed          = (int) $stats['failed_count'];
$deliveredPct    = $totalRecipients > 0 ? round($delivered / $totalRecipients * 100, 1) : 0.0;
$failedPct       = $totalRecipients > 0 ? round($failed / $totalRecipients * 100, 1) : 0.0;

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

function broadcast_icon(string $mediaType): array
{
    return match ($mediaType) {
        'image' => ['bg-[#e8f3ff] text-[#2563eb]', 'fa-image'],
        'video' => ['bg-[#fff0f3] text-[#e11d48]', 'fa-video'],
        'file'  => ['bg-[#f2edff] text-[#6d37f7]', 'fa-file'],
        default => ['bg-[#fff3e6] text-amber-600', 'fa-paper-plane'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Refer & Earn - Broadcast Dashboard</title>
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

      /* Telegram Wallpaper Pattern */
      .tg-wallpaper {
        background-color: #93b48b;
        background-image:
          radial-gradient(rgba(255, 255, 255, 0.14) 2px, transparent 2px),
          radial-gradient(rgba(255, 255, 255, 0.1) 1.5px, transparent 1.5px);
        background-size: 36px 36px;
        background-position: 0 0, 18px 18px;
        position: relative;
      }

      /* Speech bubble left tail */
      .bubble-tail::before {
        content: "";
        position: absolute;
        bottom: 0;
        left: -8px;
        width: 16px;
        height: 16px;
        background: white;
        clip-path: polygon(100% 0, 0 100%, 100% 100%);
      }

      /* Telegram formatting rendered inside the preview bubble */
      .tg-code {
        background: rgba(15, 23, 42, 0.08);
        border-radius: 4px;
        padding: 1px 4px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.95em;
      }
      .tg-pre {
        background: rgba(15, 23, 42, 0.08);
        border-radius: 8px;
        padding: 6px 8px;
        margin: 4px 0;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.95em;
        white-space: pre-wrap;
        overflow-x: auto;
      }
      .tg-link {
        color: #2563eb;
        text-decoration: underline;
        word-break: break-all;
      }
      .tg-spoiler {
        background: #1f2937;
        color: transparent;
        border-radius: 4px;
        padding: 0 2px;
        cursor: pointer;
        transition: color 0.15s ease;
      }
      .tg-spoiler.revealed {
        color: #ffffff;
      }
      .tg-quote {
        border-left: 3px solid #6339f9;
        padding-left: 8px;
        margin: 4px 0;
        color: #334155;
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
            <div class="w-full h-full rounded-full bg-amber-400 border-2 border-amber-200 flex items-center justify-center text-amber-900 font-bold text-lg"> T </div>
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
        <a href="broadcast.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
          <i class="fa-solid fa-paper-plane text-white w-5 text-center text-base"></i>
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
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Broadcast</h2>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Send messages to all or selected users via Telegram.</p>
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
        <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium border <?= $flash['type'] === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
          <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mt-0.5 shrink-0"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>
      <!-- Top KPI Cards Row -->
      <section class="grid grid-cols-2 xl:grid-cols-4 gap-2.5 sm:gap-5 mb-6">
        <!-- Total Broadcasts -->
        <div class="bg-[#f4f1fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-purple-100/80 shadow-sm">
          <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#e8dffc] flex items-center justify-center text-[#7337f6] shrink-0">
            <i class="fa-solid fa-paper-plane text-base sm:text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Broadcasts</p>
            <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format((int) $stats['total_broadcasts']) ?></p>
            <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1">All time</p>
          </div>
        </div>
        <!-- Total Recipients -->
        <div class="bg-[#eefbf5] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-emerald-100/80 shadow-sm">
          <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#d7f5e8] flex items-center justify-center text-[#18a96b] shrink-0">
            <i class="fa-solid fa-users text-base sm:text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Total Recipients</p>
            <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format($totalRecipients) ?></p>
            <p class="text-[10px] sm:text-[11px] text-slate-500 mt-1">Across all broadcasts</p>
          </div>
        </div>
        <!-- Delivered -->
        <div class="bg-[#f0f6fe] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-blue-100/80 shadow-sm">
          <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#dceafe] flex items-center justify-center shrink-0">
            <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-[#2563eb] text-white flex items-center justify-center">
              <i class="fa-solid fa-check text-xs"></i>
            </div>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Delivered</p>
            <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format($delivered) ?></p>
            <p class="text-[10px] sm:text-xs font-medium text-slate-500 mt-1"><?= e($deliveredPct) ?>%</p>
          </div>
        </div>
        <!-- Failed -->
        <div class="bg-[#fdf2f4] rounded-2xl p-3 sm:p-4 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-4 border border-rose-100/80 shadow-sm">
          <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-[#fce0e6] flex items-center justify-center shrink-0">
            <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-[#e11d48] text-white flex items-center justify-center">
              <i class="fa-solid fa-xmark text-xs"></i>
            </div>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] sm:text-xs font-medium text-slate-500 truncate">Failed</p>
            <p class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight mt-0.5"><?= number_format($failed) ?></p>
            <p class="text-[10px] sm:text-xs font-medium text-slate-500 mt-1"><?= e($failedPct) ?>%</p>
          </div>
        </div>
      </section>
      <!-- Main 3-Column Broadcast Layout -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-stretch">
        <!-- Column 1: Send Broadcast (4 Cols on XL, 6 on LG) -->
        <form id="broadcastForm" method="post" action="broadcast_send.php" class="lg:col-span-6 xl:col-span-4 bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <?= csrf_field() ?>
          <input type="hidden" name="media_type" id="mediaTypeInput" value="text" />
          <input type="hidden" name="message_text" id="messageTextInput" value="" />
          <input type="hidden" name="buttons" id="buttonsInput" value="[]" />
          <div>
            <!-- Card Header -->
            <div class="flex items-center gap-3.5 mb-5">
              <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-paper-plane text-base sm:text-lg"></i>
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900">Send Broadcast</h3>
                <p class="text-xs text-slate-500">Send a message to your users via Telegram.</p>
              </div>
            </div>
            <!-- Message Content -->
            <div class="mb-4">
              <label class="block text-xs font-bold text-slate-800 mb-2.5">Message Content</label>
              <!-- Tabs -->
              <div class="grid grid-cols-4 bg-slate-100 p-1 rounded-xl text-xs font-medium text-slate-600 mb-3">
                <button type="button" data-tab="text" class="tab-btn py-1.5 px-1 rounded-lg bg-[#6339f9] text-white font-semibold shadow-2xs transition truncate text-[11px]"> Text </button>
                <button type="button" data-tab="image" class="tab-btn py-1.5 px-1 rounded-lg hover:text-slate-900 transition truncate text-[11px]"> Image </button>
                <button type="button" data-tab="video" class="tab-btn py-1.5 px-1 rounded-lg hover:text-slate-900 transition truncate text-[11px]"> Video </button>
                <button type="button" data-tab="file" class="tab-btn py-1.5 px-1 rounded-lg hover:text-slate-900 transition truncate text-[11px]"> File </button>
              </div>
              <!-- Text Panel -->
              <div id="panel-text" class="tab-panel">
                <div class="space-y-3">
                  <div class="border border-slate-200 rounded-xl bg-slate-50/40 p-3 focus-within:border-indigo-500 focus-within:bg-white transition">
                    <textarea rows="3" id="textMessage" placeholder="Type your message here..." class="w-full text-xs text-slate-800 bg-transparent focus:outline-none resize-none placeholder:text-slate-400"></textarea>
                    <div class="flex items-center justify-between pt-1">
                      <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                        <i class="fa-regular fa-face-smile text-base"></i>
                      </button>
                      <span class="text-[11px] text-slate-400" id="textCharCount">0/4096</span>
                    </div>
                  </div>
                  <!-- Buttons List -->
                  <div id="textBtnList" class="space-y-3">
                    <!-- Button 1 -->
                    <div class="btn-item border border-slate-200 rounded-xl p-3 space-y-2.5 bg-slate-50/30">
                      <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-500">Button 1</span>
                        <button type="button" class="btn-remove text-slate-400 hover:text-rose-500 transition hidden">
                          <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Text</label>
                        <input type="text" data-field="btnText" placeholder="e.g. Open App" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Link</label>
                        <input type="text" data-field="btnLink" placeholder="https://your-link.com" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Style</label>
                        <div class="grid grid-cols-3 gap-2">
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="textBtnStyle1" value="primary" checked class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-[#6339f9] text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-[#6339f9] transition">Primary</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="textBtnStyle1" value="success" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-emerald-500 transition">Success</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="textBtnStyle1" value="danger" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-rose-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-rose-500 transition">Danger</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Add Button -->
                  <button type="button" id="addTextBtn" class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 hover:text-[#6339f9] hover:border-[#6339f9] text-xs font-medium flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Button</span>
                  </button>
                </div>
              </div>
              <!-- Image Panel (with inline button) -->
              <div id="panel-image" class="tab-panel hidden">
                <div class="space-y-3">
                  <div data-upload class="border border-dashed border-slate-300 rounded-xl bg-slate-50/40 p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-slate-50 transition">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-2"></i>
                    <p data-upload-label class="text-xs font-medium text-slate-700">Click to upload an image</p>
                    <p data-upload-hint class="text-[11px] text-slate-400 mt-1">PNG, JPG or GIF up to 5MB</p>
                    <input type="file" accept="image/*" data-field="media" class="hidden" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Caption (Optional)</label>
                    <textarea rows="2" data-field="caption" placeholder="Add a caption to your image..." class="w-full text-xs text-slate-800 bg-slate-50/40 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-indigo-500 focus:bg-white transition resize-none placeholder:text-slate-400"></textarea>
                  </div>
                  <!-- Buttons List -->
                  <div id="imageBtnList" class="space-y-3">
                    <!-- Button 1 -->
                    <div class="btn-item border border-slate-200 rounded-xl p-3 space-y-2.5 bg-slate-50/30">
                      <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-500">Button 1</span>
                        <button type="button" class="btn-remove text-slate-400 hover:text-rose-500 transition hidden">
                          <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Text</label>
                        <input type="text" data-field="btnText" placeholder="e.g. Open App" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Link</label>
                        <input type="text" data-field="btnLink" placeholder="https://your-link.com" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Style</label>
                        <div class="grid grid-cols-3 gap-2">
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="imageBtnStyle1" value="primary" checked class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-[#6339f9] text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-[#6339f9] transition">Primary</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="imageBtnStyle1" value="success" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-emerald-500 transition">Success</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="imageBtnStyle1" value="danger" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-rose-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-rose-500 transition">Danger</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Add Button -->
                  <button type="button" id="addImageBtn" class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 hover:text-[#6339f9] hover:border-[#6339f9] text-xs font-medium flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Button</span>
                  </button>
                </div>
              </div>
              <!-- Video Panel (with inline button) -->
              <div id="panel-video" class="tab-panel hidden">
                <div class="space-y-3">
                  <div data-upload class="border border-dashed border-slate-300 rounded-xl bg-slate-50/40 p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-slate-50 transition">
                    <i class="fa-solid fa-file-video text-2xl text-slate-400 mb-2"></i>
                    <p data-upload-label class="text-xs font-medium text-slate-700">Click to upload a video</p>
                    <p data-upload-hint class="text-[11px] text-slate-400 mt-1">MP4 or MOV up to 50MB</p>
                    <input type="file" accept="video/*" data-field="media" class="hidden" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Caption (Optional)</label>
                    <textarea rows="2" data-field="caption" placeholder="Add a caption to your video..." class="w-full text-xs text-slate-800 bg-slate-50/40 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-indigo-500 focus:bg-white transition resize-none placeholder:text-slate-400"></textarea>
                  </div>
                  <!-- Buttons List -->
                  <div id="videoBtnList" class="space-y-3">
                    <!-- Button 1 -->
                    <div class="btn-item border border-slate-200 rounded-xl p-3 space-y-2.5 bg-slate-50/30">
                      <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-500">Button 1</span>
                        <button type="button" class="btn-remove text-slate-400 hover:text-rose-500 transition hidden">
                          <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Text</label>
                        <input type="text" data-field="btnText" placeholder="e.g. Watch Now" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Link</label>
                        <input type="text" data-field="btnLink" placeholder="https://your-link.com" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Style</label>
                        <div class="grid grid-cols-3 gap-2">
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="videoBtnStyle1" value="primary" checked class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-[#6339f9] text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-[#6339f9] transition">Primary</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="videoBtnStyle1" value="success" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-emerald-500 transition">Success</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="videoBtnStyle1" value="danger" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-rose-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-rose-500 transition">Danger</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Add Button -->
                  <button type="button" id="addVideoBtn" class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 hover:text-[#6339f9] hover:border-[#6339f9] text-xs font-medium flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Button</span>
                  </button>
                </div>
              </div>
              <!-- File Panel (with inline button) -->
              <div id="panel-file" class="tab-panel hidden">
                <div class="space-y-3">
                  <div data-upload class="border border-dashed border-slate-300 rounded-xl bg-slate-50/40 p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:bg-slate-50 transition">
                    <i class="fa-solid fa-file-arrow-up text-2xl text-slate-400 mb-2"></i>
                    <p data-upload-label class="text-xs font-medium text-slate-700">Click to upload a file</p>
                    <p data-upload-hint class="text-[11px] text-slate-400 mt-1">PDF, DOC, XLS or ZIP up to 20MB</p>
                    <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" data-field="media" class="hidden" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Caption (Optional)</label>
                    <textarea rows="2" data-field="caption" placeholder="Add a caption to your file..." class="w-full text-xs text-slate-800 bg-slate-50/40 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-indigo-500 focus:bg-white transition resize-none placeholder:text-slate-400"></textarea>
                  </div>
                  <!-- Buttons List -->
                  <div id="fileBtnList" class="space-y-3">
                    <!-- Button 1 -->
                    <div class="btn-item border border-slate-200 rounded-xl p-3 space-y-2.5 bg-slate-50/30">
                      <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-500">Button 1</span>
                        <button type="button" class="btn-remove text-slate-400 hover:text-rose-500 transition hidden">
                          <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Text</label>
                        <input type="text" data-field="btnText" placeholder="e.g. Download" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Link</label>
                        <input type="text" data-field="btnLink" placeholder="https://your-link.com" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Button Style</label>
                        <div class="grid grid-cols-3 gap-2">
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="fileBtnStyle1" value="primary" checked class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-[#6339f9] text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-[#6339f9] transition">Primary</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="fileBtnStyle1" value="success" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-emerald-500 transition">Success</span>
                          </label>
                          <label class="flex items-center justify-center cursor-pointer">
                            <input type="radio" name="fileBtnStyle1" value="danger" class="sr-only peer" />
                            <span class="w-full py-1.5 rounded-lg bg-rose-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-rose-500 transition">Danger</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Add Button -->
                  <button type="button" id="addFileBtn" class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-slate-500 hover:text-[#6339f9] hover:border-[#6339f9] text-xs font-medium flex items-center justify-center gap-2 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Button</span>
                  </button>
                </div>
              </div>
            </div>
            <!-- Supported Formatting Box -->
            <div class="bg-[#f3f1fe] rounded-xl p-3 mb-4">
              <div class="flex items-start gap-2.5">
                <i class="fa-solid fa-circle-info text-[#4f6ef7] text-sm mt-0.5 shrink-0"></i>
                <div class="min-w-0">
                  <p class="text-[11px] font-semibold text-slate-800">Supported Formatting (Telegram)</p>
                  <p class="text-[10px] text-slate-500 mt-0.5">Click a format to insert it at the cursor.</p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-1.5 mt-2.5">
                <button type="button" data-open="*" data-close="*" data-ph="bold text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span class="font-bold">*Bold*</span>
                </button>
                <button type="button" data-open="_" data-close="_" data-ph="italic text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span class="italic">_Italic_</span>
                </button>
                <button type="button" data-open="__" data-close="__" data-ph="underlined text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span class="underline">__Underline__</span>
                </button>
                <button type="button" data-open="~" data-close="~" data-ph="strikethrough" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span class="line-through">~Strike~</span>
                </button>
                <button type="button" data-open="||" data-close="||" data-ph="hidden text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>||Spoiler||</span>
                </button>
                <button type="button" data-open="`" data-close="`" data-ph="code" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>`Code`</span>
                </button>
                <button type="button" data-open="```&#10;" data-close="&#10;```" data-ph="code block" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>```Block```</span>
                </button>
                <button type="button" data-open="[" data-close="](https://example.com)" data-ph="Link text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>[Link](url)</span>
                </button>
                <button type="button" data-open="&gt; " data-close="" data-ph="quoted text" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>&gt; Quote</span>
                </button>
                <button type="button" data-open="ðŸ˜€" data-close="" data-ph="" class="fmt-chip flex items-center justify-center gap-1.5 bg-white/80 hover:bg-white border border-white hover:border-[#6339f9]/40 rounded-lg px-2 py-1.5 text-[10px] font-mono text-slate-700 transition">
                  <span>ðŸ˜€ Emoji</span>
                </button>
              </div>
              <p class="text-[10px] text-slate-500 mt-2">You can also use emojis and line breaks.</p>
            </div>
          </div>
          <!-- Audience -->
          <div class="mt-4">
            <label for="audienceSelect" class="block text-xs font-bold text-slate-800 mb-2">Audience</label>
            <select id="audienceSelect" name="audience" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-indigo-500 transition">
              <option value="all">All Users</option>
              <option value="active">Active Users</option>
              <option value="inactive">Inactive Users</option>
            </select>
          </div>
          <!-- Bottom Action Buttons -->
          <div class="mt-5 pt-2 flex items-center gap-3">
            <button type="reset" id="clearBroadcast" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200/80 text-slate-700 font-medium text-xs flex items-center justify-center gap-2 transition shrink-0">
              <i class="fa-solid fa-xmark text-slate-500"></i>
              <span>Clear</span>
            </button>
            <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-semibold text-xs shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition">
              <i class="fa-solid fa-paper-plane text-xs"></i>
              <span>Send Broadcast</span>
            </button>
          </div>
        </form>
        <!-- Column 2: Message Preview (4 Cols on XL, 6 on LG) -->
        <div class="lg:col-span-6 xl:col-span-4 bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col">
          <!-- Card Header -->
          <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-eye text-base sm:text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">Message Preview</h3>
              <p class="text-xs text-slate-500">This is how your message will look for users.</p>
            </div>
          </div>
          <!-- Telegram Mockup Container -->
          <div class="flex-1 rounded-2xl overflow-hidden border border-slate-200/80 flex flex-col tg-wallpaper min-h-[460px]">
            <!-- Telegram Chat Header -->
            <div class="bg-white/95 backdrop-blur-xs m-2.5 rounded-xl px-3.5 py-2.5 flex items-center justify-between shadow-2xs">
              <div class="flex items-center gap-3">
                <button class="text-slate-500 hover:text-slate-700">
                  <i class="fa-solid fa-arrow-left text-sm"></i>
                </button>
                <div class="w-9 h-9 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 p-0.5 shadow-2xs flex items-center justify-center shrink-0">
                  <div class="w-full h-full rounded-full bg-amber-400 border border-amber-200 flex items-center justify-center text-amber-900 font-bold text-sm"> T </div>
                </div>
                <div>
                  <h4 class="text-xs font-bold text-slate-900 leading-tight">Refer & Earn</h4>
                  <p class="text-[11px] text-slate-400 leading-tight">bot</p>
                </div>
              </div>
              <button class="text-slate-400 hover:text-slate-600 px-1">
                <i class="fa-solid fa-ellipsis-vertical text-sm"></i>
              </button>
            </div>
            <!-- Chat Area -->
            <div class="flex-1 px-3.5 py-2 flex flex-col">
              <!-- Date Pill -->
              <div class="self-center my-2">
                <span class="px-3 py-1 rounded-full bg-black/25 text-white text-[11px] font-medium backdrop-blur-2xs"> Today </span>
              </div>
              <!-- Message Bubble + Inline Button Wrapper -->
              <div class="mt-1 max-w-[92%] sm:max-w-[88%] w-full">
                <!-- White Message Card -->
                <div class="bg-white rounded-2xl rounded-bl-none p-3.5 shadow-xs relative bubble-tail text-slate-800 text-xs leading-relaxed">
                  <!-- Media Preview -->
                  <div id="previewMedia" class="hidden mb-2">
                    <img id="previewImage" alt="Preview" class="hidden w-full rounded-xl object-cover max-h-52" />
                    <video id="previewVideo" class="hidden w-full rounded-xl max-h-52 bg-black" controls></video>
                    <div id="previewFile" class="hidden flex items-center gap-2.5 rounded-xl bg-slate-100 p-2.5">
                      <div class="w-9 h-9 rounded-lg bg-[#6339f9] text-white flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-file-lines text-sm"></i>
                      </div>
                      <div class="min-w-0">
                        <p id="previewFileName" class="text-[11px] font-semibold text-slate-800 truncate">document.pdf</p>
                        <p id="previewFileSize" class="text-[10px] text-slate-500">PDF</p>
                      </div>
                    </div>
                  </div>
                  <!-- Message Text -->
                  <div id="previewText" class="whitespace-pre-wrap break-words text-slate-800">ðŸŽ‰ Earn 150 T per Referral!

Invite your friends and earn 150 T for each successful referral.

âœ… More Friends
âœ… More Rewards
âœ… No Limit

â° Start now and earn instantly!</div>
                  <div id="previewTime" class="text-right text-[10px] text-slate-400 mt-1"> 10:30 AM </div>
                </div>
                <!-- Telegram Inline Buttons -->
                <div id="previewButtons" class="grid grid-cols-2 gap-1.5 mt-1.5"></div>
              </div>
            </div>
          </div>
        </div>
        <!-- Column 3: Broadcast History (4 Cols on XL, 12 on LG) -->
        <div class="lg:col-span-12 xl:col-span-4 bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <div>
            <!-- Card Header -->
            <div class="flex items-center gap-3.5 mb-4">
              <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clock text-base sm:text-lg"></i>
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900">Broadcast History</h3>
                <p class="text-xs text-slate-500">View all sent broadcasts and their statistics.</p>
              </div>
            </div>
            <!-- History Items List -->
            <?php if ($loadError !== null): ?>
              <div class="py-8 text-center text-xs font-medium text-rose-600">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= e($loadError) ?>
              </div>
            <?php elseif ($history === []): ?>
              <div class="py-8 text-center text-xs font-medium text-slate-500">
                <i class="fa-regular fa-folder-open mr-2"></i>No broadcasts sent yet.
              </div>
            <?php else: ?>
              <?php foreach ($history as $b): ?>
                <?php
                  [$iconClass, $icon] = broadcast_icon((string) $b['media_type']);
                  $recipients = (int) $b['total_recipients'];
                  $ok         = (int) $b['delivered_count'];
                  $bad        = (int) $b['failed_count'];
                  $okPct      = $recipients > 0 ? round($ok / $recipients * 100, 1) : 0.0;
                  $badPct     = $recipients > 0 ? round($bad / $recipients * 100, 1) : 0.0;
                  $when       = $b['sent_at'] ?? null;
                ?>
                <div class="py-3 flex items-center justify-between gap-2">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl <?= e($iconClass) ?> flex items-center justify-center shrink-0">
                      <i class="fa-solid <?= e($icon) ?> text-sm"></i>
                    </div>
                    <div class="min-w-0">
                      <div class="flex items-center gap-1.5">
                        <h4 class="text-xs font-bold text-slate-900 truncate"><?= e($b['title']) ?></h4>
                        <?php
                          $status = (string) $b['status'];
                          $badge  = match ($status) {
                              'sent'      => 'bg-emerald-50 text-emerald-600',
                              'sending'   => 'bg-blue-50 text-blue-600',
                              'scheduled' => 'bg-amber-50 text-amber-600',
                              'failed'    => 'bg-rose-50 text-rose-600',
                              default     => 'bg-slate-100 text-slate-500',
                          };
                        ?>
                        <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide <?= e($badge) ?>"><?= e($status) ?></span>
                      </div>
                      <p class="text-[11px] text-slate-400 mt-0.5">
                        <?= $when !== null ? e(date('d M Y, h:i A', strtotime((string) $when))) : 'Not sent' ?>
                      </p>
                      <p class="text-[11px] text-slate-500">
                        <?= e(ucfirst((string) $b['audience'])) ?> Users (<?= number_format($recipients) ?>)
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2.5 shrink-0">
                    <div class="text-left min-w-[92px]">
                      <p class="text-[10px] text-slate-400 leading-tight">Delivered</p>
                      <p class="text-[11px] font-bold text-emerald-600"><?= number_format($ok) ?> (<?= e($okPct) ?>%)</p>
                      <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Failed</p>
                      <p class="text-[11px] font-semibold text-rose-500"><?= number_format($bad) ?> (<?= e($badPct) ?>%)</p>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          <!-- Pagination Footer -->
          <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[11px] text-slate-500">
              Showing <?= number_format(count($history)) ?> of <?= number_format((int) $stats['total_broadcasts']) ?> broadcasts
            </p>
            <a href="broadcast.php" class="text-[11px] font-semibold text-[#6339f9] hover:underline">Refresh</a>
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
      // Broadcast content type tabs
      let activeTab = 'text';
      const tabBtns = document.querySelectorAll('.tab-btn');
      const tabPanels = document.querySelectorAll('.tab-panel');
      tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          // Reset all buttons
          tabBtns.forEach(b => {
            b.classList.remove('bg-[#6339f9]', 'text-white', 'font-semibold', 'shadow-2xs');
            b.classList.add('hover:text-slate-900');
          });
          // Activate clicked button
          btn.classList.add('bg-[#6339f9]', 'text-white', 'font-semibold', 'shadow-2xs');
          btn.classList.remove('hover:text-slate-900');
          // Show matching panel
          const target = btn.dataset.tab;
          activeTab = target;
          tabPanels.forEach(panel => {
            panel.classList.toggle('hidden', panel.id !== 'panel-' + target);
          });
          renderPreview();
        });
      });
      // Multiple buttons for Text tab
      const textBtnList = document.getElementById('textBtnList');
      const addTextBtn = document.getElementById('addTextBtn');
      let textBtnCount = 1;
      const imageBtnList = document.getElementById('imageBtnList');
      const addImageBtn = document.getElementById('addImageBtn');
      let imageBtnCount = 1;
      const videoBtnList = document.getElementById('videoBtnList');
      const addVideoBtn = document.getElementById('addVideoBtn');
      let videoBtnCount = 1;
      const fileBtnList = document.getElementById('fileBtnList');
      const addFileBtn = document.getElementById('addFileBtn');
      let fileBtnCount = 1;

      function createButtonItem(list, prefix, countRef) {
        countRef.value++;
        const n = countRef.value;
        const item = document.createElement('div');
        item.className = 'btn-item border border-slate-200 rounded-xl p-3 space-y-2.5 bg-slate-50/30';
        item.innerHTML = `
                                            
						<div class="flex items-center justify-between">
							<span class="text-[11px] font-semibold text-slate-500">Button ${n}</span>
							<button type="button" class="btn-remove text-slate-400 hover:text-rose-500 transition">
								<i class="fa-solid fa-trash-can text-xs"></i>
							</button>
						</div>
						<div>
							<label class="block text-xs font-medium text-slate-700 mb-1">Button Text</label>
							<input type="text" data-field="btnText" placeholder="e.g. Open App" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
						</div>
						<div>
							<label class="block text-xs font-medium text-slate-700 mb-1">Button Link</label>
							<input type="text" data-field="btnLink" placeholder="https://your-link.com" class="w-full text-xs text-slate-800 bg-white border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 transition placeholder:text-slate-400" />
						</div>
						<div>
							<label class="block text-xs font-medium text-slate-700 mb-1">Button Style</label>
							<div class="grid grid-cols-3 gap-2">
								<label class="flex items-center justify-center cursor-pointer">
									<input type="radio" name="${prefix}${n}" value="primary" checked class="sr-only peer" />
									<span class="w-full py-1.5 rounded-lg bg-[#6339f9] text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-[#6339f9] transition">Primary</span>
								</label>
								<label class="flex items-center justify-center cursor-pointer">
									<input type="radio" name="${prefix}${n}" value="success" class="sr-only peer" />
									<span class="w-full py-1.5 rounded-lg bg-emerald-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-emerald-500 transition">Success</span>
								</label>
								<label class="flex items-center justify-center cursor-pointer">
									<input type="radio" name="${prefix}${n}" value="danger" class="sr-only peer" />
									<span class="w-full py-1.5 rounded-lg bg-rose-500 text-white text-[11px] font-semibold text-center peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-rose-500 transition">Danger</span>
								</label>
							</div>
						</div>`;
        list.appendChild(item);
        updateRemoveButtons(list);
        renderPreview();
      }

      function updateRemoveButtons(list) {
        const items = list.querySelectorAll('.btn-item');
        items.forEach((item) => {
          const removeBtn = item.querySelector('.btn-remove');
          removeBtn.classList.toggle('hidden', items.length === 1);
        });
      }

      function setupButtonList(list, addBtnEl, prefix, countRef) {
        addBtnEl.addEventListener('click', () => createButtonItem(list, prefix, countRef));
        list.addEventListener('click', (e) => {
          const removeBtn = e.target.closest('.btn-remove');
          if (!removeBtn) return;
          const item = removeBtn.closest('.btn-item');
          item.remove();
          // Renumber remaining buttons
          list.querySelectorAll('.btn-item').forEach((el, idx) => {
            el.querySelector('span').textContent = 'Button ' + (idx + 1);
          });
          updateRemoveButtons(list);
          renderPreview();
        });
        updateRemoveButtons(list);
      }
      setupButtonList(textBtnList, addTextBtn, 'textBtnStyle', {
        value: textBtnCount
      });
      setupButtonList(imageBtnList, addImageBtn, 'imageBtnStyle', {
        value: imageBtnCount
      });
      setupButtonList(videoBtnList, addVideoBtn, 'videoBtnStyle', {
        value: videoBtnCount
      });
      setupButtonList(fileBtnList, addFileBtn, 'fileBtnStyle', {
        value: fileBtnCount
      });

      // ---- Live message preview ----
      const previewText = document.getElementById('previewText');
      const previewButtons = document.getElementById('previewButtons');
      const previewMedia = document.getElementById('previewMedia');
      const previewImage = document.getElementById('previewImage');
      const previewVideo = document.getElementById('previewVideo');
      const previewFile = document.getElementById('previewFile');
      const previewFileName = document.getElementById('previewFileName');
      const previewFileSize = document.getElementById('previewFileSize');
      const textMessage = document.getElementById('textMessage');
      const textCharCount = document.getElementById('textCharCount');

      const STYLE_COLORS = {
        primary: 'rgba(99, 57, 249, 0.85)',
        success: 'rgba(16, 185, 129, 0.85)',
        danger: 'rgba(244, 63, 94, 0.85)'
      };

      const mediaState = { image: null, video: null, file: null };

      function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
      }

      function getPanel(tab) {
        return document.getElementById('panel-' + tab);
      }

      const BTN_LISTS = {
        text: 'textBtnList',
        image: 'imageBtnList',
        video: 'videoBtnList',
        file: 'fileBtnList'
      };

      function collectButtons(tab) {
        const list = document.getElementById(BTN_LISTS[tab]);
        if (!list) return [];
        return Array.from(list.querySelectorAll('.btn-item'))
          .map(item => ({
            text: item.querySelector('[data-field="btnText"]').value.trim(),
            link: item.querySelector('[data-field="btnLink"]').value.trim(),
            style: item.querySelector('input[type="radio"]:checked')?.value || 'primary'
          }))
          .filter(b => b.text);
      }

      // ---- Telegram markdown rendering ----
      function escapeHtml(str) {
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function safeUrl(url) {
        const trimmed = String(url).trim();
        return /^(https?:\/\/|mailto:|tg:\/\/)/i.test(trimmed) ? trimmed : '#';
      }

      function renderInline(text) {
        let out = '';
        let i = 0;
        while (i < text.length) {
          const ch = text[i];

          // [label](url)
          if (ch === '[') {
            const m = /^\[([^\]\n]+)\]\(([^)\s]+)\)/.exec(text.slice(i));
            if (m) {
              out += '<a class="tg-link" href="' + escapeHtml(safeUrl(m[2])) + '" target="_blank" rel="noopener noreferrer">' + renderInline(m[1]) + '</a>';
              i += m[0].length;
              continue;
            }
          }

          // `code`
          if (ch === '`') {
            const end = text.indexOf('`', i + 1);
            if (end > i + 1) {
              out += '<code class="tg-code">' + escapeHtml(text.slice(i + 1, end)) + '</code>';
              i = end + 1;
              continue;
            }
          }

          // ||spoiler||
          if (text.startsWith('||', i)) {
            const end = text.indexOf('||', i + 2);
            if (end > i + 2) {
              out += '<span class="tg-spoiler">' + renderInline(text.slice(i + 2, end)) + '</span>';
              i = end + 2;
              continue;
            }
          }

          // __underline__
          if (text.startsWith('__', i)) {
            const end = text.indexOf('__', i + 2);
            if (end > i + 2) {
              out += '<u>' + renderInline(text.slice(i + 2, end)) + '</u>';
              i = end + 2;
              continue;
            }
          }

          // **bold**
          if (text.startsWith('**', i)) {
            const end = text.indexOf('**', i + 2);
            if (end > i + 2) {
              out += '<b>' + renderInline(text.slice(i + 2, end)) + '</b>';
              i = end + 2;
              continue;
            }
          }

          // *bold*
          if (ch === '*') {
            const end = text.indexOf('*', i + 1);
            if (end > i + 1) {
              out += '<b>' + renderInline(text.slice(i + 1, end)) + '</b>';
              i = end + 1;
              continue;
            }
          }

          // _italic_
          if (ch === '_') {
            const end = text.indexOf('_', i + 1);
            if (end > i + 1) {
              out += '<i>' + renderInline(text.slice(i + 1, end)) + '</i>';
              i = end + 1;
              continue;
            }
          }

          // ~strikethrough~
          if (ch === '~') {
            const end = text.indexOf('~', i + 1);
            if (end > i + 1) {
              out += '<s>' + renderInline(text.slice(i + 1, end)) + '</s>';
              i = end + 1;
              continue;
            }
          }

          out += escapeHtml(ch);
          i++;
        }
        return out;
      }

      function renderTelegramMarkdown(src) {
        const lines = String(src).replace(/\r\n/g, '\n').split('\n');
        const out = [];
        let i = 0;
        while (i < lines.length) {
          const line = lines[i];

          // ```fenced code block```
          if (/^\s*```/.test(line)) {
            const buf = [];
            i++;
            while (i < lines.length && !/^\s*```/.test(lines[i])) {
              buf.push(lines[i]);
              i++;
            }
            i++;
            out.push('<pre class="tg-pre">' + escapeHtml(buf.join('\n')) + '</pre>');
            continue;
          }

          // > quote block
          if (/^\s*>/.test(line)) {
            const buf = [];
            while (i < lines.length && /^\s*>/.test(lines[i])) {
              buf.push(lines[i].replace(/^\s*>\s?/, ''));
              i++;
            }
            out.push('<div class="tg-quote">' + buf.map(renderInline).join('<br>') + '</div>');
            continue;
          }

          out.push(renderInline(line));
          i++;
        }
        return out.join('\n');
      }

      // ---- Formatting chips ----
      function activeEditor() {
        if (activeTab === 'text') return textMessage;
        const panel = getPanel(activeTab);
        return panel ? panel.querySelector('[data-field="caption"]') : null;
      }

      function insertFormatting(open, close, placeholder) {
        const el = activeEditor();
        if (!el) return;
        const start = el.selectionStart ?? el.value.length;
        const end = el.selectionEnd ?? el.value.length;
        const selected = el.value.slice(start, end);
        const inner = selected || placeholder || '';
        el.value = el.value.slice(0, start) + open + inner + close + el.value.slice(end);
        const caretStart = start + open.length;
        el.focus();
        el.setSelectionRange(caretStart, caretStart + inner.length);
        if (el === textMessage) textCharCount.textContent = textMessage.value.length + '/4096';
        renderPreview();
      }

      document.querySelectorAll('.fmt-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
          insertFormatting(chip.dataset.open || '', chip.dataset.close || '', chip.dataset.ph || '');
        });
      });

      // Spoilers reveal on click, like Telegram
      previewText.addEventListener('click', (e) => {
        const spoiler = e.target.closest('.tg-spoiler');
        if (spoiler) spoiler.classList.toggle('revealed');
      });

      function renderPreview() {
        const tab = activeTab;
        const panel = getPanel(tab);

        // Message / caption text
        const raw = tab === 'text' ? textMessage.value : panel.querySelector('[data-field="caption"]').value;
        const hasText = raw.trim().length > 0;
        const media = mediaState[tab];
        // With media attached, an empty caption simply renders nothing (like Telegram)
        const showPlaceholder = !hasText && !media;
        if (hasText) {
          previewText.innerHTML = renderTelegramMarkdown(raw);
        } else {
          previewText.textContent = showPlaceholder ? 'Your message will appear here...' : '';
        }
        previewText.classList.toggle('hidden', !hasText && !showPlaceholder);
        previewText.classList.toggle('text-slate-800', hasText);
        previewText.classList.toggle('text-slate-400', showPlaceholder);
        previewText.classList.toggle('italic', showPlaceholder);

        // Media
        previewImage.classList.add('hidden');
        previewVideo.classList.add('hidden');
        previewFile.classList.add('hidden');
        previewMedia.classList.add('hidden');

        if (media) {
          if (tab === 'image') {
            previewImage.src = media.url;
            previewImage.classList.remove('hidden');
            previewMedia.classList.remove('hidden');
          } else if (tab === 'video') {
            previewVideo.src = media.url;
            previewVideo.classList.remove('hidden');
            previewMedia.classList.remove('hidden');
          } else if (tab === 'file') {
            previewFileName.textContent = media.name;
            previewFileSize.textContent = formatSize(media.size);
            previewFile.classList.remove('hidden');
            previewMedia.classList.remove('hidden');
          }
        }

        // Inline buttons â€” pairs side by side, a trailing odd button spans the full width below
        previewButtons.innerHTML = '';
        const buttons = collectButtons(tab);
        const hasOddTail = buttons.length % 2 === 1;
        buttons.forEach((b, index) => {
          const btn = document.createElement('div');
          const isOddTail = hasOddTail && index === buttons.length - 1;
          btn.className = 'py-2.5 px-3 rounded-xl text-white font-semibold text-xs backdrop-blur-xs flex items-center justify-center relative shadow-2xs text-center' + (isOddTail ? ' col-span-2' : '');
          btn.style.backgroundColor = STYLE_COLORS[b.style] || STYLE_COLORS.primary;
          const label = document.createElement('span');
          label.className = 'truncate';
          label.textContent = b.text;
          btn.appendChild(label);
          if (b.link) {
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-arrow-up-right-from-square text-[10px] opacity-80 absolute right-2.5 top-2.5';
            btn.appendChild(icon);
          }
          previewButtons.appendChild(btn);
        });
      }

      function handleMedia(input) {
        const panel = input.closest('.tab-panel');
        const tab = panel ? panel.id.replace('panel-', '') : activeTab;
        const file = input.files && input.files[0];
        const prev = mediaState[tab];
        if (prev && prev.url) URL.revokeObjectURL(prev.url);
        if (!file) {
          mediaState[tab] = null;
          updateUploadBox(input, null);
          return;
        }
        mediaState[tab] = {
          url: tab === 'file' ? null : URL.createObjectURL(file),
          name: file.name,
          size: file.size
        };
        updateUploadBox(input, file);
      }

      function updateUploadBox(input, file) {
        const box = input.closest('[data-upload]');
        if (!box) return;
        const label = box.querySelector('[data-upload-label]');
        const hint = box.querySelector('[data-upload-hint]');
        if (file) {
          label.textContent = file.name;
          label.classList.add('truncate', 'max-w-full');
          hint.textContent = formatSize(file.size) + ' Â· click to change';
        } else {
          label.textContent = box.dataset.defaultLabel;
          label.classList.remove('truncate', 'max-w-full');
          hint.textContent = box.dataset.defaultHint;
        }
      }

      // Clicking the upload box opens the file picker
      document.querySelectorAll('[data-upload]').forEach(box => {
        const input = box.querySelector('[data-field="media"]');
        box.dataset.defaultLabel = box.querySelector('[data-upload-label]').textContent;
        box.dataset.defaultHint = box.querySelector('[data-upload-hint]').textContent;
        box.addEventListener('click', (e) => {
          if (e.target === input) return;
          input.click();
        });
      });

      const editorPanels = '#panel-text, #panel-image, #panel-video, #panel-file';
      document.addEventListener('input', (e) => {
        if (e.target.closest(editorPanels)) renderPreview();
      });
      document.addEventListener('change', (e) => {
        if (e.target.matches('[data-field="media"]')) handleMedia(e.target);
        if (e.target.closest(editorPanels)) renderPreview();
      });
      textMessage.addEventListener('input', () => {
        textCharCount.textContent = textMessage.value.length + '/4096';
      });

      // ---- Submit: mirror the composer state into the hidden form fields ----
      const broadcastForm = document.getElementById('broadcastForm');
      const mediaTypeInput = document.getElementById('mediaTypeInput');
      const messageTextInput = document.getElementById('messageTextInput');
      const buttonsInput = document.getElementById('buttonsInput');

      broadcastForm.addEventListener('submit', (e) => {
        const panel = getPanel(activeTab);
        const caption = panel ? panel.querySelector('[data-field="caption"]') : null;
        const text = activeTab === 'text' ? textMessage.value : (caption ? caption.value : '');
        const buttons = collectButtons(activeTab);

        mediaTypeInput.value = activeTab === 'text' ? 'none' : activeTab;
        messageTextInput.value = text;
        buttonsInput.value = JSON.stringify(buttons);

        if (text.trim() === '' && buttons.length === 0) {
          e.preventDefault();
          alert('Add a message or at least one button before sending.');
        }
      });

      document.getElementById('clearBroadcast').addEventListener('click', () => {
        setTimeout(() => {
          textCharCount.textContent = '0/4096';
          Object.keys(mediaState).forEach((tab) => {
            if (mediaState[tab] && mediaState[tab].url) URL.revokeObjectURL(mediaState[tab].url);
            mediaState[tab] = null;
          });
          document.querySelectorAll('[data-upload]').forEach((box) => {
            const input = box.querySelector('[data-field="media"]');
            if (input) input.value = '';
            updateUploadBox(input, null);
          });
          renderPreview();
        }, 0);
      });

      renderPreview();
    </script>
  </body>
</html>
