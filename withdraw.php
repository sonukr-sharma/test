<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = require_onboarded_user();

$withdrawalsEnabled = (bool) setting('withdrawals_enabled', true);
$minWithdrawalT     = (float) setting('min_withdrawal_t', 2000);
$maxWithdrawalT     = (float) setting('max_withdrawal_t', 50000);
$minReferrals       = (int) setting('min_referrals_withdrawal', 5);
$processingHours    = (int) setting('withdrawal_processing_hours', 24);
$balanceT           = (float) $user['balance_t'];
$referralCount      = (int) $user['referral_count'];

// The first payout is capped at the minimum amount and paid out automatically;
// later ones are only bounded by max_withdrawal_t.
$isFirstWithdrawal  = is_first_withdrawal((int) $user['id']);
$firstWithdrawalMax = first_withdrawal_max_t();
$effectiveMinT      = $isFirstWithdrawal ? 1.0 : $minWithdrawalT;
$effectiveMaxT      = $isFirstWithdrawal ? $firstWithdrawalMax : $maxWithdrawalT;
$referralsRequired  = $isFirstWithdrawal ? 0 : $minReferrals;

/**
 * Build the quick-pick amount chips: the minimum, two random values rounded to
 * the nearest 100, then the maximum.
 *
 * The top chip is capped at the user's balance, because a chip that always
 * fails the balance check is useless.
 *
 * @return list<int>
 */
function withdrawal_amount_chips(float $minT, float $maxT, float $balanceT): array
{
    $min = (int) ceil($minT);
    $max = (int) floor(min($maxT, $balanceT));

    if ($max < $min) {
        return [];
    }

    if ($max === $min) {
        return [$min];
    }

    $chips = [$min];

    // Two random values strictly between the bounds, rounded to 100.
    if ($max - $min >= 300) {
        $picks = [];
        for ($attempt = 0; $attempt < 40 && count($picks) < 2; $attempt++) {
            $value = (int) (round(random_int($min + 1, $max - 1) / 100) * 100);
            if ($value > $min && $value < $max && !in_array($value, $picks, true)) {
                $picks[] = $value;
            }
        }
        sort($picks);
        $chips = array_merge($chips, $picks);
    }

    $chips[] = $max;

    return array_values(array_unique($chips));
}

$amountChips = withdrawal_amount_chips($effectiveMinT, $effectiveMaxT, $balanceT);
$defaultAmount = $amountChips !== [] ? $amountChips[0] : (int) ceil($effectiveMinT);
$canWithdraw = $amountChips !== [];

