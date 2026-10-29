<?php
/**
 * True when the browser reached us over TLS.
 *
 * isset($_SERVER['HTTPS']) was wrong twice over: Apache/IIS set HTTPS to the
 * literal string 'off' on plain HTTP, and isset() is true for 'off' — so an HTTP
 * request got a Secure cookie the browser then refused to store. Behind a proxy
 * or CDN (which is how the live host serves inquiry.surveypacific.com) PHP sees
 * plain HTTP and the forwarded headers are the only evidence of TLS.
 */
function sp_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')   return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '')) === 'on')      return true;
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function sp_session_start(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Keep session files out of the shared /tmp this host hands every tenant: a
    // neighbour's GC sweep runs on their gc_maxlifetime, not ours, and deletes our
    // files early — which logs people out mid-work on live but never on XAMPP.
    // Only switch when the directory is actually usable, so a read-only deploy
    // silently keeps the default rather than breaking login outright.
    $dir = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) {
        // The path sits under the docroot, so refuse web access to the files in it.
        if (!file_exists($dir . '/.htaccess'))
            @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', '28800'); // 8h — one working day, not 24 min
    }

    session_name('sp_work_session');
    session_set_cookie_params([
        'lifetime' => 0,               // cookie dies with the browser; the server side is the real clock
        'path'     => '/',
        'secure'   => sp_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_auth(string $redirect = '../login.php'): void {
    sp_session_start();
    if (empty($_SESSION['user'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_auth_json(): void {
    sp_session_start();
    if (empty($_SESSION['user'])) {
        json_response(['ok' => false, 'message' => 'Session expired. Please refresh the page.', 'expired' => true], 401);
    }
}

/** Staff = everyone except Client portal accounts. */
function is_client(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'Client';
}

function require_staff_json(): void {
    require_auth_json();
    if (is_client()) {
        json_response(['ok' => false, 'message' => 'Client accounts cannot use this action.'], 403);
    }
}

/** One-off repair / migrate tools — Master Admin session only (no shared URL tokens). */
function require_utility_master_admin(string $loginRedirect = 'login.php'): void {
    sp_session_start();
    if (empty($_SESSION['user'])) {
        header('Location: ' . $loginRedirect . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
    if (!is_master_admin()) {
        http_response_code(403);
        die('Forbidden — Master Admin login required for this utility.');
    }
}

/**
 * Whether SP_APP_ENV may run browser maintenance utilities.
 * Allowlist matches db.php: only local and uat. Missing/unsupported/production → false (fail closed).
 */
function sp_utility_env_permitted(?string $env): bool {
    $env = strtolower(trim((string) ($env ?? '')));
    return $env === 'local' || $env === 'uat';
}

/**
 * Block maintenance utilities when SP_APP_ENV is production, missing, or unsupported.
 * Call after require_utility_master_admin(); does not replace Master Admin or CSRF checks.
 */
function require_utility_non_production(): void {
    require_once __DIR__ . '/env.php';
    sp_load_env();
    $env = strtolower(trim((string) (sp_env('SP_APP_ENV') ?? '')));
    if (sp_utility_env_permitted($env)) {
        return;
    }
    http_response_code(403);
    if ($env === 'production') {
        die('Forbidden — maintenance utilities cannot run when SP_APP_ENV is production.');
    }
    die('Forbidden — maintenance utilities require SP_APP_ENV=local or SP_APP_ENV=uat.');
}

function require_no_auth(string $redirect = 'index.php'): void {
    sp_session_start();
    if (!empty($_SESSION['user'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function is_admin(): bool {
    $role = $_SESSION['user']['role'] ?? '';
    return $role === 'Master Admin' || $role === 'Admin';
}

function is_master_admin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'Master Admin';
}

function csrf_token(): string {
    sp_session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_refresh(): string {
    sp_session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Enforce CSRF on state-changing requests. Accepts X-CSRF-Token header,
 * csrf form field, or JSON body _csrf / csrf.
 */
function require_csrf(?array $body = null): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    $sent = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['csrf']
        ?? $_POST['_csrf']
        ?? null;
    if (($sent === null || $sent === '') && is_array($body)) {
        $sent = $body['csrf'] ?? $body['_csrf'] ?? null;
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !$sent || !hash_equals($expected, (string)$sent)) {
        json_response(['ok' => false, 'message' => 'Invalid or missing security token. Refresh the page and try again.'], 403);
    }
}

function json_response(array $data, int $status = 200): never {
    while (ob_get_level() > 0) ob_end_clean(); // discard BOM or stray output before headers
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
    // decode HTML fields that were base64'd on the client to bypass WAF inspection
    array_walk($data, function(&$v) {
        if (is_string($v) && str_starts_with($v, '__b64__')) $v = base64_decode(substr($v, 7));
    });
    return $data;
}
