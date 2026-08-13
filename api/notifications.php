<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$user   = current_user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    json_response(['ok'=>true, 'data'=>load_notifications($pdo, $user['id'])]);
}

if ($method === 'POST') {
    $b = get_json_body();
    if (($b['action']??'') === 'mark_read') {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE account_id=?")->execute([$user['id']]);
        json_response(['ok'=>true]);
    }
    if (($b['action']??'') === 'dismiss') {
        $id = (int)($b['id'] ?? 0);
        $pdo->prepare("DELETE FROM notifications WHERE id=? AND account_id=?")->execute([$id, $user['id']]);
        json_response(['ok'=>true]);
    }
    if (($b['action']??'') === 'clear_all') {
        $pdo->prepare("DELETE FROM notifications WHERE account_id=?")->execute([$user['id']]);
        json_response(['ok'=>true]);
    }
}

json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
