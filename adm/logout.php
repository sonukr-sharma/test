<?php
/**
 * Admin — Sign out
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (is_admin_logged_in()) {
    try {
        $log = db()->prepare(
            'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `ip_address`)
             VALUES (:admin_id, :action, :entity_type, :entity_id, :ip)'
        );
        $log->execute([
            ':admin_id'    => $_SESSION['admin_id'],
            ':action'      => 'admin.logout',
            ':entity_type' => 'admin',
            ':entity_id'   => $_SESSION['admin_id'],
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Logout audit failed: ' . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

redirect('adm/auth/index.php');
