<?php
/**
 * =====================================================================
 *  config.php — Refer & Earn
 *  Central configuration, database connection and shared helpers.
 *
 *  Include from any page:
 *      require_once __DIR__ . '/config.php';        // root pages
 *      require_once __DIR__ . '/../config.php';     // adm/ pages
 *      require_once __DIR__ . '/../../config.php';  // adm/auth/ pages
 *
 *  Database is created by importing schema.sql:
 *      mysql -u root -p < schema.sql
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 |  1. Environment
 * ------------------------------------------------------------------- */

define('APP_NAME', 'Refer & Earn');

/**
 * Read an environment variable, falling back to a default.
 *
 * Works with real env vars (Apache SetEnv, Docker, systemd, cPanel) and with
 * a `.env` file placed next to this file.
 */
function env(string $key, ?string $default = null): ?string
{
    static $fileVars = null;

    $value = getenv($key);

    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if ($fileVars === null) {
        $fileVars = [];

        $path = __DIR__ . '/.env';

        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);

                if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && $v[0] === substr($v, -1)) {
                    $v = substr($v, 1, -1);
                }

                $fileVars[$k] = $v;
            }
        }
    }

    return $fileVars[$key] ?? $default;
}

/**
 * Detect the current request's scheme + host, honouring reverse proxies.
 *
 * Shared hosts (cPanel, Cloudflare, Render, Railway, nginx→PHP-FPM) terminate
 * TLS at the edge, so $_SERVER['HTTPS'] is empty even though the visitor is on
 * https. Without this, every generated link would silently downgrade to http.
 */
function detect_request_origin(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

    // Prefer the proxy's forwarded host when the direct host is missing.
    if ($host === '' && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    }

    if ($host === '') {
        $host = 'localhost';
    }

    // Strip anything that is not a valid host[:port] to avoid header injection.
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';

    return ($https ? 'https://' : 'http://') . $host;
}

$host    = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = $host === ''
    || preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host) === 1;

// APP_ENV=production forces production mode even on localhost (useful for
// testing the real error handling before deploying).
$forcedEnv = env('APP_ENV');

if ($forcedEnv !== null && $forcedEnv !== '') {
    $isLocal = strtolower($forcedEnv) === 'local';
}

define('APP_ENV', $isLocal ? 'local' : 'production');
define('APP_DEBUG', filter_var(env('APP_DEBUG', $isLocal ? '1' : '0'), FILTER_VALIDATE_BOOLEAN));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata') ?? 'Asia/Kolkata');

// Absolute base URL, no trailing slash.
//
// Resolution order:
//   1. APP_URL from .env / real env  — explicit override, wins everywhere.
//   2. The current request's origin  — so the app follows whatever domain it is
//      served from (localhost, a staging host, the live domain) with no edits.
//
// This means you never have to hardcode a domain: deploy to a new host and all
// links, the webhook URL and the Telegram WebApp button follow automatically.
$configuredUrl = rtrim((string) env('APP_URL', ''), '/');

define('APP_URL', $configuredUrl !== '' ? $configuredUrl : detect_request_origin());

