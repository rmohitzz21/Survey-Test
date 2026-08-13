<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
require_auth_json();

$method = $_SERVER['REQUEST_METHOD'];
$b      = get_json_body();
$action = $b['action'] ?? '';

if (!is_admin()) json_response(['ok'=>false,'message'=>'Access denied'], 403);

function accounts_list(PDO $pdo): array {
    return $pdo->query("SELECT id,name,email,role,status,requested_at FROM accounts ORDER BY id")->fetchAll();
}

// GET — list accounts
if ($method === 'GET') {
    json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
}

if ($method === 'POST') {
    // ─── Approve ─────────────────────────────────────────────────────────────
    if ($action === 'approve') {
        $pdo->prepare("UPDATE accounts SET status='approved' WHERE id=?")->execute([$b['id']]);
        $urow = $pdo->prepare("SELECT name,email FROM accounts WHERE id=?"); $urow->execute([$b['id']]); $u=$urow->fetch();
        if ($u) $pdo->prepare("DELETE FROM notifications WHERE title='New registration request' AND body LIKE ?")->execute([$u['name'].' ('.$u['email'].')%']);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Reject ──────────────────────────────────────────────────────────────
    if ($action === 'reject') {
        $pdo->prepare("UPDATE accounts SET status='rejected' WHERE id=?")->execute([$b['id']]);
        $urow = $pdo->prepare("SELECT name,email FROM accounts WHERE id=?"); $urow->execute([$b['id']]); $u=$urow->fetch();
        if ($u) $pdo->prepare("DELETE FROM notifications WHERE title='New registration request' AND body LIKE ?")->execute([$u['name'].' ('.$u['email'].')%']);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Block ───────────────────────────────────────────────────────────────
    if ($action === 'block') {
        $pdo->prepare("UPDATE accounts SET status='blocked' WHERE id=?")->execute([$b['id']]);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Unblock ─────────────────────────────────────────────────────────────
    if ($action === 'unblock') {
        $pdo->prepare("UPDATE accounts SET status='approved' WHERE id=?")->execute([$b['id']]);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Role Change (Master Admin only) ─────────────────────────────────────
    if ($action === 'role_change') {
        if (!is_master_admin()) json_response(['ok'=>false,'message'=>'Only Master Admin can change roles'], 403);
        $pdo->prepare("UPDATE accounts SET role=? WHERE id=?")->execute([$b['role'],$b['id']]);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Delete (Master Admin only) ──────────────────────────────────────────
    if ($action === 'delete') {
        if (!is_master_admin()) json_response(['ok'=>false,'message'=>'Only Master Admin can delete accounts'], 403);
        $pdo->prepare("DELETE FROM accounts WHERE id=?")->execute([$b['id']]);
        json_response(['ok'=>true,'data'=>accounts_list($pdo)]);
    }
    // ─── Direct Add (Master Admin only) ──────────────────────────────────────
    if ($action === 'direct_add') {
        if (!is_master_admin()) json_response(['ok'=>false,'message'=>'Only Master Admin can add users directly'], 403);
        $email = strtolower(trim($b['email']??''));
        $ex = $pdo->prepare("SELECT id FROM accounts WHERE email=?"); $ex->execute([$email]);
        if ($ex->fetch()) json_response(['ok'=>false,'message'=>'An account with this email already exists.']);
        $hash = password_hash($b['password']??'changeme', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO accounts (name,email,password_hash,role,status,requested_at) VALUES (?,?,?,?,'approved',CURDATE())")
            ->execute([trim($b['name']??''),$email,$hash,$b['role']??'Member']);
        json_response(['ok'=>true,'message'=>trim($b['name']??'').' added successfully.','data'=>accounts_list($pdo)]);
    }
}

json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
