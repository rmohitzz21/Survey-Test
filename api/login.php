<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method not allowed'], 405);

$body = get_json_body();
$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

if (!$email || !$password) json_response(['ok'=>false,'message'=>'Email and password are required.']);

// Rate limiting: max 5 failures per email, then 5-minute lockout
$rk = 'login_fail_'.md5($email);
$_SESSION[$rk] = $_SESSION[$rk] ?? ['count'=>0,'until'=>0];
if ($_SESSION[$rk]['until'] > time()) {
    $wait = $_SESSION[$rk]['until'] - time();
    json_response(['ok'=>false,'message'=>"Too many failed attempts. Try again in {$wait}s."], 429);
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION[$rk]['count']++;
    if ($_SESSION[$rk]['count'] >= 5) { $_SESSION[$rk]['until'] = time()+300; $_SESSION[$rk]['count']=0; }
    json_response(['ok'=>false,'message'=>'No account found with this email.']);
}

if ($user['status'] === 'pending')  json_response(['ok'=>false,'message'=>'Your account is pending admin approval.']);
if ($user['status'] === 'rejected') json_response(['ok'=>false,'message'=>'Your account request was rejected. Contact your admin.']);
if ($user['status'] === 'blocked')  json_response(['ok'=>false,'message'=>'Your account has been blocked. Contact your admin.']);

if (!password_verify($password, $user['password_hash'])) {
    $_SESSION[$rk]['count']++;
    if ($_SESSION[$rk]['count'] >= 5) { $_SESSION[$rk]['until'] = time()+300; $_SESSION[$rk]['count']=0; }
    json_response(['ok'=>false,'message'=>'Incorrect password.']);
}
unset($_SESSION[$rk]); // clear on success
session_regenerate_id(true); // prevent session fixation

$_SESSION['user'] = [
    'id'    => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];

json_response(['ok'=>true, 'name'=>$user['name'], 'role'=>$user['role']]);
