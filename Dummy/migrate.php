<?php
// One-time DB migration — delete this file after running.
// Access: /migrate.php?token=sp_migrate_2026
if (($_GET['token'] ?? '') !== 'sp_migrate_2026') {
    http_response_code(403); die('Forbidden');
}

require_once '../includes/db.php';

$results = [];

// ── Create follow_ups table ───────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follow_ups (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        inquiry_id    VARCHAR(20) NOT NULL,
        note          TEXT NOT NULL,
        follow_up_date DATE NOT NULL,
        follow_up_time TIME NULL,
        assigned_to   VARCHAR(100) NOT NULL,
        completed     TINYINT(1) DEFAULT 0,
        completed_at  DATETIME NULL,
        completed_by  VARCHAR(100) NULL,
        created_by    VARCHAR(100) NOT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fu_inquiry (inquiry_id),
        INDEX idx_fu_date    (follow_up_date),
        INDEX idx_fu_assigned(assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Fix collation if table already existed with a different collation
    $pdo->exec("ALTER TABLE follow_ups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $results[] = ['ok', 'follow_ups — table ready, collation set to utf8mb4_unicode_ci ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'follow_ups — ERROR: '.$e->getMessage()];
}

$migrations = [
    // Column additions
    ['table'=>'inquiries',     'column'=>'secondary_email', 'sql'=>"ALTER TABLE inquiries ADD COLUMN secondary_email VARCHAR(150) NULL AFTER email"],
    ['table'=>'inquiries',     'column'=>'outcome_reason',  'sql'=>"ALTER TABLE inquiries ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome"],
    ['table'=>'stage_history', 'column'=>'outcome_reason',  'sql'=>"ALTER TABLE stage_history ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome"],
    ['table'=>'stage_history', 'column'=>'final_remark',    'sql'=>"ALTER TABLE stage_history ADD COLUMN final_remark TEXT NULL AFTER remark"],
    ['table'=>'inquiries',     'column'=>'designation',     'sql'=>"ALTER TABLE inquiries ADD COLUMN designation VARCHAR(150) NULL AFTER client"],
    ['table'=>'inquiries',     'column'=>'email_subject',   'sql'=>"ALTER TABLE inquiries ADD COLUMN email_subject VARCHAR(255) NULL AFTER website"],
    ['table'=>'inquiries',     'column'=>'admin_remark',    'sql'=>"ALTER TABLE inquiries ADD COLUMN admin_remark TEXT NULL"],
];

// Index migrations (check by index name)
$indexMigrations = [
    ['table'=>'inquiries',     'index'=>'idx_inq_created_by',    'sql'=>"CREATE INDEX idx_inq_created_by    ON inquiries(created_by)"],
    ['table'=>'inquiries',     'index'=>'idx_inq_current_owner', 'sql'=>"CREATE INDEX idx_inq_current_owner ON inquiries(current_owner)"],
    ['table'=>'inquiries',     'index'=>'idx_inq_created_at',    'sql'=>"CREATE INDEX idx_inq_created_at    ON inquiries(created_at)"],
    ['table'=>'inquiry_steps', 'index'=>'idx_steps_assigned_to', 'sql'=>"CREATE INDEX idx_steps_assigned_to ON inquiry_steps(assigned_to)"],
    ['table'=>'inquiry_steps', 'index'=>'idx_steps_assigned_by', 'sql'=>"CREATE INDEX idx_steps_assigned_by ON inquiry_steps(assigned_by)"],
];

foreach ($indexMigrations as $m) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $check->execute([$m['table'], $m['index']]);
    if ((int)$check->fetchColumn() > 0) {
        $results[] = ['skip', $m['table'].'('.$m['index'].') — index already exists, skipped'];
        continue;
    }
    try {
        $pdo->exec($m['sql']);
        $results[] = ['ok', $m['table'].'('.$m['index'].') — index added ✓'];
    } catch (Exception $e) {
        $results[] = ['err', $m['table'].'('.$m['index'].') — ERROR: '.$e->getMessage()];
    }
}

foreach ($migrations as $m) {
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $check->execute([$m['table'], $m['column']]);
    if ((int)$check->fetchColumn() > 0) {
        $results[] = ['skip', $m['table'].'.'.$m['column'].' — already exists, skipped'];
        continue;
    }
    try {
        $pdo->exec($m['sql']);
        $results[] = ['ok', $m['table'].'.'.$m['column'].' — added ✓'];
    } catch (Exception $e) {
        $results[] = ['err', $m['table'].'.'.$m['column'].' — ERROR: '.$e->getMessage()];
    }
}
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Migration</title>
<style>body{font-family:monospace;padding:2rem;background:#f4f6f8}h2{color:#172B3A}.ok{color:#16803C}.skip{color:#667085}.err{color:#B42318}li{margin:.4rem 0;font-size:14px}</style>
</head><body>
<h2>DB Migration — Survey Pacific</h2>
<ul>
<?php foreach ($results as [$type,$msg]): ?>
  <li class="<?= $type ?>"><?= htmlspecialchars($msg) ?></li>
<?php endforeach; ?>
</ul>
<p style="margin-top:1.5rem;font-size:13px;color:#B42318"><strong>Delete this file from the server after running.</strong></p>
</body></html>
