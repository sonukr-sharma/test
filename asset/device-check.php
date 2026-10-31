<?php
/**
 * =====================================================================
 *  device-check.php — one Telegram account per device
 *
 *  Called by device-guard.js on every app open.
 *
 *  1. Validates Telegram WebApp initData (HMAC) so the Telegram id can't
 *     be forged by the client.
 *  2. Looks for the same device (cookie / localStorage id) already bound
 *     to a DIFFERENT user.
 *  3. If found → the NEW account is blocked (users.status = 'blocked')
 *     and the event is written to audit_logs.
 *  4. Otherwise → binds / refreshes this device for this user.
 *
 *  Place next to config.php (project root).
 * =====================================================================
 */

declare(strict_types=1);

define('APP_NO_SESSION', true);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** How old (seconds) Telegram's auth_date may be. */
const DG_AUTH_MAX_AGE = 86400;

/**
 * Also treat "same fingerprint + same IP" as the same device.
 * Catches users who clear cookies/storage. Can false-positive for two
 * identical phones on the same Wi-Fi, so turn it off if that matters.
 */
const DG_BLOCK_ON_FP_IP = true;

/** Cookie lifetime: 2 years. */
const DG_COOKIE_TTL = 63072000;

function dg_reply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Verify Telegram WebApp initData and return the Telegram user array.
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 *
 * @return array<string, mixed>|null
 */
function dg_validate_init_data(string $initData, string $botToken): ?array
{
    if ($initData === '' || $botToken === '') {
        return null;
    }

    parse_str($initData, $data);

    if (!isset($data['hash']) || !is_string($data['hash'])) {
        return null;
    }

    $hash = $data['hash'];
    unset($data['hash']);
    ksort($data);

    $pairs = [];
    foreach ($data as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }

    $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calc   = hash_hmac('sha256', implode("\n", $pairs), $secret);

    if (!hash_equals($calc, $hash)) {
        return null;
    }

    $authDate = (int) ($data['auth_date'] ?? 0);
    if ($authDate === 0 || (time() - $authDate) > DG_AUTH_MAX_AGE) {
        return null;
    }

    $user = json_decode((string) ($data['user'] ?? ''), true);

    return (is_array($user) && isset($user['id'])) ? $user : null;
}

/* ---------------------------------------------------------------------
 |  Request
 * ------------------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    dg_reply(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    dg_reply(['ok' => false, 'error' => 'bad_request'], 400);
}

$initData    = (string) ($body['initData'] ?? '');
$clientDid   = strtolower((string) ($body['deviceId'] ?? ''));
$fingerprint = strtolower((string) ($body['fingerprint'] ?? ''));

$uuidRe = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';

if (!preg_match($uuidRe, $clientDid)) {
    dg_reply(['ok' => false, 'error' => 'bad_device_id'], 400);
}
if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
    $fingerprint = '';
}

$tgUser = dg_validate_init_data($initData, trim((string) setting('bot_token', '')));

if ($tgUser === null) {
    dg_reply(['ok' => false, 'blocked' => true, 'reason' => 'invalid_telegram_session'], 401);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT `id`, `status` FROM `users` WHERE `telegram_id` = :tid LIMIT 1');
    $stmt->execute([':tid' => (int) $tgUser['id']]);
    $user = $stmt->fetch();

    if ($user === false) {
        // Not registered yet (user hasn't hit /start). Nothing to bind.
        dg_reply(['ok' => true, 'blocked' => false, 'skipped' => 'no_user']);
    }

    $userId = (int) $user['id'];

    if (($user['status'] ?? 'active') === 'blocked') {
        dg_reply(['ok' => true, 'blocked' => true, 'reason' => 'account_blocked']);
    }

    $ip        = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    // Candidate device ids: the one the client sent + the HttpOnly cookie.
    $candidates = [$clientDid];
    $cookieDid  = strtolower((string) ($_COOKIE['rae_did'] ?? ''));
    if ($cookieDid !== '' && preg_match($uuidRe, $cookieDid) && $cookieDid !== $clientDid) {
        $candidates[] = $cookieDid;
    }

    // --- Conflict check: same device already bound to another user? ------
    $ph  = implode(',', array_fill(0, count($candidates), '?'));
    $sql = "SELECT DISTINCT `user_id` FROM `user_devices`
             WHERE `device_id` IN ($ph) AND `user_id` <> ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$candidates, $userId]);
    $conflict = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $matchedBy = $conflict ? 'device_id' : null;

    if (!$conflict && DG_BLOCK_ON_FP_IP && $fingerprint !== '' && $ip !== '') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT `user_id` FROM `user_devices`
              WHERE `fingerprint` = :fp AND `ip_address` = :ip AND `user_id` <> :uid'
        );
        $stmt->execute([':fp' => $fingerprint, ':ip' => $ip, ':uid' => $userId]);
        $conflict  = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $matchedBy = $conflict ? 'fingerprint+ip' : null;
    }

    if ($conflict) {
        // Block the NEW account (the one connecting now); the original
        // owner of the device keeps access.
        $pdo->prepare("UPDATE `users` SET `status` = 'blocked' WHERE `id` = :id")
            ->execute([':id' => $userId]);

        $pdo->prepare(
            'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
             VALUES (NULL, :a, :et, :eid, :meta, :ip)'
        )->execute([
            ':a'    => 'device.multi_account_blocked',
            ':et'   => 'user',
            ':eid'  => $userId,
            ':meta' => json_encode([
                'matched_by'      => $matchedBy,
                'device_id'       => $clientDid,
                'fingerprint'     => $fingerprint,
                'existing_users'  => array_map('intval', $conflict),
                'user_agent'      => $userAgent,
            ], JSON_UNESCAPED_SLASHES),
            ':ip'   => $ip,
        ]);

        dg_reply(['ok' => true, 'blocked' => true, 'reason' => 'multiple_accounts_on_device']);
    }

    // --- No conflict: bind / refresh this device -------------------------
    $stmt = $pdo->prepare(
        'INSERT INTO `user_devices` (`user_id`, `device_id`, `fingerprint`, `ip_address`, `user_agent`)
         VALUES (:uid, :did, :fp, :ip, :ua)
         ON DUPLICATE KEY UPDATE
            `fingerprint` = VALUES(`fingerprint`),
            `ip_address`  = VALUES(`ip_address`),
            `user_agent`  = VALUES(`user_agent`),
            `last_seen_at` = NOW()'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':did' => $clientDid,
        ':fp'  => $fingerprint !== '' ? $fingerprint : null,
        ':ip'  => $ip,
        ':ua'  => $userAgent,
    ]);

    // Server-set HttpOnly cookie (JS can't read or delete it directly).
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    setcookie('rae_did', $clientDid, [
        'expires'  => time() + DG_COOKIE_TTL,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => $https ? 'None' : 'Lax',   // None needed inside Telegram's iframe
    ]);

    dg_reply(['ok' => true, 'blocked' => false]);
} catch (Throwable $e) {
    error_log('device-check failed: ' . $e->getMessage());
    // Fail open so a DB hiccup doesn't lock everyone out.
    dg_reply(['ok' => true, 'blocked' => false, 'error' => 'check_failed']);
}
