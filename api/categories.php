<?php
/**
 * Project categories for Add Inquiry.
 * Defaults: Online, Offline. Staff can add more names; they appear for everyone.
 */
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_staff_json();

$user = current_user();

try {
$b = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? 'list');

$pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed built-ins once (case-insensitive check so we don't duplicate "online").
$seed = $pdo->query("SELECT COUNT(*) FROM project_categories")->fetchColumn();
if ((int)$seed === 0) {
    $ins = $pdo->prepare("INSERT INTO project_categories (name, is_default, created_by) VALUES (?,?,?)");
    $ins->execute(['Online', 1, 'system']);
    $ins->execute(['Offline', 1, 'system']);
} else {
    foreach (['Online', 'Offline'] as $def) {
        $chk = $pdo->prepare("SELECT id FROM project_categories WHERE LOWER(TRIM(name))=LOWER(?)");
        $chk->execute([$def]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO project_categories (name, is_default, created_by) VALUES (?,?,?)")
                ->execute([$def, 1, 'system']);
        }
    }
}

// Ensure inquiries.project_category exists (safe if already migrated).
try {
    $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inquiries' AND COLUMN_NAME='project_category'")->fetchColumn();
    if ((int)$has === 0) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN project_category VARCHAR(100) NULL AFTER inquiry_type");
    }
} catch (Exception $e) { /* ignore — create still works without the column */ }

if ($action === 'list') {
    $rows = $pdo->query("SELECT id, name, is_default, created_by, created_at
        FROM project_categories ORDER BY is_default DESC, name ASC")->fetchAll();
    json_response(['ok'=>true, 'data'=>$rows]);
}

if ($action === 'add') {
    if (!is_admin()) json_response(['ok'=>false, 'message'=>'Only Admin can add project categories.'], 403);
    $name = trim((string)($b['name'] ?? ''));
    if ($name === '') json_response(['ok'=>false, 'message'=>'Category name is required.'], 400);
    if (mb_strlen($name) > 100) json_response(['ok'=>false, 'message'=>'Name must be 100 characters or less.'], 400);

    // Reuse existing row if same name (any case) so the dropdown stays clean.
    $chk = $pdo->prepare("SELECT id, name, is_default, created_by, created_at
        FROM project_categories WHERE LOWER(TRIM(name))=LOWER(TRIM(?))");
    $chk->execute([$name]);
    $existing = $chk->fetch();
    if ($existing) {
        json_response(['ok'=>true, 'data'=>$existing, 'existed'=>true]);
    }

    try {
        $pdo->prepare("INSERT INTO project_categories (name, is_default, created_by) VALUES (?,?,?)")
            ->execute([$name, 0, $user['name']]);
        $id = (int)$pdo->lastInsertId();
        $row = $pdo->prepare("SELECT id, name, is_default, created_by, created_at FROM project_categories WHERE id=?");
        $row->execute([$id]);
        json_response(['ok'=>true, 'data'=>$row->fetch(), 'existed'=>false]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $chk->execute([$name]);
            json_response(['ok'=>true, 'data'=>$chk->fetch(), 'existed'=>true]);
        }
        throw $e;
    }
}

json_response(['ok'=>false, 'message'=>'Unknown action'], 400);
} catch (Exception $e) {
    json_response(['ok'=>false, 'message'=>'Server error: '.$e->getMessage()], 500);
}
