<?php
/**
 * Admin — Account & password
 *
 * Lets the signed-in admin change their own password, and lets a super_admin
 * reset the password of any other admin account.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

$notice = null;
$error  = null;
$errors = [];

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
                /* -----------------------------------------------------
                 |  Change my own password
                 * --------------------------------------------------- */
                case 'change_own':
                    $current = (string) ($_POST['current_password'] ?? '');
                    $new     = (string) ($_POST['new_password'] ?? '');
                    $confirm = (string) ($_POST['confirm_password'] ?? '');

                    $stmt = $pdo->prepare('SELECT `password_hash` FROM `admins` WHERE `id` = :id LIMIT 1');
                    $stmt->execute([':id' => $admin['id']]);
                    $hash = (string) ($stmt->fetchColumn() ?: '');

                    if ($current === '') {
                        $errors['current_password'] = 'Enter your current password.';
                    } elseif (!password_verify($current, $hash)) {
                        $errors['current_password'] = 'That is not your current password.';
                    }

                    if ($new === '') {
                        $errors['new_password'] = 'Enter a new password.';
                    } elseif (strlen($new) < 8) {
                        $errors['new_password'] = 'Use at least 8 characters.';
                    } elseif ($new === $current) {
                        $errors['new_password'] = 'The new password must be different.';
                    }

                    if ($confirm === '') {
                        $errors['confirm_password'] = 'Repeat the new password.';
                    } elseif ($new !== $confirm) {
                        $errors['confirm_password'] = 'The two passwords do not match.';
                    }

                    if ($errors === []) {
                        $upd = $pdo->prepare('UPDATE `admins` SET `password_hash` = :h WHERE `id` = :id');
                        $upd->execute([
                            ':h'  => password_hash($new, PASSWORD_BCRYPT),
                            ':id' => $admin['id'],
                        ]);

                        $log = $pdo->prepare(
                            'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `ip_address`)
                             VALUES (:a, :act, :et, :eid, :ip)'
                        );
                        $log->execute([
                            ':a'   => $admin['id'],
                            ':act' => 'admin.password_changed',
                            ':et'  => 'admin',
                            ':eid' => $admin['id'],
                            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);

                        $notice = 'Your password has been changed.';
                    }
                    break;

                /* -----------------------------------------------------
                 |  Reset another admin's password (super_admin only)
                 * --------------------------------------------------- */
                case 'reset_other':
                    if (($admin['role'] ?? '') !== 'super_admin') {
                        $error = 'Only a super admin can reset another account.';
                        break;
                    }

                    $targetId = (int) ($_POST['admin_id'] ?? 0);
                    $new      = (string) ($_POST['new_password'] ?? '');
                    $confirm  = (string) ($_POST['confirm_password'] ?? '');

                    if ($targetId === 0) {
                        $error = 'Pick an account to reset.';
                        break;
                    }

                    if ($targetId === (int) $admin['id']) {
                        $error = 'Use "Change my password" for your own account.';
                        break;
                    }

                    if (strlen($new) < 8) {
                        $errors['new_password'] = 'Use at least 8 characters.';
                    }

                    if ($new !== $confirm) {
                        $errors['confirm_password'] = 'The two passwords do not match.';
                    }

                    if ($errors !== []) {
                        break;
                    }

                    $exists = $pdo->prepare('SELECT `username` FROM `admins` WHERE `id` = :id LIMIT 1');
                    $exists->execute([':id' => $targetId]);
                    $targetName = $exists->fetchColumn();

                    if ($targetName === false) {
                        $error = 'That account no longer exists.';
                        break;
                    }

                    $upd = $pdo->prepare('UPDATE `admins` SET `password_hash` = :h WHERE `id` = :id');
                    $upd->execute([
                        ':h'  => password_hash($new, PASSWORD_BCRYPT),
                        ':id' => $targetId,
                    ]);

                    $log = $pdo->prepare(
                        'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
                         VALUES (:a, :act, :et, :eid, :meta, :ip)'
                    );
                    $log->execute([
                        ':a'    => $admin['id'],
                        ':act'  => 'admin.password_reset',
                        ':et'   => 'admin',
                        ':eid'  => $targetId,
                        ':meta' => json_encode(['target_username' => $targetName], JSON_UNESCAPED_UNICODE),
                        ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);

                    $notice = 'Password reset for "' . $targetName . '".';
                    break;

                default:
                    $error = 'Unknown action.';
            }
        } catch (Throwable $e) {
            error_log('adm/account.php failed: ' . $e->getMessage());
            $error = APP_DEBUG ? $e->getMessage() : 'The action failed. Check the server log.';
        }
    }
}

