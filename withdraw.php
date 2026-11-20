<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$withdrawalsEnabled = (bool) setting('withdrawals_enabled', true);
$minReferrals       = (int) setting('min_referrals_withdrawal', 5);
$minReferralsSubseq = (int) setting('min_referrals_subsequent_withdrawal', 20);
$processingHours    = (int) setting('withdrawal_processing_hours', 24);
$coinToInrRate      = (float) setting('coin_to_inr_rate', 0.10);
$balanceT           = (float) $user['balance_t'];
$referralCount      = (int) $user['referral_count'];

$withdrawalOptionsInr = [10, 50, 100];
$withdrawalAmountsT = [];
if ($coinToInrRate > 0 && is_finite($coinToInrRate)) {
    foreach ($withdrawalOptionsInr as $amountInr) {
        $amountT = round($amountInr / $coinToInrRate, 2);
        $convertedInrPaise = (int) round($amountT * $coinToInrRate * 100);
        if ($convertedInrPaise === $amountInr * 100) {
            $withdrawalAmountsT[$amountInr] = $amountT;
        }
    }
}

$isFirstWithdrawal  = is_first_withdrawal((int) $user['id']);

$referralsRequired  = $isFirstWithdrawal ? $minReferrals : $minReferralsSubseq;
$availableWithdrawalsInr = [];
foreach ($withdrawalOptionsInr as $amountInr) {
    if (isset($withdrawalAmountsT[$amountInr]) && $withdrawalAmountsT[$amountInr] <= $balanceT) {
        $availableWithdrawalsInr[] = $amountInr;
    }
}
$defaultAmountInr = $availableWithdrawalsInr[0] ?? $withdrawalOptionsInr[0];
$canWithdraw = $availableWithdrawalsInr !== [];

