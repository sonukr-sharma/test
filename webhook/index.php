<?php
/**
 * =====================================================================
 *  webhook/index.php — Telegram Bot update receiver
 *
 *  Register this URL with Telegram from webhook/setup.php:
 *      https://<your-domain>/webhook/index.php
 *
 *  Handles:
 *    /start <referral_code>  → upsert user, credit referrer + signup bonus
 *    /start                  → upsert user, welcome message
 *    /balance, /invite       → quick replies
 *    web_app_data            → acknowledgement
 *
 *  Always answers HTTP 200 so Telegram does not retry the same update.
 * =====================================================================
 */

declare(strict_types=1);

// The webhook is stateless — no session, no cookies.
define('APP_NO_SESSION', true);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Send the JSON reply and stop.
 *
 * @param array<string, mixed> $payload
 */
function webhook_reply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Persist the raw update for debugging.
 *
 * @param array<string, mixed> $update
 */
function log_update(array $update, ?int $telegramId, ?string $type, ?string $command, bool $handled, ?string $error = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO `telegram_updates`
                (`update_id`, `telegram_id`, `update_type`, `command`, `payload`, `handled`, `error_message`)
             VALUES (:uid, :tid, :type, :cmd, :payload, :handled, :err)
             ON DUPLICATE KEY UPDATE
                `handled` = VALUES(`handled`),
                `error_message` = VALUES(`error_message`)'
        );
        $stmt->execute([
            ':uid'     => isset($update['update_id']) ? (int) $update['update_id'] : null,
            ':tid'     => $telegramId,
            ':type'    => $type,
            ':cmd'     => $command,
            ':payload' => json_encode($update, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':handled' => $handled ? 1 : 0,
            ':err'     => $error !== null ? mb_substr($error, 0, 500) : null,
        ]);
    } catch (Throwable $e) {
        error_log('webhook log_update failed: ' . $e->getMessage());
    }
}

/**
 * Find or create the user for a Telegram identity payload.
 *
 * @param  array<string, mixed> $from
 * @return array<string, mixed>|null
 */
