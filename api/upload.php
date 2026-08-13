<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method not allowed'], 405);

$uploader  = current_user();
$allowed   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','zip','csv','txt'];
$files     = $_FILES['files'] ?? [];
$count     = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;

// ── Inquiry-level attachment ──────────────────────────────────────────────────
$inquiryId = trim($_POST['inquiry_id'] ?? '');
if ($inquiryId) {
    $chk = $pdo->prepare("SELECT created_by FROM inquiries WHERE id=?");
    $chk->execute([$inquiryId]);
    $inqRow = $chk->fetch();
    if (!$inqRow) json_response(['ok'=>false,'message'=>'Inquiry not found'], 404);
    if (!is_admin() && $inqRow['created_by'] !== $uploader['name']) {
        $sc = $pdo->prepare("SELECT COUNT(*) FROM inquiry_steps WHERE inquiry_id=? AND (assigned_to=? OR assigned_by=?)");
        $sc->execute([$inquiryId, $uploader['name'], $uploader['name']]);
        if ((int)$sc->fetchColumn() === 0)
            json_response(['ok'=>false,'message'=>'Not authorised to upload to this inquiry.'], 403);
    }

    $uploadDir = dirname(__DIR__).'/uploads/inq/'.$inquiryId.'/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $saved = [];
    for ($i = 0; $i < $count; $i++) {
        $tmp  = $files['tmp_name'][$i];
        $name = $files['name'][$i];
        if (!is_uploaded_file($tmp)) continue;
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;
        $stem = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo(basename($name), PATHINFO_FILENAME));
        $safe = $stem.'.'.$ext;
        if (file_exists($uploadDir.$safe))
            $safe = $stem.'_'.time().'.'.$ext;
        if (move_uploaded_file($tmp, $uploadDir.$safe)) {
            $pdo->prepare("INSERT INTO inquiry_attachments (inquiry_id,filename,uploaded_by) VALUES (?,?,?)")
                ->execute([$inquiryId, $safe, $uploader['name']]);
            $saved[] = $safe;
        }
    }
    json_response(['ok'=>true,'saved'=>$saved,'data'=>load_inquiries($pdo,$uploader)]);
}

// ── Step-level attachment ─────────────────────────────────────────────────────
$stepId = (int)($_POST['step_id'] ?? 0);
if (!$stepId) json_response(['ok'=>false,'message'=>'step_id or inquiry_id required']);

$check = $pdo->prepare("SELECT s.assigned_to, s.assigned_by, i.created_by
    FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
$check->execute([$stepId]);
$prow = $check->fetch();
if (!$prow) json_response(['ok'=>false,'message'=>'Step not found'], 404);
if (!is_admin()
    && $prow['assigned_to'] !== $uploader['name']
    && $prow['assigned_by'] !== $uploader['name']
    && $prow['created_by']  !== $uploader['name'])
    json_response(['ok'=>false,'message'=>'Not authorised to upload to this step.'], 403);

$uploadDir = dirname(__DIR__).'/uploads/'.$stepId.'/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$saved = [];
for ($i = 0; $i < $count; $i++) {
    $tmp  = $files['tmp_name'][$i];
    $name = $files['name'][$i];
    if (!is_uploaded_file($tmp)) continue;
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    $stem = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo(basename($name), PATHINFO_FILENAME));
    $safe = $stem.'.'.$ext;
    if (file_exists($uploadDir.$safe))
        $safe = $stem.'_'.time().'.'.$ext;
    if (move_uploaded_file($tmp, $uploadDir.$safe)) {
        try { $pdo->prepare("INSERT INTO step_attachments (step_id,filename) VALUES (?,?)")->execute([$stepId, $safe]); } catch(Exception $e) {}
        $saved[] = $safe;
    }
}

json_response(['ok'=>true,'saved'=>$saved]);
