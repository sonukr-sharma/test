<?php
/**
 * Admin — Withdrawal approve / reject handler
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('adm/withdrawal.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Your session expired. Please try again.'];
    redirect('adm/withdrawal.php');
}

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Invalid withdrawal request.'];
    redirect('adm/withdrawal.php');
}

$newStatus = $action === 'approve' ? 'completed' : 'rejected';

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT `id`, `user_id`, `amount_t`, `status`
           FROM `withdrawals`
          WHERE `id` = :id
          FOR UPDATE'
    );
    $stmt->execute([':id' => $id]);
    $withdrawal = $stmt->fetch();

    if ($withdrawal === false) {
        $pdo->rollBack();
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Withdrawal not found.'];
        redirect('adm/withdrawal.php');
    }

    if (!in_array($withdrawal['status'], ['pending', 'processing'], true)) {
        $pdo->rollBack();
        $_SESSION['admin_flash'] = [
            'type'    => 'error',
            'message' => 'This withdrawal has already been ' . $withdrawal['status'] . '.',
        ];
        redirect('adm/withdrawal.php');
    }

    $update = $pdo->prepare(
        'UPDATE `withdrawals`
            SET `status` = :status, `processed_at` = NOW(), `processed_by` = :admin
          WHERE `id` = :id'
    );
    $update->execute([
        ':status' => $newStatus,
        ':admin'  => $admin['id'],
        ':id'     => $id,
    ]);

    $history = $pdo->prepare(
        'INSERT INTO `withdrawal_status_history`
             (`withdrawal_id`, `from_status`, `to_status`, `admin_id`)
         VALUES (:wid, :from, :to, :admin)'
    );
    $history->execute([
        ':wid'   => $id,
        ':from'  => $withdrawal['status'],
        ':to'    => $newStatus,
        ':admin' => $admin['id'],
    ]);

    // Rejected withdrawals return the held amount to the user's wallet.
    if ($newStatus === 'rejected') {
        $refund = $pdo->prepare(
            'UPDATE `users`
                SET `balance_t` = `balance_t` + :amount
              WHERE `id` = :user'
        );
        $refund->execute([
            ':amount' => $withdrawal['amount_t'],
            ':user'   => $withdrawal['user_id'],
        ]);

        $balanceStmt = $pdo->prepare('SELECT `balance_t` FROM `users` WHERE `id` = :user');
        $balanceStmt->execute([':user' => $withdrawal['user_id']]);
        $balanceAfter = (float) $balanceStmt->fetchColumn();

        $ledger = $pdo->prepare(
            'INSERT INTO `wallet_transactions`
                 (`user_id`, `type`, `direction`, `amount_t`, `balance_after_t`,
                  `reference_type`, `reference_id`, `description`)
             VALUES (:user, :type, :direction, :amount, :balance,
                     :ref_type, :ref_id, :description)'
        );
        $ledger->execute([
            ':user'        => $withdrawal['user_id'],
            ':type'        => 'withdrawal_refund',
            ':direction'   => 'credit',
            ':amount'      => $withdrawal['amount_t'],
            ':balance'     => $balanceAfter,
            ':ref_type'    => 'withdrawal',
            ':ref_id'      => $id,
            ':description' => 'Withdrawal #' . $id . ' rejected — amount refunded',
        ]);
    }

    $audit = $pdo->prepare(
        'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
         VALUES (:admin, :action, :entity_type, :entity_id, :meta, :ip)'
    );
    $audit->execute([
        ':admin'       => $admin['id'],
        ':action'      => 'withdrawal.' . $action,
        ':entity_type' => 'withdrawal',
        ':entity_id'   => $id,
        ':meta'        => json_encode([
            'from'   => $withdrawal['status'],
            'to'     => $newStatus,
            'amount' => $withdrawal['amount_t'],
        ]),
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $pdo->commit();

    $_SESSION['admin_flash'] = [
        'type'    => 'success',
        'message' => $action === 'approve'
            ? 'Withdrawal #' . $id . ' approved.'
            : 'Withdrawal #' . $id . ' rejected and refunded.',
    ];
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Withdrawal action failed: ' . $e->getMessage());
    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => APP_DEBUG ? $e->getMessage() : 'Unable to process the withdrawal right now.',
    ];
}

redirect('adm/withdrawal.php');
