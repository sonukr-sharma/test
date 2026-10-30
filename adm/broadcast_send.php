<?php
/**
 * Admin - Broadcast save handler
 *
 * Persists a composed broadcast (and its inline buttons) as a draft.
 * Actual Telegram delivery is performed by the bot worker once a bot token
 * is configured in Settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('adm/broadcast.php');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Your session expired. Please try again.'];
    redirect('adm/broadcast.php');
}

/** Collect the posted inline buttons for the active media tab. */
function collect_posted_buttons(): array
{
    $raw = $_POST['buttons'] ?? '[]';
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return [];
    }

    $buttons = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['text'] ?? ''));
        $url   = trim((string) ($row['link'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }
        if (!preg_match('~^(https?://|tg://|mailto:)~i', $url)) {
            continue;
        }
        $style = (string) ($row['style'] ?? 'primary');
        $buttons[] = [
            'label' => mb_substr($label, 0, 64),
            'url'   => mb_substr($url, 0, 500),
            'style' => in_array($style, ['primary', 'success', 'danger'], true) ? $style : 'primary',
        ];
        if (count($buttons) >= 8) {
            break;
        }
    }

    return $buttons;
}

$mediaType = (string) ($_POST['media_type'] ?? 'none');
$mediaType = in_array($mediaType, ['none', 'image', 'video', 'file'], true) ? $mediaType : 'none';

$audience = (string) ($_POST['audience'] ?? 'all');
$audience = in_array($audience, ['all', 'active', 'inactive', 'custom'], true) ? $audience : 'all';

$messageText = trim((string) ($_POST['message_text'] ?? ''));
$buttons     = collect_posted_buttons();

if ($messageText === '' && $buttons === []) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Add a message or at least one button before saving.'];
    redirect('adm/broadcast.php');
}

if (mb_strlen($messageText) > 4096) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Message is too long. Telegram allows 4096 characters.'];
    redirect('adm/broadcast.php');
}

$firstLine = trim((string) strtok($messageText, "\n"));
$title     = $firstLine !== '' ? mb_substr($firstLine, 0, 190) : 'Broadcast ' . date('d M Y, H:i');

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO `broadcasts`
            (`title`, `message_text`, `parse_mode`, `media_type`, `audience`, `status`, `created_by`)
         VALUES (:title, :message_text, :parse_mode, :media_type, :audience, :status, :created_by)'
    );
    $stmt->execute([
        ':title'        => $title,
        ':message_text' => $messageText !== '' ? $messageText : null,
        ':parse_mode'   => 'Markdown',
        ':media_type'   => $mediaType,
        ':audience'     => $audience,
        ':status'       => 'draft',
        ':created_by'   => (int) $admin['id'],
    ]);

    $broadcastId = (int) $pdo->lastInsertId();

    if ($buttons !== []) {
        $btnStmt = $pdo->prepare(
            'INSERT INTO `broadcast_buttons` (`broadcast_id`, `label`, `url`, `style`, `position`)
             VALUES (:broadcast_id, :label, :url, :style, :position)'
        );
        foreach ($buttons as $index => $button) {
            $btnStmt->execute([
                ':broadcast_id' => $broadcastId,
                ':label'        => $button['label'],
                ':url'          => $button['url'],
                ':style'        => $button['style'],
                ':position'     => $index + 1,
            ]);
        }
    }

    $pdo->prepare(
        'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
         VALUES (:admin_id, :action, :entity_type, :entity_id, :meta, :ip)'
    )->execute([
        ':admin_id'    => (int) $admin['id'],
        ':action'      => 'broadcast.create',
        ':entity_type' => 'broadcast',
        ':entity_id'   => $broadcastId,
        ':meta'        => json_encode(['title' => $title, 'audience' => $audience, 'buttons' => count($buttons)], JSON_UNESCAPED_UNICODE),
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $pdo->commit();

    $botToken = (string) setting('bot_token', '');
    $message  = $botToken !== ''
        ? 'Broadcast #' . $broadcastId . ' queued for delivery.'
        : 'Broadcast #' . $broadcastId . ' saved as a draft. Add a Telegram bot token in Settings to enable delivery.';

    $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $message];
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Broadcast save failed: ' . $e->getMessage());
    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => APP_DEBUG ? $e->getMessage() : 'Could not save the broadcast. Please try again.',
    ];
}

redirect('adm/broadcast.php');
