<?php
/**
 * Admin — Required channels (channel gate)
 *
 * Users must join every active channel here before the mini app unlocks,
 * but only when "Require Channel Membership" is enabled in Settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$notice = null;
$error  = null;

/* ---------------------------------------------------------------------
 |  Actions
 * ------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            $pdo = db();

            switch ($action) {
                case 'add':
                    $chatId = trim((string) ($_POST['chat_id'] ?? ''));
                    $title  = trim((string) ($_POST['title'] ?? ''));
                    $invite = trim((string) ($_POST['invite_url'] ?? ''));
                    $order  = (int) ($_POST['sort_order'] ?? 0);

                    if ($chatId === '' || $title === '') {
                        $error = 'Channel id and title are required.';
                        break;
                    }

                    if (!preg_match('/^(@[A-Za-z0-9_]{4,64}|-?\d{5,25})$/', $chatId)) {
                        $error = 'Channel id must be @username or a numeric chat id.';
                        break;
                    }

                    if ($invite === '') {
                        $invite = str_starts_with($chatId, '@')
                            ? 'https://t.me/' . ltrim($chatId, '@')
                            : '';
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO `required_channels` (`chat_id`, `title`, `invite_url`, `sort_order`)
                         VALUES (:c, :t, :u, :o)'
                    );
                    $stmt->execute([':c' => $chatId, ':t' => $title, ':u' => $invite, ':o' => $order]);

                    $notice = 'Channel added.';
                    break;

                case 'update':
                    $id     = (int) ($_POST['id'] ?? 0);
                    $title  = trim((string) ($_POST['title'] ?? ''));
                    $invite = trim((string) ($_POST['invite_url'] ?? ''));
                    $order  = (int) ($_POST['sort_order'] ?? 0);
                    $active = !empty($_POST['is_active']) ? 1 : 0;

                    if ($id === 0 || $title === '') {
                        $error = 'Channel title is required.';
                        break;
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE `required_channels`
                            SET `title` = :t, `invite_url` = :u, `sort_order` = :o, `is_active` = :a
                          WHERE `id` = :id'
                    );
                    $stmt->execute([
                        ':t'  => $title,
                        ':u'  => $invite,
                        ':o'  => $order,
                        ':a'  => $active,
                        ':id' => $id,
                    ]);

                    $notice = 'Channel updated.';
                    break;

                case 'delete':
                    $id = (int) ($_POST['id'] ?? 0);

                    if ($id === 0) {
                        $error = 'Invalid channel.';
                        break;
                    }

                    $stmt = $pdo->prepare('DELETE FROM `required_channels` WHERE `id` = :id');
                    $stmt->execute([':id' => $id]);

                    $notice = 'Channel removed.';
                    break;

                case 'toggle_gate':
                    $enabled = !empty($_POST['require_channel_join']) ? '1' : '0';

                    $stmt = $pdo->prepare(
                        'INSERT INTO `settings` (`key`, `value`, `data_type`, `group_name`, `label`, `updated_by`)
                         VALUES (:k, :v, :t, :g, :l, :a)
                         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`)'
                    );
                    $stmt->execute([
                        ':k' => 'require_channel_join',
                        ':v' => $enabled,
                        ':t' => 'boolean',
                        ':g' => 'gate',
                        ':l' => 'Require Channel Membership',
                        ':a' => $admin['id'],
                    ]);

                    $notice = $enabled === '1'
                        ? 'Channel gate enabled. Users must join every active channel.'
                        : 'Channel gate disabled. The mini app is open to everyone.';
                    break;

                default:
                    $error = 'Unknown action.';
            }

            if ($notice !== null) {
                $audit = $pdo->prepare(
                    'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `ip_address`)
                     VALUES (:a, :act, :et, :ip)'
                );
                $audit->execute([
                    ':a'   => $admin['id'],
                    ':act' => 'channels.' . $action,
                    ':et'  => 'required_channels',
                    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
        } catch (Throwable $e) {
            error_log('adm/channels.php failed: ' . $e->getMessage());
            $error = APP_DEBUG ? $e->getMessage() : 'The action failed. Check the server log.';
        }
    }
}

/* ---------------------------------------------------------------------
 |  Data
 * ------------------------------------------------------------------- */

$channels = [];
$loadError = null;

try {
    $channels = db()
        ->query('SELECT * FROM `required_channels` ORDER BY `sort_order`, `id`')
        ->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Could not load channels: ' . $e->getMessage();
}

$gateEnabled = (bool) setting('require_channel_join', false);
$botStart    = (bool) setting('require_bot_start', true);
$gateMessage = (string) setting('channel_gate_message', '');
$activeCount = count(array_filter($channels, static fn ($c) => (int) $c['is_active'] === 1));
$botReady    = bot_token() !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer &amp; Earn - Required Channels</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="bg-[#f6f7fb] text-slate-900 antialiased">

