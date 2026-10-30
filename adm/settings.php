<?php
/**
 * Admin — Platform settings
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$saved = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        // key => [data_type, cast]
        $fields = [
            'referral_reward_t'            => 'decimal',
            'min_referrals_withdrawal'     => 'integer',
            'min_withdrawal_t'             => 'decimal',
            'max_withdrawal_t'             => 'decimal',
            'auto_approve_withdrawals'     => 'boolean',
            'withdrawal_api_url'           => 'url',
            'coin_to_inr_rate'             => 'decimal',
            'bot_token'                    => 'secret',
            'bot_web_app_url'              => 'url',
            'signup_bonus_t'               => 'decimal',
            'enable_signup_bonus'          => 'boolean',
            'allow_registration'           => 'boolean',
            'withdrawals_enabled'          => 'boolean',
            'withdrawal_processing_hours'  => 'integer',
            'require_channel_join'         => 'boolean',
            'require_bot_start'            => 'boolean',
            'channel_gate_strict'          => 'boolean',
            'channel_gate_message'         => 'string',
        ];

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO `settings` (`key`, `value`, `data_type`, `group_name`, `updated_by`)
                 VALUES (:key, :value, :type, :grp, :admin)
                 ON DUPLICATE KEY UPDATE
                    `value` = VALUES(`value`),
                    `updated_by` = VALUES(`updated_by`)'
            );

            $groups = [
                'referral'   => ['referral_reward_t', 'min_referrals_withdrawal'],
                'withdrawal' => ['min_withdrawal_t', 'max_withdrawal_t', 'auto_approve_withdrawals',
                                 'withdrawal_api_url', 'coin_to_inr_rate', 'withdrawal_processing_hours'],
                'bot'        => ['bot_token', 'bot_web_app_url'],
                'bonus'      => ['signup_bonus_t', 'enable_signup_bonus'],
                'platform'   => ['allow_registration', 'withdrawals_enabled'],
                'gate'       => ['require_channel_join', 'require_bot_start', 'channel_gate_strict', 'channel_gate_message'],
            ];

            foreach ($fields as $key => $type) {
                if ($type === 'boolean') {
                    $value = !empty($_POST[$key]) ? '1' : '0';
                } else {
                    // Skip fields that are not rendered on the page so we never blank them out.
                    if (!array_key_exists($key, $_POST)) {
                        continue;
                    }
                    $value = trim((string) $_POST[$key]);
                }

                $group = 'general';
                foreach ($groups as $groupName => $keys) {
                    if (in_array($key, $keys, true)) {
                        $group = $groupName;
                        break;
                    }
                }

                $stmt->execute([
                    ':key'   => $key,
                    ':value' => $value,
                    ':type'  => $type,
                    ':grp'   => $group,
                    ':admin' => $admin['id'],
                ]);
            }

            $audit = $pdo->prepare(
                'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `ip_address`)
                 VALUES (:admin, :action, :entity_type, :ip)'
            );
            $audit->execute([
                ':admin'       => $admin['id'],
                ':action'      => 'settings.update',
                ':entity_type' => 'settings',
                ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $pdo->commit();
            $saved = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Settings save failed: ' . $e->getMessage());
            $error = APP_DEBUG ? $e->getMessage() : 'Unable to save settings right now.';
        }
    }
}

$s = settings();
?>
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
      <a href="channels.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-tower-broadcast text-slate-400 w-5 text-center text-base"></i>
        <span>Channels</span>
      </a>
      <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-gear text-white w-5 text-center text-base"></i>
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
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Settings</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Configure your Refer & Earn platform settings and view key statistics.</p>
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

    <?php if ($saved): ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium bg-emerald-50 border border-emerald-200 text-emerald-700">
        <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
        <span>Settings saved successfully.</span>
      </div>
    <?php elseif ($error !== null): ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium bg-rose-50 border border-rose-200 text-rose-700">
        <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="" class="contents">
      <?= csrf_field() ?>

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
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Reward per Referral (T)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                <input type="text" name="referral_reward_t" value="<?= e(number_format((float) $s['referral_reward_t'], 0, '', '')) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                              <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">T</span>
              </div>
            </div>

                        <div>
                          <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Referrals per Withdrawal</label>
                          <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                            <input type="text" name="min_referrals_withdrawal" value="<?= e((string) $s['min_referrals_withdrawal']) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
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
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Withdrawal Amount (T)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                <input type="text" name="min_withdrawal_t" value="<?= e(number_format((float) $s['min_withdrawal_t'], 0, '', '')) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                              <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">T</span>
              </div>
            </div>

            <div>
                          <label class="block text-xs font-medium text-slate-700 mb-1.5">Maximum Withdrawal Amount (T)</label>
                          <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                            <input type="text" name="max_withdrawal_t" value="<?= e(number_format((float) $s['max_withdrawal_t'], 0, '', '')) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                                                      <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">T</span>
                          </div>
                        </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Conversion Rate (₹ per 1 T)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                <input type="text" name="coin_to_inr_rate" value="<?= e((string) $s['coin_to_inr_rate']) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">₹</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Processing Time Shown to Users (hours)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                <input type="text" name="withdrawal_processing_hours" value="<?= e((string) $s['withdrawal_processing_hours']) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">hrs</span>
              </div>
            </div>
                      </div>
        </div>

        <!-- Auto Approve Toggle -->
        <div class="mt-5 pt-3 border-t border-slate-100">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-slate-800">Auto Approve Withdrawals</span>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" name="auto_approve_withdrawals" value="1" <?= !empty($s['auto_approve_withdrawals']) ? 'checked' : '' ?> class="sr-only peer">
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
                      <input id="withdrawalApiUrl" type="text" name="withdrawal_api_url" value="<?= e((string) $s['withdrawal_api_url']) ?>" placeholder="https://your-payout-service.com/api/withdraw" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button type="button" data-copy-target="withdrawalApiUrl" class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-copy text-xs"></i>
                      </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Optional. Only needed to forward payouts to an external service — leave blank to keep withdrawals in the admin panel.</p>
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
                      <input type="password" name="bot_token" value="<?= e((string) $s['bot_token']) ?>" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-eye text-xs"></i>
                      </button>
                    </div>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Bot Web App URL</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/70 overflow-hidden focus-within:border-indigo-500">
                      <input id="botWebAppUrl" name="bot_web_app_url" type="text" value="<?= e((string) $s['bot_web_app_url']) ?>" placeholder="<?= e(web_app_url()) ?>" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button type="button" data-copy-target="botWebAppUrl" class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-copy text-xs"></i>
                      </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Leave blank to follow the current domain automatically: <span class="font-mono"><?= e(web_app_url()) ?></span></p>
                  </div>

                  <a href="../webhook/setup.php" class="flex items-center justify-center gap-2 w-full px-3.5 py-2 rounded-lg border border-[#1d9bf0]/30 bg-[#e5f4ff]/60 text-xs font-semibold text-[#1d9bf0] hover:bg-[#e5f4ff] transition-colors">
                    <i class="fa-solid fa-plug-circle-check text-xs"></i>
                    Configure Telegram Webhook
                  </a>
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
                    <label class="block text-xs font-medium text-slate-700 mb-1">Signup Bonus (T)</label>
                    <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                      <input type="text" name="signup_bonus_t" value="<?= e(number_format((float) $s['signup_bonus_t'], 0, '', '')) ?>" class="w-full px-3.5 py-1.5 text-sm text-slate-800 bg-white focus:outline-none" />
                      <span class="px-3.5 py-1.5 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">T</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Bonus Toggles -->
              <div class="mt-5 space-y-2.5 pt-2">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-medium text-slate-700">Enable Signup Bonus</span>
                  <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" name="enable_signup_bonus" value="1" <?= !empty($s['enable_signup_bonus']) ? 'checked' : '' ?> class="sr-only peer">
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
                      <input type="checkbox" name="allow_registration" value="1" <?= !empty($s['allow_registration']) ? 'checked' : '' ?> class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>

                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-slate-800">Withdrawals</span>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" name="withdrawals_enabled" value="1" <?= !empty($s['withdrawals_enabled']) ? 'checked' : '' ?> class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Save Changes Button -->
              <div class="mt-5 flex justify-end">
                <button type="submit" class="w-full sm:w-auto justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
                  <i class="fa-solid fa-floppy-disk"></i>
                  <span>Save Changes</span>
                </button>
              </div>
            </div>

            <!-- 7. Access Gate -->
            <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
              <div>
                <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-6">
                  <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-lock text-base sm:text-lg"></i>
                  </div>
                  <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Access Gate</h3>
                    <p class="text-xs text-slate-500">Require the bot and channel joins before the app opens.</p>
                  </div>
                </div>

                <!-- Toggle Rows -->
                <div class="space-y-4">
                  <div class="flex items-center justify-between gap-2">
                    <div>
                      <span class="text-xs font-medium text-slate-800">Require /start First</span>
                      <p class="text-[11px] text-slate-400 mt-0.5">Block the mini app until the user messages the bot.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" name="require_bot_start" value="1" <?= !empty($s['require_bot_start']) ? 'checked' : '' ?> class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>

                  <div class="flex items-center justify-between gap-2">
                    <div>
                      <span class="text-xs font-medium text-slate-800">Require Channel Membership</span>
                      <p class="text-[11px] text-slate-400 mt-0.5">Users must join every active channel.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" name="require_channel_join" value="1" <?= !empty($s['require_channel_join']) ? 'checked' : '' ?> class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>

                  <div class="flex items-center justify-between gap-2">
                    <div>
                      <span class="text-xs font-medium text-slate-800">Strict Mode</span>
                      <p class="text-[11px] text-slate-400 mt-0.5">Block users when membership cannot be verified.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" name="channel_gate_strict" value="1" <?= !empty($s['channel_gate_strict']) ? 'checked' : '' ?> class="sr-only peer">
                      <div class="w-10 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
                    </label>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Gate Message</label>
                    <textarea name="channel_gate_message" rows="2" class="w-full px-3.5 py-2 text-xs text-slate-600 rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500 resize-none"><?= e((string) $s['channel_gate_message']) ?></textarea>
                  </div>

                  <a href="channels.php" class="flex items-center justify-center gap-2 w-full px-3.5 py-2 rounded-lg border border-[#6d37f7]/30 bg-[#f2edff]/60 text-xs font-semibold text-[#6d37f7] hover:bg-[#f2edff] transition-colors">
                    <i class="fa-solid fa-tower-broadcast text-xs"></i>
                    Manage Required Channels
                  </a>
                </div>
              </div>

              <!-- Save Changes Button -->
              <div class="mt-5 flex justify-end">
                <button type="submit" class="w-full sm:w-auto justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
                  <i class="fa-solid fa-floppy-disk"></i>
                  <span>Save Changes</span>
                </button>
              </div>
            </div>

          </section>
    </form>
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

    // Copy buttons: copy the input's value, or its placeholder when left blank
    // (blank means "follow the current domain", so the placeholder is the real URL).
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.copyTarget);
        if (!input) return;
        const text = input.value.trim() || input.placeholder || '';
        if (!text) return;
        navigator.clipboard.writeText(text).then(function () {
          const icon = btn.querySelector('i');
          if (!icon) return;
          icon.className = 'fa-solid fa-check text-xs text-emerald-500';
          setTimeout(function () { icon.className = 'fa-regular fa-copy text-xs'; }, 1500);
        });
      });
    });
  </script>
</body>
</html>