function webhook_upsert_user(array $from): ?array
{
    $telegramId = (int) ($from['id'] ?? 0);

    if ($telegramId === 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM `users` WHERE `telegram_id` = :tid LIMIT 1');
    $stmt->execute([':tid' => $telegramId]);
    $existing = $stmt->fetch();

    $username  = isset($from['username']) ? (string) $from['username'] : null;
    $firstName = isset($from['first_name']) ? (string) $from['first_name'] : null;
    $lastName  = isset($from['last_name']) ? (string) $from['last_name'] : null;

    if ($existing !== false) {
        // Any message proves the user has started the bot.
        $stmt = db()->prepare(
            'UPDATE `users`
                SET `username` = :un, `first_name` = :fn, `last_name` = :ln,
                    `last_active_at` = NOW(),
                    `bot_started_at` = COALESCE(`bot_started_at`, NOW())
              WHERE `id` = :id'
        );
        $stmt->execute([
            ':un' => $username,
            ':fn' => $firstName,
            ':ln' => $lastName,
            ':id' => $existing['id'],
        ]);

        $existing['username']       = $username;
        $existing['first_name']     = $firstName;
        $existing['last_name']      = $lastName;
        $existing['bot_started_at'] = $existing['bot_started_at'] ?? date('Y-m-d H:i:s');

        return $existing;
    }

    if (!setting('allow_registration', true)) {
        return null;
    }

    $code = generate_referral_code($username ?? $firstName ?? 'USER');

    $stmt = db()->prepare(
        'INSERT INTO `users`
            (`telegram_id`, `username`, `first_name`, `last_name`, `referral_code`, `bot_started_at`, `last_active_at`)
         VALUES (:tid, :un, :fn, :ln, :rc, NOW(), NOW())'
    );
    $stmt->execute([
        ':tid' => $telegramId,
        ':un'  => $username,
        ':fn'  => $firstName,
        ':ln'  => $lastName,
        ':rc'  => $code,
    ]);

    $stmt = db()->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
    $stmt->execute([':id' => (int) db()->lastInsertId()]);

    return $stmt->fetch() ?: null;
}

/**
 * Build the WebApp keyboard used in bot replies.
 *
 * @return array<string, mixed>
 */
function webhook_webapp_keyboard(): array
{
    return [
        'inline_keyboard' => [[
            ['text' => '🚀 Open Refer & Earn', 'web_app' => ['url' => web_app_url()]],
        ]],
    ];
}

/* ---------------------------------------------------------------------
 |  Request handling
 * ------------------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    webhook_reply([
        'ok'      => true,
        'service' => 'Refer & Earn Telegram webhook',
        'hint'    => 'Telegram posts updates here. Configure it from webhook/setup.php.',
    ]);
}

$rawBody = file_get_contents('php://input') ?: '';

if ($rawBody === '') {
    webhook_reply(['ok' => true, 'skipped' => 'empty body']);
}

// Verify the secret token Telegram echoes back, when one is configured.
$expectedSecret = trim((string) setting('webhook_secret', ''));
$providedSecret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');

if ($expectedSecret !== '' && !hash_equals($expectedSecret, $providedSecret)) {
    error_log('webhook: rejected update with a bad secret token.');
    webhook_reply(['ok' => false, 'error' => 'unauthorized'], 401);
}

$update = json_decode($rawBody, true);

if (!is_array($update)) {
    log_update([], null, null, null, false, 'Invalid JSON body');
    webhook_reply(['ok' => true, 'skipped' => 'invalid json']);
}

$message = $update['message'] ?? $update['edited_message'] ?? null;
$from    = is_array($message) ? ($message['from'] ?? null) : null;
$chatId  = is_array($message) ? ($message['chat']['id'] ?? null) : null;
$text    = is_array($message) ? trim((string) ($message['text'] ?? '')) : '';

$telegramId = is_array($from) && isset($from['id']) ? (int) $from['id'] : null;
$updateType = isset($update['message']) ? 'message' : (isset($update['edited_message']) ? 'edited_message' : 'other');
$command    = null;

if ($text !== '' && str_starts_with($text, '/')) {
    $command = strtolower(explode(' ', $text)[0]);
    $command = explode('@', $command)[0];
}

// Callback queries (inline button taps) — acknowledge and stop.
if (isset($update['callback_query'])) {
    log_update($update, $telegramId, 'callback_query', null, true);
    telegram_api('answerCallbackQuery', ['callback_query_id' => $update['callback_query']['id'] ?? '']);
    webhook_reply(['ok' => true]);
}

// chat_member updates — Telegram notifies us when a user joins/leaves one of
// our channels. This is the automatic "postback" verification: when a user
// joins a required channel we re-check their membership and, if they now pass
// the gate, record channels_verified_at so the app unlocks without the user
// having to press "I've joined — verify".
if (isset($update['chat_member'])) {
    $cm = $update['chat_member'];

    // The affected user is in new_chat_member.user; the chat is the channel.
    $memberFrom = is_array($cm['new_chat_member'] ?? null) ? ($cm['new_chat_member']['user'] ?? null) : null;
    $memberTid  = is_array($memberFrom) && isset($memberFrom['id']) ? (int) $memberFrom['id'] : 0;
    $memberChat = is_array($cm['chat'] ?? null) ? ($cm['chat']['id'] ?? null) : null;

    if ($memberTid === 0 || $memberChat === null) {
        log_update($update, $memberTid, 'chat_member', null, false, 'Missing member user or chat id');
        webhook_reply(['ok' => true]);
    }

    // Only act on join events (member/administrator/creator), not leaves.
    $newStatus = (string) ($cm['new_chat_member']['status'] ?? '');
    $isJoin    = in_array($newStatus, ['member', 'administrator', 'creator'], true);

    if ($isJoin) {
        try {
            $stmt = db()->prepare('SELECT * FROM `users` WHERE `telegram_id` = :tid LIMIT 1');
            $stmt->execute([':tid' => $memberTid]);
            $memberUser = $stmt->fetch();

            if ($memberUser !== false) {
                // Re-run the full membership check; if the user now passes the
                // gate, mark them verified.
                $state = channel_membership_status($memberUser);

                if ($state['ok'] && !$state['unknown']) {
                    $stmt = db()->prepare('UPDATE `users` SET `channels_verified_at` = NOW() WHERE `id` = :id');
                    $stmt->execute([':id' => $memberUser['id']]);
                }
            }
        } catch (Throwable $e) {
            error_log('webhook chat_member handler failed: ' . $e->getMessage());
        }
    }

    log_update($update, $memberTid, 'chat_member', null, true);
    webhook_reply(['ok' => true]);
}

if (!is_array($from) || $chatId === null) {
    log_update($update, $telegramId, $updateType, $command, false, 'No sender or chat id');
    webhook_reply(['ok' => true, 'skipped' => 'no sender']);
}

try {
    $user = webhook_upsert_user($from);

    if ($user === null) {
        log_update($update, $telegramId, $updateType, $command, false, 'Registration disabled or user blocked');
        telegram_send_message($chatId, 'Sorry, registration is currently closed.');
        webhook_reply(['ok' => true]);
    }

    if (($user['status'] ?? 'active') === 'blocked') {
        log_update($update, $telegramId, $updateType, $command, false, 'User is blocked');
        webhook_reply(['ok' => true]);
    }

    $replyText = '';

    if ($command === '/start') {
        $parts        = preg_split('/\s+/', $text) ?: [];
        $referralCode = trim((string) ($parts[1] ?? ''));

        // The referral is only *recorded* here. It is verified and applied
        // inside the web app, after the user passes the channel gate.
        if ($referralCode !== '') {
            try {
                $stmt = db()->prepare(
                    'UPDATE `users` SET `pending_referral_code` = :c
                      WHERE `id` = :id AND `referred_by_id` IS NULL'
                );
                $stmt->execute([':c' => $referralCode, ':id' => $user['id']]);
            } catch (Throwable $e) {
                error_log('webhook pending referral failed: ' . $e->getMessage());
            }
        }

        grant_signup_bonus($user);

        // Reload the wallet after the credits above.
        $stmt = db()->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $user['id']]);
        $user = $stmt->fetch() ?: $user;

        $name = trim((string) ($user['first_name'] ?? '')) ?: 'there';

        $replyText = "👋 <b>Welcome, " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "!</b>\n\n"
            . "💰 Balance: <b>" . t_format((float) $user['balance_t']) . "</b>\n"
            . "👥 Referrals: <b>" . number_format((int) $user['referral_count']) . "</b>\n"
            . "🎁 Reward per referral: <b>" . t_format((float) setting('referral_reward_t', 150)) . "</b>\n\n";

        if ($referralCode !== '') {
            $replyText .= "🎯 Referral code <b>" . htmlspecialchars($referralCode, ENT_QUOTES, 'UTF-8') . "</b> saved.\n"
                . "Open the app to confirm it.\n\n";
        }

        $replyText .= "Tap the button below to open the mini app and start earning.";

        if (channel_gate_enabled()) {
            $replyText .= "\n\n🔒 Join our channels inside the app to unlock your wallet.";
        }
    } elseif ($command === '/balance') {
        $replyText = "💰 Your balance is <b>" . t_format((float) $user['balance_t']) . "</b>\n"
            . "≈ " . inr_format(t_to_inr((float) $user['balance_t']));
    } elseif ($command === '/invite' || $command === '/refer') {
        $replyText = "🔗 <b>Your invite link</b>\n"
            . invite_link($user) . "\n\n"
            . "Earn <b>" . t_format((float) setting('referral_reward_t', 150)) . "</b> for every friend who joins.";
    } elseif ($command === '/help') {
        $replyText = "<b>Available commands</b>\n"
            . "/start — open the app\n"
            . "/balance — show your wallet\n"
            . "/invite — get your invite link\n"
            . "/help — this message";
    } elseif (isset($message['web_app_data'])) {
        $replyText = "✅ Thanks! Your submission was received.";
    } else {
        $replyText = "Use /start to open Refer & Earn, or /help to see all commands.";
    }

    telegram_send_message($chatId, $replyText, webhook_webapp_keyboard());

    log_update($update, $telegramId, $updateType, $command, true);

    webhook_reply(['ok' => true]);
} catch (Throwable $e) {
    error_log('webhook handler failed: ' . $e->getMessage());
    log_update($update, $telegramId, $updateType, $command, false, $e->getMessage());

    // Still answer 200 so Telegram does not hammer the endpoint.
    webhook_reply(['ok' => true, 'error' => 'handled with errors']);
}
