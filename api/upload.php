<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_staff_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
require_csrf();

$uploader  = current_user();
$allowed   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','gif','zip','csv','txt'];
$maxBytes  = 10 * 1024 * 1024; // 10 MB per file
$files     = $_FILES['files'] ?? [];
$count     = is_array($files['tmp_name'] ?? null) ? count($files['tmp_name']) : 0;

// PHP silently discards the whole body when it exceeds post_max_size: $_POST and $_FILES
// both arrive empty, which used to surface as the misleading "no valid files" message.
if (!$count && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_response(['ok'=>false,'message'=>'The upload was larger than this server accepts ('
        .(ini_get('post_max_size') ?: '?').' per request, '
        .(ini_get('upload_max_filesize') ?: '?').' per file). Send fewer or smaller files.'], 413);
}

// sp_upload_ok() lives in includes/functions.php — api/resources.php uses it too.
//
// Saves one validated file and records it, appending to $saved or $errors. Every failure used
// to be silent: a file rejected by validation, one the server could not write, and one whose
// database row failed all looked identical to the caller — "uploaded" with nothing stored.
// The row failure was the worst, since a file on disk with no row is invisible forever, so it
// now deletes the orphan rather than leaving disk and database disagreeing.
$saved = [];
$errors = [];
$store = function (int $i, string $dir, callable $record) use ($files, $allowed, $maxBytes, &$saved, &$errors): void {
    $orig = basename((string)($files['name'][$i] ?? 'file'));
    $err  = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = $orig.' — larger than this server accepts ('.(ini_get('upload_max_filesize') ?: '?').')';
        return;
    }
    if ($err === UPLOAD_ERR_NO_TMP_DIR || $err === UPLOAD_ERR_CANT_WRITE) {
        $errors[] = $orig.' — the server has no writable temp folder for uploads';
        return;
    }
    $safe = sp_upload_ok($files, $i, $allowed, $maxBytes);
    if ($safe === null) {
        $errors[] = $orig.' — not an allowed file type, empty, or over 10 MB';
        return;
    }
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $errors[] = $orig.' — could not create the upload folder (check write permissions on uploads/)';
        return;
    }
    if (file_exists($dir.$safe))
        $safe = pathinfo($safe, PATHINFO_FILENAME).'_'.time().'.'.pathinfo($safe, PATHINFO_EXTENSION);
    if (!move_uploaded_file($files['tmp_name'][$i], $dir.$safe)) {
        $errors[] = $orig.' — could not be written to uploads/ (check write permissions)';
        return;
    }
    try {
        $record($safe);
    } catch (Throwable $e) {
        @unlink($dir.$safe);
        $errors[] = $orig.' — saved but could not be recorded, so it was removed: '.$e->getMessage();
        return;
    }
    $saved[] = $safe;
};

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

    for ($i = 0; $i < $count; $i++) {
        $store($i, $uploadDir, fn(string $f) =>
            $pdo->prepare("INSERT INTO inquiry_attachments (inquiry_id,filename,uploaded_by) VALUES (?,?,?)")
                ->execute([$inquiryId, $f, $uploader['name']]));
    }
    json_response(['ok'=>true,'saved'=>$saved,'errors'=>$errors,'data'=>load_inquiries($pdo,$uploader)]);
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

// This insert used to be wrapped in `catch(Exception $e) {}`. api/serve.php will only hand out
// a file that has a step_attachments row, so swallowing the error left the file on disk and
// permanently unreachable — the exact "it uploaded but nothing is stored" symptom.
for ($i = 0; $i < $count; $i++) {
    $store($i, $uploadDir, fn(string $f) =>
        $pdo->prepare("INSERT INTO step_attachments (step_id,filename) VALUES (?,?)")
            ->execute([$stepId, $f]));
}

json_response(['ok'=>true,'saved'=>$saved,'errors'=>$errors]);
