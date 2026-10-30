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

$host    = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = $host === ''
    || preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host) === 1;

define('APP_ENV', $isLocal ? 'local' : 'production');
define('APP_DEBUG', APP_ENV === 'local');
define('APP_TIMEZONE', 'Asia/Kolkata');

// Absolute base URL, no trailing slash.
define('APP_URL', $isLocal
    ? 'http://' . ($host !== '' ? $host : 'localhost')
    : 'https://' . $host);

/* ---------------------------------------------------------------------
 |  2. Database  (XAMPP defaults)
 * ------------------------------------------------------------------- */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'refer_earn');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 |  3. Session
 * ------------------------------------------------------------------- */

define('SESSION_NAME', 'refer_earn_sid');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

/* ---------------------------------------------------------------------
 |  4. Bootstrap
 * ------------------------------------------------------------------- */

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
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
        'withdrawal_api_url'           => 'https://yourdomain.com/api/withdraw',
        'coin_to_inr_rate'             => 0.10,
        'withdrawal_processing_hours'  => 24,

        // Bot
        'bot_token'                    => '',
        'bot_web_app_url'              => '',

        // Bonus
        'signup_bonus_t'               => 50.0,
        'enable_signup_bonus'          => true,

        // Platform
        'allow_registration'           => true,
        'withdrawals_enabled'          => true,
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
function settings(): array
{
    static $cache = null;

    if ($cache !== null) {
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
 */
function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) === 1 ? $path : APP_URL . '/' . ltrim($path, '/');

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
