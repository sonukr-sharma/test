<?php
declare(strict_types=1);

/**
 * Telegram WebApp handshake endpoint.
 *
 * Telegram delivers `initData` in the URL *fragment* (#tgWebAppData=...), which
 * browsers never send to the server. The client therefore posts the payload
 * here once, we verify its HMAC signature, and the value is kept in the session
 * so every later request can resolve the user.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$initData = '';
$fingerprintRaw = '';
$platform = '';

if (!empty($_POST['initData']) && is_string($_POST['initData'])) {
    $initData       = $_POST['initData'];
    $fingerprintRaw = isset($_POST['fingerprint']) && is_string($_POST['fingerprint']) ? $_POST['fingerprint'] : '';
    $platform       = isset($_POST['platform']) && is_string($_POST['platform']) ? $_POST['platform'] : '';
} else {
    $raw     = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    if (is_array($decoded) && !empty($decoded['initData']) && is_string($decoded['initData'])) {
        $initData       = $decoded['initData'];
        $fingerprintRaw = isset($decoded['fingerprint']) && is_string($decoded['fingerprint']) ? $decoded['fingerprint'] : '';
        $platform       = isset($decoded['platform']) && is_string($decoded['platform']) ? $decoded['platform'] : '';
    }
}

if ($initData === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_init_data']);
    exit;
}

$user = telegram_validate_init_data($initData);

if ($user === null || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid_init_data']);
    exit;
}

// The signature is the credential, so a fresh session id is safe here and
// prevents fixation on a session created by the unauthenticated gate page.
session_regenerate_id(true);

$_SESSION['tg_init_data'] = $initData;

// Device fingerprinting runs only after the signature check above, so the
// Telegram ID it is attached to is genuine. It is advisory: a shared device
// flags the account for review and never blocks anything.
//
// current_user() resolves the Telegram identity to the internal users row
// (creating it on first login) and returns null for blocked or unregistered
// users, in which case there is nothing to attach a device to.
$device = ['device_count' => 0, 'flagged' => false];
$account = current_user();

if ($account !== null && !empty($account['id'])) {
    $device = record_user_device(
        (int) $account['id'],
        normalise_fingerprint($fingerprintRaw),
        $platform,
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
}

echo json_encode([
    'ok'      => true,
    'user_id' => (int) $user['id'],
    'device'  => [
        'count'   => $device['device_count'],
        'flagged' => $device['flagged'],
    ],
]);
