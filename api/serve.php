<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
require_auth_json();

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