// Bare host[:port] of the current request, e.g. "referearn.com".
define('APP_HOST', (string) (parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost'));

// True when APP_URL is a real public address Telegram can reach.
define('APP_URL_IS_PUBLIC', !$isLocal
    && preg_match('/^https:\/\//i', APP_URL) === 1
    && !preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|0\.0\.0\.0)$/i', APP_HOST));

/* ---------------------------------------------------------------------
 |  2. Database
 |
 |  Defaults match a stock XAMPP install. Override them in production with
 |  environment variables or a .env file — never commit real credentials.
 * ------------------------------------------------------------------- */

define('DB_HOST', env('DB_HOST', '127.0.0.1') ?? '127.0.0.1');
define('DB_PORT', env('DB_PORT', '3306') ?? '3306');
define('DB_NAME', env('DB_NAME', 'refer_earn') ?? 'refer_earn');
define('DB_USER', env('DB_USER', 'root') ?? 'root');
define('DB_PASS', env('DB_PASS', '') ?? '');
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 |  3. Session
 * ------------------------------------------------------------------- */

define('SESSION_NAME', env('SESSION_NAME', 'refer_earn_sid') ?? 'refer_earn_sid');
define('SESSION_LIFETIME', (int) (env('SESSION_LIFETIME', '28800') ?? 28800)); // 8 hours

/* ---------------------------------------------------------------------
 |  4. Bootstrap
 * ------------------------------------------------------------------- */

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// In production, log to a file outside the web root when possible.
if (!APP_DEBUG) {
    $logDir = env('LOG_DIR', '');

    if ($logDir !== null && $logDir !== '' && is_dir($logDir) && is_writable($logDir)) {
        ini_set('error_log', rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . 'php-error.log');
    }
}

// Security headers for every response.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // HSTS only makes sense (and is only honoured) over a real HTTPS request.
    $httpsOn = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    if (!$isLocal && $httpsOn) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (PHP_SAPI !== 'cli' && !defined('APP_NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !$isLocal,
    ]);
    session_start();
}

/* ---------------------------------------------------------------------
 |  5. Database connection
 * ------------------------------------------------------------------- */

/**
 * Shared PDO connection (lazily created, one per request).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable.');
    }

    return $pdo;
}

/**
 * True when the database is reachable.
 */
function db_available(): bool
{
    try {
        db();
        return true;
    } catch (Throwable) {
        return false;
    }
}

/* ---------------------------------------------------------------------
 |  6. Settings  (mirrors the `settings` table seeded by schema.sql)
 * ------------------------------------------------------------------- */

/**
 * Fallback values used when the database or the settings table is missing.
 *
 * @return array<string, mixed>
 */
function default_settings(): array
{
    return [
        // Referral
        'referral_reward_t'            => 150.0,
        'min_referrals_withdrawal'     => 5,

        // Withdrawal
        'min_withdrawal_t'             => 2000.0,
        'max_withdrawal_t'             => 50000.0,
        'auto_approve_withdrawals'     => false,
        // Optional external payout hook; blank means "not configured".
        'withdrawal_api_url'           => '',
        'coin_to_inr_rate'             => 0.10,
        'withdrawal_processing_hours'  => 24,

        // Bot
        'bot_token'                    => '',
        'bot_web_app_url'              => '',
        'bot_username'                 => '',
        'webhook_secret'               => '',

        // Bonus
        'signup_bonus_t'               => 50.0,
        'enable_signup_bonus'          => true,

        // Platform
        'allow_registration'           => true,
        'withdrawals_enabled'          => true,

        // Channel gate
        'require_channel_join'         => false,
        'require_bot_start'            => true,
        'channel_gate_message'         => 'Join our channels to unlock Refer & Earn and start earning rewards.',
        'channel_gate_strict'          => false,
    ];
}

/**
 * Cast a raw settings value to its declared type.
 */
function cast_setting(mixed $value, string $type): mixed
{
    if ($value === null) {
        return null;
    }

    return match ($type) {
        'integer' => (int) $value,
        'decimal' => (float) $value,
        'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
        'json'    => json_decode((string) $value, true) ?? [],
        default   => (string) $value,
    };
}

/**
 * All settings, merged over the defaults. Cached per request.
 *
 * @return array<string, mixed>
 */
function settings(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = default_settings();

    try {
        $rows = db()
            ->query('SELECT `key`, `value`, `data_type` FROM `settings`')
            ->fetchAll();

        foreach ($rows as $row) {
            $cache[$row['key']] = cast_setting($row['value'], (string) $row['data_type']);
        }
    } catch (Throwable $e) {
        error_log('Settings load failed, using defaults: ' . $e->getMessage());
    }

    return $cache;
}

/**
 * Read a single setting.
 */
function setting(string $key, mixed $default = null): mixed
{
    $all = settings();

    return array_key_exists($key, $all) ? $all[$key] : $default;
}

/**
 * The URL Telegram should open for this WebApp.
 *
 * A stored value is only honoured when it points at the domain the app is
 * currently served from. Anything else (a stale localhost URL left over from
 * development, or a placeholder) is ignored so the button always follows the
 * live domain instead of sending users to a dead host.
 */
function web_app_url(): string
{
    $stored = trim((string) setting('bot_web_app_url', ''));

    if ($stored !== '' && preg_match('#^https?://#i', $stored) === 1) {
        $storedHost = strtolower((string) (parse_url($stored, PHP_URL_HOST) ?? ''));

        if ($storedHost !== '' && $storedHost === strtolower(APP_HOST)) {
            return $stored;
        }
    }

    return APP_URL . '/index.php';
}

/**
 * The endpoint withdrawals are forwarded to, or '' when not configured.
 *
 * This is an *external* integration hook: a blank value simply means "no
 * payout service wired up yet". It is never derived from the current domain,
 * because pointing it at this site would imply an endpoint that does not
 * exist.
 */
function withdrawal_api_url(): string
{
    $stored = trim((string) setting('withdrawal_api_url', ''));

    return preg_match('#^https?://#i', $stored) === 1 ? $stored : '';
}

/**
 * How many withdrawals a user has already requested.
 *
 * Cancelled and rejected requests are ignored: neither ever paid out, so they
 * must not consume the user's one-time first-withdrawal allowance.
 */
function user_withdrawal_count(int $userId): int
{
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM `withdrawals`
              WHERE `user_id` = :uid AND `status` NOT IN ('cancelled', 'rejected')"
        );
        $stmt->execute([':uid' => $userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('user_withdrawal_count failed: ' . $e->getMessage());

        return 0;
    }
}

/**
 * True when the user has never requested a withdrawal.
 */
function is_first_withdrawal(int $userId): bool
{
    return user_withdrawal_count($userId) === 0;
}

/**
 * The cap applied to a user's first withdrawal.
 *
 * The first payout is deliberately small: it is limited to the configured
 * minimum withdrawal amount, so a brand-new account can cash out once without
 * the platform risking a large payout on an unproven account. Every later
 * withdrawal is only bounded by `max_withdrawal_t`.
 */
function first_withdrawal_max_t(): float
{
    return (float) setting('min_withdrawal_t', 2000);
}

/**
 * Forward a payout to the configured withdrawal API.
 *
 * The endpoint may contain `{amt}`, `{upi}` and `{txid}` placeholders; they are
 * substituted before the request. The same values are also sent as a JSON body
 * so an endpoint that reads the payload instead of the query string works too.
 *
 * @return array{ok: bool, transaction_id: ?string, response: mixed, error: ?string}
 */
function withdrawal_api_payout(float $amountT, string $upiId, string $txid = '', int $timeout = 20): array
{
    $endpoint = withdrawal_api_url();

    if ($endpoint === '') {
        return [
            'ok'             => false,
            'transaction_id' => null,
            'response'       => null,
            'error'          => 'Withdrawal API is not configured.',
        ];
    }

    $url = strtr($endpoint, [
        '{amt}'  => (string) $amountT,
        '{upi}'  => $upiId,
        '{txid}' => $txid,
    ]);

    $payload = json_encode([
        'amt'  => $amountT,
        'upi'  => $upiId,
        'txid' => $txid,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errNo  = curl_errno($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $errNo !== 0) {
        return [
            'ok'             => false,
            'transaction_id' => null,
            'response'       => null,
            'error'          => 'cURL error: ' . ($error !== '' ? $error : (string) $errNo),
        ];
    }

    $decoded  = json_decode((string) $body, true);
    $response = is_array($decoded) ? $decoded : ['raw' => substr((string) $body, 0, 1000)];

    $ok     = $status >= 200 && $status < 300;
    $reason = null;

    // An endpoint can answer 200 and still report a failed payout.
    if ($ok && is_array($decoded)) {
        if (array_key_exists('success', $decoded) && $decoded['success'] === false) {
            $ok     = false;
            $reason = 'API reported success=false';
        }

        $state = strtolower((string) ($decoded['status'] ?? ''));

        if (in_array($state, ['failed', 'failure', 'error', 'rejected', 'declined'], true)) {
            $ok     = false;
            $reason = 'API reported status=' . $state;
        }

        if (!$ok) {
            foreach (['message', 'error', 'reason', 'detail'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
                    $reason .= ': ' . (string) $decoded[$key];
                    break;
                }
            }
        }
    }

    if (!$ok && $reason === null) {
        $reason = 'Payout API returned HTTP ' . $status;
    }

    $transactionId = null;

    if (is_array($decoded)) {
        foreach (['transaction_id', 'txid', 'txn_id', 'utr', 'reference', 'reference_id', 'id'] as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
                $transactionId = (string) $decoded[$key];
                break;
            }
        }
    }

    return [
        'ok'             => $ok,
        'transaction_id' => $transactionId,
        'response'       => $response,
        'error'          => $reason,
    ];
}

/**
 * Pay out a first withdrawal immediately through the configured API.
 *
 * Runs after the request has been committed so a slow payout endpoint never
 * holds the wallet row lock. On success the withdrawal is marked completed;
 * on failure it stays pending for the admin queue and the API answer is kept
 * for debugging.
 *
 * @return array{status: string, message: string}
 */
function process_first_withdrawal_payout(int $withdrawalId, float $amountT, string $upiId): array
{
    if (withdrawal_api_url() === '') {
        return [
            'status'  => 'pending',
            'message' => 'Your first withdrawal of ' . t_format($amountT) . ' is queued for review.',
        ];
    }

    $result = withdrawal_api_payout($amountT, $upiId, 'WD' . $withdrawalId);
    $pdo    = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT `status` FROM `withdrawals` WHERE `id` = :id FOR UPDATE');
        $stmt->execute([':id' => $withdrawalId]);
        $current = $stmt->fetchColumn();

        if ($current === false || $current !== 'pending') {
            $pdo->rollBack();

            return [
                'status'  => (string) $current,
                'message' => 'Your first withdrawal of ' . t_format($amountT) . ' is already being processed.',
            ];
        }

        $newStatus = $result['ok'] ? 'completed' : 'pending';

        $update = $pdo->prepare(
            'UPDATE `withdrawals`
                SET `status` = :status,
                    `transaction_id` = :txid,
                    `api_response` = :response,
                    `admin_note` = :note,
                    `processed_at` = :processed
              WHERE `id` = :id'
        );
        $update->execute([
            ':status'    => $newStatus,
            ':txid'      => $result['transaction_id'],
            ':response'  => json_encode($result['response'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':note'      => $result['ok'] ? null : mb_substr('Auto-payout failed: ' . (string) $result['error'], 0, 500),
            ':processed' => $result['ok'] ? date('Y-m-d H:i:s') : null,
            ':id'        => $withdrawalId,
        ]);

        if ($result['ok']) {
            $history = $pdo->prepare(
                'INSERT INTO `withdrawal_status_history`
                     (`withdrawal_id`, `from_status`, `to_status`, `note`, `admin_id`)
                 VALUES (:wid, :from, :to, :note, NULL)'
            );
            $history->execute([
                ':wid'  => $withdrawalId,
                ':from' => 'pending',
                ':to'   => 'completed',
                ':note' => 'Auto-paid via withdrawal API'
                    . ($result['transaction_id'] !== null ? ' (txid ' . $result['transaction_id'] . ')' : ''),
            ]);
        }

        $audit = $pdo->prepare(
            'INSERT INTO `audit_logs` (`admin_id`, `action`, `entity_type`, `entity_id`, `meta`, `ip_address`)
             VALUES (NULL, :action, :entity_type, :entity_id, :meta, :ip)'
        );
        $audit->execute([
            ':action'      => $result['ok'] ? 'withdrawal.auto_payout' : 'withdrawal.auto_payout_failed',
            ':entity_type' => 'withdrawal',
            ':entity_id'   => $withdrawalId,
            ':meta'        => json_encode([
                'amount'         => $amountT,
                'transaction_id' => $result['transaction_id'],
                'error'          => $result['error'],
            ]),
            ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('process_first_withdrawal_payout failed: ' . $e->getMessage());

        return [
            'status'  => 'pending',
            'message' => 'Your first withdrawal of ' . t_format($amountT) . ' is queued for review.',
        ];
    }

    if ($result['ok']) {
        return [
            'status'  => 'completed',
            'message' => 'Your first withdrawal of ' . t_format($amountT) . ' was paid out automatically.',
        ];
    }

    error_log('First withdrawal #' . $withdrawalId . ' auto-payout failed: ' . (string) $result['error']);

    return [
        'status'  => 'pending',
        'message' => 'Your first withdrawal of ' . t_format($amountT) . ' is queued for review.',
    ];
}

/* ---------------------------------------------------------------------
 |  7. Helpers
 * ------------------------------------------------------------------- */

/**
 * Escape output for HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect and stop.
 *
 * In local development the `tg_id` identity fallback is carried across the
 * redirect so the next page can still resolve the user.
 */
function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) === 1 ? $path : APP_URL . '/' . ltrim($path, '/');

    if (APP_ENV === 'local' && !empty($_GET['tg_id']) && !str_contains($target, 'tg_id=')) {
        $target .= (str_contains($target, '?') ? '&' : '?') . 'tg_id=' . rawurlencode((string) $_GET['tg_id']);
    }

    header('Location: ' . $target);
    exit;
}

/**
 * Format a T-coin amount, e.g. 2000 -> "2,000 T".
 */
function t_format(float|int|string $amount, bool $withSuffix = true): string
{
    $formatted = number_format((float) $amount, 0, '.', ',');

    return $withSuffix ? $formatted . ' T' : $formatted;
}

/**
 * Convert T coins to ₹ using the configured rate.
 */
function t_to_inr(float|int|string $amount): float
{
    return round((float) $amount * (float) setting('coin_to_inr_rate', 0.10), 2);
}

/**
 * Format an amount as Indian rupees, e.g. "₹ 2,000.00".
 */
function inr_format(float|int|string $amount): string
{
    return '₹ ' . number_format((float) $amount, 2, '.', ',');
}

/**
 * Current CSRF token for this session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hidden CSRF input for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * True when an admin is signed in.
 */
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * The signed-in admin row, or null.
 *
 * @return array<string, mixed>|null
 */
function current_admin(): ?array
{
    static $admin = null;

    if ($admin !== null) {
        return $admin;
    }

    if (!is_admin_logged_in()) {
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT `id`, `name`, `username`, `email`, `role`, `avatar_url`, `last_login_at`
               FROM `admins`
              WHERE `id` = :id AND `is_active` = 1
              LIMIT 1'
        );
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('current_admin() failed: ' . $e->getMessage());
        return null;
    }

    if ($admin === null) {
        unset($_SESSION['admin_id']);
    }

    return $admin;
}

/**
 * Guard for admin-only pages.
 */
function require_admin(): array
{
    $admin = current_admin();

    if ($admin === null) {
        redirect('adm/auth/index.php');
    }

    return $admin;
}

/* ---------------------------------------------------------------------
 |  8. Telegram Bot API
 * ------------------------------------------------------------------- */

/**
 * The configured bot token, or '' when not set.
 */
function bot_token(): string
{
    return trim((string) setting('bot_token', ''));
}

/**
 * Base URL for the Telegram Bot API.
 *
 * Overridable via TELEGRAM_API_BASE so the delivery path can be exercised
 * against a local mock without touching production code.
 */
function telegram_api_base(): string
{
    $base = env('TELEGRAM_API_BASE', 'https://api.telegram.org');

    return rtrim($base !== null && $base !== '' ? $base : 'https://api.telegram.org', '/');
}

/**
 * Call a Telegram Bot API method.
 *
 * @param  array<string, mixed> $params
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_api(string $method, array $params = [], int $timeout = 15): array
{
    $token = bot_token();

    if ($token === '') {
        return ['ok' => false, 'error' => 'Bot token is not configured.'];
    }

    $url = telegram_api_base() . '/bot' . $token . '/' . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body  = curl_exec($ch);
    $errNo = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $errNo !== 0) {
        return ['ok' => false, 'error' => 'cURL error: ' . ($error !== '' ? $error : (string) $errNo)];
    }

    $decoded = json_decode((string) $body, true);

    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid JSON from Telegram: ' . substr((string) $body, 0, 200)];
    }

    return $decoded;
}

/**
 * Register the webhook URL with Telegram.
 *
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_set_webhook(string $url, ?string $secret = null): array
{
    $params = [
        'url'                  => $url,
        'max_connections'      => 40,
        'allowed_updates'      => json_encode(['message', 'callback_query', 'edited_message']),
        'drop_pending_updates' => 'false',
    ];

    if ($secret !== null && $secret !== '') {
        $params['secret_token'] = $secret;
    }

    return telegram_api('setWebhook', $params);
}

/**
 * Current webhook status as reported by Telegram.
 *
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_get_webhook_info(): array
{
    return telegram_api('getWebhookInfo');
}

/**
 * Remove the registered webhook.
 *
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_delete_webhook(): array
{
    return telegram_api('deleteWebhook', ['drop_pending_updates' => 'false']);
}

/**
 * Bot identity (username, id) from getMe. Cached per request.
 *
 * @return array<string, mixed>|null
 */
function telegram_me(bool $refresh = false): ?array
{
    static $me = null;

    if ($me !== null && !$refresh) {
        return $me;
    }

    $response = telegram_api('getMe');

    if (($response['ok'] ?? false) !== true || !is_array($response['result'] ?? null)) {
        return null;
    }

    $me = $response['result'];

    return $me;
}

/**
 * The bot @username without the leading @, or '' when unknown.
 */
function bot_username(bool $refresh = false): string
{
    $stored = trim((string) setting('bot_username', ''));

    if ($stored !== '' && !$refresh) {
        return $stored;
    }

    $me = telegram_me($refresh);

    if ($me === null || empty($me['username'])) {
        return $stored;
    }

    $username = (string) $me['username'];

    try {
        $stmt = db()->prepare(
            'INSERT INTO `settings` (`key`, `value`, `data_type`, `group_name`, `label`)
             VALUES (:k, :v, :t, :g, :l)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([
            ':k' => 'bot_username',
            ':v' => $username,
            ':t' => 'string',
            ':g' => 'bot',
            ':l' => 'Telegram Bot Username (auto-filled)',
        ]);
        settings(true);
    } catch (Throwable $e) {
        error_log('bot_username() persist failed: ' . $e->getMessage());
    }

    return $username;
}

/**
 * Send a plain text message to a Telegram chat.
 *
 * @param  array<string, mixed>|null $replyMarkup
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_send_message(int|string $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
{
    $params = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'disable_web_page_preview' => 'true',
    ];

    if ($parseMode !== 'none' && $parseMode !== '') {
        $params['parse_mode'] = $parseMode;
    }

    if ($replyMarkup !== null) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    return telegram_api('sendMessage', $params);
}

/**
 * Send a photo, video or document to a Telegram chat.
 *
 * The file is uploaded from disk as multipart/form-data so the admin never
 * has to host it at a public URL.
 *
 * @param  array<string, mixed>|null $replyMarkup
 * @return array{ok: bool, result?: mixed, description?: string, error?: string}
 */
function telegram_send_media(int|string $chatId, string $mediaType, string $filePath, string $caption = '', ?array $replyMarkup = null, string $parseMode = 'HTML'): array
{
    $method = match ($mediaType) {
        'image' => 'sendPhoto',
        'video' => 'sendVideo',
        'file'  => 'sendDocument',
        default => null,
    };

    if ($method === null) {
        return ['ok' => false, 'error' => 'Unsupported media type: ' . $mediaType];
    }

    $token = bot_token();

    if ($token === '') {
        return ['ok' => false, 'error' => 'Bot token is not configured.'];
    }

    if (!is_file($filePath)) {
        return ['ok' => false, 'error' => 'Media file is missing: ' . basename($filePath)];
    }

    $field = match ($mediaType) {
        'image' => 'photo',
        'video' => 'video',
        'file'  => 'document',
    };

    $params = [
        'chat_id'    => (string) $chatId,
        $field       => new CURLFile($filePath),
    ];

    if ($parseMode !== 'none' && $parseMode !== '') {
        $params['parse_mode'] = $parseMode;
    }

    if ($caption !== '') {
        $params['caption'] = $caption;
    }

    if ($replyMarkup !== null) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }

    $ch = curl_init(telegram_api_base() . '/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body  = curl_exec($ch);
    $errNo = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $errNo !== 0) {
        return ['ok' => false, 'error' => 'cURL error: ' . ($error !== '' ? $error : (string) $errNo)];
    }

    $decoded = json_decode((string) $body, true);

    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid JSON from Telegram: ' . substr((string) $body, 0, 200)];
    }

    return $decoded;
}

/* ---------------------------------------------------------------------
 |  9. Telegram WebApp identity
 * ------------------------------------------------------------------- */

/**
 * Validate Telegram WebApp `initData` and return the embedded user array.
 *
 * @return array<string, mixed>|null
 */
function telegram_validate_init_data(string $initData): ?array
{
    $token = bot_token();

    if ($token === '' || $initData === '') {
        return null;
    }

    parse_str($initData, $fields);

    if (!isset($fields['hash']) || !is_string($fields['hash'])) {
        return null;
    }

    $hash = $fields['hash'];
    unset($fields['hash']);

    ksort($fields);

    $pairs = [];
    foreach ($fields as $key => $value) {
        $pairs[] = $key . '=' . (is_array($value) ? '' : (string) $value);
    }

    $dataCheckString = implode("\n", $pairs);
    $secretKey       = hash_hmac('sha256', $token, 'WebAppData', true);
    $expected        = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($expected, $hash)) {
        return null;
    }

    // Reject stale payloads (older than 24h).
    if (isset($fields['auth_date']) && is_numeric($fields['auth_date'])) {
        if (time() - (int) $fields['auth_date'] > 86400) {
            return null;
        }
    }

    if (!isset($fields['user']) || !is_string($fields['user'])) {
        return null;
    }

    $user = json_decode($fields['user'], true);

    return is_array($user) ? $user : null;
}

