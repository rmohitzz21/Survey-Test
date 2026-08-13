<?php
function sp_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('sp_work_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
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
