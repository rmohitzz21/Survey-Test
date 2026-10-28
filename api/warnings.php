<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_staff_json();

$user = current_user();
const WARN_STRIKE = 3;   // warnings that trigger one deduction
const WARN_AMOUNT = 500; // ₹ deducted each time the strike count is reached

try {
$b      = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? '');

$pdo->exec("CREATE TABLE IF NOT EXISTS warnings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT          NOT NULL,
    reason     TEXT         NOT NULL,
    issued_by  VARCHAR(100) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_warn_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$uid = (int)$user['id'];

// Every WARN_STRIKE warnings costs WARN_AMOUNT once — 3 warnings deduct ₹500, 6 deduct ₹1000.
// ponytail: counted over the account's whole history, not per year. Add a YEAR(created_at)
// filter here and in the list query if the count should reset each year.
$deduction = static fn(int $count): int => intdiv($count, WARN_STRIKE) * WARN_AMOUNT;
if ($action === 'list') {
    if (is_admin()) {
        $rows = $pdo->query("SELECT w.*, a.name FROM warnings w JOIN accounts a ON a.id=w.account_id
            ORDER BY w.created_at DESC, w.id DESC")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT w.*, a.name FROM warnings w JOIN accounts a ON a.id=w.account_id
            WHERE w.account_id=? ORDER BY w.created_at DESC, w.id DESC");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
    }
    $mine = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE account_id=?");
    $mine->execute([$uid]);
    $myCount = (int)$mine->fetchColumn();
    json_response(['ok'=>true, 'data'=>$rows, 'myCount'=>$myCount, 'myDeduction'=>$deduction($myCount),
                   'strike'=>WARN_STRIKE, 'amount'=>WARN_AMOUNT]);
}

if ($action === 'issue') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can issue a warning.'], 403);
    $id     = (int)($b['accountId'] ?? 0);
    $reason = trim($b['reason'] ?? '');
    if ($reason === '') json_response(['ok'=>false,'message'=>'Please write the reason for this warning.'], 400);

    $who = $pdo->prepare("SELECT name,role FROM accounts WHERE id=?");
    $who->execute([$id]);
    $target = $who->fetch();
    if (!$target) json_response(['ok'=>false,'message'=>'Please pick a user to warn.'], 400);
    if ($id === $uid) json_response(['ok'=>false,'message'=>'You cannot warn yourself.'], 400);

    $pdo->prepare("INSERT INTO warnings (account_id,reason,issued_by) VALUES (?,?,?)")
        ->execute([$id, mb_substr($reason, 0, 1000), $user['name']]);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE account_id=?");
    $cnt->execute([$id]);
    $count = (int)$cnt->fetchColumn();

    $step = (($count - 1) % WARN_STRIKE) + 1;
    $level = 'Warning '.$step.' of '.WARN_STRIKE;
    $body = $level.' — '.$reason;
    if ($count % WARN_STRIKE === 0) {
        $body .= ' This completes a full set, so ₹'.WARN_AMOUNT.' will be deducted from your salary.';
    } else {
        $left = WARN_STRIKE - $step;
        $body .= ' '.$left.' more until the next ₹'.WARN_AMOUNT.' deduction.';
    }
    notify_user($pdo, $target['name'], 'You received a warning', $body);

    json_response(['ok'=>true, 'count'=>$count, 'deduction'=>$deduction($count)]);
}

if ($action === 'revoke') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can revoke a warning.'], 403);
    $stmt = $pdo->prepare("SELECT w.account_id, a.name FROM warnings w JOIN accounts a ON a.id=w.account_id WHERE w.id=?");
    $stmt->execute([(int)($b['id'] ?? 0)]);
    $row = $stmt->fetch();
    if (!$row) json_response(['ok'=>false,'message'=>'Warning not found.'], 404);
    $pdo->prepare("DELETE FROM warnings WHERE id=?")->execute([(int)$b['id']]);
    notify_user($pdo, $row['name'], 'Warning withdrawn', 'A warning on your record was withdrawn by '.$user['name'].'.');
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
