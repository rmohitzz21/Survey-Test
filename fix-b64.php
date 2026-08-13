<?php
// One-time cleanup: decode __b64__-prefixed values saved before auth.php was uploaded.
// Run once via browser, then DELETE this file.
require_once 'includes/db.php';

function fix_col(PDO $pdo, string $table, string $col, string $pk = 'id'): int {
    $n = 0;
    $rows = $pdo->query("SELECT `$pk`, `$col` FROM `$table` WHERE `$col` LIKE '__b64__%'")->fetchAll();
    foreach ($rows as $row) {
        $decoded = base64_decode(substr($row[$col], 7));
        $pdo->prepare("UPDATE `$table` SET `$col`=? WHERE `$pk`=?")->execute([$decoded, $row[$pk]]);
        $n++;
    }
    return $n;
}

$total = 0;
$total += fix_col($pdo, 'inquiries',      'requirement');
$total += fix_col($pdo, 'inquiry_steps',  'instruction');
$total += fix_col($pdo, 'inquiry_steps',  'remark');
$total += fix_col($pdo, 'stage_history',  'remark');
$total += fix_col($pdo, 'stage_history',  'final_remark');
$total += fix_col($pdo, 'follow_ups',     'note');

echo "Fixed $total records. Delete this file now.";