$formError = null;
$formUpi   = '';
$formAmountInr = $defaultAmountInr;
$formAmountT = $withdrawalAmountsT[$formAmountInr] ?? 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formUpi = trim((string) ($_POST['upi_id'] ?? ''));
    $submittedAmountInr = filter_var($_POST['amount_inr'] ?? '', FILTER_VALIDATE_INT);
    if ($submittedAmountInr !== false && in_array($submittedAmountInr, $withdrawalOptionsInr, true)) {
        $formAmountInr = $submittedAmountInr;
    }
    $formAmountT = $withdrawalAmountsT[$formAmountInr] ?? 0.0;

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $formError = 'Your session expired. Please try again.';
    } elseif (!$withdrawalsEnabled) {
        $formError = 'Withdrawals are temporarily disabled.';
    } elseif ($referralCount < $referralsRequired) {
        $formError = sprintf('You need at least %d referrals to withdraw.', $referralsRequired);
    } elseif ($submittedAmountInr === false || !in_array($submittedAmountInr, $withdrawalOptionsInr, true)) {
        $formError = 'Choose a withdrawal amount of ₹10, ₹50, or ₹100.';
    } elseif ($coinToInrRate <= 0 || !is_finite($coinToInrRate)) {
        $formError = 'Withdrawals are unavailable because the conversion rate is invalid.';
    } elseif (setting('withdrawal_mode', 'manual') === 'auto' && withdrawal_api_url() === '') {
        $formError = 'Auto withdrawals are unavailable because the payout gateway is not configured.';
    } elseif (!array_key_exists($submittedAmountInr, $withdrawalAmountsT)) {
        $formError = 'This withdrawal amount cannot be represented at the current conversion rate.';
    } elseif (preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $formUpi) !== 1) {
        $formError = 'Please enter a valid UPI ID (e.g. yourname@upi).';
    } elseif ($formAmountT > $balanceT) {
        $formError = 'Insufficient balance. You have ' . t_format($balanceT) . '.';
    } else {
        $amountInr = (int) $submittedAmountInr;
        $amountT = $withdrawalAmountsT[$amountInr];
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT `balance_t` FROM `users` WHERE `id` = :id FOR UPDATE');
            $stmt->execute([':id' => $user['id']]);
            $lockedBalance = (float) $stmt->fetchColumn();

            if ($amountT > $lockedBalance) {
                throw new RuntimeException('Insufficient balance.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO `withdrawals`
                    (`user_id`, `amount_t`, `amount_inr`, `payout_method`, `upi_id`, `status`, `requested_at`)
                 VALUES (:uid, :amt, :inr, :method, :upi, :status, NOW())'
            );
            $stmt->execute([
                ':uid'    => $user['id'],
                ':amt'    => $amountT,
                ':inr'    => $amountInr,
                ':method' => 'upi',
                ':upi'    => $formUpi,
                ':status' => 'pending',
            ]);

            $withdrawalId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO `withdrawal_status_history`
                    (`withdrawal_id`, `from_status`, `to_status`, `note`, `admin_id`)
                 VALUES (:wid, NULL, :to, :note, NULL)'
            );
            $stmt->execute([
                ':wid'  => $withdrawalId,
                ':to'   => 'pending',
                ':note' => 'Requested by user via mini app.',
            ]);

            credit_wallet(
                (int) $user['id'],
                'withdrawal_debit',
                'debit',
                $amountT,
                'withdrawal',
                $withdrawalId,
                'Withdrawal request to ' . $formUpi
            );

            $pdo->commit();

            // External notifications and payouts happen after the transaction
            // commits so slow network calls never hold the wallet row lock.
            if (setting('withdrawal_mode', 'manual') === 'auto') {
                $payout = process_withdrawal_payout($withdrawalId, $amountT, $formUpi);
                flash_set($payout['ok'] ? 'success' : 'warning', $payout['message']);
            } else {
                $notification = notify_manual_withdrawal(
                    $user,
                    $withdrawalId,
                    $amountT,
                    (float) $amountInr,
                    $formUpi
                );
                if (!$notification['configured']) {
                    flash_set(
                        'warning',
                        'Manual withdrawal request submitted, but no Telegram notification chats are configured. Please contact the administrator.'
                    );
                } elseif ($notification['failures'] !== []) {
                    flash_set(
                        'warning',
                        'Manual withdrawal request submitted, but Telegram could not notify every configured chat. Please contact the administrator.'
                    );
                } else {
                    flash_set(
                        'success',
                        'Manual withdrawal request of ' . inr_format($amountInr) . ' (' . t_format($amountT)
                            . ') submitted. The payment team has been notified and will process it within '
                            . $processingHours . ' hours.'
                    );
                }
            }

            redirect('withdraw.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('withdraw.php failed: ' . $e->getMessage());
            $formError = APP_DEBUG ? $e->getMessage() : 'Could not submit your request. Please try again.';
        }
    }
}

$flash = flash_get();

