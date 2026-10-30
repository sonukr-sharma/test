<?php
/**
 * Admin - Broadcast send handler
 *
 * Persists a composed broadcast (and its inline buttons), then delivers it to
 * the resolved audience through the Telegram Bot API. Delivery is synchronous:
 * the request stays open until every recipient has been attempted, and the
 * delivered/failed counters are written as it goes.
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

/**
 * Escape text for Telegram's HTML parse mode.
 */
function tg_h(string $s): string
{
    return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Convert one line of the composer's Markdown into Telegram HTML.
 *
 * The composer emits `*bold*`, `_italic_`, `__underline__`, `~strike~`,
 * `||spoiler||`, `` `code` `` and `[text](url)`. Legacy Markdown supports none
 * of the last four, and MarkdownV2 would require escaping every `.`/`!`/`-`,
 * so the message is converted to HTML and sent with `parse_mode = 'HTML'`.
 */
function tg_inline(string $t): string
{
    $t     = tg_h($t);
    $stash = [];

    $keep = static function (string $html) use (&$stash): string {
        $key          = "\x01" . count($stash) . "\x02";
        $stash[$key]  = $html;

        return $key;
    };

    $t = preg_replace_callback(
        '/`([^`\n]+)`/',
        static fn (array $m): string => $keep('<code>' . $m[1] . '</code>'),
        $t
    ) ?? $t;

    $t = preg_replace_callback(
        '/\[([^\]\n]+)\]\(((?:https?|tg):\/\/[^)\s]+)\)/i',
        static fn (array $m): string => $keep(
            '<a href="' . str_replace('"', '&quot;', $m[2]) . '">' . $m[1] . '</a>'
        ),
        $t
    ) ?? $t;

    $t = preg_replace('/\|\|(.+?)\|\|/s', '<tg-spoiler>$1</tg-spoiler>', $t) ?? $t;
    $t = preg_replace('/__(.+?)__/s', '<u>$1</u>', $t) ?? $t;
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t) ?? $t;
    $t = preg_replace('/\*(.+?)\*/s', '<b>$1</b>', $t) ?? $t;
    $t = preg_replace('/(?<!\w)_(.+?)_(?!\w)/s', '<i>$1</i>', $t) ?? $t;
    $t = preg_replace('/~(.+?)~/s', '<s>$1</s>', $t) ?? $t;

    return strtr($t, $stash);
}

/**
 * Convert the composer's Markdown into Telegram HTML.
 */
function tg_markdown_to_html(string $src): string
{
    $lines = preg_split('/\r\n|\r|\n/', $src) ?: [];
    $count = count($lines);
    $i     = 0;
    $out   = [];

    while ($i < $count) {
        if (preg_match('/^\s*```/', $lines[$i])) {
            $buffer = [];
            $i++;
            while ($i < $count && !preg_match('/^\s*```/', $lines[$i])) {
                $buffer[] = $lines[$i++];
            }
            $i++;
            $out[] = '<pre>' . tg_h(implode("\n", $buffer)) . '</pre>';
            continue;
        }

        if (preg_match('/^\s*>/', $lines[$i])) {
            $buffer = [];
            while ($i < $count && preg_match('/^\s*>/', $lines[$i])) {
                $buffer[] = tg_inline((string) preg_replace('/^\s*>\s?/', '', $lines[$i]));
                $i++;
            }
            $out[] = '<blockquote>' . implode("\n", $buffer) . '</blockquote>';
            continue;
        }

        $out[] = tg_inline($lines[$i++]);
    }

    return implode("\n", $out);
}

/** Collect the posted inline buttons for the active media tab. */
function collect_posted_buttons(): array
{
    $raw     = $_POST['buttons'] ?? '[]';
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

        if ($label === '' && $url === '') {
            continue;
        }

        // A button with text but no link would be silently dropped, leaving the
        // admin thinking it was sent.
        if ($label === '' || $url === '') {
            throw new InvalidArgumentException(
                'Button "' . ($label !== '' ? $label : $url) . '" needs both a label and a link.'
            );
        }

        // Telegram inline URL buttons accept only http(s) and tg:// — a
        // mailto: link fails the whole message with BUTTON_URL_INVALID.
        if (!preg_match('~^(https?://|tg://)~i', $url)) {
            throw new InvalidArgumentException(
                'Button "' . $label . '" has an unsupported link. Use an http(s):// or tg:// URL.'
            );
        }

        $style     = (string) ($row['style'] ?? 'primary');
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

/**
 * Validate and store an uploaded media file.
 *
 * @return array{path: string|null, error: string}
 */
function store_broadcast_media(string $mediaType): array
{
    $file = $_FILES['media_' . $mediaType] ?? null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => ''];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'The upload failed (code ' . (int) $file['error'] . '). Please try again.'];
    }

    $limits = ['image' => 5, 'video' => 50, 'file' => 20];
    $maxMb  = $limits[$mediaType] ?? 20;

    if ((int) $file['size'] > $maxMb * 1024 * 1024) {
        return ['path' => null, 'error' => 'That file is larger than the ' . $maxMb . 'MB limit.'];
    }

    $allowed = [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
        'video' => ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'],
        'file'  => [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/zip' => 'zip',
        ],
    ];

    // finfo reports legacy Office files as application/CDFV2 and modern ones as
    // application/zip, so the original extension is a necessary fallback.
    $extensionMap = [
        'image' => ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'],
        'video' => ['mp4' => 'mp4', 'mov' => 'mov'],
        'file'  => [
            'pdf' => 'pdf', 'doc' => 'doc', 'docx' => 'docx',
            'xls' => 'xls', 'xlsx' => 'xlsx', 'zip' => 'zip',
        ],
    ];

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $ext  = $allowed[$mediaType][$mime] ?? null;

    if ($ext === null) {
        $originalExt = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext         = $extensionMap[$mediaType][$originalExt] ?? null;
    }

    if ($ext === null) {
        return ['path' => null, 'error' => 'That file type is not allowed for a ' . $mediaType . ' broadcast.'];
    }

    $dir  = broadcast_upload_dir();
    $name = 'bc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['path' => null, 'error' => 'Could not save the uploaded file. Check the uploads directory permissions.'];
    }

    return ['path' => $dest, 'error' => ''];
}

