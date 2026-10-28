<?php
// Shared document library — any signed-in user can list/download, only Admins
// can add or remove. Upload is multipart (files[]), everything else is JSON.
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$user = current_user();

try {
$b      = get_json_body();
$action = $_POST['action'] ?? ($b['action'] ?? ($_GET['action'] ?? ''));
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);

// UAT/prod may not have run migrate.php — create the table on first use.
$pdo->exec("CREATE TABLE IF NOT EXISTS resources (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    filename    VARCHAR(255) NOT NULL,
    uploaded_by VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$dir = dirname(__DIR__).'/uploads/resources/';

// ─── List (newest first) ─────────────────────────────────────────────────────
if ($action === 'list') {
    $rows = $pdo->query("SELECT id, title, filename, uploaded_by, created_at FROM resources ORDER BY id DESC")->fetchAll();
    foreach ($rows as &$r) {
        $p = $dir.$r['filename'];
        $r['size'] = file_exists($p) ? (int)filesize($p) : 0;
        $r['ext']  = strtolower(pathinfo($r['filename'], PATHINFO_EXTENSION));
    }
    json_response(['ok'=>true, 'data'=>$rows]);
}

// ─── Upload (Admin / Master Admin only) ──────────────────────────────────────
if ($action === 'upload') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can add resources.'], 403);

    $allowed  = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','zip','csv','txt'];
    $maxBytes = 10 * 1024 * 1024; // 10 MB per file
    $files    = $_FILES['files'] ?? [];
    $count    = is_array($files['tmp_name'] ?? null) ? count($files['tmp_name']) : 0;
    // PHP discards the whole body past post_max_size, leaving $_FILES empty.
    if (!$count && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        json_response(['ok'=>false,'message'=>'The upload was larger than this server accepts ('
            .(ini_get('post_max_size') ?: '?').' per request, '
            .(ini_get('upload_max_filesize') ?: '?').' per file).'], 413);
    }
    if (!$count) json_response(['ok'=>false,'message'=>'No file selected.'], 400);

    $title  = trim($_POST['title'] ?? '');
    $saved  = [];
    $errors = [];

    for ($i = 0; $i < $count; $i++) {
        $orig = basename((string)($files['name'][$i] ?? 'file'));
        $err  = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = $orig.' — larger than this server accepts ('.(ini_get('upload_max_filesize') ?: '?').')';
            continue;
        }
        $safe = sp_upload_ok($files, $i, $allowed, $maxBytes);
        if ($safe === null) {
            $errors[] = $orig.' — not an allowed file type, empty, or over 10 MB';
            continue;
        }
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $errors[] = $orig.' — could not create uploads/resources/ (check write permissions)';
            continue;
        }
        if (file_exists($dir.$safe))
            $safe = pathinfo($safe, PATHINFO_FILENAME).'_'.time().'.'.pathinfo($safe, PATHINFO_EXTENSION);
        if (!move_uploaded_file($files['tmp_name'][$i], $dir.$safe)) {
            $errors[] = $orig.' — could not be written to uploads/resources/ (check write permissions)';
            continue;
        }
        // One title for one file; a multi-select falls back to each file's own name.
        $label = ($count === 1 && $title !== '') ? $title : pathinfo($orig, PATHINFO_FILENAME);
        try {
            $pdo->prepare("INSERT INTO resources (title, filename, uploaded_by) VALUES (?,?,?)")
                ->execute([mb_substr($label, 0, 200), $safe, $user['name']]);
        } catch (Throwable $e) {
            // A file with no row can never be served — don't leave one behind.
            @unlink($dir.$safe);
            $errors[] = $orig.' — saved but could not be recorded, so it was removed: '.$e->getMessage();
            continue;
        }
        $saved[] = $safe;
    }

    if (!$saved) json_response(['ok'=>false,'errors'=>$errors,
        'message'=>$errors[0] ?? 'No valid files — allowed: PDF, Word, Excel, PowerPoint, images, ZIP, CSV, TXT (max 10 MB).'], 400);
    json_response(['ok'=>true, 'saved'=>$saved, 'errors'=>$errors]);
}

// ─── Rename (Admin / Master Admin only) ──────────────────────────────────────
if ($action === 'rename') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can edit resources.'], 403);
    $id    = (int)($b['id'] ?? 0);
    $title = trim($b['title'] ?? '');
    if ($id < 1)      json_response(['ok'=>false,'message'=>'Invalid resource.'], 400);
    if ($title === '') json_response(['ok'=>false,'message'=>'Title cannot be empty.'], 400);
    $stmt = $pdo->prepare("UPDATE resources SET title=? WHERE id=?");
    $stmt->execute([mb_substr($title, 0, 200), $id]);
    json_response(['ok'=>true]);
}

// ─── Delete (Admin / Master Admin only) ──────────────────────────────────────
if ($action === 'delete') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can delete resources.'], 403);
    $id = (int)($b['id'] ?? 0);
    if ($id < 1) json_response(['ok'=>false,'message'=>'Invalid resource.'], 400);
    $sel = $pdo->prepare("SELECT filename FROM resources WHERE id=?");
    $sel->execute([$id]);
    $fn = $sel->fetchColumn();
    if ($fn === false) json_response(['ok'=>false,'message'=>'Resource not found.'], 404);
    $pdo->prepare("DELETE FROM resources WHERE id=?")->execute([$id]);
    // Only unlink once no other row points at the same file.
    $still = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE filename=?");
    $still->execute([$fn]);
    if (!(int)$still->fetchColumn() && file_exists($dir.$fn)) unlink($dir.$fn);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Exception $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