// Refresh the balance after a successful submission.
if ($flash !== null) {
    $stmt = db()->prepare('SELECT `balance_t` FROM `users` WHERE `id` = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $balanceT = (float) $stmt->fetchColumn();
}

$recentWithdrawals = [];
try {
    $stmt = db()->prepare(
        'SELECT `id`, `amount_t`, `amount_inr`, `upi_id`, `status`, `requested_at`
           FROM `withdrawals`
          WHERE `user_id` = :uid
          ORDER BY `requested_at` DESC, `id` DESC
          LIMIT 5'
    );
    $stmt->execute([':uid' => $user['id']]);
    $recentWithdrawals = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('withdraw.php history failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Withdraw - Refer & Earn</title>
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
            <h1 class="text-white font-extrabold text-xl tracking-tight leading-tight">Withdraw</h1>
            <p class="text-white/85 text-xs font-medium mt-0.5">Cash out your earnings</p>
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

      <!-- Wallet Balance Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <div class="text-xs font-semibold text-slate-500">Available Balance</div>
        <div class="flex items-center gap-3 mt-2">
          <div class="w-11 h-11 rounded-full t-coin flex items-center justify-center shrink-0"></div>
          <div>
            <div class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none"><?= e(t_format($balanceT)) ?></div>
            <div class="text-xs font-semibold text-slate-500 mt-1">≈ <?= e(inr_format(t_to_inr($balanceT))) ?></div>
          </div>
        </div>

        <!-- Withdrawal Amount Options -->
        <div class="mt-4 bg-[#f5f3ff] rounded-xl px-3 py-2.5 flex items-center justify-between text-xs">
          <div class="flex items-center gap-1.5">
            <i class="fa-solid fa-circle-info text-[#5925dc] text-sm"></i>
            <span class="text-slate-600 font-medium">Choose ₹10, ₹50, or ₹100. T-coins are calculated at <?= e(inr_format($coinToInrRate)) ?> per T.</span>
          </div>
        </div>
      </div>

      <!-- Withdraw Amount Card -->
      <div class="bg-white rounded-3xl p-4 card-shadow">
        <h3 class="font-extrabold text-sm text-slate-900 mb-3">Withdraw Amount</h3>

        <?php if ($formError !== null): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
            <?= e($formError) ?>
          </div>
        <?php endif; ?>

        <?php if (!$withdrawalsEnabled): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            Withdrawals are temporarily disabled. Please check back later.
          </div>
        <?php elseif ($referralCount < $referralsRequired): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            You need at least <?= e((string) $referralsRequired) ?> referrals to withdraw. You currently have <?= e((string) $referralCount) ?>.
          </div>
        <?php elseif ($coinToInrRate <= 0 || !is_finite($coinToInrRate)): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            Withdrawals are unavailable because the conversion rate is invalid.
          </div>
        <?php elseif (!$canWithdraw): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            <?php if (isset($withdrawalAmountsT[10])): ?>
              Your balance of <?= e(t_format($balanceT)) ?> is not enough for the ₹10 option, which requires <?= e(t_format($withdrawalAmountsT[10])) ?>.
            <?php else: ?>
              The current conversion rate cannot represent the available withdrawal amounts. Please contact support.
            <?php endif; ?>
          </div>
        <?php elseif (setting('withdrawal_mode', 'manual') === 'auto'): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            Auto withdrawal is enabled. Requests are sent to the configured payment gateway without an approval step.
          </div>
        <?php else: ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
            Manual payment is enabled. Your request will be sent to the payment team for processing.
          </div>
        <?php endif; ?>

        <form method="post" action="withdraw.php" id="withdrawForm" class="contents">
          <?= csrf_field() ?>

          <!-- UPI ID Input -->
          <div class="mb-3">
            <label class="text-xs font-bold text-slate-600 mb-1.5 block" for="upiId">UPI ID</label>
            <div class="bg-[#f4f4f8] rounded-2xl px-3.5 py-3 flex items-center justify-between border border-slate-200/80">
              <input type="text" id="upiId" name="upi_id" value="<?= e($formUpi) ?>" placeholder="yourname@upi"
                     class="bg-transparent text-sm font-semibold text-slate-800 w-full outline-none" />
              <i class="fa-solid fa-building-columns text-[#5925dc] text-sm ml-2"></i>
            </div>
          </div>

          <!-- Fixed INR Amount Options -->
          <div class="mb-3">
            <label class="text-xs font-bold text-slate-600 mb-1.5 block">Choose amount</label>
            <input type="hidden" id="withdrawAmountInr" name="amount_inr" value="<?= e((string) $formAmountInr) ?>" />
            <div class="grid grid-cols-3 gap-2 mt-2" id="amountOptions">
              <?php foreach ($withdrawalOptionsInr as $optionInr): ?>
                <?php
                  $optionAmountT = $withdrawalAmountsT[$optionInr] ?? null;
                  $optionAvailable = $optionAmountT !== null && $optionAmountT <= $balanceT;
                  $optionActive = $optionAvailable && $optionInr === $formAmountInr;
                  $optionClass = $optionActive
                      ? 'bg-[#5925dc] text-white'
                      : 'bg-[#f2eeff] text-[#5925dc]';
                  if (!$optionAvailable) {
                      $optionClass = 'bg-slate-100 text-slate-400 cursor-not-allowed';
                  }
                ?>
                <button type="button" data-amount-inr="<?= e((string) $optionInr) ?>"
                        <?= $optionAvailable ? '' : 'disabled' ?>
                        aria-pressed="<?= $optionActive ? 'true' : 'false' ?>"
                        class="amount-option <?= $optionClass ?> text-xs font-bold py-2.5 rounded-xl transition">
                  <span class="block"><?= e(inr_format($optionInr)) ?></span>
                  <span class="block mt-1 text-[10px] font-medium opacity-80">
                    <?= $optionAmountT === null ? 'Unavailable' : 'Requires ' . t_format($optionAmountT) ?>
                  </span>
                </button>
              <?php endforeach; ?>
            </div>
            <div class="text-[10px] font-semibold text-[#5925dc] mt-2" id="amountInrHint">
              <?php if (isset($withdrawalAmountsT[$formAmountInr])): ?>
                <?= e(inr_format($formAmountInr)) ?> deducts <?= e(t_format($formAmountT)) ?> from your T wallet.
              <?php else: ?>
                This amount is unavailable at the current conversion rate.
              <?php endif; ?>
            </div>
          </div>

          <!-- Withdraw Button -->
          <button type="submit" <?= $canWithdraw ? '' : 'disabled' ?> class="btn-purple w-full text-white font-bold text-sm py-3.5 rounded-2xl flex items-center justify-center gap-2.5 mt-4 active:scale-[0.98] transition <?= $canWithdraw ? '' : 'opacity-50 cursor-not-allowed' ?>">
            <i class="fa-solid fa-wallet text-base"></i>
            <span>Withdraw Now</span>
          </button>
        </form>
      </div>

      <?php if ($recentWithdrawals !== []): ?>
        <!-- Recent Requests Card -->
        <div class="bg-white rounded-3xl p-4 card-shadow">
          <div class="flex items-center gap-2 mb-3">
            <i class="fa-regular fa-clock text-slate-500 text-sm"></i>
            <h3 class="font-extrabold text-sm text-slate-900">Recent Requests</h3>
          </div>

          <div class="divide-y divide-slate-100">
            <?php foreach ($recentWithdrawals as $index => $row): ?>
              <?php
                $status = (string) $row['status'];
                $badge  = match ($status) {
                    'completed'  => 'bg-emerald-50 text-emerald-700',
                    'processing' => 'bg-blue-50 text-blue-700',
                    'rejected'   => 'bg-rose-50 text-rose-700',
                    'cancelled'  => 'bg-slate-100 text-slate-600',
                    default      => 'bg-amber-50 text-amber-700',
                };
                $rowClass = $index === 0 ? 'py-2.5 first:pt-0' : 'py-2.5';
                $rowClass .= $index === count($recentWithdrawals) - 1 ? ' last:pb-0' : '';
              ?>
              <div class="<?= e($rowClass) ?> flex items-center justify-between">
                <div class="min-w-0">
                  <div class="text-xs font-extrabold text-slate-900"><?= e(t_format((float) $row['amount_t'])) ?></div>
                  <div class="text-[11px] font-semibold text-[#5925dc] mt-0.5"><?= e(inr_format((float) $row['amount_inr'])) ?></div>
                  <div class="text-[11px] font-medium text-slate-500 mt-0.5 truncate"><?= e((string) $row['upi_id']) ?></div>
                  <div class="text-[10px] font-medium text-slate-400 mt-0.5"><?= e(activity_time($row['requested_at'] ?? null)) ?></div>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-wide px-2.5 py-1 rounded-full <?= e($badge) ?> shrink-0 ml-2">
                  <?= e($status) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Withdrawal Info Card -->
      <div class="bg-[#e8f8f0] rounded-2xl p-3.5 border border-emerald-100">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm">
            <i class="fa-solid fa-circle-info text-base"></i>
          </div>
          <div>
            <div class="text-xs font-extrabold text-slate-900">Withdrawal Info</div>
            <div class="text-[11px] font-medium text-slate-600 mt-0.5 leading-snug">
              Withdrawals are processed within <?= e((string) $processingHours) ?> hours. The amount will be sent to the UPI ID you provide.
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] bg-white rounded-t-3xl shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-6 pt-2.5 pb-2 flex items-center justify-around z-50">
      <!-- Home -->
      <a href="index.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-[#5925dc] transition pb-1.5 w-20">
        <i class="fa-solid fa-house text-lg"></i>
        <span class="text-[11px] font-semibold">Home</span>
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

      <!-- Withdraw (Active) -->
      <a href="withdraw.php" class="flex flex-col items-center gap-1 text-[#5925dc] relative pb-1.5 w-20">
        <i class="fa-solid fa-wallet text-lg"></i>
        <span class="text-[11px] font-bold">Withdraw</span>
        <span class="absolute bottom-0 w-16 h-1 bg-[#5925dc] rounded-full"></span>
      </a>
    </nav>

  </div>

  <!-- Withdraw Script -->
  <script>
        const BALANCE_T = <?= (float) $balanceT ?>;
        const AMOUNT_T_BY_INR = <?= json_encode($withdrawalAmountsT, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const ALLOWED_INR = [10, 50, 100];

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

        function setAmount(amountInr) {
          const input = document.getElementById('withdrawAmountInr');
          input.value = amountInr;

          document.querySelectorAll('.amount-option').forEach(function (option) {
            const isActive = !option.disabled && parseInt(option.dataset.amountInr, 10) === amountInr;
            option.classList.toggle('bg-[#5925dc]', isActive);
            option.classList.toggle('text-white', isActive);
            option.classList.toggle('bg-[#f2eeff]', !isActive && !option.disabled);
            option.classList.toggle('text-[#5925dc]', !isActive && !option.disabled);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });

          const hint = document.getElementById('amountInrHint');
          const amountT = AMOUNT_T_BY_INR[String(amountInr)];
          if (hint) {
            hint.textContent = amountT === undefined
              ? 'This amount is unavailable at the current conversion rate.'
              : 'You will receive ₹ ' + amountInr.toLocaleString('en-IN') + ' and ' +
                amountT.toLocaleString('en-IN', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                }) + ' T will be deducted from your wallet.';
          }
        }

        document.querySelectorAll('.amount-option').forEach(function (option) {
          option.addEventListener('click', function () {
            setAmount(parseInt(option.dataset.amountInr, 10));
          });
        });

        setAmount(parseInt(document.getElementById('withdrawAmountInr').value, 10));

        document.getElementById('withdrawForm').addEventListener('submit', function (event) {
          const upiId = document.getElementById('upiId').value.trim();
          const amountInr = parseInt(document.getElementById('withdrawAmountInr').value, 10);
          const amountT = AMOUNT_T_BY_INR[String(amountInr)];

          if (!upiId) {
            event.preventDefault();
            showToast('Please enter your UPI ID', true);
            return;
          }
          if (!/^[\w.\-]{2,}@[a-zA-Z]{2,}$/.test(upiId)) {
            event.preventDefault();
            showToast('Please enter a valid UPI ID (e.g. yourname@upi)', true);
            return;
          }
          if (!ALLOWED_INR.includes(amountInr) || amountT === undefined) {
            event.preventDefault();
            showToast('Choose ₹10, ₹50, or ₹100', true);
            return;
          }
          if (amountT > BALANCE_T) {
            event.preventDefault();
            showToast('Insufficient balance', true);
            return;
          }

          const button = this.querySelector('button[type="submit"]');
          button.disabled = true;
          button.classList.add('opacity-70');
          button.querySelector('span').textContent = 'Submitting...';
        });

        if (window.Telegram && window.Telegram.WebApp) {
          const tg = window.Telegram.WebApp;
          tg.ready();
          tg.expand();
          tg.setHeaderColor('#5925dc');
        }

        const serverFlash = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        if (serverFlash && serverFlash.message) {
          showToast(serverFlash.message, serverFlash.type !== 'success');
        }
    </script>
</body>
</html>