/* ---------------------------------------------------------------------
 |  Data
 * ------------------------------------------------------------------- */

$isSuperAdmin = ($admin['role'] ?? '') === 'super_admin';
$otherAdmins  = [];

if ($isSuperAdmin) {
    try {
        $stmt = db()->prepare(
            'SELECT `id`, `name`, `username`, `email`, `role`, `is_active`, `last_login_at`
               FROM `admins`
              WHERE `id` <> :id
              ORDER BY `name`'
        );
        $stmt->execute([':id' => $admin['id']]);
        $otherAdmins = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('adm/account.php list failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Refer &amp; Earn - Account</title>
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
      <a href="channels.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-tower-broadcast text-slate-400 w-5 text-center text-base"></i>
        <span>Channels</span>
      </a>
      <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5 transition">
        <i class="fa-solid fa-gear text-slate-400 w-5 text-center text-base"></i>
        <span>Settings</span>
      </a>
      <a href="account.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30">
        <i class="fa-solid fa-user-shield text-white w-5 text-center text-base"></i>
        <span>Account</span>
      </a>
    </nav>

    <div class="px-3 pt-3 mt-2 border-t border-white/5">
      <div class="px-4 py-2">
        <p class="text-xs font-semibold text-white truncate"><?= e((string) $admin['name']) ?></p>
        <p class="text-[11px] text-slate-400 truncate"><?= e((string) $admin['email']) ?></p>
      </div>
      <a href="logout.php" class="flex items-center gap-3.5 px-4 py-2.5 rounded-xl text-slate-300 hover:bg-white/5 transition text-sm font-medium">
        <i class="fa-solid fa-right-from-bracket text-slate-400 w-5 text-center text-base"></i>
        <span>Sign out</span>
      </a>
    </div>
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
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Account</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Manage your password and other admin accounts.</p>
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

    <!-- Who am I -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-4">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-[#f2edff] text-[#6d37f7] flex items-center justify-center shrink-0">
          <i class="fa-solid fa-user-shield text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="text-sm sm:text-base font-bold text-slate-900 truncate"><?= e((string) $admin['name']) ?></h3>
          <p class="text-xs text-slate-500 truncate"><?= e((string) $admin['email']) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#f2edff] text-[#6d37f7]">
            <?= e(str_replace('_', ' ', (string) $admin['role'])) ?>
          </span>
          <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 font-mono">
            @<?= e((string) $admin['username']) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Change my password -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-4">
      <h3 class="text-sm font-bold text-slate-900 mb-1 flex items-center gap-2">
        <i class="fa-solid fa-key text-[#6d37f7]"></i> Change my password
      </h3>
      <p class="text-xs text-slate-500 mb-4">You will stay signed in on this device.</p>

      <form method="post" class="grid grid-cols-1 sm:grid-cols-3 gap-3" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_own">

        <div>
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Current password</label>
          <input type="password" name="current_password" autocomplete="current-password"
                 class="w-full px-3 py-2 text-xs rounded-lg border <?= isset($errors['current_password']) ? 'border-rose-400' : 'border-slate-200' ?> bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
          <?php if (isset($errors['current_password'])): ?>
            <p class="mt-1 text-[11px] font-semibold text-rose-600"><?= e($errors['current_password']) ?></p>
          <?php endif; ?>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">New password</label>
          <input type="password" name="new_password" autocomplete="new-password"
                 class="w-full px-3 py-2 text-xs rounded-lg border <?= isset($errors['new_password']) ? 'border-rose-400' : 'border-slate-200' ?> bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
          <?php if (isset($errors['new_password'])): ?>
            <p class="mt-1 text-[11px] font-semibold text-rose-600"><?= e($errors['new_password']) ?></p>
          <?php else: ?>
            <p class="mt-1 text-[11px] text-slate-400">At least 8 characters.</p>
          <?php endif; ?>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-slate-600 mb-1">Confirm new password</label>
          <input type="password" name="confirm_password" autocomplete="new-password"
                 class="w-full px-3 py-2 text-xs rounded-lg border <?= isset($errors['confirm_password']) ? 'border-rose-400' : 'border-slate-200' ?> bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
          <?php if (isset($errors['confirm_password'])): ?>
            <p class="mt-1 text-[11px] font-semibold text-rose-600"><?= e($errors['confirm_password']) ?></p>
          <?php endif; ?>
        </div>

        <div class="sm:col-span-3">
          <button type="submit"
                  class="w-full sm:w-auto bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] hover:opacity-95 text-white font-semibold text-xs px-5 py-2.5 rounded-lg shadow-md shadow-indigo-500/25 flex items-center justify-center gap-2 transition">
            <i class="fa-solid fa-floppy-disk"></i><span>Update password</span>
          </button>
        </div>
      </form>
    </div>

    <?php if ($isSuperAdmin): ?>
      <!-- Reset another admin -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 mb-4">
        <h3 class="text-sm font-bold text-slate-900 mb-1 flex items-center gap-2">
          <i class="fa-solid fa-user-gear text-[#6d37f7]"></i> Reset another admin's password
        </h3>
        <p class="text-xs text-slate-500 mb-4">
          Super admin only. Use this when a teammate is locked out — you do not need their old password.
        </p>

        <?php if ($otherAdmins === []): ?>
          <p class="px-3.5 py-3 rounded-lg bg-slate-50 border border-slate-200 text-xs font-medium text-slate-500">
            There are no other admin accounts yet.
          </p>
        <?php else: ?>
          <form method="post" class="grid grid-cols-1 sm:grid-cols-3 gap-3" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_other">

            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Account</label>
              <select name="admin_id" required
                      class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 bg-slate-50/70 focus:outline-none focus:border-indigo-500">
                <option value="">Choose an account…</option>
                <?php foreach ($otherAdmins as $other): ?>
                  <option value="<?= (int) $other['id'] ?>">
                    <?= e((string) $other['name']) ?> (@<?= e((string) $other['username']) ?>)<?= (int) $other['is_active'] === 1 ? '' : ' — inactive' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">New password</label>
              <input type="password" name="new_password" autocomplete="new-password"
                     class="w-full px-3 py-2 text-xs rounded-lg border <?= isset($errors['new_password']) ? 'border-rose-400' : 'border-slate-200' ?> bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
              <?php if (isset($errors['new_password'])): ?>
                <p class="mt-1 text-[11px] font-semibold text-rose-600"><?= e($errors['new_password']) ?></p>
              <?php else: ?>
                <p class="mt-1 text-[11px] text-slate-400">At least 8 characters.</p>
              <?php endif; ?>
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-slate-600 mb-1">Confirm new password</label>
              <input type="password" name="confirm_password" autocomplete="new-password"
                     class="w-full px-3 py-2 text-xs rounded-lg border <?= isset($errors['confirm_password']) ? 'border-rose-400' : 'border-slate-200' ?> bg-slate-50/70 focus:outline-none focus:border-indigo-500" />
              <?php if (isset($errors['confirm_password'])): ?>
                <p class="mt-1 text-[11px] font-semibold text-rose-600"><?= e($errors['confirm_password']) ?></p>
              <?php endif; ?>
            </div>

            <div class="sm:col-span-3">
              <button type="submit"
                      onclick="return confirm('Reset this account\'s password?');"
                      class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs px-5 py-2.5 rounded-lg shadow-md flex items-center justify-center gap-2 transition">
                <i class="fa-solid fa-rotate"></i><span>Reset password</span>
              </button>
            </div>
          </form>

          <!-- Existing accounts -->
          <div class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">All admin accounts</p>
            <div class="space-y-1.5">
              <?php foreach ($otherAdmins as $other): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-lg bg-slate-50">
                  <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-800 truncate"><?= e((string) $other['name']) ?></p>
                    <p class="text-[11px] text-slate-500 truncate"><?= e((string) $other['email']) ?></p>
                  </div>
                  <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white text-slate-600 border border-slate-200">
                      <?= e(str_replace('_', ' ', (string) $other['role'])) ?>
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= (int) $other['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
                      <?= (int) $other['is_active'] === 1 ? 'active' : 'inactive' ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Locked out? -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <h3 class="text-sm font-bold text-slate-900 mb-2 flex items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation text-amber-500"></i> Locked out completely?
      </h3>
      <p class="text-xs text-slate-500 mb-3">
        If you cannot sign in at all, reset the password from the server. Run this on the machine
        that hosts the site, then paste the printed hash into the database.
      </p>
      <pre class="px-3.5 py-3 rounded-lg bg-slate-900 text-slate-100 text-[11px] font-mono overflow-x-auto leading-relaxed">php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT), PHP_EOL;"</pre>
      <pre class="mt-2 px-3.5 py-3 rounded-lg bg-slate-900 text-slate-100 text-[11px] font-mono overflow-x-auto leading-relaxed">UPDATE admins SET password_hash = '&lt;paste-the-hash&gt;' WHERE username = 'admin';</pre>
      <p class="mt-3 text-[11px] text-slate-400 font-medium leading-relaxed">
        On XAMPP the command is
        <span class="font-mono">C:\xampp\php\php.exe -r "..."</span>.
        See <span class="font-mono">DEPLOYMENT.md</span> §5 for the full procedure.
      </p>
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
