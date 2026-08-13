<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$b      = get_json_body();
$action = $b['action'] ?? '';
$user   = current_user();

try {

// ─── Add ─────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $inquiryId  = $b['inquiryId'] ?? '';
    $note       = trim($b['note'] ?? '');
    $date       = $b['date'] ?? '';
    $time       = $b['time'] ?: null;
    $assignedTo = $b['assignedTo'] ?? $user['name'];
    if (!$inquiryId || !$note || !$date)
        json_response(['ok'=>false,'message'=>'Inquiry, note and date are required.'], 400);
    $pdo->prepare("INSERT INTO follow_ups (inquiry_id,note,follow_up_date,follow_up_time,assigned_to,created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$inquiryId, $note, $date, $time, $assignedTo, $user['name']]);
    $newId = (int)$pdo->lastInsertId();
    if ($assignedTo !== $user['name'])
        notify_user($pdo, $assignedTo, 'Follow-up scheduled — '.$inquiryId,
            $user['name'].' scheduled a follow-up on '.date('d M Y', strtotime($date)).': '.$note, $inquiryId);
    json_response(['ok'=>true, 'id'=>$newId]);
}

// ─── Complete ─────────────────────────────────────────────────────────────────
if ($action === 'complete') {
    $id = (int)($b['id'] ?? 0);
    $pdo->prepare("UPDATE follow_ups SET completed=1,completed_at=NOW(),completed_by=? WHERE id=?")
        ->execute([$user['name'], $id]);
    json_response(['ok'=>true]);
}

// ─── Delete ──────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($b['id'] ?? 0);
    if (!is_admin()) {
        $row = $pdo->prepare("SELECT created_by FROM follow_ups WHERE id=?");
        $row->execute([$id]);
        $fu = $row->fetch();
        if (!$fu || $fu['created_by'] !== $user['name'])
            json_response(['ok'=>false,'message'=>'Only the creator or an admin can delete this.'], 403);
    }
    $pdo->prepare("DELETE FROM follow_ups WHERE id=?")->execute([$id]);
    json_response(['ok'=>true]);
}

// ─── Load for Inquiry ─────────────────────────────────────────────────────────
if ($action === 'load') {
    $inquiryId = $b['inquiryId'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM follow_ups WHERE inquiry_id=? ORDER BY completed ASC, follow_up_date ASC, follow_up_time ASC");
    $stmt->execute([$inquiryId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['completed'] = (int)$r['completed']; } unset($r);
    json_response(['ok'=>true, 'data'=>$rows]);
}

// ─── Load All (admin only — for reports) ─────────────────────────────────────
if ($action === 'loadAll') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Unauthorized'], 403);
    $stmt = $pdo->prepare("SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id ORDER BY f.completed ASC,f.follow_up_date ASC,f.follow_up_time ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) { $r['completed'] = (int)$r['completed']; } unset($r);
    json_response(['ok'=>true, 'data'=>$rows]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Exception $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