<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-[#0f172a] flex flex-col py-5 -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="flex items-center justify-between gap-3 px-5 pb-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] flex items-center justify-center">
          <i class="fa-solid fa-bolt text-white text-sm"></i>
        </div>
        <div>
          <h1 class="text-white font-semibold text-base leading-tight">Refer &amp; Earn</h1>
          <p class="text-xs text-slate-400">Admin Panel</p>
        </div>
      </div>
      <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white p-1">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <nav class="mt-2 px-3 space-y-1.5 text-sm font-medium flex-1 overflow-y-auto">
      <a href="broadcast.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-paper-plane text-slate-400 w-5 text-center text-base"></i>
        <span>Broadcast</span>
      </a>
      <a href="withdrawal.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-wallet text-slate-400 w-5 text-center text-base"></i>
        <span>Withdrawals</span>
      </a>
      <a href="channels.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-tower-broadcast text-white w-5 text-center text-base"></i>
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

  <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"></div>

  <!-- Main -->
  <main class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-4 sm:py-6 max-w-[1440px] mx-auto overflow-x-hidden">

    <header class="sticky top-0 z-30 flex items-center justify-between gap-4 mb-6 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-3 bg-[#f6f7fb]/90 backdrop-blur-sm">
      <div class="flex items-start sm:items-center gap-3">
        <button onclick="toggleSidebar()" aria-label="Open menu" class="lg:hidden mt-0.5 sm:mt-0 w-10 h-10 rounded-xl bg-gradient-to-br from-[#6c3cf7] to-[#5a29e4] text-white shadow-md shadow-indigo-500/30 flex items-center justify-center shrink-0 transition">
          <i class="fa-solid fa-bars text-base"></i>
        </button>
        <div>
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Required Channels</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Users must join these channels before the mini app unlocks.</p>
        </div>
      </div>
      <a href="logout.php" class="text-xs font-semibold text-slate-500 hover:text-slate-800 whitespace-nowrap">
        <i class="fa-solid fa-right-from-bracket mr-1"></i> Sign out
      </a>
    </header>

    <?php if ($notice !== null): ?>
      <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-circle-check mt-0.5"></i><span><?= e($notice) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl px-4 py-3 text-sm font-semibold flex items-start gap-2.5">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($loadError !== null): ?>
      <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl px-4 py-3 text-sm font-semibold">
        <?= e($loadError) ?>
      </div>
    <?php endif; ?>

    <!-- Gate switch -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
          <div class="w-11 h-11 rounded-xl <?= $gateEnabled ? 'bg-[#f2edff] text-[#6d37f7]' : 'bg-slate-100 text-slate-400' ?> flex items-center justify-center shrink-0">
            <i class="fa-solid fa-lock text-lg"></i>
          </div>
          <div>
            <h3 class="text-sm sm:text-base font-bold text-slate-900">Channel Gate</h3>
            <p class="text-xs text-slate-500 mt-0.5">
              <?= $gateEnabled
                    ? 'Enabled — users must join all ' . $activeCount . ' active channel(s).'
                    : 'Disabled — the mini app is open to everyone.' ?>
            </p>
          </div>
        </div>

        <form method="post" class="flex items-center gap-3 shrink-0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle_gate">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="require_channel_join" value="1" <?= $gateEnabled ? 'checked' : '' ?> onchange="this.form.submit()" class="sr-only peer">
            <div class="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#6339f9]"></div>
          </label>
        </form>
      </div>

      <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <p class="font-semibold text-slate-500">Bot token</p>
          <p class="mt-0.5 font-bold <?= $botReady ? 'text-emerald-600' : 'text-rose-600' ?>">
            <?= $botReady ? 'Configured' : 'Missing' ?>
          </p>
        </div>
        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <p class="font-semibold text-slate-500">Require /start</p>
          <p class="mt-0.5 font-bold <?= $botStart ? 'text-emerald-600' : 'text-slate-400' ?>">
            <?= $botStart ? 'Yes' : 'No' ?>
          </p>
        </div>
        <div class="bg-slate-50 rounded-xl px-3.5 py-2.5">
          <p class="font-semibold text-slate-500">Active channels</p>
          <p class="mt-0.5 font-bold text-slate-800"><?= $activeCount ?> / <?= count($channels) ?></p>
        </div>
      </div>

      <?php if (!$botReady): ?>
        <p class="mt-3 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
          Membership checks need a bot token. Without one the gate cannot verify anyone, so it lets everyone through.
        </p>
      <?php endif; ?>

      <p class="mt-3 text-[11px] text-slate-400 font-medium leading-relaxed">
        The bot must be an <strong>administrator</strong> in each channel, otherwise
        <span class="font-mono">getChatMember</span> fails and the check is skipped.
      </p>
    </div>

    <!-- Add channel -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-4">
      <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
        <i class="fa-solid fa-plus text-[#6d37f7]"></i> Add a channel
      </h3>

      <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">

        <div class="lg:col-span-3">
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Channel ID</label>
          <input name="chat_id" required placeholder="@mychannel"
                 class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500 font-mono" />
        </div>

        <div class="lg:col-span-3">
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Title</label>
          <input name="title" required placeholder="Official Channel"
                 class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
        </div>

        <div class="lg:col-span-3">
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Invite URL (optional)</label>
          <input name="invite_url" placeholder="https://t.me/+abc123"
                 class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500 font-mono" />
        </div>

        <div class="lg:col-span-1">
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Order</label>
          <input name="sort_order" type="number" value="0"
                 class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
        </div>

        <div class="lg:col-span-2 flex items-end">
          <button type="submit"
                  class="w-full bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition whitespace-nowrap">
            <i class="fa-solid fa-plus"></i><span>Add channel</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Channel list -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
          <i class="fa-solid fa-tower-broadcast text-[#6d37f7]"></i> Channels
          <span class="text-xs font-semibold text-slate-400">(<?= count($channels) ?>)</span>
        </h3>
      </div>

      <?php if ($channels === []): ?>
        <p class="px-5 py-10 text-center text-xs font-medium text-slate-400">
          No channels yet. Add one above to start gating the mini app.
        </p>
      <?php else: ?>
        <div class="divide-y divide-slate-100">
          <?php foreach ($channels as $channel): ?>
            <?php $isActive = (int) $channel['is_active'] === 1; ?>
            <form method="post" class="px-4 sm:px-5 py-4">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $channel['id'] ?>">

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:items-end">

                <!-- Channel ID (read-only) -->
                <div class="lg:col-span-3">
                  <label class="block text-[11px] font-semibold text-slate-600 mb-1">Channel ID</label>
                  <p class="px-3 py-2 text-xs rounded-lg bg-slate-100 font-mono text-slate-600 truncate"
                     title="<?= e((string) $channel['chat_id']) ?>">
                    <?= e((string) $channel['chat_id']) ?>
                  </p>
                </div>

                <!-- Title -->
                <div class="lg:col-span-3">
                  <label class="block text-[11px] font-semibold text-slate-600 mb-1">Title</label>
                  <input name="title" value="<?= e((string) $channel['title']) ?>" required
                         class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
                </div>

                <!-- Invite URL -->
                <div class="lg:col-span-3">
                  <label class="block text-[11px] font-semibold text-slate-600 mb-1">Invite URL</label>
                  <input name="invite_url" value="<?= e((string) $channel['invite_url']) ?>"
                         placeholder="https://t.me/+abc123"
                         class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500 font-mono" />
                </div>

                <!-- Order -->
                <div class="lg:col-span-1">
                  <label class="block text-[11px] font-semibold text-slate-600 mb-1">Order</label>
                  <input name="sort_order" type="number" value="<?= (int) $channel['sort_order'] ?>"
                         class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
                </div>

                <!-- Active + actions -->
                <div class="lg:col-span-2 flex items-center justify-between lg:justify-end gap-2 pt-1 lg:pt-0">
                  <label class="flex items-center gap-2 cursor-pointer select-none" title="Show this channel on the lock screen">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> class="sr-only peer">
                    <span class="relative inline-block w-9 h-5 shrink-0 bg-slate-300 rounded-full transition-colors peer-checked:bg-[#6339f9] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform peer-checked:after:translate-x-4"></span>
                    <span class="text-[11px] font-semibold <?= $isActive ? 'text-emerald-600' : 'text-slate-400' ?>">
                      <?= $isActive ? 'Active' : 'Off' ?>
                    </span>
                  </label>

                  <div class="flex items-center gap-1.5">
                    <button type="submit" name="action" value="update" title="Save changes"
                            class="w-9 h-9 rounded-lg bg-[#f2edff] text-[#6d37f7] hover:bg-[#e6dcff] flex items-center justify-center transition">
                      <i class="fa-solid fa-floppy-disk text-xs"></i>
                    </button>
                    <button type="submit" name="action" value="delete" formnovalidate
                            title="Remove channel"
                            onclick="return confirm('Remove <?= e(addslashes((string) $channel['title'])) ?> from the gate?');"
                            class="w-9 h-9 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition">
                      <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                  </div>
                </div>

              </div>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Gate message -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mt-4">
      <h3 class="text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
        <i class="fa-solid fa-comment-dots text-[#6d37f7]"></i> Gate message
      </h3>
      <p class="text-xs text-slate-500 mb-2">Shown on the lock screen before the user joins.</p>
      <p class="px-3.5 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-medium text-slate-700">
        <?= e($gateMessage) ?>
      </p>
      <a href="settings.php" class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-bold text-[#6d37f7] hover:underline">
        <i class="fa-solid fa-pen"></i> Edit in Settings
      </a>
    </div>

  </main>
</div>

<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
  }
</script>
</body>
</html>
