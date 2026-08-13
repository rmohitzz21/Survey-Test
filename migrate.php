<?php
// One-time DB migration — delete this file after running.
// Access: /migrate.php?token=sp_migrate_2026
if (($_GET['token'] ?? '') !== 'sp_migrate_2026') {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__.'/includes/db.php';

$results = [];

// ── Base tables (CREATE IF NOT EXISTS — safe to run on existing DBs) ──────────

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100)  NOT NULL,
        email         VARCHAR(150)  NOT NULL UNIQUE,
        password_hash VARCHAR(255)  NOT NULL,
        role          VARCHAR(50)   NOT NULL DEFAULT 'Member',
        status        ENUM('approved','pending','rejected','blocked') NOT NULL DEFAULT 'pending',
        requested_at  DATE          NOT NULL,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'accounts — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'accounts — ERROR: '.$e->getMessage()];
}

// Seed default admin if table is empty
try {
    if ((int)$pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO accounts (name,email,password_hash,role,status,requested_at) VALUES
            ('Admin','admin@surveypacific.com','\$2y\$10\$WhuFm.9AH94Bl9yMoXJ29OJMRA.r3eYPDSCoeD.kK0PhPwPcf8zui','Master Admin','approved',CURDATE())");
        $results[] = ['ok', 'accounts — default admin seeded (password: Survey@404) ✓'];
    } else {
        $results[] = ['skip', 'accounts — already has users, seed skipped'];
    }
} catch (Exception $e) {
    $results[] = ['err', 'accounts seed — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id             VARCHAR(20)  PRIMARY KEY,
        date           VARCHAR(20)  NOT NULL,
        inquiry_type   VARCHAR(20)  NOT NULL DEFAULT 'Client Project',
        client         VARCHAR(150) NOT NULL,
        designation    VARCHAR(150),
        company        VARCHAR(150) NOT NULL,
        country        VARCHAR(100) NOT NULL DEFAULT 'India',
        is_new         TINYINT(1)   NOT NULL DEFAULT 0,
        client_type    VARCHAR(50)  NOT NULL DEFAULT 'New',
        requirement    TEXT         NOT NULL,
        created_by     VARCHAR(100) NOT NULL,
        current_owner  VARCHAR(100) NOT NULL,
        due_date       VARCHAR(20)  NOT NULL DEFAULT 'TBD',
        stage          VARCHAR(100) NOT NULL DEFAULT 'Inquiry Received',
        outcome        VARCHAR(100) NOT NULL DEFAULT 'In Progress',
        outcome_reason VARCHAR(255),
        proposal_value VARCHAR(30),
        delivery_date  VARCHAR(20),
        follow_up_date VARCHAR(20),
        final_value    VARCHAR(30),
        email          VARCHAR(150),
        secondary_email VARCHAR(150),
        phone          VARCHAR(30),
        website        VARCHAR(255),
        email_subject  VARCHAR(255),
        admin_remark   TEXT,
        created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'inquiries — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'inquiries — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stage_history (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        inquiry_id     VARCHAR(20)  NOT NULL,
        stage          VARCHAR(100) NOT NULL,
        outcome        VARCHAR(100) NOT NULL,
        outcome_reason VARCHAR(255),
        by_user        VARCHAR(100) NOT NULL,
        date           VARCHAR(50)  NOT NULL,
        remark         TEXT,
        final_remark   TEXT,
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'stage_history — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'stage_history — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiry_steps (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        inquiry_id  VARCHAR(20)  NOT NULL,
        assigned_by VARCHAR(100) NOT NULL,
        assigned_to VARCHAR(100) NOT NULL,
        instruction TEXT         NOT NULL,
        remark      TEXT,
        status      VARCHAR(50)  NOT NULL DEFAULT 'New',
        due         VARCHAR(20)  NOT NULL DEFAULT 'TBD',
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'inquiry_steps — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'inquiry_steps — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS step_attachments (
        id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        step_id  INT UNSIGNED NOT NULL,
        filename VARCHAR(255) NOT NULL,
        INDEX idx_sa_step (step_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'step_attachments — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'step_attachments — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiry_attachments (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        inquiry_id  VARCHAR(20) NOT NULL,
        filename    VARCHAR(255) NOT NULL,
        uploaded_by VARCHAR(100) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ia_inquiry (inquiry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'inquiry_attachments — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'inquiry_attachments — ERROR: '.$e->getMessage()];
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT          NOT NULL,
        title      VARCHAR(255) NOT NULL,
        body       TEXT         NOT NULL,
        is_read    TINYINT(1)   NOT NULL DEFAULT 0,
        inquiry_id VARCHAR(20),
        created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'notifications — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'notifications — ERROR: '.$e->getMessage()];
}

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
    $pdo->exec("ALTER TABLE follow_ups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $results[] = ['ok', 'follow_ups — table ready ✓'];
} catch (Exception $e) {
    $results[] = ['err', 'follow_ups — ERROR: '.$e->getMessage()];
}

// ── Column migrations (ALTER — skipped if column already exists) ──────────────

$migrations = [
    ['table'=>'inquiry_steps', 'column'=>'updated_at',      'sql'=>"ALTER TABLE inquiry_steps ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"],
    ['table'=>'inquiries',     'column'=>'secondary_email',  'sql'=>"ALTER TABLE inquiries ADD COLUMN secondary_email VARCHAR(150) NULL AFTER email"],
    ['table'=>'inquiries',     'column'=>'outcome_reason',   'sql'=>"ALTER TABLE inquiries ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome"],
    ['table'=>'stage_history', 'column'=>'outcome_reason',   'sql'=>"ALTER TABLE stage_history ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome"],
    ['table'=>'stage_history', 'column'=>'final_remark',     'sql'=>"ALTER TABLE stage_history ADD COLUMN final_remark TEXT NULL AFTER remark"],
    ['table'=>'inquiries',     'column'=>'designation',      'sql'=>"ALTER TABLE inquiries ADD COLUMN designation VARCHAR(150) NULL AFTER client"],
    ['table'=>'inquiries',     'column'=>'email_subject',    'sql'=>"ALTER TABLE inquiries ADD COLUMN email_subject VARCHAR(255) NULL AFTER website"],
    ['table'=>'inquiries',     'column'=>'admin_remark',     'sql'=>"ALTER TABLE inquiries ADD COLUMN admin_remark TEXT NULL"],
    ['table'=>'inquiries',     'column'=>'inquiry_type',     'sql'=>"ALTER TABLE inquiries ADD COLUMN inquiry_type VARCHAR(20) NOT NULL DEFAULT 'Client Project' AFTER date"],
];

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

// ── Index migrations (skipped if index already exists) ────────────────────────

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
