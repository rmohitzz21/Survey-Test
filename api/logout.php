<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
if (!empty($_SESSION['user']['id'])) {
    try {
        $pdo->prepare("UPDATE accounts SET last_seen_at=NULL WHERE id=?")->execute([(int)$_SESSION['user']['id']]);
    } catch (Exception $e) { /* ignore */ }
}
$_SESSION = [];
session_destroy();
header('Location: ../login.php');
exit;
