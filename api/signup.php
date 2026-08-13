<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method not allowed'], 405);

$body  = get_json_body();
$name  = trim($body['name'] ?? '');
$email = strtolower(trim($body['email'] ?? ''));
$pass  = $body['password'] ?? '';

if (!$name)  json_response(['ok'=>false,'message'=>'Full name is required.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['ok'=>false,'message'=>'Enter a valid email address.']);
if (strlen($pass) < 6) json_response(['ok'=>false,'message'=>'Password must be at least 6 characters.']);

$exists = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
$exists->execute([$email]);
if ($exists->fetch()) json_response(['ok'=>false,'message'=>'An account with this email already exists.']);

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO accounts (name,email,password_hash,role,status,requested_at) VALUES (?,?,?,'Member','pending',CURDATE())");
$stmt->execute([$name, $email, $hash]);

// Notify all approved Master Admins about the new sign-up
$masters = $pdo->query("SELECT name FROM accounts WHERE role='Master Admin' AND status='approved'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($masters as $masterName) {
    notify_user($pdo, $masterName, 'New registration request', $name.' ('.$email.') has requested access. Please review in Approvals.', null);
}

json_response(['ok'=>true,'message'=>'Request submitted! A Master Admin will review your account before you can sign in.']);