/**
 * Resolve the Telegram identity for this request.
 *
 * Order of precedence:
 *   1. `initData` POST field (WebApp form submissions)
 *   2. `X-Telegram-Init-Data` header
 *   3. `tgWebAppData` query string (WebApp boot)
 *   4. `tg_init_data` session value — set by webapp-auth.php after the
 *      client-side handshake, because Telegram delivers initData in the URL
 *      fragment and fragments are never sent to the server.
 *   5. `tg_id` query string — development fallback only
 *
 * @return array<string, mixed>|null
 */
function telegram_identity(): ?array
{
    static $identity = null;
    static $resolved = false;

    if ($resolved) {
        return $identity;
    }

    $resolved = true;

    $initData = '';

    if (!empty($_POST['initData']) && is_string($_POST['initData'])) {
        $initData = $_POST['initData'];
    } elseif (!empty($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'])) {
        $initData = (string) $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'];
    } elseif (!empty($_GET['tgWebAppData']) && is_string($_GET['tgWebAppData'])) {
        $initData = $_GET['tgWebAppData'];
    } elseif (!empty($_SESSION['tg_init_data']) && is_string($_SESSION['tg_init_data'])) {
        $initData = $_SESSION['tg_init_data'];
    }

    if ($initData !== '') {
        $user = telegram_validate_init_data($initData);

        if ($user !== null && isset($user['id'])) {
            $identity = $user;
            return $identity;
        }

        // Drop a stale or invalid session payload so the client can retry.
        unset($_SESSION['tg_init_data']);
    }

    // Development fallback: ?tg_id=900000001
    if (APP_ENV === 'local' && !empty($_GET['tg_id']) && is_numeric($_GET['tg_id'])) {
        $identity = ['id' => (int) $_GET['tg_id'], '_dev' => true];
    }

    return $identity;
}

/**
 * The signed-in Telegram user row, or null.
 *
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    static $user = null;
    static $resolved = false;

    if ($resolved) {
        return $user;
    }

    $resolved = true;

    $identity = telegram_identity();

    if ($identity === null || empty($identity['id'])) {
        return null;
    }

    $telegramId = (int) $identity['id'];

    try {
        $stmt = db()->prepare('SELECT * FROM `users` WHERE `telegram_id` = :tid LIMIT 1');
        $stmt->execute([':tid' => $telegramId]);
        $row = $stmt->fetch();

        if ($row === false) {
            $row = create_user_from_telegram($identity);
        }

        if ($row === false || $row === null) {
            return null;
        }

        if (($row['status'] ?? 'active') === 'blocked') {
            return null;
        }

        // Refresh the profile snapshot from Telegram on every request.
        $updates = [];
        $params  = [];

        if (empty($identity['_dev'])) {
            foreach (['username' => 'username', 'first_name' => 'first_name', 'last_name' => 'last_name'] as $col => $key) {
                $incoming = isset($identity[$key]) ? (string) $identity[$key] : null;
                if ($incoming !== null && $incoming !== (string) ($row[$col] ?? '')) {
                    $updates[]        = "`$col` = :$col";
                    $params[":$col"]  = $incoming;
                    $row[$col]        = $incoming;
                }
            }
        }

        $updates[] = '`last_active_at` = NOW()';
        $params[':id'] = $row['id'];

        $stmt = db()->prepare('UPDATE `users` SET ' . implode(', ', $updates) . ' WHERE `id` = :id');
        $stmt->execute($params);

        $user = $row;
    } catch (Throwable $e) {
        error_log('current_user() failed: ' . $e->getMessage());
        return null;
    }

    return $user;
}

/**
 * Guard for user-facing pages. Renders a friendly "open in Telegram" screen
 * when the request carries no Telegram identity.
 */
function require_user(): array
{
    $user = current_user();

    if ($user === null) {
        render_telegram_gate();
    }

    return $user;
}

/* ---------------------------------------------------------------------
 |  9b. Onboarding gate — bot start + required channel membership
 * ------------------------------------------------------------------- */

/**
 * Active channels the user must join before the mini app unlocks.
 *
 * @return list<array<string, mixed>>
 */
function required_channels(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = [];

    try {
        $cache = db()
            ->query('SELECT * FROM `required_channels` WHERE `is_active` = 1 ORDER BY `sort_order`, `id`')
            ->fetchAll();
    } catch (Throwable $e) {
        error_log('required_channels() failed: ' . $e->getMessage());
    }

    return $cache;
}

/**
 * Is the channel gate switched on and actually configured?
 */
function channel_gate_enabled(): bool
{
    return (bool) setting('require_channel_join', false) && required_channels() !== [];
}

/**
 * Ask Telegram whether the user is a member of a chat.
 *
 * Returns true / false, or null when the answer is unknown (no bot token,
 * bot not an admin in the channel, network failure). Unknown is treated as
 * "allow" so a misconfiguration never locks everybody out.
 */
function telegram_chat_member(int|string $chatId, int $telegramId): ?bool
{
    if (bot_token() === '' || $telegramId === 0) {
        return null;
    }

    $response = telegram_api('getChatMember', [
        'chat_id' => $chatId,
        'user_id' => $telegramId,
    ]);

    if (($response['ok'] ?? false) !== true) {
        return null;
    }

    $status = (string) ($response['result']['status'] ?? '');

    return in_array($status, ['creator', 'administrator', 'member'], true);
}

/**
 * Check every required channel for this user.
 *
 * @param  array<string, mixed> $user
 * @return array{ok: bool, missing: list<array<string, mixed>>, unknown: bool}
 */
function channel_membership_status(array $user): array
{
    $channels = required_channels();

    if ($channels === []) {
        return ['ok' => true, 'missing' => [], 'unknown' => false];
    }

    $telegramId = (int) ($user['telegram_id'] ?? 0);
    $missing    = [];
    $unknown    = false;

    foreach ($channels as $channel) {
        $member = telegram_chat_member((string) $channel['chat_id'], $telegramId);

        if ($member === null) {
            $unknown = true;
            continue;
        }

        if ($member === false) {
            $missing[] = $channel;
        }
    }

    // Fail-open by default: a missing token or a bot that is not a channel
    // admin must never lock every user out. Strict mode flips that.
    if ($unknown && (bool) setting('channel_gate_strict', false)) {
        return ['ok' => false, 'missing' => $channels, 'unknown' => true];
    }

    return [
        'ok'      => $missing === [],
        'missing' => $missing,
        'unknown' => $unknown,
    ];
}

/**
 * Has the user started the bot? (i.e. can the bot message them at all)
 *
 * @param array<string, mixed> $user
 */
function has_started_bot(array $user): bool
{
    return !empty($user['bot_started_at']);
}

/**
 * Everything the onboarding gate needs to decide what to show.
 *
 * @param  array<string, mixed> $user
 * @return array{blocked: bool, reason: string, missing: list<array<string, mixed>>, channels: list<array<string, mixed>>}
 */
function onboarding_state(array $user): array
{
    $channels = required_channels();

    // 1. The bot must be started first — the web app is only reachable from it.
    if ((bool) setting('require_bot_start', true) && !has_started_bot($user)) {
        return [
            'blocked'  => true,
            'reason'   => 'bot_start',
            'missing'  => [],
            'channels' => $channels,
        ];
    }

    // 2. Then every required channel must be joined.
    if (channel_gate_enabled()) {
        $status = channel_membership_status($user);

        // Local preview: ?gate_preview=1 forces the lock screen so the UI can
        // be inspected without a real bot token.
        if (APP_ENV === 'local' && !empty($_GET['gate_preview'])) {
            return [
                'blocked'  => true,
                'reason'   => 'channels',
                'missing'  => $channels,
                'channels' => $channels,
            ];
        }

        if (!$status['ok']) {
            return [
                'blocked'  => true,
                'reason'   => 'channels',
                'missing'  => $status['missing'],
                'channels' => $channels,
            ];
        }

        // Remember the last successful verification.
        if (!$status['unknown']) {
            try {
                $stmt = db()->prepare('UPDATE `users` SET `channels_verified_at` = NOW() WHERE `id` = :id');
                $stmt->execute([':id' => $user['id']]);
            } catch (Throwable $e) {
                error_log('channels_verified_at update failed: ' . $e->getMessage());
            }
        }
    }

    return ['blocked' => false, 'reason' => '', 'missing' => [], 'channels' => $channels];
}

/**
 * Guard for user-facing pages that must sit behind the onboarding gate.
 *
 * @return array<string, mixed>
 */
function require_onboarded_user(): array
{
    $user = require_user();

    // "I've joined — verify" button on the gate overlay.
    if (($_POST['gate_action'] ?? '') === 'verify') {
        $state = onboarding_state($user);

        if (!$state['blocked']) {
            $target = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/index.php';
            $query  = APP_ENV === 'local' && !empty($_GET['tg_id'])
                ? '?tg_id=' . urlencode((string) $_GET['tg_id'])
                : '';

            header('Location: ' . $target . $query);
            exit;
        }

        render_onboarding_gate($user, $state);
    }

    $state = onboarding_state($user);

    if ($state['blocked']) {
        render_onboarding_gate($user, $state);
    }

    // The user is fully onboarded — now it is safe to verify and apply the
    // referral code captured by /start.
    if (!empty($user['pending_referral_code'])) {
        $referralError = consume_pending_referral($user);

        if ($referralError !== null) {
            flash_set('error', $referralError);
        } else {
            flash_set('success', 'Referral confirmed — your inviter has been rewarded.');
        }

        $fresh = current_user();

        if ($fresh !== null) {
            $user = $fresh;
        }
    }

    return $user;
}

/**
 * Full-page overlay shown until the user starts the bot and joins the channels.
 *
 * @param array<string, mixed> $user
 * @param array<string, mixed> $state
 */
function render_onboarding_gate(array $user, array $state): never
{
    http_response_code(403);

    $bot      = bot_username();
    $botUrl   = $bot !== '' ? 'https://t.me/' . $bot : '';
    $reason   = (string) $state['reason'];
    $missing  = $state['missing'];
    $channels = $state['channels'];
    $message  = (string) setting('channel_gate_message', 'Join our channels to unlock the app.');
    $isDev    = !empty(telegram_identity()['_dev']);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Unlock <?= e(APP_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="min-h-screen bg-[#f4f3fa] flex items-center justify-center p-5">
  <div class="w-full max-w-[400px] bg-white rounded-3xl p-6 shadow-xl">

    <div class="text-center">
      <div class="w-16 h-16 mx-auto rounded-full <?= $reason === 'bot_start' ? 'bg-[#e7f3ff] text-[#0088cc]' : 'bg-[#f2edff] text-[#6d37f7]' ?> flex items-center justify-center">
        <i class="fa-<?= $reason === 'bot_start' ? 'brands fa-telegram' : 'solid fa-lock' ?> text-3xl"></i>
      </div>
      <h1 class="mt-4 text-lg font-extrabold text-slate-900">
        <?= $reason === 'bot_start' ? 'Start the bot first' : 'Join to unlock' ?>
      </h1>
      <p class="mt-2 text-sm text-slate-500 leading-relaxed"><?= e($message) ?></p>
    </div>

    <?php if ($reason === 'bot_start'): ?>
      <div class="mt-5 rounded-2xl bg-slate-50 border border-slate-100 p-4">
        <p class="text-xs font-semibold text-slate-700 mb-2">How to unlock</p>
        <ol class="text-xs text-slate-500 space-y-1.5 list-decimal list-inside font-medium">
          <li>Open the bot in Telegram.</li>
          <li>Send <span class="font-mono font-bold text-slate-700">/start</span>.</li>
          <li>Tap the button the bot replies with.</li>
        </ol>
      </div>
      <?php if ($botUrl !== ''): ?>
        <a href="<?= e($botUrl) ?>" class="mt-4 inline-flex items-center justify-center gap-2 w-full bg-[#0088cc] text-white font-bold text-sm py-3.5 rounded-2xl active:scale-[0.98] transition">
          <i class="fa-brands fa-telegram"></i>
          <span>Open @<?= e($bot) ?></span>
        </a>
      <?php else: ?>
        <p class="mt-4 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
          Bot token is not configured yet. Set it in Admin → Settings.
        </p>
      <?php endif; ?>
    <?php else: ?>
      <div class="mt-5 space-y-2.5">
        <?php foreach ($channels as $channel): ?>
          <?php $joined = !in_array($channel, $missing, true); ?>
          <a href="<?= e((string) ($channel['invite_url'] ?: 'https://t.me/' . ltrim((string) $channel['chat_id'], '@'))) ?>"
             target="_blank" rel="noopener"
             class="flex items-center gap-3 rounded-2xl border px-3.5 py-3 transition <?= $joined ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50 hover:bg-slate-100' ?>">
            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 <?= $joined ? 'bg-emerald-100 text-emerald-600' : 'bg-[#e7f3ff] text-[#0088cc]' ?>">
              <i class="fa-<?= $joined ? 'solid fa-check' : 'brands fa-telegram' ?> text-sm"></i>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-bold text-slate-800 truncate"><?= e((string) $channel['title']) ?></p>
              <p class="text-[11px] font-medium text-slate-400 truncate"><?= e((string) $channel['chat_id']) ?></p>
            </div>
            <span class="text-[11px] font-bold shrink-0 <?= $joined ? 'text-emerald-600' : 'text-[#0088cc]' ?>">
              <?= $joined ? 'Joined' : 'Join' ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>

      <form method="post" action="" class="mt-4">
        <input type="hidden" name="gate_action" value="verify">
        <button type="submit" class="w-full bg-[#5925dc] hover:bg-[#4a1cbe] text-white font-bold text-sm py-3.5 rounded-2xl flex items-center justify-center gap-2 active:scale-[0.98] transition">
          <i class="fa-solid fa-rotate"></i>
          <span>I've joined — verify</span>
        </button>
      </form>
    <?php endif; ?>

    <?php if ($isDev): ?>
      <p class="mt-4 text-[11px] text-slate-400 leading-relaxed text-center">
        Local preview: the gate is bypassed for <code class="font-mono text-slate-600">?tg_id=</code> identities
        once <code class="font-mono text-slate-600">bot_started_at</code> is set on the user row.
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
    <?php
    exit;
}

/**
 * Full-page notice shown when a page is opened outside Telegram.
 */
function render_telegram_gate(): never
{
    http_response_code(403);

    $bot = bot_username();
    $url = $bot !== '' ? 'https://t.me/' . $bot : '';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Open in Telegram — <?= e(APP_NAME) ?></title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script src="<?= e(APP_URL) ?>/asset/webapp-auth.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="min-h-screen bg-[#f4f3fa] flex items-center justify-center p-5">
  <div class="w-full max-w-[380px] bg-white rounded-3xl p-6 text-center shadow-xl">
    <div class="w-16 h-16 mx-auto rounded-full bg-[#e7f3ff] text-[#0088cc] flex items-center justify-center">
      <i class="fa-brands fa-telegram text-3xl"></i>
    </div>
    <h1 class="mt-4 text-lg font-extrabold text-slate-900">Open inside Telegram</h1>
    <p class="mt-2 text-sm text-slate-500 leading-relaxed">
      This mini app only works from the Telegram bot. Launch it from the bot menu to load your wallet.
    </p>
    <?php if ($url !== ''): ?>
      <a href="<?= e($url) ?>" class="mt-5 inline-flex items-center justify-center gap-2 w-full bg-[#0088cc] text-white font-bold text-sm py-3.5 rounded-2xl active:scale-[0.98] transition">
        <i class="fa-brands fa-telegram"></i>
        <span>Open @<?= e($bot) ?></span>
      </a>
    <?php else: ?>
      <p class="mt-5 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">
        Bot token is not configured yet. Set it in Admin → Settings.
      </p>
    <?php endif; ?>
    <?php if (APP_ENV === 'local'): ?>
      <p class="mt-4 text-[11px] text-slate-400 leading-relaxed">
        Local preview: append <code class="font-mono text-slate-600">?tg_id=900000001</code> to the URL.
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
    <?php
    exit;
}

/**
 * Mask a Telegram id the way the UI shows it: *****6789.
 */
function mask_telegram_id(int|string|null $id): string
{
    $digits = preg_replace('/\D+/', '', (string) $id) ?? '';

    if ($digits === '') {
        return '*****0000';
    }

    return '*****' . substr($digits, -4);
}

/**
 * Display name for a user row.
 *
 * @param array<string, mixed> $user
 */
function user_display_name(array $user): string
{
    $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

    if ($name !== '') {
        return $name;
    }

    if (!empty($user['username'])) {
        return '@' . $user['username'];
    }

    return 'User ' . mask_telegram_id($user['telegram_id'] ?? null);
}

/**
 * The user's referral invite link.
 *
 * @param array<string, mixed> $user
 */
function invite_link(array $user): string
{
    $bot  = bot_username();
    $code = (string) ($user['referral_code'] ?? '');

    if ($bot === '') {
        return APP_URL . '/refer-earn.php';
    }

    return 'https://t.me/' . $bot . '?start=' . rawurlencode($code);
}

/**
 * Generate a unique referral code for a new user.
 */
function generate_referral_code(string $seed = ''): string
{
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $seed) ?? '');

    if ($base === '') {
        $base = 'USER';
    }

    $base = substr($base, 0, 8);

    for ($attempt = 0; $attempt < 25; $attempt++) {
        $code = $base . strtoupper(bin2hex(random_bytes(3)));

        $stmt = db()->prepare('SELECT 1 FROM `users` WHERE `referral_code` = :c LIMIT 1');
        $stmt->execute([':c' => $code]);

        if ($stmt->fetchColumn() === false) {
            return $code;
        }
    }

    return 'U' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Create a `users` row from a Telegram identity payload.
 *
 * @param  array<string, mixed> $identity
 * @return array<string, mixed>|false
 */
function create_user_from_telegram(array $identity): array|false
{
    if (!setting('allow_registration', true)) {
        return false;
    }

    $telegramId = (int) ($identity['id'] ?? 0);

    if ($telegramId === 0) {
        return false;
    }

    $username  = isset($identity['username']) ? (string) $identity['username'] : null;
    $firstName = isset($identity['first_name']) ? (string) $identity['first_name'] : null;
    $lastName  = isset($identity['last_name']) ? (string) $identity['last_name'] : null;

    $code = generate_referral_code($username ?? $firstName ?? 'USER');

    $stmt = db()->prepare(
        'INSERT INTO `users`
            (`telegram_id`, `username`, `first_name`, `last_name`, `referral_code`, `last_active_at`)
         VALUES (:tid, :un, :fn, :ln, :rc, NOW())'
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

    return $stmt->fetch() ?: false;
}

/* ---------------------------------------------------------------------
 |  9c. Referral application (shared by the webhook and the web app)
 * ------------------------------------------------------------------- */

/**
 * Link a referee to a referrer and pay the reward.
 *
 * Safe to call repeatedly: returns an error string instead of throwing when
 * the code is unknown, the user referred themselves, or the user was already
 * referred.
 *
 * @param  array<string, mixed> $referee
 * @return string|null Error message, or null on success
 */
function apply_referral(array $referee, string $referralCode): ?string
{
    $referralCode = trim($referralCode);

    if ($referralCode === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM `users` WHERE `referral_code` = :c LIMIT 1');
    $stmt->execute([':c' => $referralCode]);
    $referrer = $stmt->fetch();

    if ($referrer === false) {
        return 'Referral code not found.';
    }

    if ((int) $referrer['id'] === (int) $referee['id']) {
        return 'You cannot refer yourself.';
    }

    if (!empty($referee['referred_by_id'])) {
        return 'You have already been referred.';
    }

    $stmt = db()->prepare('SELECT 1 FROM `referrals` WHERE `referee_id` = :rid LIMIT 1');
    $stmt->execute([':rid' => $referee['id']]);

    if ($stmt->fetchColumn() !== false) {
        return 'You have already been referred.';
    }

    $reward = (float) setting('referral_reward_t', 150);
    $pdo    = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE `users` SET `referred_by_id` = :ref WHERE `id` = :id');
        $stmt->execute([':ref' => $referrer['id'], ':id' => $referee['id']]);

        $stmt = $pdo->prepare(
            'INSERT INTO `referrals` (`referrer_id`, `referee_id`, `reward_t`, `status`, `credited_at`)
             VALUES (:ref, :ree, :reward, :status, NOW())'
        );
        $stmt->execute([
            ':ref'    => $referrer['id'],
            ':ree'    => $referee['id'],
            ':reward' => $reward,
            ':status' => $reward > 0 ? 'credited' : 'pending',
        ]);

        $referralId = (int) $pdo->lastInsertId();

        if ($reward > 0) {
            $refereeName = !empty($referee['username'])
                ? '@' . $referee['username']
                : (trim((string) ($referee['first_name'] ?? '')) ?: 'A friend');

            credit_wallet(
                (int) $referrer['id'],
                'referral_reward',
                'credit',
                $reward,
                'referral',
                $referralId,
                $refereeName . ' joined using your link'
            );

            $stmt = $pdo->prepare('UPDATE `users` SET `referral_count` = `referral_count` + 1 WHERE `id` = :id');
            $stmt->execute([':id' => $referrer['id']]);
        }

        $pdo->commit();

        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('apply_referral failed: ' . $e->getMessage());

        return 'Could not apply the referral.';
    }
}

/**
 * Credit the signup bonus once per user.
 *
 * @param array<string, mixed> $user
 */
function grant_signup_bonus(array $user): void
{
    if (!setting('enable_signup_bonus', true)) {
        return;
    }

    $bonus = (float) setting('signup_bonus_t', 50);

    if ($bonus <= 0) {
        return;
    }

    $stmt = db()->prepare(
        "SELECT 1 FROM `wallet_transactions`
          WHERE `user_id` = :uid AND `type` = 'signup_bonus' LIMIT 1"
    );
    $stmt->execute([':uid' => $user['id']]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    credit_wallet(
        (int) $user['id'],
        'signup_bonus',
        'credit',
        $bonus,
        'manual',
        null,
        'Welcome bonus for joining Refer & Earn'
    );
}

/**
 * Apply a referral code captured by /start, then clear it from the user row.
 *
 * @param  array<string, mixed> $user
 * @return string|null Error message, or null on success
 */
function consume_pending_referral(array $user): ?string
{
    $code = trim((string) ($user['pending_referral_code'] ?? ''));

    if ($code === '') {
        return null;
    }

    $error = apply_referral($user, $code);

    try {
        $stmt = db()->prepare('UPDATE `users` SET `pending_referral_code` = NULL WHERE `id` = :id');
        $stmt->execute([':id' => $user['id']]);
    } catch (Throwable $e) {
        error_log('consume_pending_referral failed: ' . $e->getMessage());
    }

    return $error;
}

/* ---------------------------------------------------------------------
 | 10. Wallet ledger
 * ------------------------------------------------------------------- */

/**
 * Move T coins in or out of a user's wallet and write the ledger row.
 *
 * Must be called inside an open transaction when part of a larger unit of
 * work; otherwise it opens its own.
 *
 * @param  string      $type        wallet_transactions.type enum value
 * @param  string      $direction   'credit' or 'debit'
 * @param  float       $amount      Positive amount in T
 * @param  string|null $refType     referral|withdrawal|broadcast|manual
 * @param  int|null    $refId
 * @return float The balance after the movement
 */
function credit_wallet(
    int $userId,
    string $type,
    string $direction,
    float $amount,
    ?string $refType = null,
    ?int $refId = null,
    ?string $description = null
): float {
    if ($amount <= 0) {
        throw new InvalidArgumentException('Wallet amount must be greater than zero.');
    }

    $direction = $direction === 'debit' ? 'debit' : 'credit';
    $pdo       = db();
    $ownTx     = !$pdo->inTransaction();

    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT `balance_t` FROM `users` WHERE `id` = :id FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            throw new RuntimeException('User not found: ' . $userId);
        }

        $balance = (float) $current;
        $delta   = $direction === 'credit' ? $amount : -$amount;
        $after   = round($balance + $delta, 2);

        if ($after < 0) {
            throw new RuntimeException('Insufficient balance.');
        }

        $sets = ['`balance_t` = :bal'];

        if ($direction === 'credit') {
            $sets[] = '`total_earned_t` = `total_earned_t` + :earned';
        }

        $params = [':bal' => $after, ':id' => $userId];

        if ($direction === 'credit') {
            $params[':earned'] = $amount;
        }

        $stmt = $pdo->prepare('UPDATE `users` SET ' . implode(', ', $sets) . ' WHERE `id` = :id');
        $stmt->execute($params);

        $stmt = $pdo->prepare(
            'INSERT INTO `wallet_transactions`
                (`user_id`, `type`, `direction`, `amount_t`, `balance_after_t`,
                 `reference_type`, `reference_id`, `description`)
             VALUES (:uid, :type, :dir, :amt, :bal, :rt, :rid, :desc)'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':type' => $type,
            ':dir'  => $direction,
            ':amt'  => $amount,
            ':bal'  => $after,
            ':rt'   => $refType,
            ':rid'  => $refId,
            ':desc' => $description,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }

        return $after;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Human label for a wallet transaction type.
 */
function wallet_type_label(string $type): string
{
    return match ($type) {
        'referral_reward'   => 'Referral Reward',
        'signup_bonus'      => 'Signup Bonus',
        'bonus'             => 'Bonus',
        'withdrawal_debit'  => 'Withdrawal',
        'withdrawal_refund' => 'Withdrawal Refund',
        'admin_credit'      => 'Admin Credit',
        'admin_debit'       => 'Admin Debit',
        default             => ucwords(str_replace('_', ' ', $type)),
    };
}

/**
 * Font Awesome icon + colour classes for a wallet transaction type.
 *
 * @return array{icon: string, bg: string, text: string}
 */
function wallet_type_style(string $type): array
{
    return match ($type) {
        'referral_reward'   => ['icon' => 'fa-user-plus',        'bg' => 'bg-[#e8f8f0]', 'text' => 'text-emerald-600'],
        'signup_bonus'      => ['icon' => 'fa-gift',             'bg' => 'bg-[#eaf2ff]', 'text' => 'text-blue-600'],
        'bonus'             => ['icon' => 'fa-star',             'bg' => 'bg-[#fff6e4]', 'text' => 'text-amber-600'],
        'withdrawal_debit'  => ['icon' => 'fa-arrow-up-right-from-square', 'bg' => 'bg-[#ffeef2]', 'text' => 'text-rose-500'],
        'withdrawal_refund' => ['icon' => 'fa-rotate-left',      'bg' => 'bg-[#f3e8ff]', 'text' => 'text-purple-600'],
        'admin_credit'      => ['icon' => 'fa-shield-halved',    'bg' => 'bg-[#e0f2fe]', 'text' => 'text-sky-600'],
        'admin_debit'       => ['icon' => 'fa-shield-halved',    'bg' => 'bg-[#ffeef2]', 'text' => 'text-rose-500'],
        default             => ['icon' => 'fa-circle-info',      'bg' => 'bg-[#f4f4f8]', 'text' => 'text-slate-600'],
    };
}

/**
 * Format a datetime for the activity lists, e.g. "29 Oct 2026, 10:24 AM".
 */
function activity_time(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    $ts = strtotime($datetime);

    return $ts === false ? '' : date('d M Y, h:i A', $ts);
}

/**
 * Flash message helpers (session-backed).
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * @return array{type: string, message: string}|null
 */
function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

/* ---------------------------------------------------------------------
 | 11. Broadcast delivery
 * ------------------------------------------------------------------- */

/**
 * Absolute path of the private directory that holds broadcast uploads.
 *
 * It lives outside the document root's asset folder and is protected by
 * .htaccess, so uploaded media is never publicly reachable.
 */
function broadcast_upload_dir(): string
{
    $dir = __DIR__ . '/uploads/broadcasts';

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

/**
 * Resolve the audience of a broadcast to a list of Telegram chat ids.
 *
 * Only users who have started the bot can receive messages, so
 * `bot_started_at` is always required.
 *
 * @return list<array{id: int, telegram_id: int}>
 */
function broadcast_recipients(string $audience): array
{
    $sql    = 'SELECT `id`, `telegram_id` FROM `users` WHERE `bot_started_at` IS NOT NULL AND `status` <> \'blocked\'';
    $params = [];

    if ($audience === 'active') {
        $sql .= ' AND `last_active_at` IS NOT NULL AND `last_active_at` >= :since';
        $params[':since'] = date('Y-m-d H:i:s', strtotime('-30 days'));
    } elseif ($audience === 'inactive') {
        $sql .= ' AND (`last_active_at` IS NULL OR `last_active_at` < :since)';
        $params[':since'] = date('Y-m-d H:i:s', strtotime('-30 days'));
    }

    $sql .= ' ORDER BY `id`';

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'id'          => (int) $row['id'],
                'telegram_id' => (int) $row['telegram_id'],
            ],
            $stmt->fetchAll()
        );
    } catch (Throwable $e) {
        error_log('broadcast_recipients() failed: ' . $e->getMessage());

        return [];
    }
}

/**
 * Build the Telegram `inline_keyboard` markup for a broadcast.
 *
 * @param  list<array{label: string, url: string}> $buttons
 * @return array<string, mixed>|null
 */
function broadcast_reply_markup(array $buttons): ?array
{
    if ($buttons === []) {
        return null;
    }

    $rows = [];
    foreach ($buttons as $button) {
        $rows[] = [['text' => (string) $button['label'], 'url' => (string) $button['url']]];
    }

    return ['inline_keyboard' => $rows];
}

/**
 * Deliver one broadcast to its audience, synchronously.
 *
 * Writes `sending` before the loop and `sent`/`failed` afterwards, and keeps
 * the delivered/failed counters current so the dashboard reflects reality
 * even if the request dies part-way through.
 *
 * @return array{status: string, total: int, delivered: int, failed: int, error: string}
 */
function broadcast_deliver(int $broadcastId): array
{
    $result = ['status' => 'failed', 'total' => 0, 'delivered' => 0, 'failed' => 0, 'error' => ''];

    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM `broadcasts` WHERE `id` = :id LIMIT 1');
    $stmt->execute([':id' => $broadcastId]);
    $broadcast = $stmt->fetch();

    if (!$broadcast) {
        $result['error'] = 'Broadcast not found.';

        return $result;
    }

    if (bot_token() === '') {
        $result['error'] = 'Bot token is not configured.';

        return $result;
    }

    $btnStmt = $pdo->prepare(
        'SELECT `label`, `url` FROM `broadcast_buttons` WHERE `broadcast_id` = :id ORDER BY `position`'
    );
    $btnStmt->execute([':id' => $broadcastId]);
    $replyMarkup = broadcast_reply_markup($btnStmt->fetchAll());

    $recipients = broadcast_recipients((string) $broadcast['audience']);
    $total      = count($recipients);

    $pdo->prepare(
        'UPDATE `broadcasts`
            SET `status` = :status, `total_recipients` = :total,
                `delivered_count` = 0, `failed_count` = 0
          WHERE `id` = :id'
    )->execute([':status' => 'sending', ':total' => $total, ':id' => $broadcastId]);

    $result['status'] = 'sending';
    $result['total']  = $total;

    if ($total === 0) {
        $pdo->prepare(
            'UPDATE `broadcasts` SET `status` = :status, `sent_at` = NOW() WHERE `id` = :id'
        )->execute([':status' => 'failed', ':id' => $broadcastId]);

        $result['status'] = 'failed';
        $result['error']  = 'No users have started the bot yet, so there was nobody to send to.';

        return $result;
    }

    $mediaType = (string) $broadcast['media_type'];
    $mediaPath = (string) ($broadcast['media_path'] ?? '');
    $text      = (string) ($broadcast['message_text'] ?? '');
    $parseMode = (string) ($broadcast['parse_mode'] ?? 'HTML');
    $useMedia  = $mediaType !== 'none' && $mediaPath !== '' && is_file($mediaPath);

    $delivered = 0;
    $failed    = 0;
    $lastError = '';

    foreach ($recipients as $recipient) {
        $chatId = $recipient['telegram_id'];

        $response = $useMedia
            ? telegram_send_media($chatId, $mediaType, $mediaPath, $text, $replyMarkup, $parseMode)
            : telegram_send_message($chatId, $text, $replyMarkup, $parseMode);

        // Telegram rate-limits at ~30 messages/second; back off and retry once.
        if (($response['ok'] ?? false) !== true && (int) ($response['error_code'] ?? 0) === 429) {
            $retryAfter = (int) ($response['parameters']['retry_after'] ?? 1);
            sleep(max(1, min(30, $retryAfter)));

            $response = $useMedia
                ? telegram_send_media($chatId, $mediaType, $mediaPath, $text, $replyMarkup, $parseMode)
                : telegram_send_message($chatId, $text, $replyMarkup, $parseMode);
        }

        // A malformed entity would otherwise fail for every single recipient.
        if (($response['ok'] ?? false) !== true && (int) ($response['error_code'] ?? 0) === 400
            && stripos((string) ($response['description'] ?? ''), 'entit') !== false) {
            $response = $useMedia
                ? telegram_send_media($chatId, $mediaType, $mediaPath, $text, $replyMarkup, 'none')
                : telegram_send_message($chatId, $text, $replyMarkup, 'none');
        }

        if (($response['ok'] ?? false) === true) {
            $delivered++;
        } else {
            $failed++;
            $lastError = (string) ($response['description'] ?? $response['error'] ?? 'Unknown error');
        }

        // Persist progress in batches so a timeout still leaves usable counts.
        if (($delivered + $failed) % 25 === 0) {
            $pdo->prepare(
                'UPDATE `broadcasts` SET `delivered_count` = :d, `failed_count` = :f WHERE `id` = :id'
            )->execute([':d' => $delivered, ':f' => $failed, ':id' => $broadcastId]);
        }

        // Stay well under Telegram's per-chat and global limits.
        usleep(50000);
    }

    $status = $delivered > 0 ? 'sent' : 'failed';

    $pdo->prepare(
        'UPDATE `broadcasts`
            SET `status` = :status, `delivered_count` = :d, `failed_count` = :f, `sent_at` = NOW()
          WHERE `id` = :id'
    )->execute([':status' => $status, ':d' => $delivered, ':f' => $failed, ':id' => $broadcastId]);

    $result['status']    = $status;
    $result['delivered'] = $delivered;
    $result['failed']    = $failed;
    $result['error']     = $delivered === 0 ? $lastError : '';

    return $result;
}
