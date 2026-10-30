<?php
/**
 * =====================================================================
 *  webhook/setup.php — Telegram webhook auto-configuration
 *
 *  Open this page in a browser to:
 *    • check the bot token and see the bot identity
 *    • register / re-register / delete the webhook with Telegram
 *    • inspect Telegram's getWebhookInfo response
 *    • send a test message to yourself
 *
 *  The page is protected by the admin session (log in at adm/auth/).
 * =====================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$settings = settings(true);

$botToken   = trim((string) ($settings['bot_token'] ?? ''));
$webhookUrl = APP_URL . '/webhook/index.php';
$secret     = trim((string) ($settings['webhook_secret'] ?? ''));

$notice = null;
$error  = null;
$me     = null;
$info   = null;

/**
 * Persist a setting value.
 */
function save_setting(string $key, string $value, string $type = 'string', string $group = 'bot', string $label = ''): void
{
    $adminId = current_admin()['id'] ?? null;

    $stmt = db()->prepare(
        'INSERT INTO `settings` (`key`, `value`, `data_type`, `group_name`, `label`, `updated_by`)
         VALUES (:k, :v, :t, :g, :l, :a)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`)'
    );
    $stmt->execute([
        ':k' => $key,
        ':v' => $value,
        ':t' => $type,
        ':g' => $group,
        ':l' => $label !== '' ? $label : $key,
        ':a' => $adminId,
    ]);
}

/* ---------------------------------------------------------------------
 |  Actions
 * ------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload the page and try again.';
    } elseif ($botToken === '') {
        $error = 'Set the bot token in Admin → Settings before configuring the webhook.';
    } else {
        try {
            switch ($action) {
                case 'set':
                    if ($secret === '') {
                        $secret = bin2hex(random_bytes(24));
                        save_setting('webhook_secret', $secret, 'secret', 'bot', 'Telegram Webhook Secret Token');
                    }

                    $response = telegram_set_webhook($webhookUrl, $secret);

                    if (($response['ok'] ?? false) === true) {
                        $notice = 'Webhook registered: ' . $webhookUrl;
                    } else {
                        $error = 'Telegram rejected the webhook: ' . ($response['description'] ?? $response['error'] ?? 'unknown error');
                    }
                    break;

                case 'delete':
                    $response = telegram_delete_webhook();

                    if (($response['ok'] ?? false) === true) {
                        $notice = 'Webhook deleted. Telegram will no longer deliver updates.';
                    } else {
                        $error = 'Could not delete the webhook: ' . ($response['description'] ?? $response['error'] ?? 'unknown error');
                    }
                    break;

                case 'rotate':
                    $secret = bin2hex(random_bytes(24));
                    save_setting('webhook_secret', $secret, 'secret', 'bot', 'Telegram Webhook Secret Token');

                    $response = telegram_set_webhook($webhookUrl, $secret);

                    if (($response['ok'] ?? false) === true) {
                        $notice = 'Secret token rotated and the webhook re-registered.';
                    } else {
                        $error = 'Secret rotated, but re-registering failed: ' . ($response['description'] ?? $response['error'] ?? 'unknown error');
                    }
                    break;

                case 'test':
                    $chatId = (int) ($_POST['chat_id'] ?? 0);

                    if ($chatId === 0) {
                        $error = 'Enter a Telegram chat id to send the test message to.';
                        break;
                    }

                    $response = telegram_send_message(
                        $chatId,
                        "✅ <b>Refer &amp; Earn</b>\nWebhook test message from the admin panel."
                    );

                    if (($response['ok'] ?? false) === true) {
                        $notice = 'Test message sent to ' . $chatId . '.';
                    } else {
                        $error = 'Test message failed: ' . ($response['description'] ?? $response['error'] ?? 'unknown error');
                    }
                    break;

                case 'refresh':
                    $me = telegram_me(true);

                    if ($me !== null && !empty($me['username'])) {
                        save_setting('bot_username', (string) $me['username'], 'string', 'bot', 'Telegram Bot Username (auto-filled)');
                        $notice = 'Bot identity refreshed: @' . $me['username'];
                    } else {
                        $error = 'Could not reach the Telegram Bot API. Check the token.';
                    }
                    break;

                default:
                    $error = 'Unknown action.';
            }
        } catch (Throwable $e) {
            error_log('webhook/setup.php failed: ' . $e->getMessage());
            $error = APP_DEBUG ? $e->getMessage() : 'The action failed. Check the server log.';
        }

        $settings = settings(true);
        $secret   = trim((string) ($settings['webhook_secret'] ?? ''));
    }
}

/* ---------------------------------------------------------------------
 |  Read-only status
 * ------------------------------------------------------------------- */

if ($botToken !== '') {
    $me   = telegram_me();
    $info = telegram_get_webhook_info();
}

$currentUrl   = is_array($info) && ($info['ok'] ?? false) ? (string) ($info['result']['url'] ?? '') : '';
$pendingCount = is_array($info) && ($info['ok'] ?? false) ? (int) ($info['result']['pending_update_count'] ?? 0) : 0;
$lastError    = is_array($info) && ($info['ok'] ?? false) ? (string) ($info['result']['last_error_message'] ?? '') : '';
$lastErrorAt  = is_array($info) && ($info['ok'] ?? false) ? (int) ($info['result']['last_error_date'] ?? 0) : 0;

$isHttps = str_starts_with($webhookUrl, 'https://');
$isLocal = APP_ENV === 'local';

$recentUpdates = [];
try {
    $recentUpdates = db()
        ->query('SELECT `update_id`, `telegram_id`, `update_type`, `command`, `handled`, `error_message`, `created_at`
                   FROM `telegram_updates`
                  ORDER BY `id` DESC
                  LIMIT 10')
        ->fetchAll();
} catch (Throwable $e) {
    error_log('webhook/setup.php recent updates failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Telegram Webhook Setup — <?= e(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased py-8 px-4">
  <div class="max-w-3xl mx-auto space-y-4">

    <!-- Header -->
    <div class="bg-gradient-to-br from-[#5925dc] to-[#7c3aed] rounded-3xl p-6 text-white shadow-lg">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center">
            <i class="fa-brands fa-telegram text-2xl"></i>
          </div>
          <div>
            <h1 class="text-xl font-extrabold leading-tight">Telegram Webhook Setup</h1>
            <p class="text-white/80 text-xs font-medium mt-0.5">Register this app with your Telegram bot</p>
          </div>
        </div>
        <a href="<?= e(APP_URL) ?>/adm/settings.php" class="text-xs font-bold bg-white/15 hover:bg-white/25 transition px-3.5 py-2 rounded-xl whitespace-nowrap">
          <i class="fa-solid fa-gear mr-1"></i> Settings
        </a>
      </div>
    </div>

    <?php if ($notice !== null): ?>
      <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-circle-check mt-0.5"></i>
        <span><?= e($notice) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Warnings -->
    <?php if ($botToken === ''): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
        <span>
          No bot token configured. Add it in
          <a href="<?= e(APP_URL) ?>/adm/settings.php" class="underline font-bold">Admin → Settings → Telegram Bot Token</a>
          (get one from <span class="font-mono">@BotFather</span>).
        </span>
      </div>
    <?php endif; ?>

    <?php if (!$isHttps): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-lock mt-0.5"></i>
        <span>
          Telegram only accepts <strong>HTTPS</strong> webhook URLs. The computed URL is
          <span class="font-mono text-xs"><?= e($webhookUrl) ?></span>.
          Deploy behind HTTPS (or use a tunnel such as ngrok) and reload this page —
          the URL is detected from the current domain automatically.
        </span>
      </div>
    <?php endif; ?>

    <!-- Status card -->
    <div class="bg-white rounded-3xl p-5 shadow-sm">
      <h2 class="text-sm font-extrabold text-slate-900 mb-3 flex items-center gap-2">
        <i class="fa-solid fa-circle-info text-[#5925dc]"></i> Current status
      </h2>

      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <dt class="font-semibold text-slate-500">Bot token</dt>
          <dd class="mt-0.5 font-bold <?= $botToken !== '' ? 'text-emerald-600' : 'text-rose-600' ?>">
            <?= $botToken !== '' ? 'Configured (' . e(substr($botToken, 0, 6)) . '…' . e(substr($botToken, -4)) . ')' : 'Missing' ?>
          </dd>
        </div>

        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <dt class="font-semibold text-slate-500">Bot identity</dt>
          <dd class="mt-0.5 font-bold text-slate-800">
            <?php if ($me !== null && !empty($me['username'])): ?>
              @<?= e((string) $me['username']) ?>
              <span class="text-slate-400 font-medium">(id <?= e((string) ($me['id'] ?? '')) ?>)</span>
            <?php else: ?>
              <span class="text-slate-400 font-medium">Unknown</span>
            <?php endif; ?>
          </dd>
        </div>

        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5 sm:col-span-2">
          <dt class="font-semibold text-slate-500">Webhook URL to register</dt>
          <dd class="mt-0.5 font-mono text-[11px] font-bold text-slate-800 break-all"><?= e($webhookUrl) ?></dd>
        </div>

        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5 sm:col-span-2">
          <dt class="font-semibold text-slate-500">Registered with Telegram</dt>
          <dd class="mt-0.5 font-mono text-[11px] font-bold break-all <?= $currentUrl !== '' ? 'text-emerald-600' : 'text-slate-400' ?>">
            <?= $currentUrl !== '' ? e($currentUrl) : 'Not registered' ?>
          </dd>
        </div>

        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <dt class="font-semibold text-slate-500">Pending updates</dt>
          <dd class="mt-0.5 font-bold text-slate-800"><?= e((string) $pendingCount) ?></dd>
        </div>

        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <dt class="font-semibold text-slate-500">Secret token</dt>
          <dd class="mt-0.5 font-bold <?= $secret !== '' ? 'text-emerald-600' : 'text-slate-400' ?>">
            <?= $secret !== '' ? 'Set (' . e(substr($secret, 0, 6)) . '…)' : 'Not set' ?>
          </dd>
        </div>

        <?php if ($lastError !== ''): ?>
          <div class="bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 sm:col-span-2">
            <dt class="font-semibold text-rose-600">Last Telegram error</dt>
            <dd class="mt-0.5 font-semibold text-rose-800 break-words">
              <?= e($lastError) ?>
              <?php if ($lastErrorAt > 0): ?>
                <span class="block text-[10px] font-medium text-rose-500 mt-0.5"><?= e(date('d M Y, h:i A', $lastErrorAt)) ?></span>
              <?php endif; ?>
            </dd>
          </div>
        <?php endif; ?>
      </dl>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-3xl p-5 shadow-sm">
      <h2 class="text-sm font-extrabold text-slate-900 mb-3 flex items-center gap-2">
        <i class="fa-solid fa-bolt text-[#5925dc]"></i> Actions
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <form method="post" class="contents">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set">
          <button type="submit" <?= $botToken === '' ? 'disabled' : '' ?>
                  class="w-full bg-[#5925dc] hover:bg-[#4a1cbe] disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm py-3 rounded-2xl flex items-center justify-center gap-2 transition">
            <i class="fa-solid fa-link"></i>
            <span>Set Webhook</span>
          </button>
        </form>

        <form method="post" class="contents">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="refresh">
          <button type="submit" <?= $botToken === '' ? 'disabled' : '' ?>
                  class="w-full bg-slate-800 hover:bg-slate-900 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm py-3 rounded-2xl flex items-center justify-center gap-2 transition">
            <i class="fa-solid fa-rotate"></i>
            <span>Refresh Bot Info</span>
          </button>
        </form>

        <form method="post" class="contents">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="rotate">
          <button type="submit" <?= $botToken === '' ? 'disabled' : '' ?>
                  class="w-full bg-amber-500 hover:bg-amber-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm py-3 rounded-2xl flex items-center justify-center gap-2 transition">
            <i class="fa-solid fa-key"></i>
            <span>Rotate Secret &amp; Re-register</span>
          </button>
        </form>

        <form method="post" class="contents">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button type="submit" <?= $botToken === '' ? 'disabled' : '' ?>
                  onclick="return confirm('Delete the webhook? The bot will stop receiving updates until you set it again.');"
                  class="w-full bg-rose-600 hover:bg-rose-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm py-3 rounded-2xl flex items-center justify-center gap-2 transition">
            <i class="fa-solid fa-trash-can"></i>
            <span>Delete Webhook</span>
          </button>
        </form>
      </div>

      <form method="post" class="mt-3 flex flex-col sm:flex-row gap-2.5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test">
        <input type="number" name="chat_id" placeholder="Your Telegram chat id (e.g. 900000001)"
               class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-3 text-sm font-semibold outline-none focus:border-[#5925dc]" />
        <button type="submit" <?= $botToken === '' ? 'disabled' : '' ?>
                class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-sm px-5 py-3 rounded-2xl flex items-center justify-center gap-2 transition whitespace-nowrap">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Send Test</span>
        </button>
      </form>
    </div>

    <!-- Recent updates -->
    <div class="bg-white rounded-3xl p-5 shadow-sm">
      <h2 class="text-sm font-extrabold text-slate-900 mb-3 flex items-center gap-2">
        <i class="fa-solid fa-inbox text-[#5925dc]"></i> Recent updates
      </h2>

      <?php if ($recentUpdates === []): ?>
        <p class="text-xs font-medium text-slate-400 py-4 text-center">No updates received yet.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-[11px]">
            <thead>
              <tr class="text-left text-slate-500 font-semibold border-b border-slate-100">
                <th class="py-2 pr-3">Update</th>
                <th class="py-2 pr-3">Telegram ID</th>
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Command</th>
                <th class="py-2 pr-3">Status</th>
                <th class="py-2">Received</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($recentUpdates as $row): ?>
                <tr>
                  <td class="py-2 pr-3 font-mono text-slate-700"><?= e((string) ($row['update_id'] ?? '—')) ?></td>
                  <td class="py-2 pr-3 font-mono text-slate-700"><?= e((string) ($row['telegram_id'] ?? '—')) ?></td>
                  <td class="py-2 pr-3 text-slate-600"><?= e((string) ($row['update_type'] ?? '—')) ?></td>
                  <td class="py-2 pr-3 font-mono text-slate-600"><?= e((string) ($row['command'] ?? '—')) ?></td>
                  <td class="py-2 pr-3">
                    <?php if ((int) $row['handled'] === 1): ?>
                      <span class="text-emerald-600 font-bold">handled</span>
                    <?php else: ?>
                      <span class="text-rose-600 font-bold" title="<?= e((string) ($row['error_message'] ?? '')) ?>">failed</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2 text-slate-500"><?= e(activity_time($row['created_at'] ?? null)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Help -->
    <div class="bg-white rounded-3xl p-5 shadow-sm">
      <h2 class="text-sm font-extrabold text-slate-900 mb-2 flex items-center gap-2">
        <i class="fa-solid fa-book text-[#5925dc]"></i> How to finish the setup
      </h2>
      <ol class="text-xs text-slate-600 space-y-1.5 list-decimal list-inside font-medium">
        <li>Create a bot with <span class="font-mono font-bold">@BotFather</span> and copy the token.</li>
        <li>Paste the token into <a href="<?= e(APP_URL) ?>/adm/settings.php" class="text-[#5925dc] font-bold underline">Admin → Settings</a> and save.</li>
        <li>Set <span class="font-mono">bot_web_app_url</span> to your public HTTPS mini-app URL — or leave it blank and it follows the current domain automatically.</li>
        <li>Serve this app over HTTPS, then press <strong>Set Webhook</strong> above.</li>
        <li>Open the bot in Telegram and send <span class="font-mono">/start</span>.</li>
      </ol>
      <?php if ($isLocal): ?>
        <p class="mt-3 text-[11px] text-slate-400 font-medium leading-relaxed">
          Local development tip: expose this folder with a tunnel
          (<span class="font-mono">ngrok http 80</span>) and open this page through the tunnel's
          HTTPS URL — the webhook and WebApp URLs are detected from the request, so they update
          automatically. Telegram cannot reach <span class="font-mono"><?= e(APP_HOST) ?></span>.
        </p>
      <?php endif; ?>
    </div>

    <p class="text-center text-[11px] text-slate-400 font-medium pb-4">
      Signed in as <?= e((string) $admin['username']) ?> &middot;
      <a href="<?= e(APP_URL) ?>/adm/logout.php" class="underline">Sign out</a>
    </p>

  </div>
</body>
</html>
