<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$user = current_user();

try {
$b      = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? '');

// Private per-user notepad — created on first use so migrate is optional.
$pdo->exec("CREATE TABLE IF NOT EXISTS call_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT          NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_notes_account (account_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$uid = (int)$user['id'];

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT id, title, body, created_at, updated_at
        FROM call_notes WHERE account_id=?
        ORDER BY COALESCE(updated_at, created_at) DESC, id DESC");
    $stmt->execute([$uid]);
    json_response(['ok'=>true, 'data'=>$stmt->fetchAll()]);
}

if ($action === 'create') {
    $title = trim($b['title'] ?? '');
    $body  = trim($b['body'] ?? '');
    if ($title === '') json_response(['ok'=>false,'message'=>'Please add a title.'], 400);
    if ($body === '')  json_response(['ok'=>false,'message'=>'Please write the note.'], 400);
    $pdo->prepare("INSERT INTO call_notes (account_id, title, body) VALUES (?,?,?)")
        ->execute([$uid, mb_substr($title, 0, 200), mb_substr($body, 0, 10000)]);
    json_response(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
}

if ($action === 'update') {
    $id    = (int)($b['id'] ?? 0);
    $title = trim($b['title'] ?? '');
    $body  = trim($b['body'] ?? '');
    if ($id < 1) json_response(['ok'=>false,'message'=>'Invalid note.'], 400);
    if ($title === '') json_response(['ok'=>false,'message'=>'Please add a title.'], 400);
    if ($body === '')  json_response(['ok'=>false,'message'=>'Please write the note.'], 400);
    $stmt = $pdo->prepare("UPDATE call_notes SET title=?, body=? WHERE id=? AND account_id=?");
    $stmt->execute([mb_substr($title, 0, 200), mb_substr($body, 0, 10000), $id, $uid]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM call_notes WHERE id=? AND account_id=?");
        $check->execute([$id, $uid]);
        if (!(int)$check->fetchColumn()) json_response(['ok'=>false,'message'=>'Note not found.'], 404);
    }
    json_response(['ok'=>true]);
}

if ($action === 'delete') {
    $id = (int)($b['id'] ?? 0);
    if ($id < 1) json_response(['ok'=>false,'message'=>'Invalid note.'], 400);
    $stmt = $pdo->prepare("DELETE FROM call_notes WHERE id=? AND account_id=?");
    $stmt->execute([$id, $uid]);
    if ($stmt->rowCount() === 0) json_response(['ok'=>false,'message'=>'Note not found.'], 404);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Exception $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