$formError = null;
$formUpi   = '';
$formAmount = $defaultAmount;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formUpi = trim((string) ($_POST['upi_id'] ?? ''));
    $amount  = (float) ($_POST['amount'] ?? 0);
    $formAmount = (int) $amount;

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $formError = 'Your session expired. Please try again.';
    } elseif (!$withdrawalsEnabled) {
        $formError = 'Withdrawals are temporarily disabled.';
    } elseif ($referralCount < $referralsRequired) {
        $formError = sprintf('You need at least %d referrals to withdraw.', $referralsRequired);
    } elseif (preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $formUpi) !== 1) {
        $formError = 'Please enter a valid UPI ID (e.g. yourname@upi).';
    } elseif ($amount <= 0) {
        $formError = 'Please enter the amount you want to withdraw.';
    } elseif ($isFirstWithdrawal && $amount > $firstWithdrawalMax) {
        $formError = 'Your first withdrawal is limited to ' . t_format($firstWithdrawalMax) . '.';
    } elseif (!$isFirstWithdrawal && $amount < $minWithdrawalT) {
        $formError = 'Minimum withdrawal is ' . t_format($minWithdrawalT) . '.';
    } elseif ($amount > $effectiveMaxT) {
        $formError = 'Maximum withdrawal is ' . t_format($effectiveMaxT) . '.';
    } elseif ($amount > $balanceT) {
        $formError = 'Insufficient balance. You have ' . t_format($balanceT) . '.';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT `balance_t` FROM `users` WHERE `id` = :id FOR UPDATE');
            $stmt->execute([':id' => $user['id']]);
            $lockedBalance = (float) $stmt->fetchColumn();

            if ($amount > $lockedBalance) {
                throw new RuntimeException('Insufficient balance.');
            }

            // Re-check under the row lock so two concurrent submissions cannot
            // both claim the first-withdrawal allowance.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `withdrawals`
                  WHERE `user_id` = :uid AND `status` NOT IN ('cancelled', 'rejected')"
            );
            $stmt->execute([':uid' => $user['id']]);
            $lockedIsFirst = (int) $stmt->fetchColumn() === 0;

            if ($lockedIsFirst && $amount > $firstWithdrawalMax) {
                throw new RuntimeException('Your first withdrawal is limited to ' . t_format($firstWithdrawalMax) . '.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO `withdrawals`
                    (`user_id`, `amount_t`, `amount_inr`, `payout_method`, `upi_id`, `status`, `requested_at`)
                 VALUES (:uid, :amt, :inr, :method, :upi, :status, NOW())'
            );
            $stmt->execute([
                ':uid'    => $user['id'],
                ':amt'    => $amount,
                ':inr'    => t_to_inr($amount),
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
                ':note' => $lockedIsFirst
                    ? 'First withdrawal requested by user via mini app (auto-payout).'
                    : 'Requested by user via mini app.',
            ]);

            credit_wallet(
                (int) $user['id'],
                'withdrawal_debit',
                'debit',
                $amount,
                'withdrawal',
                $withdrawalId,
                'Withdrawal request to ' . $formUpi
            );

            $pdo->commit();

            // The payout call happens after the commit so a slow endpoint never
            // holds the wallet row lock.
            if ($lockedIsFirst) {
                $payout = process_first_withdrawal_payout($withdrawalId, $amount, $formUpi);
                flash_set('success', $payout['message']);
            } else {
                flash_set('success', 'Withdrawal request of ' . t_format($amount) . ' (' . inr_format(t_to_inr($amount)) . ') submitted. It will be processed within ' . $processingHours . ' hours.');
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

        <!-- Minimum Cashout Bar -->
        <div class="mt-4 bg-[#f5f3ff] rounded-xl px-3 py-2.5 flex items-center justify-between text-xs">
          <div class="flex items-center gap-1.5">
            <i class="fa-solid fa-crown text-amber-400 text-sm"></i>
            <?php if ($isFirstWithdrawal): ?>
              <span class="text-slate-600 font-medium">First withdrawal limit: <strong class="text-[#5925dc] font-bold"><?= e(t_format($firstWithdrawalMax)) ?> (<?= e(inr_format(t_to_inr($firstWithdrawalMax))) ?>)</strong></span>
            <?php else: ?>
              <span class="text-slate-600 font-medium">Minimum cashout: <strong class="text-[#5925dc] font-bold"><?= e(t_format($minWithdrawalT)) ?> (<?= e(inr_format(t_to_inr($minWithdrawalT))) ?>)</strong></span>
            <?php endif; ?>
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
        <?php elseif (!$canWithdraw): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            Your balance of <?= e(t_format($balanceT)) ?> is below the minimum withdrawal of <?= e(t_format($effectiveMinT)) ?>.
          </div>
        <?php elseif ($isFirstWithdrawal): ?>
          <div class="mb-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            This is your first withdrawal. It is limited to <?= e(t_format($firstWithdrawalMax)) ?> and is paid out automatically.
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

          <!-- Amount Input -->
          <div class="mb-3">
            <label class="text-xs font-bold text-slate-600 mb-1.5 block" for="withdrawAmount">Amount</label>
            <div class="bg-[#f4f4f8] rounded-2xl px-3.5 py-3 flex items-center justify-between border border-slate-200/80">
              <input type="number" id="withdrawAmount" name="amount" value="<?= e((string) $formAmount) ?>"
                     min="<?= e((string) (int) $effectiveMinT) ?>" max="<?= e((string) (int) $effectiveMaxT) ?>" step="1"
                     readonly aria-readonly="true"
                     class="bg-transparent text-sm font-semibold text-slate-800 w-full outline-none cursor-default select-none" />
              <span class="text-xs font-bold text-[#5925dc] whitespace-nowrap ml-2">T</span>
            </div>
            <div class="text-[10px] font-medium text-slate-400 mt-1.5">
              <?php if ($isFirstWithdrawal): ?>
                Max <?= e(t_format($firstWithdrawalMax)) ?> &middot; Balance <?= e(t_format($balanceT)) ?>
              <?php else: ?>
                Min <?= e(t_format($minWithdrawalT)) ?> &middot; Max <?= e(t_format($maxWithdrawalT)) ?> &middot; Balance <?= e(t_format($balanceT)) ?>
              <?php endif; ?>
            </div>
            <div class="text-[10px] font-semibold text-[#5925dc] mt-1" id="amountInrHint">
              You will receive <?= e(inr_format(t_to_inr($formAmount))) ?>
            </div>
          </div>

          <!-- Quick Amount Chips: minimum, two random values, maximum -->
          <?php if ($amountChips !== []): ?>
            <?php
              $chipCols = match (count($amountChips)) {
                  1       => 'grid-cols-1',
                  2       => 'grid-cols-2',
                  3       => 'grid-cols-3',
                  default => 'grid-cols-4',
              };
            ?>
            <div class="grid <?= $chipCols ?> gap-2 mt-3" id="amountChips">
              <?php foreach ($amountChips as $index => $chip): ?>
                <?php
                  $isActive  = $index === 0;
                  $chipClass = $isActive
                      ? 'bg-[#5925dc] text-white'
                      : 'bg-[#f2eeff] text-[#5925dc]';
                ?>
                <button type="button" data-amount="<?= e((string) $chip) ?>"
                        class="amount-chip <?= $chipClass ?> text-xs font-bold py-2 rounded-xl active:scale-95 transition"><?= e(t_format($chip, false)) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

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
        const MIN_WITHDRAW = <?= (int) $effectiveMinT ?>;
        const MAX_WITHDRAW = <?= (int) $effectiveMaxT ?>;
        const IS_FIRST     = <?= $isFirstWithdrawal ? 'true' : 'false' ?>;
        const BALANCE_T    = <?= (float) $balanceT ?>;
        const COIN_RATE    = <?= (float) setting('coin_to_inr_rate', 0.10) ?>;

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

        function setAmount(value) {
          const input = document.getElementById('withdrawAmount');
          input.value = value;

          document.querySelectorAll('.amount-chip').forEach(function (chip) {
            const isActive = parseInt(chip.dataset.amount, 10) === parseInt(value, 10);
            chip.classList.toggle('bg-[#5925dc]', isActive);
            chip.classList.toggle('text-white', isActive);
            chip.classList.toggle('bg-[#f2eeff]', !isActive);
            chip.classList.toggle('text-[#5925dc]', !isActive);
          });

          const hint = document.getElementById('amountInrHint');
          if (hint) {
            const inr = (parseInt(value, 10) || 0) * COIN_RATE;
            hint.textContent = 'You will receive ₹ ' + inr.toLocaleString('en-IN', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            });
          }
        }

        document.querySelectorAll('.amount-chip').forEach(function (chip) {
          chip.addEventListener('click', function () {
            setAmount(parseInt(chip.dataset.amount, 10));
          });
        });

        // Highlight whichever chip matches the pre-selected amount.
        setAmount(parseInt(document.getElementById('withdrawAmount').value, 10) || MIN_WITHDRAW);

        document.getElementById('withdrawForm').addEventListener('submit', function (event) {
          const upiId = document.getElementById('upiId').value.trim();
          const amount = parseInt(document.getElementById('withdrawAmount').value, 10);

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
          if (!amount || amount < MIN_WITHDRAW) {
            event.preventDefault();
            showToast(IS_FIRST
              ? 'Please pick an amount between 1 and ' + MAX_WITHDRAW.toLocaleString() + ' T'
              : 'Minimum withdrawal is ' + MIN_WITHDRAW.toLocaleString() + ' T', true);
            return;
          }
          if (amount > MAX_WITHDRAW) {
            event.preventDefault();
            showToast(IS_FIRST
              ? 'Your first withdrawal is limited to ' + MAX_WITHDRAW.toLocaleString() + ' T'
              : 'Maximum withdrawal is ' + MAX_WITHDRAW.toLocaleString() + ' T', true);
            return;
          }
          if (amount > BALANCE_T) {
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