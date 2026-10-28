<?php
/**
 * Call Notes — private per-user CRUD smoke test (CLI, local MySQL).
 * Usage: C:\xampp\php\php.exe tests/notes-test.php
 */
$fail = 0;
function assert_true($cond, string $msg): void {
    global $fail;
    if ($cond) echo "  OK  $msg\n";
    else { echo " FAIL $msg\n"; $fail++; }
}

echo "notes-test\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sp_work;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    echo " SKIP MySQL not available: ".$e->getMessage()."\n";
    exit(0);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS call_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT          NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_account (account_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$accounts = $pdo->query("SELECT id FROM accounts WHERE status='approved' ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
assert_true(count($accounts) >= 1, 'at least one approved account exists');
$a = (int)$accounts[0];
$b = isset($accounts[1]) ? (int)$accounts[1] : $a;

$marker = 'notes-test-' . bin2hex(random_bytes(4));
$pdo->prepare("INSERT INTO call_notes (account_id, title, body) VALUES (?,?,?)")
    ->execute([$a, $marker, "Body for $marker"]);
$id = (int)$pdo->lastInsertId();
assert_true($id > 0, 'insert returns id');

$mine = $pdo->prepare("SELECT id FROM call_notes WHERE account_id=? AND title=?");
$mine->execute([$a, $marker]);
assert_true((int)$mine->fetchColumn() === $id, 'owner can find their note');

if ($b !== $a) {
    $other = $pdo->prepare("SELECT COUNT(*) FROM call_notes WHERE account_id=? AND title=?");
    $other->execute([$b, $marker]);
    assert_true((int)$other->fetchColumn() === 0, 'other account does not see this note by account_id filter');
}

$pdo->prepare("UPDATE call_notes SET title=?, body=? WHERE id=? AND account_id=?")
    ->execute([$marker.'-edited', 'Updated body', $id, $a]);
$row = $pdo->prepare("SELECT title, body FROM call_notes WHERE id=? AND account_id=?");
$row->execute([$id, $a]);
$r = $row->fetch();
assert_true($r && $r['title'] === $marker.'-edited' && $r['body'] === 'Updated body', 'owner can update');

$stolen = $pdo->prepare("DELETE FROM call_notes WHERE id=? AND account_id=?");
$stolen->execute([$id, $b !== $a ? $b : 0]);
$still = $pdo->prepare("SELECT COUNT(*) FROM call_notes WHERE id=?");
$still->execute([$id]);
assert_true((int)$still->fetchColumn() === 1, 'delete with wrong account_id does not remove note');

$pdo->prepare("DELETE FROM call_notes WHERE id=? AND account_id=?")->execute([$id, $a]);
$gone = $pdo->prepare("SELECT COUNT(*) FROM call_notes WHERE id=?");
$gone->execute([$id]);
assert_true((int)$gone->fetchColumn() === 0, 'owner can delete');

$pdo->prepare("DELETE FROM call_notes WHERE title LIKE 'notes-test-%'")->execute();

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
