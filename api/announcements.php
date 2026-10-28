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

// UAT/prod may not have run migrate.php — create tables on first use.
$pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    created_by VARCHAR(100) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
    account_id   INT       PRIMARY KEY,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$uid = (int)$user['id'];

// ─── List (newest first) ─────────────────────────────────────────────────────
if ($action === 'list') {
    $rows = $pdo->query("SELECT id, created_by, body, created_at FROM announcements ORDER BY id DESC")->fetchAll();
    json_response(['ok'=>true, 'data'=>$rows]);
}

// ─── Post (Admin / Master Admin only) ────────────────────────────────────────
if ($action === 'post') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can post announcements.'], 403);
    $body = trim($b['body'] ?? '');
    if ($body === '') json_response(['ok'=>false,'message'=>'Announcement cannot be empty.'], 400);
    $pdo->prepare("INSERT INTO announcements (created_by, body) VALUES (?,?)")
        ->execute([$user['name'], mb_substr($body, 0, 5000)]);
    json_response(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
}

// ─── Edit (Admin / Master Admin only) ────────────────────────────────────────
if ($action === 'edit') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can edit announcements.'], 403);
    $id   = (int)($b['id'] ?? 0);
    $body = trim($b['body'] ?? '');
    if ($id < 1) json_response(['ok'=>false,'message'=>'Invalid announcement.'], 400);
    if ($body === '') json_response(['ok'=>false,'message'=>'Announcement cannot be empty.'], 400);
    $stmt = $pdo->prepare("UPDATE announcements SET body=? WHERE id=?");
    $stmt->execute([mb_substr($body, 0, 5000), $id]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM announcements WHERE id=?");
        $check->execute([$id]);
        if (!(int)$check->fetchColumn()) json_response(['ok'=>false,'message'=>'Announcement not found.'], 404);
    }
    json_response(['ok'=>true]);
}

// ─── Delete (Admin / Master Admin only) ──────────────────────────────────────
if ($action === 'delete') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can delete announcements.'], 403);
    $id = (int)($b['id'] ?? 0);
    if ($id < 1) json_response(['ok'=>false,'message'=>'Invalid announcement.'], 400);
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) json_response(['ok'=>false,'message'=>'Announcement not found.'], 404);
    json_response(['ok'=>true]);
}

// ─── Mark feed as seen (drives the unread badge via ping.php) ────────────────
if ($action === 'markSeen') {
    $pdo->prepare("INSERT INTO announcement_reads (account_id, last_seen_at) VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE last_seen_at=NOW()")
        ->execute([$uid]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Exception $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