$mediaType = (string) ($_POST['media_type'] ?? 'none');
$mediaType = in_array($mediaType, ['none', 'image', 'video', 'file'], true) ? $mediaType : 'none';

$audience = (string) ($_POST['audience'] ?? 'all');
$audience = in_array($audience, ['all', 'active', 'inactive', 'custom'], true) ? $audience : 'all';

// When post_max_size is exceeded PHP empties both $_POST and $_FILES, which
// would otherwise surface as a misleading "session expired" message.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && $_POST === []) {
    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => 'That upload is larger than the server accepts ('
            . ini_get('post_max_size') . '). Please use a smaller file.',
    ];
    redirect('adm/broadcast.php');
}

$messageText = trim((string) ($_POST['message_text'] ?? ''));

try {
    $buttons = collect_posted_buttons();
} catch (InvalidArgumentException $e) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    redirect('adm/broadcast.php');
}

if ($messageText === '' && $buttons === []) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Add a message or at least one button before sending.'];
    redirect('adm/broadcast.php');
}

// sendMessage rejects an empty text, so buttons alone cannot carry a text post.
if ($mediaType === 'none' && $messageText === '') {
    $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Add a message before sending a text broadcast.'];
    redirect('adm/broadcast.php');
}

// Telegram caps media captions at 1024 characters, not 4096.
$textLimit = $mediaType === 'none' ? 4096 : 1024;

if (mb_strlen($messageText) > $textLimit) {
    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => $mediaType === 'none'
            ? 'Message is too long. Telegram allows 4096 characters.'
            : 'Caption is too long. Telegram allows 1024 characters on a ' . $mediaType . ' broadcast.',
    ];
    redirect('adm/broadcast.php');
}

$media = ['path' => null, 'error' => ''];

if ($mediaType !== 'none') {
    $media = store_broadcast_media($mediaType);

    if ($media['error'] !== '') {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => $media['error']];
        redirect('adm/broadcast.php');
    }

    if ($media['path'] === null) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'message' => 'Choose a file to upload for this ' . $mediaType . ' broadcast.'];
        redirect('adm/broadcast.php');
    }
}

$firstLine = trim((string) strtok($messageText, "\n"));
$title     = $firstLine !== '' ? mb_substr($firstLine, 0, 190) : 'Broadcast ' . date('d M Y, H:i');

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO `broadcasts`
            (`title`, `message_text`, `parse_mode`, `media_type`, `media_path`, `audience`, `status`, `created_by`)
         VALUES (:title, :message_text, :parse_mode, :media_type, :media_path, :audience, :status, :created_by)'
    );
    $stmt->execute([
        ':title'        => $title,
        ':message_text' => $messageText !== '' ? tg_markdown_to_html($messageText) : null,
        ':parse_mode'   => 'HTML',
        ':media_type'   => $mediaType,
        ':media_path'   => $media['path'],
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
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Broadcast save failed: ' . $e->getMessage());
    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => APP_DEBUG ? $e->getMessage() : 'Could not save the broadcast. Please try again.',
    ];
    redirect('adm/broadcast.php');
}

// The row is committed, so a delivery failure can no longer lose the draft.
try {
    $delivery = broadcast_deliver($broadcastId);
} catch (Throwable $e) {
    error_log('Broadcast delivery failed: ' . $e->getMessage());
    $delivery = [
        'status'    => 'failed',
        'total'     => 0,
        'delivered' => 0,
        'failed'    => 0,
        'error'     => APP_DEBUG ? $e->getMessage() : 'Delivery failed unexpectedly.',
    ];
}

try {
    db()->prepare(
        'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
         VALUES (:admin_id, :action, :entity_type, :entity_id, :meta, :ip)'
    )->execute([
        ':admin_id'    => (int) $admin['id'],
        ':action'      => $delivery['status'] === 'sent' ? 'broadcast.send' : 'broadcast.send_failed',
        ':entity_type' => 'broadcast',
        ':entity_id'   => $broadcastId,
        ':meta'        => json_encode([
            'total'     => $delivery['total'],
            'delivered' => $delivery['delivered'],
            'failed'    => $delivery['failed'],
            'error'     => $delivery['error'],
        ], JSON_UNESCAPED_UNICODE),
        ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('Broadcast audit failed: ' . $e->getMessage());
}

if ($delivery['status'] === 'sent') {
    $message = 'Broadcast #' . $broadcastId . ' sent to ' . number_format($delivery['delivered']) . ' of '
        . number_format($delivery['total']) . ' user' . ($delivery['total'] === 1 ? '' : 's') . '.';

    if ($delivery['failed'] > 0) {
        $message .= ' ' . number_format($delivery['failed']) . ' failed.';
    }

    $_SESSION['admin_flash'] = ['type' => 'success', 'message' => $message];
} else {
    $reason = $delivery['error'] !== '' ? $delivery['error'] : 'Telegram rejected every message.';

    $_SESSION['admin_flash'] = [
        'type'    => 'error',
        'message' => 'Broadcast #' . $broadcastId . ' could not be delivered. ' . $reason,
    ];
}

redirect('adm/broadcast.php');
