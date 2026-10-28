<?php
require_once __DIR__ . '/env.php';
sp_load_env();

/**
 * Abort before any DB connection. Never pass credential values into $logMessage.
 * Web: HTTP 500 + existing JSON shape. CLI: stderr + exit 1.
 */
if (!function_exists('sp_db_fail_config')) {
    function sp_db_fail_config(string $publicMessage, string $logMessage): void {
        error_log($logMessage);
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            fwrite(STDERR, $publicMessage . PHP_EOL);
            exit(1);
        }
        http_response_code(500);
        die(json_encode(['ok' => false, 'message' => $publicMessage]));
    }
}

// Environment comes only from trusted process/.env config (SP_APP_ENV).
// Never infer from HTTP_HOST, SERVER_NAME, or REMOTE_ADDR.
// Supported: local | uat | production — missing/unknown always fail closed.
$_app_env = strtolower(trim((string) (sp_env('SP_APP_ENV') ?? '')));

if ($_app_env === '') {
    sp_db_fail_config(
        'Server configuration error. Application environment is not set.',
        'SP_APP_ENV is not set. Set SP_APP_ENV to local, uat, or production (see .env.example).'
    );
}

if ($_app_env !== 'local' && $_app_env !== 'uat' && $_app_env !== 'production') {
    sp_db_fail_config(
        'Server configuration error. Application environment is invalid.',
        'SP_APP_ENV has an unsupported value. Use local, uat, or production.'
    );
}

$_is_local = ($_app_env === 'local');

if ($_is_local) {
    // XAMPP local DB — ignores SP_DB_* so a production .env cannot point local mode at live.
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sp_work');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // UAT / production — every value required and non-empty (including SP_DB_PASS).
    // An explicitly empty password fails closed the same as an unset one.
    $_db_host = sp_env('SP_DB_HOST');
    $_db_name = sp_env('SP_DB_NAME');
    $_db_user = sp_env('SP_DB_USER');
    $_db_pass = sp_env('SP_DB_PASS');
    $_missing = [];
    if ($_db_host === null || $_db_host === '') $_missing[] = 'SP_DB_HOST';
    if ($_db_name === null || $_db_name === '') $_missing[] = 'SP_DB_NAME';
    if ($_db_user === null || $_db_user === '') $_missing[] = 'SP_DB_USER';
    if ($_db_pass === null || $_db_pass === '') $_missing[] = 'SP_DB_PASS';
    if ($_missing) {
        sp_db_fail_config(
            'Server configuration error. Database credentials are not set.',
            'Missing required DB config for ' . $_app_env . ': ' . implode(', ', $_missing) . '. Create a .env from .env.example.'
        );
    }
    define('DB_HOST', $_db_host);
    define('DB_NAME', $_db_name);
    define('DB_USER', $_db_user);
    define('DB_PASS', $_db_pass);
    unset($_db_host, $_db_name, $_db_user, $_db_pass, $_missing);
}

// Shared hosting: never dump PHP errors into HTML for visitors.
if (!$_is_local) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}
unset($_app_env, $_is_local);

// Everything the portal does — created_at, overdue, "today's follow-ups", the month scope — is
// business-day logic for one office in India, so pin PHP and MySQL to IST here rather than trusting
// whatever php.ini or the host happens to be set to. Every entry point loads this file, so this is
// the one place that has to be right. Offset, not 'Asia/Kolkata': named zones need MySQL's tz tables
// loaded, which shared hosts often skip, and India has no DST so +05:30 is always correct.
date_default_timezone_set('Asia/Kolkata');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, 'Database connection failed. Please try again later.' . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    die(json_encode(['ok' => false, 'message' => 'Database connection failed. Please try again later.']));
}
