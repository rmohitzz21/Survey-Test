<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
require_auth_json();

// ── Shared resource (any signed-in user; filename comes from the DB) ──────────
$resourceId = (int)($_GET['resource_id'] ?? 0);
if ($resourceId) {
    $stmt = $pdo->prepare("SELECT filename FROM resources WHERE id=?");
    $stmt->execute([$resourceId]);
    $fn = $stmt->fetchColumn();
    if ($fn === false) { http_response_code(404); exit('Not found'); }
    $path = dirname(__DIR__).'/uploads/resources/'.basename($fn);
    if (!file_exists($path)) { http_response_code(404); exit('File missing'); }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    // Preview in-browser only for formats a browser renders without script.
    $viewable = ['application/pdf','image/jpeg','image/png','image/gif','text/plain'];
    $inline   = !empty($_GET['inline']) && in_array($mime, $viewable, true);
    header('Content-Type: '.$mime);
    header('X-Content-Type-Options: nosniff');
    header(($inline ? 'Content-Disposition: inline; filename="' : 'Content-Disposition: attachment; filename="').rawurlencode($fn).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

$filename = basename($_GET['file'] ?? '');
if (!$filename) { http_response_code(400); exit('Bad request'); }

// ── Inquiry attachment ────────────────────────────────────────────────────────
$inquiryId = trim($_GET['inquiry_id'] ?? '');
if ($inquiryId) {
    $stmt = $pdo->prepare("SELECT id FROM inquiry_attachments WHERE inquiry_id=? AND filename=?");
    $stmt->execute([$inquiryId, $filename]);
    if (!$stmt->fetch()) { http_response_code(404); exit('Not found'); }
    // Access: admin, or user connected to this inquiry (creator, step assignee/assigner)
    $user = current_user();
    if (!is_admin()) {
        $acc = $pdo->prepare("SELECT created_by FROM inquiries WHERE id=?");
        $acc->execute([$inquiryId]);
        $creator = $acc->fetchColumn();
        if ($creator !== $user['name']) {
            $sc = $pdo->prepare("SELECT COUNT(*) FROM inquiry_steps WHERE inquiry_id=? AND (assigned_to=? OR assigned_by=?)");
            $sc->execute([$inquiryId, $user['name'], $user['name']]);
            if ((int)$sc->fetchColumn() === 0) { http_response_code(403); exit('Forbidden'); }
        }
    }
    $path = dirname(__DIR__).'/uploads/inq/'.$inquiryId.'/'.$filename;
    if (!file_exists($path)) { http_response_code(404); exit('File missing'); }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

// ── Message attachment ────────────────────────────────────────────────────────
$msgId = (int)($_GET['msg_id'] ?? 0);
if ($msgId) {
    $user = current_user();
    $stmt = $pdo->prepare("SELECT sender_id,receiver_id,attachment FROM messages WHERE id=?");
    $stmt->execute([$msgId]);
    $row = $stmt->fetch();
    if (!$row || $row['attachment'] !== $filename) { http_response_code(404); exit('Not found'); }
    if ((int)$row['sender_id'] !== (int)$user['id'] && (int)$row['receiver_id'] !== (int)$user['id']) {
        http_response_code(403); exit('Forbidden');
    }
    $path = dirname(__DIR__).'/uploads/msg/'.$msgId.'/'.$filename;
    if (!file_exists($path)) { http_response_code(404); exit('File missing'); }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

// ── Step attachment ───────────────────────────────────────────────────────────
$stepId = (int)($_GET['step_id'] ?? 0);
if (!$stepId) { http_response_code(400); exit('Bad request'); }

$stmt = $pdo->prepare("SELECT id FROM step_attachments WHERE step_id=? AND filename=?");
$stmt->execute([$stepId, $filename]);
if (!$stmt->fetch()) { http_response_code(404); exit('Not found'); }

// Access: admin, or user connected to this step (assignee, assigner, or inquiry creator)
if (!is_admin()) {
    $user = $user ?? current_user();
    $sc = $pdo->prepare("SELECT s.assigned_to, s.assigned_by, i.created_by FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
    $sc->execute([$stepId]);
    $sr = $sc->fetch();
    if (!$sr || ($sr['assigned_to'] !== $user['name'] && $sr['assigned_by'] !== $user['name'] && $sr['created_by'] !== $user['name'])) {
        http_response_code(403); exit('Forbidden');
    }
}

$path = dirname(__DIR__).'/uploads/'.$stepId.'/'.$filename;
if (!file_exists($path)) { http_response_code(404); exit('File missing'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"');
header('Content-Length: '.filesize($path));
readfile($path);
