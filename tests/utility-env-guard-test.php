<?php
/**
 * Env allowlist for browser maintenance utilities — no DB, no utility execution.
 * Run: php tests/utility-env-guard-test.php
 */
require_once __DIR__ . '/../includes/auth.php';

function eq($actual, $expected, string $msg): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $msg\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function ok(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

// ── Allowlist: local and uat ──
eq(sp_utility_env_permitted('local'), true, 'local is permitted');
eq(sp_utility_env_permitted('LOCAL'), true, 'LOCAL (case) is permitted');
eq(sp_utility_env_permitted(' uat '), true, 'uat with whitespace is permitted');
eq(sp_utility_env_permitted('uat'), true, 'uat is permitted');

// ── Deny: production ──
eq(sp_utility_env_permitted('production'), false, 'production is denied');
eq(sp_utility_env_permitted('PRODUCTION'), false, 'PRODUCTION is denied');

// ── Fail closed: missing / unsupported ──
eq(sp_utility_env_permitted(''), false, 'empty env is denied');
eq(sp_utility_env_permitted(null), false, 'null env is denied');
eq(sp_utility_env_permitted('staging'), false, 'unsupported staging is denied');
eq(sp_utility_env_permitted('dev'), false, 'unsupported dev is denied');
eq(sp_utility_env_permitted('prod'), false, 'unsupported prod alias is denied');

// ── Shared helpers still present (Master Admin + CSRF unchanged) ──
ok(function_exists('require_utility_master_admin'), 'require_utility_master_admin still defined');
ok(function_exists('require_csrf'), 'require_csrf still defined');
ok(function_exists('require_utility_non_production'), 'require_utility_non_production defined');
ok(function_exists('sp_utility_env_permitted'), 'sp_utility_env_permitted defined');

// ── Utilities wire master admin → env guard → (still) CSRF on POST paths ──
$utilities = [
    'fix-today-due.php',
    'clear-overdue.php',
    'delete-inquiry.php',
    'update-due-date.php',
    'demo-data.php',
];
$root = dirname(__DIR__);
foreach ($utilities as $file) {
    $src = file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    ok($src !== false, "$file readable");
    ok(str_contains($src, 'require_utility_master_admin'), "$file still calls require_utility_master_admin");
    ok(str_contains($src, 'require_utility_non_production'), "$file calls require_utility_non_production");
    ok(str_contains($src, 'require_csrf'), "$file still calls require_csrf");
    $posAdmin = strpos($src, 'require_utility_master_admin');
    $posEnv   = strpos($src, 'require_utility_non_production');
    ok($posAdmin !== false && $posEnv !== false && $posAdmin < $posEnv,
        "$file invokes env guard after Master Admin check");
}

// demo-data keeps DB-name defense in depth
$demo = file_get_contents($root . DIRECTORY_SEPARATOR . 'demo-data.php');
ok(str_contains($demo, "DB_NAME === 'survevap_inquiry'"), 'demo-data.php retains production DB-name check');

echo "utility-env-guard-test: all assertions passed\n";
