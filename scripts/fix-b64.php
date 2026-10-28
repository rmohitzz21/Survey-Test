<?php
/**
 * One-time cleanup: decode __b64__-prefixed values.
 * Quarantined under scripts/ (HTTP denied). Run via CLI only:
 *   php scripts/fix-b64.php --confirm
 */
$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Forbidden — run via CLI: php scripts/fix-b64.php --confirm\n");
}
if (!in_array('--confirm', $argv ?? [], true)) {
    fwrite(STDERR, "Refusing to run without --confirm\n");
    exit(1);
}

$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
require_once $root . '/includes/db.php';

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

echo "Fixed $total records.\n";
