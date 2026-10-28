<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
require_auth_json();

$user = current_user();

try {
$b      = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? '');

// UAT/prod may not have run migrate.php — create/patch the table on first use.
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT       NOT NULL,
    receiver_id INT       NOT NULL,
    body        TEXT      NOT NULL,
    attachment  VARCHAR(255) NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (sender_id, receiver_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if (!$pdo->query("SHOW COLUMNS FROM messages LIKE 'attachment'")->fetch()) {
    $pdo->exec("ALTER TABLE messages ADD COLUMN attachment VARCHAR(255) NULL AFTER body");
}

$uid = (int)$user['id'];

// ─── Contacts (unread + last activity per conversation partner) ─────────────
if ($action === 'contacts') {
    $stmt = $pdo->prepare("SELECT sender_id,receiver_id,body,attachment,created_at,is_read
        FROM messages WHERE sender_id=? OR receiver_id=? ORDER BY id DESC");
    $stmt->execute([$uid, $uid]);
    $map = [];
    foreach ($stmt as $m) {
        $other = (int)$m['sender_id'] === $uid ? (int)$m['receiver_id'] : (int)$m['sender_id'];
        if (!isset($map[$other])) {
            $att = $m['attachment'] ?? '';
            $map[$other] = [
                'account_id' => $other,
                'last_at'    => $m['created_at'],
                'unread'     => 0,
                'last_body'  => $m['body'] !== '' ? $m['body'] : ($att !== '' && $att !== null ? '📎 '.$att : ''),
            ];
        }
        if ((int)$m['receiver_id'] === $uid && !(int)$m['is_read']) $map[$other]['unread']++;
    }
    json_response(['ok'=>true, 'data'=>array_values($map)]);
}

// ─── Thread with one account (marks their messages as read) ─────────────────
if ($action === 'thread') {
    $withId = (int)($b['withId'] ?? $_GET['withId'] ?? 0);
    if (!$withId) json_response(['ok'=>false,'message'=>'Missing withId'], 400);
    $stmt = $pdo->prepare("SELECT id,sender_id,receiver_id,body,attachment,created_at FROM messages
        WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC");
    $stmt->execute([$uid, $withId, $withId, $uid]);
    $rows = $stmt->fetchAll();
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
        ->execute([$withId, $uid]);
    json_response(['ok'=>true, 'data'=>$rows]);
}

// ─── Send ─────────────────────────────────────────────────────────────────────
if ($action === 'send') {
    $receiverId = (int)($b['receiverId'] ?? 0);
    $body       = trim($b['body'] ?? '');
    if (!$receiverId || $body === '') json_response(['ok'=>false,'message'=>'Message is required.'], 400);
    if ($receiverId === $uid) json_response(['ok'=>false,'message'=>'Cannot message yourself.'], 400);

    $stmt = $pdo->prepare("SELECT name,role,status FROM accounts WHERE id=?");
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch();
    if (!$receiver || $receiver['status'] !== 'approved') json_response(['ok'=>false,'message'=>'Recipient not found.'], 404);
    if ($user['role'] === 'Client' && $receiver['role'] === 'Client')
        json_response(['ok'=>false,'message'=>'You can only message team members.'], 403);

    $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)")
        ->execute([$uid, $receiverId, $body]);
    json_response(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
}

// ─── Send with attachment (multipart — action passed as ?action=sendFile) ──────
if ($action === 'sendFile') {
    $receiverId = (int)($_POST['receiverId'] ?? 0);
    $body       = trim($_POST['body'] ?? '');
    $file       = $_FILES['file'] ?? null;
    if (!$receiverId || !$file || !is_uploaded_file($file['tmp_name'])) json_response(['ok'=>false,'message'=>'File is required.'], 400);
    if ($receiverId === $uid) json_response(['ok'=>false,'message'=>'Cannot message yourself.'], 400);

    $stmt = $pdo->prepare("SELECT name,role,status FROM accounts WHERE id=?");
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch();
    if (!$receiver || $receiver['status'] !== 'approved') json_response(['ok'=>false,'message'=>'Recipient not found.'], 404);
    if ($user['role'] === 'Client' && $receiver['role'] === 'Client')
        json_response(['ok'=>false,'message'=>'You can only message team members.'], 403);

    $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','zip','csv','txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) json_response(['ok'=>false,'message'=>'File type not allowed.'], 400);

    $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)")
        ->execute([$uid, $receiverId, $body]);
    $msgId = (int)$pdo->lastInsertId();

    $stem = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo(basename($file['name']), PATHINFO_FILENAME));
    $safe = $stem.'.'.$ext;
    $dir  = dirname(__DIR__).'/uploads/msg/'.$msgId.'/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dir.$safe)) {
        $pdo->prepare("DELETE FROM messages WHERE id=?")->execute([$msgId]); // don't leave a blank phantom message behind
        json_response(['ok'=>false,'message'=>'Upload failed.'], 500);
    }

    $pdo->prepare("UPDATE messages SET attachment=? WHERE id=?")->execute([$safe, $msgId]);
    json_response(['ok'=>true, 'id'=>$msgId]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
