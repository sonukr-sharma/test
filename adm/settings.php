<?php
/**
 * Admin — Platform settings
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reveal_bot_token') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }

    $savedBotToken = trim((string) setting('bot_token', ''));
    if ($savedBotToken === '') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No bot token is saved.']);
        exit;
    }

    echo json_encode([
        'ok'    => true,
        'token' => $savedBotToken,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$saved = false;
$error = null;
$botSetupNotice = null;
$botSetupError = null;
$submittedBotToken = '';
$botInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $sectionFields = [
            'general' => [
                'referral_reward_t' => 'decimal',
                'min_referrals_withdrawal' => 'integer',
                'min_referrals_subsequent_withdrawal' => 'integer',
                'signup_bonus_t' => 'decimal',
                'enable_signup_bonus' => 'boolean',
            ],
            'withdrawal' => [
                'withdrawal_mode' => 'string',
                'coin_to_inr_rate' => 'decimal',
                'withdrawal_processing_hours' => 'integer',
            ],
            'withdrawal_api' => [
                'withdrawal_api_url' => 'url',
            ],
            'withdrawal_notifications' => [
                'withdrawal_manual_chat_ids' => 'string',
                'withdrawal_action_admin_ids' => 'string',
            ],
            'bot' => [
                'bot_token' => 'secret',
                'bot_web_app_url' => 'url',
            ],
        ];
        $section = trim((string) ($_POST['settings_section'] ?? ''));
        if (!array_key_exists($section, $sectionFields)) {
            $error = 'Invalid settings section. Please submit the card again.';
        }

        $submittedWithdrawalMode = trim((string) ($_POST['withdrawal_mode'] ?? ''));
        if ($error === null && $section === 'withdrawal'
            && !in_array($submittedWithdrawalMode, ['manual', 'auto'], true)) {
            $error = 'Choose either Manual or Auto withdrawal mode.';
        }

        $manualChatIdsValue = '';
        $actionAdminIdsValue = '';
        if ($error === null && $section === 'withdrawal_notifications') {
            $rawChatIds = (string) ($_POST['withdrawal_manual_chat_ids'] ?? '');
            $chatIds = preg_split('/[\r\n,]+/', $rawChatIds) ?: [];
            $normalizedChatIds = [];
            foreach ($chatIds as $chatId) {
                $chatId = trim($chatId);
                if ($chatId === '') {
                    continue;
                }
                if (preg_match('/^(?:-?\d+|@[A-Za-z0-9_]{5,32})$/', $chatId) !== 1) {
                    $error = 'Enter numeric Telegram chat IDs or public @channel usernames, one per line.';
                    break;
                }
                if (!in_array($chatId, $normalizedChatIds, true)) {
                    $normalizedChatIds[] = $chatId;
                }
            }
            $manualChatIdsValue = implode("\n", $normalizedChatIds);

            if ($error === null) {
                $rawActionAdminIds = (string) ($_POST['withdrawal_action_admin_ids'] ?? '');
                $actionAdminIds = preg_split('/[\r\n,]+/', $rawActionAdminIds) ?: [];
                $normalizedActionAdminIds = [];
                foreach ($actionAdminIds as $telegramUserId) {
                    $telegramUserId = trim($telegramUserId);
                    if ($telegramUserId === '') {
                        continue;
                    }
                    if (preg_match('/^[1-9][0-9]{0,15}$/', $telegramUserId) !== 1) {
                        $error = 'Authorized Telegram admin IDs must be positive numeric user IDs.';
                        break;
                    }
                    if (!in_array($telegramUserId, $normalizedActionAdminIds, true)) {
                        $normalizedActionAdminIds[] = $telegramUserId;
                    }
                }
                $actionAdminIdsValue = implode("\n", $normalizedActionAdminIds);
            }
        }

        $submittedBotToken = trim((string) ($_POST['bot_token'] ?? ''));
        if ($error === null && $section === 'bot' && $submittedBotToken !== '') {
            $tokenResponse = telegram_api('getMe', [], 15, $submittedBotToken);
            if (($tokenResponse['ok'] ?? false) !== true
                || !is_array($tokenResponse['result'] ?? null)
                || empty($tokenResponse['result']['is_bot'])) {
                $reason = (string) ($tokenResponse['description'] ?? $tokenResponse['error'] ?? 'Telegram could not verify this bot token.');
                $error = 'Settings were not saved because the bot token could not be verified: ' . $reason;
            } else {
                $botInfo = $tokenResponse['result'];
            }
        }

        if ($error === null) {
            $fields = $sectionFields[$section];
            $fieldGroups = [
                'referral_reward_t' => 'referral',
                'min_referrals_withdrawal' => 'referral',
                'min_referrals_subsequent_withdrawal' => 'referral',
                'signup_bonus_t' => 'bonus',
                'enable_signup_bonus' => 'bonus',
                'withdrawal_mode' => 'withdrawal',
                'coin_to_inr_rate' => 'withdrawal',
                'withdrawal_processing_hours' => 'withdrawal',
                'withdrawal_api_url' => 'withdrawal',
                'withdrawal_manual_chat_ids' => 'withdrawal',
                'withdrawal_action_admin_ids' => 'withdrawal',
                'bot_token' => 'bot',
                'bot_web_app_url' => 'bot',
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

                foreach ($fields as $key => $type) {
                    if ($key === 'bot_token' && $submittedBotToken === '') {
                        continue;
                    }
                    if ($type === 'boolean') {
                        $value = !empty($_POST[$key]) ? '1' : '0';
                    } elseif ($key === 'withdrawal_mode') {
                        $value = $submittedWithdrawalMode;
                    } elseif ($key === 'withdrawal_manual_chat_ids') {
                        $value = $manualChatIdsValue;
                    } elseif ($key === 'withdrawal_action_admin_ids') {
                        $value = $actionAdminIdsValue;
                    } else {
                        // Skip fields that are not rendered on the page so we never blank them out.
                        if (!array_key_exists($key, $_POST)) {
                            continue;
                        }
                        $value = $key === 'bot_token'
                            ? $submittedBotToken
                            : trim((string) $_POST[$key]);
                    }

                    $stmt->execute([
                        ':key'   => $key,
                        ':value' => $value,
                        ':type'  => $type,
                        ':grp'   => $fieldGroups[$key],
                        ':admin' => $admin['id'],
                    ]);
                }

                if ($botInfo !== null) {
                    $botUsername = trim((string) ($botInfo['username'] ?? ''));
                    if ($botUsername !== '') {
                        $stmt->execute([
                            ':key'   => 'bot_username',
                            ':value' => $botUsername,
                            ':type'  => 'string',
                            ':grp'   => 'bot',
                            ':admin' => $admin['id'],
                        ]);
                    }

                    $webhookSecret = trim((string) setting('webhook_secret', ''));
                    if ($webhookSecret === '') {
                        $webhookSecret = bin2hex(random_bytes(24));
                        $stmt->execute([
                            ':key'   => 'webhook_secret',
                            ':value' => $webhookSecret,
                            ':type'  => 'secret',
                            ':grp'   => 'bot',
                            ':admin' => $admin['id'],
                        ]);
                    }
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

            if ($saved && $botInfo !== null) {
                settings(true);
                $botUsername = trim((string) ($botInfo['username'] ?? ''));
                $botLabel    = $botUsername !== '' ? '@' . $botUsername : 'The bot';
                $webhookUrl  = APP_URL . '/webhook/index.php';

                if (!APP_URL_IS_PUBLIC) {
                    $botSetupError = $botLabel . ' was verified and saved, but Telegram requires a public HTTPS URL before the webhook can be registered. Current webhook URL: ' . $webhookUrl;
                } else {
                    try {
                        $webhookResponse = telegram_set_webhook(
                            $webhookUrl,
                            trim((string) setting('webhook_secret', ''))
                        );

                        if (($webhookResponse['ok'] ?? false) === true) {
                            $botSetupNotice = 'Connected to ' . $botLabel . ' and configured the webhook at ' . $webhookUrl;
                        } else {
                            $reason = (string) ($webhookResponse['description'] ?? $webhookResponse['error'] ?? 'Unknown Telegram API error.');
                            $botSetupError = $botLabel . ' was saved, but Telegram could not register the webhook: ' . $reason;
                        }
                    } catch (Throwable $e) {
                        error_log('Automatic webhook setup failed: ' . $e->getMessage());
                        $botSetupError = APP_DEBUG ? $e->getMessage() : 'Bot settings were saved, but automatic webhook setup failed. Check the server log.';
                    }
                }
            }
        }
    }
}

$s = settings();
$withdrawalMode = in_array($s['withdrawal_mode'], ['manual', 'auto'], true)
    ? $s['withdrawal_mode']
    : 'manual';
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
  <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f6f7fb;
    }
    [data-withdrawal-mode-card][hidden] {
      display: none !important;
    }
    .tagify {
      --tags-border-color: #e2e8f0;
      --tags-hover-border-color: #6366f1;
      --tags-focus-border-color: #6366f1;
      --tag-bg: #f3f1fe;
      --tag-hover: #e9e3ff;
      --tag-text-color: #4f46e5;
      width: 100%;
      border-radius: 0.5rem;
      min-height: 8rem;
      align-content: flex-start;
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

  <?php require __DIR__ . '/common/sidebar.php'; ?>

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
    <?php if ($botSetupNotice !== null): ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium bg-emerald-50 border border-emerald-200 text-emerald-700">
        <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
        <span><?= e($botSetupNotice) ?></span>
      </div>
    <?php elseif ($botSetupError !== null): ?>
      <div class="mb-5 flex items-start gap-2.5 rounded-xl px-4 py-3 text-xs font-medium bg-amber-50 border border-amber-200 text-amber-800">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
        <span><?= e($botSetupError) ?></span>
      </div>
    <?php endif; ?>

    <!-- Main Settings Grid -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6">

      <!-- 1. General Settings -->
      <form method="post" action="" class="contents">
        <?= csrf_field() ?>
        <input type="hidden" name="settings_section" value="general" />
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
          <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-user-plus text-base sm:text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">General Settings</h3>
              <p class="text-xs text-slate-500">Configure referral rewards and signup bonuses.</p>
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
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Referrals for the 1st Withdrawal</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                <input type="text" name="min_referrals_withdrawal" value="<?= e((string) $s['min_referrals_withdrawal']) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">refs</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Minimum Referrals for Every Withdrawal After the 1st</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden bg-slate-50/40 focus-within:border-indigo-500">
                <input type="text" name="min_referrals_subsequent_withdrawal" value="<?= e((string) $s['min_referrals_subsequent_withdrawal']) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">refs</span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1.5">Signup Bonus (T)</label>
              <div class="flex rounded-lg border border-slate-200 overflow-hidden focus-within:border-indigo-500">
                <input type="text" name="signup_bonus_t" value="<?= e(number_format((float) $s['signup_bonus_t'], 0, '', '')) ?>" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white focus:outline-none" />
                <span class="px-3.5 py-2 bg-slate-100/80 text-slate-600 text-xs font-medium flex items-center border-l border-slate-200">T</span>
          </div>
            </div>

            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-medium text-slate-700">Enable Signup Bonus</span>
              <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" name="enable_signup_bonus" value="1" <?= !empty($s['enable_signup_bonus']) ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#6339f9]"></div>
              </label>
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
        <div class="mt-5 flex justify-end">
          <button type="submit" class="w-full justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Save Changes</span>
          </button>
        </div>
      </div>
      </form>

      <!-- 2. Withdrawal Settings -->
      <form method="post" action="" class="contents">
        <?= csrf_field() ?>
        <input type="hidden" name="settings_section" value="withdrawal" />
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
          <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-wallet text-base sm:text-lg"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900">Withdrawal Settings</h3>
              <p class="text-xs text-slate-500">Set the conversion rate and processing details.</p>
            </div>
          </div>

          <!-- Fields -->
          <div class="space-y-4">
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

        <div class="mt-5 pt-3 border-t border-slate-100">
          <label for="withdrawalMode" class="block text-xs font-medium text-slate-800 mb-1.5">Withdrawal Mode</label>
          <select id="withdrawalMode" name="withdrawal_mode" class="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:border-indigo-500">
            <option value="manual" <?= $withdrawalMode === 'manual' ? 'selected' : '' ?>>Manual withdrawal</option>
            <option value="auto" <?= $withdrawalMode === 'auto' ? 'selected' : '' ?>>Auto withdrawal</option>
          </select>
          <p class="text-[11px] text-slate-400 mt-1">Manual payments are handled by you and notify the configured Telegram chats. Auto withdrawals are sent to the gateway immediately without an approval step.</p>
        </div>

        <div class="mt-4 bg-[#f3f1fe] rounded-xl p-3">
          <p class="text-[11px] font-semibold text-slate-700 mb-1.5">Withdrawal amounts</p>
          <p class="text-[11px] leading-relaxed text-slate-600">
            Users can choose ₹10, ₹50, or ₹100. The amount is converted to T using the rate above and checked against
            the wallet balance. In Auto mode, every valid request is sent to the configured gateway; in Manual mode,
            requests are sent to the admin queue for payment.
          </p>
        </div>
        <div class="mt-5 flex justify-end">
          <button type="submit" class="w-full justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Save Changes</span>
          </button>
        </div>
      </div>
      </form>

      <!-- 3. Withdrawal API -->
      <form method="post" action="" class="contents" data-withdrawal-mode-card="auto" <?= $withdrawalMode === 'manual' ? 'hidden' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="settings_section" value="withdrawal_api" />
      <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
          <!-- Card Header -->
                <div class="flex items-center gap-3.5 mb-5">
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-code text-base sm:text-lg"></i>
            </div>
            <div>
                    <h3 class="text-sm sm:text-base font-bold text-slate-900">Auto Withdrawal Gateway</h3>
                    <p class="text-xs text-slate-500">Configure the payout gateway used in Auto mode.</p>
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
                    <p class="mt-1 text-[11px] text-slate-400">Required for automatic payments. Every withdrawal request is sent to this gateway when Auto mode is selected.</p>
                  </div>
                </div>

                <!-- Placeholder Info -->
                <div class="mt-5 bg-[#f3f1fe] rounded-xl p-3">
                  <p class="text-[11px] font-semibold text-slate-700 mb-1.5">POST payload placeholders</p>
                  <div class="space-y-1 font-mono text-[11px] text-slate-600">
                    <p><span class="text-[#6d37f7]">{amt}</span> — payout amount in ₹ (already converted from T)</p>
                    <p><span class="text-[#6d37f7]">{upi}</span> — user's UPI ID</p>
                    <p><span class="text-[#6d37f7]">{txid}</span> — transaction ID <span class="text-slate-400">(optional)</span></p>
                  </div>
                  <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                    Placeholders are substituted in the URL. The same values are also sent as a JSON body
                    (<code class="font-mono">amt</code>, <code class="font-mono">upi</code>, <code class="font-mono">txid</code>).
                    <strong class="text-slate-700">amt is the rupee value</strong> — the T-coin amount is converted with the
                    Coin → ₹ rate above, so 2,000 T is sent as <code class="font-mono">200.00</code>.
                    A 2xx response marks the payout completed; a <code class="font-mono">transaction_id</code>,
                    <code class="font-mono">txid</code>, <code class="font-mono">utr</code> or <code class="font-mono">reference</code>
                    field is stored as the transaction ID.
                  </p>
                </div>
              </div>
              <div class="mt-5 flex justify-end">
                <button type="submit" class="w-full justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
                  <i class="fa-solid fa-floppy-disk"></i>
                  <span>Save Changes</span>
                </button>
              </div>
            </div>
      </form>

      <form method="post" action="" class="contents" data-withdrawal-mode-card="manual" <?= $withdrawalMode === 'auto' ? 'hidden' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="settings_section" value="withdrawal_notifications" />
        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <div>
            <div class="flex items-center gap-3.5 mb-5">
              <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bell text-base sm:text-lg"></i>
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-slate-900">Manual Payment Notifications</h3>
                <p class="text-xs text-slate-500">Notify Telegram chats when a manual request arrives.</p>
              </div>
            </div>
            <label class="block text-xs font-medium text-slate-700 mb-1.5">Telegram Chat IDs</label>
            <textarea id="withdrawalManualChatIds" name="withdrawal_manual_chat_ids" aria-label="Telegram Chat IDs" placeholder="Add a chat ID or @channel, then press Enter"><?= e((string) $s['withdrawal_manual_chat_ids']) ?></textarea>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-400">Add user, group, or channel IDs as tags. Numeric IDs and public @channel usernames are accepted. The bot must be able to message each destination.</p>
            <label class="block text-xs font-medium text-slate-700 mt-4 mb-1.5">Authorized Telegram Admin User IDs</label>
            <textarea id="withdrawalActionAdminIds" name="withdrawal_action_admin_ids" aria-label="Authorized Telegram Admin User IDs" placeholder="Add a Telegram user ID, then press Enter"><?= e((string) $s['withdrawal_action_admin_ids']) ?></textarea>
            <p class="mt-1 text-[11px] leading-relaxed text-slate-400">Every notification includes the payment buttons. Personal user IDs listed as private-chat destinations above are authorized automatically; add other personal Telegram user IDs here for group or channel notifications. Group and channel IDs cannot authorize button actions.</p>
          </div>
          <div class="mt-5 flex justify-end">
            <button type="submit" class="w-full justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
              <i class="fa-solid fa-floppy-disk"></i>
              <span>Save Changes</span>
            </button>
          </div>
        </div>
      </form>

            <!-- 4. Bot & Links -->
            <form method="post" action="" class="contents">
              <?= csrf_field() ?>
              <input type="hidden" name="settings_section" value="bot" />
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
                      <input id="botToken" type="password" name="bot_token" value="" autocomplete="new-password" placeholder="Enter your Telegram bot token" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent focus:outline-none min-w-0" />
                      <button type="button" data-toggle-password="botToken" data-token-saved="<?= trim((string) $s['bot_token']) !== '' ? 'true' : 'false' ?>" aria-label="Show bot token" aria-pressed="false" class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-eye text-xs"></i>
                      </button>
                    </div>
                    <p id="botTokenFeedback" aria-live="polite" class="mt-1 text-[11px] text-slate-400">
                      <?= trim((string) $s['bot_token']) !== '' ? 'Token is saved. Leave this blank to keep it; enter a token to connect or refresh the bot.' : 'Enter a token from @BotFather to connect your bot.' ?>
                    </p>
                  </div>

                  <div class="rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 text-[11px] text-slate-600">
                    <p>
                      Bot:
                      <span class="font-semibold text-slate-800">
                        <?= trim((string) $s['bot_username']) !== '' ? '@' . e((string) $s['bot_username']) : 'Not connected' ?>
                      </span>
                    </p>
                    <p class="mt-1">
                      Webhook URL:
                      <span class="break-all font-mono"><?= e(APP_URL . '/webhook/index.php') ?></span>
                    </p>
                    <p class="mt-1 text-slate-500">Saving a token verifies the bot, refreshes its username, and automatically registers this webhook. Configure the Mini App button in @BotFather. Telegram requires a public HTTPS URL.</p>
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Bot Web App URL</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50/70 overflow-hidden focus-within:border-indigo-500">
                      <input id="botWebAppUrl" name="bot_web_app_url" type="text" value="<?= e((string) $s['bot_web_app_url']) ?>" placeholder="<?= e(web_app_url()) ?>" readonly aria-readonly="true" class="w-full px-3.5 py-2 text-xs text-slate-600 bg-transparent placeholder:text-slate-600 placeholder:opacity-100 focus:outline-none min-w-0" />
                      <button type="button" data-copy-target="botWebAppUrl" aria-label="Copy Bot Web App URL" title="Copy Bot Web App URL" class="px-3 py-2 text-slate-400 hover:text-slate-600 shrink-0">
                        <i class="fa-regular fa-copy text-xs"></i>
                      </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Read-only and copyable. When no custom URL is saved, the current app URL is shown and follows the current domain automatically.</p>
                  </div>
                </div>
              </div>
              <div class="mt-5 flex justify-end">
                <button type="submit" class="w-full justify-center bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-medium text-xs px-5 py-3 sm:py-2.5 rounded-xl shadow-md shadow-indigo-500/25 flex items-center gap-2 transition">
                  <i class="fa-solid fa-floppy-disk"></i>
                  <span>Save Changes</span>
                </button>
              </div>
            </div>
            </form>

          </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
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

    const withdrawalModeSelect = document.getElementById('withdrawalMode');
    if (withdrawalModeSelect) {
      withdrawalModeSelect.addEventListener('change', function () {
        const selectedMode = withdrawalModeSelect.value;
        document.querySelectorAll('[data-withdrawal-mode-card]').forEach(function (card) {
          card.hidden = card.dataset.withdrawalModeCard !== selectedMode;
        });
      });
    }

    const manualChatIdsInput = document.getElementById('withdrawalManualChatIds');
    if (manualChatIdsInput && typeof Tagify === 'function') {
      new Tagify(manualChatIdsInput, {
        delimiters: ',|\\n',
        pattern: /^(?:-?\d+|@[A-Za-z0-9_]{5,32})$/,
        originalInputValueFormat: function (values) {
          return values.map(function (tag) { return tag.value; }).join('\n');
        },
        duplicates: false,
        editTags: false,
        keepInvalidTags: false,
        placeholder: 'Add a chat ID or @channel, then press Enter'
      });
    }

    const withdrawalActionAdminIdsInput = document.getElementById('withdrawalActionAdminIds');
    if (withdrawalActionAdminIdsInput && typeof Tagify === 'function') {
      new Tagify(withdrawalActionAdminIdsInput, {
        delimiters: ',|\\n',
        pattern: /^[1-9][0-9]{0,15}$/,
        originalInputValueFormat: function (values) {
          return values.map(function (tag) { return tag.value; }).join('\n');
        },
        duplicates: false,
        editTags: false,
        keepInvalidTags: false,
        placeholder: 'Add an authorized Telegram user ID'
      });
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

    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
      const passwordInput = document.getElementById(btn.dataset.togglePassword);
      if (passwordInput) {
        passwordInput.addEventListener('input', function () {
          btn.dataset.tokenSaved = 'false';
        });
      }

      btn.addEventListener('click', async function () {
        const input = document.getElementById(btn.dataset.togglePassword);
        const icon = btn.querySelector('i');
        if (!input || !icon) return;

        if (input.type === 'password' && input.value === '' && btn.dataset.tokenSaved === 'true') {
          const csrfToken = document.querySelector('input[name="csrf_token"]');
          const feedback = document.getElementById('botTokenFeedback');
          btn.disabled = true;
          try {
            const response = await fetch(window.location.href, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
              body: new URLSearchParams({
                action: 'reveal_bot_token',
                csrf_token: csrfToken ? csrfToken.value : ''
              })
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true || typeof result.token !== 'string') {
              throw new Error(result.error || 'Unable to reveal the saved bot token.');
            }

            input.value = result.token;
            if (feedback) {
              feedback.textContent = 'Saved token shown. Hide it when you are finished.';
              feedback.classList.remove('text-rose-600');
              feedback.classList.add('text-slate-400');
            }
          } catch (error) {
            if (feedback) {
              feedback.textContent = error.message || 'Unable to reveal the saved bot token.';
              feedback.classList.remove('text-slate-400');
              feedback.classList.add('text-rose-600');
            }
            return;
          } finally {
            btn.disabled = false;
          }
        }

        const showToken = input.type === 'password';
        input.type = showToken ? 'text' : 'password';
        btn.setAttribute('aria-pressed', showToken ? 'true' : 'false');
        btn.setAttribute('aria-label', showToken ? 'Hide bot token' : 'Show bot token');
        icon.className = showToken ? 'fa-regular fa-eye-slash text-xs' : 'fa-regular fa-eye text-xs';
      });
    });
  </script>
</body>
</html>
