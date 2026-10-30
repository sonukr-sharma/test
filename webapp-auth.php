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

if (!empty($_POST['initData']) && is_string($_POST['initData'])) {
    $initData = $_POST['initData'];
} else {
    $raw     = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    if (is_array($decoded) && !empty($decoded['initData']) && is_string($decoded['initData'])) {
        $initData = $decoded['initData'];
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

echo json_encode([
    'ok'      => true,
    'user_id' => (int) $user['id'],
]);
