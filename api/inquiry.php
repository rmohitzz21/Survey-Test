<?php
// Single inquiry operations: stage update, add step, update step status/remark, delete step
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$method = $_SERVER['REQUEST_METHOD'];
$b      = get_json_body();
$action = $b['action'] ?? '';
$user   = current_user();

// ─── Load History (lazy) ─────────────────────────────────────────────────────
if ($action === 'load_history') {
    $id = $b['id'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM stage_history WHERE inquiry_id=? ORDER BY id DESC");
    $stmt->execute([$id]);
    json_response(['ok'=>true, 'data'=>$stmt->fetchAll()]);
}

// ─── Stage Update ─────────────────────────────────────────────────────────────
if ($action === 'stage_update') {
    $id = $b['id'] ?? '';
    // Only admins or the inquiry's own creator can update the stage
    if (!is_admin()) {
        $ownerStmt = $pdo->prepare("SELECT created_by FROM inquiries WHERE id=?");
        $ownerStmt->execute([$id]);
        $creator = $ownerStmt->fetchColumn();
        if ($creator !== $user['name']) {
            json_response(['ok'=>false,'message'=>'Only admins or the inquiry creator can update the stage.'], 403);
        }
    }
    $pdo->prepare("UPDATE inquiries SET stage=?,outcome=?,outcome_reason=?,proposal_value=?,delivery_date=?,follow_up_date=?,final_value=? WHERE id=?")
        ->execute([$b['stage'],$b['outcome'],$b['outcomeReason']??null,$b['proposalValue']??null,$b['deliveryDate']??null,$b['followUpDate']??null,$b['finalValue']??null,$id]);
    $pdo->prepare("INSERT INTO stage_history (inquiry_id,stage,outcome,outcome_reason,by_user,date,remark,final_remark) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$id,$b['stage'],$b['outcome'],$b['outcomeReason']??'',$user['name'],date('d M Y · H:i'),$b['remark']??'',$b['finalRemark']??'']);

    // Notify the inquiry creator and current_owner (skip the person making the change)
    $inqStmt = $pdo->prepare("SELECT created_by, current_owner, client FROM inquiries WHERE id=?");
    $inqStmt->execute([$id]);
    $inqRow = $inqStmt->fetch();
    if ($inqRow) {
        $ntitle = 'Stage updated — '.$id;
        $nbody  = $user['name'].' changed stage to "'.$b['stage'].'" ('.$inqRow['client'].')';
        if ($inqRow['created_by'] !== $user['name'])
            notify_user($pdo, $inqRow['created_by'], $ntitle, $nbody, $id);
        if ($inqRow['current_owner'] !== $user['name'] && $inqRow['current_owner'] !== $inqRow['created_by'])
            notify_user($pdo, $inqRow['current_owner'], $ntitle, $nbody, $id);
    }

    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Add Step ─────────────────────────────────────────────────────────────────
if ($action === 'add_step') {
    // Only admin, inquiry creator, or someone already assigned a step on this inquiry
    if (!is_admin()) {
        $inqId = $b['inquiryId'] ?? '';
        $accessStmt = $pdo->prepare("SELECT created_by FROM inquiries WHERE id=?");
        $accessStmt->execute([$inqId]);
        $creator = $accessStmt->fetchColumn();
        if ($creator !== $user['name']) {
            $stepCheck = $pdo->prepare("SELECT COUNT(*) FROM inquiry_steps WHERE inquiry_id=? AND assigned_to=?");
            $stepCheck->execute([$inqId, $user['name']]);
            if ((int)$stepCheck->fetchColumn() === 0) {
                json_response(['ok'=>false,'message'=>'Only admins, the inquiry creator, or assigned team members can add steps.'], 403);
            }
        }
    }
    $due = $b['due'] ?? 'TBD';
    if ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $due = date('d M Y', strtotime($due));
        if (!empty($b['dueTime'])) $due .= ' · '.date('h:i A', strtotime($b['dueTime']));
    }
    $ins = $pdo->prepare("INSERT INTO inquiry_steps (inquiry_id,assigned_by,assigned_to,instruction,status,due) VALUES (?,?,?,?,?,?)");
    $ins->execute([$b['inquiryId'],$user['name'],$b['assignedTo'],$b['instruction'],'New',$due]);
    $stepId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE inquiries SET current_owner=? WHERE id=?")->execute([$b['assignedTo'],$b['inquiryId']]);
    $instruction = $b['instruction'] ?? '';
    $preview = mb_strimwidth(strip_tags($instruction), 0, 80, '…');
    notify_user($pdo, $b['assignedTo'], 'New task — '.$b['inquiryId'], $user['name'].' assigned: '.$preview, $b['inquiryId']);
    json_response(['ok'=>true,'stepId'=>$stepId,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Update Step Status ───────────────────────────────────────────────────────
if ($action === 'step_status') {
    $newStatus = $b['status'] ?? '';
    $stepId    = (int)($b['stepId'] ?? 0);
    $validStatuses = ['New','Pending','In Progress','Client/Team Se Reply Pending','Checking Me','On Hold','Blocked','Submitted for Review','Other','Completed','Done','Rejected','Cancelled'];
    if (!in_array($newStatus, $validStatuses))
        json_response(['ok'=>false,'message'=>'Invalid status value.'], 400);

    // Fetch step + parent inquiry in one query
    $sinfo = $pdo->prepare("SELECT s.assigned_by, s.assigned_to, s.instruction, s.remark,
        i.id AS inq_id, i.created_by, i.client
        FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
    $sinfo->execute([$stepId]);
    $srow = $sinfo->fetch();

    // Assignee can mark their own task Done; Rejected is restricted to assigner/creator/admin
    if (in_array($newStatus, ['Done','Completed'])) {
        if (!is_admin()
            && ($srow['assigned_by'] ?? '') !== $user['name']
            && ($srow['created_by']  ?? '') !== $user['name']
            && ($srow['assigned_to'] ?? '') !== $user['name']) {
            json_response(['ok'=>false,'message'=>'Only the task assigner, assignee, or an admin can mark a step as completed.'], 403);
        }
    }
    if ($newStatus === 'Rejected') {
        if (!is_admin()
            && ($srow['assigned_by'] ?? '') !== $user['name']
            && ($srow['created_by']  ?? '') !== $user['name']) {
            json_response(['ok'=>false,'message'=>'Only the task assigner, inquiry creator, or an admin can reject a step.'], 403);
        }
    }

    $pdo->prepare("UPDATE inquiry_steps SET status=? WHERE id=?")->execute([$newStatus, $stepId]);

    if ($srow) {
        $preview = mb_strimwidth(strip_tags($srow['instruction']), 0, 60, '…');
        $remarkText = trim(strip_tags($srow['remark'] ?? ''));
        $remarkSuffix = $remarkText ? ' — "'.mb_strimwidth($remarkText, 0, 80, '…').'"' : '';

        if ($newStatus === 'Submitted for Review') {
            // Notify the person who assigned this task so they can review
            if ($srow['assigned_by'] !== $user['name'])
                notify_user($pdo, $srow['assigned_by'],
                    'Work submitted for review — '.$srow['inq_id'],
                    $user['name'].' submitted work ('.$srow['client'].'): '.$preview.'. Please review and approve.'.$remarkSuffix,
                    $srow['inq_id']);
        } elseif (in_array($newStatus, ['Done','Completed'])) {
            // Notify inquiry creator
            if ($srow['created_by'] !== $user['name'])
                notify_user($pdo, $srow['created_by'],
                    'Task completed — '.$srow['inq_id'],
                    $user['name'].' completed a task ('.$srow['client'].'): '.$preview.$remarkSuffix,
                    $srow['inq_id']);
            // Also notify whoever assigned the task, if different from the creator
            if ($srow['assigned_by'] !== $user['name'] && $srow['assigned_by'] !== $srow['created_by'])
                notify_user($pdo, $srow['assigned_by'],
                    'Task completed — '.$srow['inq_id'],
                    $user['name'].' completed a task you assigned ('.$srow['client'].'): '.$preview.$remarkSuffix,
                    $srow['inq_id']);
        } elseif ($newStatus === 'Rejected') {
            // Notify the assignee so they know to rework
            if ($srow['assigned_to'] !== $user['name'])
                notify_user($pdo, $srow['assigned_to'],
                    'Work rejected — '.$srow['inq_id'],
                    $user['name'].' rejected your task ('.$srow['client'].'): '.$preview.'. Please rework and resubmit.',
                    $srow['inq_id']);
        } elseif ($srow['assigned_by'] !== $user['name']) {
            // Any other status change (In Progress, Pending, On Hold, etc.) — notify the assigner with the remark
            notify_user($pdo, $srow['assigned_by'],
                'Task updated — '.$srow['inq_id'],
                $user['name'].' set status to "'.$newStatus.'" ('.$srow['client'].'): '.$preview.$remarkSuffix,
                $srow['inq_id']);
        }

        if (in_array($newStatus, ['Done','Completed'])) {
            // Return current_owner to creator when no active steps remain
            $active = $pdo->prepare("SELECT COUNT(*) FROM inquiry_steps WHERE inquiry_id=? AND status NOT IN ('Done','Completed','Cancelled','Rejected')");
            $active->execute([$srow['inq_id']]);
            if ((int)$active->fetchColumn() === 0)
                $pdo->prepare("UPDATE inquiries SET current_owner=? WHERE id=?")->execute([$srow['created_by'], $srow['inq_id']]);
        }
    }

    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Admin Remark on Inquiry ─────────────────────────────────────────────────
if ($action === 'admin_remark') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Admin only'], 403);
    $id = $b['inquiryId'] ?? '';
    $pdo->prepare("UPDATE inquiries SET admin_remark=? WHERE id=?")->execute([$b['remark'] ?? null, $id]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Save Step Remark ─────────────────────────────────────────────────────────
if ($action === 'step_remark') {
    $chk = $pdo->prepare("SELECT assigned_to FROM inquiry_steps WHERE id=?");
    $chk->execute([$b['stepId']]);
    $srow = $chk->fetch();
    if (!is_admin() && ($srow['assigned_to'] ?? '') !== $user['name'])
        json_response(['ok'=>false,'message'=>'Only the task assignee can write a remark.'], 403);
    $pdo->prepare("UPDATE inquiry_steps SET remark=? WHERE id=?")->execute([$b['remark'],$b['stepId']]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Save Step Instruction — disabled: instructions are locked after creation ──
if ($action === 'step_instruction') {
    json_response(['ok'=>false,'message'=>'Task instructions are locked after creation.'], 403);
}

// ─── Delete Step ──────────────────────────────────────────────────────────────
if ($action === 'delete_step') {
    $stepId = (int)($b['stepId'] ?? 0);
    if (!is_admin()) {
        $ownerStmt = $pdo->prepare("SELECT assigned_by FROM inquiry_steps WHERE id=?");
        $ownerStmt->execute([$stepId]);
        if ($ownerStmt->fetchColumn() !== $user['name'])
            json_response(['ok'=>false,'message'=>'Only the task assigner or an admin can delete this step.'], 403);
    }
    // Clean up step attachment files from disk
    $saStmt = $pdo->prepare("SELECT filename FROM step_attachments WHERE step_id=?");
    $saStmt->execute([$stepId]);
    foreach ($saStmt->fetchAll() as $sa) {
        $p = dirname(__DIR__).'/uploads/'.$stepId.'/'.$sa['filename'];
        if (file_exists($p)) unlink($p);
    }
    @rmdir(dirname(__DIR__).'/uploads/'.$stepId);
    $pdo->prepare("DELETE FROM inquiry_steps WHERE id=?")->execute([$stepId]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Update Step Due Date ──────────────────────────────────────────────────────
if ($action === 'update_step_due') {
    if (!is_admin()) {
        $chk = $pdo->prepare("SELECT s.assigned_by, i.created_by FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
        $chk->execute([$b['stepId']]);
        $row = $chk->fetch();
        if (!$row || ($row['assigned_by'] !== $user['name'] && $row['created_by'] !== $user['name']))
            json_response(['ok'=>false,'message'=>'Only the task assigner, inquiry creator, or an admin can change the due date.'], 403);
    }
    $due = $b['due'] ?? 'TBD';
    if ($due && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $due = date('d M Y', strtotime($due));
        if (!empty($b['dueTime'])) $due .= ' · '.date('h:i A', strtotime($b['dueTime']));
    }
    $pdo->prepare("UPDATE inquiry_steps SET due=? WHERE id=?")->execute([$due, $b['stepId']]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Reassign Step ────────────────────────────────────────────────────────────
if ($action === 'update_step_assignee') {
    if (!is_admin()) {
        $chk = $pdo->prepare("SELECT s.assigned_by, i.created_by FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
        $chk->execute([$b['stepId']]);
        $row = $chk->fetch();
        if (!$row || ($row['assigned_by'] !== $user['name'] && $row['created_by'] !== $user['name']))
            json_response(['ok'=>false,'message'=>'Only the task assigner, inquiry creator, or an admin can reassign a step.'], 403);
    }
    $assignedTo = $b['assignedTo'] ?? '';
    $oldRow = $pdo->prepare("SELECT assigned_to, instruction FROM inquiry_steps WHERE id=?");
    $oldRow->execute([$b['stepId']]);
    $old = $oldRow->fetch();
    $pdo->prepare("UPDATE inquiry_steps SET assigned_to=? WHERE id=?")->execute([$assignedTo, $b['stepId']]);
    if (!empty($b['inquiryId'])) {
        $pdo->prepare("UPDATE inquiries SET current_owner=? WHERE id=?")->execute([$assignedTo, $b['inquiryId']]);
        $instr   = $old['instruction'] ?? '';
        $preview = strlen($instr) > 80 ? substr($instr, 0, 80).'…' : $instr;
        notify_user($pdo, $assignedTo, 'Task assigned — '.$b['inquiryId'], $user['name'].' assigned you: '.$preview, $b['inquiryId']);
        // Notify old assignee that their task was taken (P10)
        if ($old && $old['assigned_to'] && $old['assigned_to'] !== $assignedTo && $old['assigned_to'] !== $user['name'])
            notify_user($pdo, $old['assigned_to'], 'Task removed — '.$b['inquiryId'], $user['name'].' reassigned your task to '.$assignedTo.'.', $b['inquiryId']);
    }
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Delete Inquiry Attachment ────────────────────────────────────────────────
if ($action === 'delete_inquiry_attachment') {
    $attId = (int)($b['id'] ?? 0);
    $chk = $pdo->prepare("SELECT uploaded_by, filename, inquiry_id FROM inquiry_attachments WHERE id=?");
    $chk->execute([$attId]);
    $row = $chk->fetch();
    if (!$row) json_response(['ok'=>false,'message'=>'Attachment not found'], 404);
    if (!is_admin() && $row['uploaded_by'] !== $user['name'])
        json_response(['ok'=>false,'message'=>'Only the uploader or an admin can delete this attachment.'], 403);
    $path = dirname(__DIR__).'/uploads/inq/'.$row['inquiry_id'].'/'.$row['filename'];
    if (file_exists($path)) unlink($path);
    $pdo->prepare("DELETE FROM inquiry_attachments WHERE id=?")->execute([$attId]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Delete Inquiry (Master Admin only) ───────────────────────────────────────
if ($action === 'delete_inquiry') {
    if (!is_master_admin())
        json_response(['ok'=>false,'message'=>'Only Master Admin can delete an inquiry.'], 403);
    $id = $b['id'] ?? '';
    // Clean up inquiry attachment files from disk
    $inqAtts = $pdo->prepare("SELECT filename FROM inquiry_attachments WHERE inquiry_id=?");
    $inqAtts->execute([$id]);
    foreach ($inqAtts->fetchAll() as $a) {
        $p = dirname(__DIR__).'/uploads/inq/'.$id.'/'.$a['filename'];
        if (file_exists($p)) unlink($p);
    }
    @rmdir(dirname(__DIR__).'/uploads/inq/'.$id);
    // Clean up step attachment files from disk
    $stepIds = $pdo->prepare("SELECT id FROM inquiry_steps WHERE inquiry_id=?");
    $stepIds->execute([$id]);
    foreach ($stepIds->fetchAll() as $s) {
        $saStmt = $pdo->prepare("SELECT filename FROM step_attachments WHERE step_id=?");
        $saStmt->execute([$s['id']]);
        foreach ($saStmt->fetchAll() as $sa) {
            $p = dirname(__DIR__).'/uploads/'.$s['id'].'/'.$sa['filename'];
            if (file_exists($p)) unlink($p);
        }
        @rmdir(dirname(__DIR__).'/uploads/'.$s['id']);
    }
    $pdo->prepare("DELETE FROM notifications WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM follow_ups WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiry_attachments WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM step_attachments WHERE step_id IN (SELECT id FROM inquiry_steps WHERE inquiry_id=?)")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiry_steps WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM stage_history WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiries WHERE id=?")->execute([$id]);
    json_response(['ok'=>true,'data'=>load_inquiries($pdo,$user)]);
}

// ─── Delete Single Step Attachment ───────────────────────────────────────────
if ($action === 'delete_step_attachment') {
    $stepId   = (int)($b['stepId'] ?? 0);
    $filename = basename($b['filename'] ?? '');
    if (!$stepId || !$filename) json_response(['ok'=>false,'message'=>'Missing params'], 400);
    // Only assignee, assigner, inquiry creator, or admin
    if (!is_admin()) {
        $chk = $pdo->prepare("SELECT s.assigned_to, s.assigned_by, i.created_by FROM inquiry_steps s JOIN inquiries i ON i.id=s.inquiry_id WHERE s.id=?");
        $chk->execute([$stepId]);
        $row = $chk->fetch();
        if (!$row || !in_array($user['name'], [$row['assigned_to'],$row['assigned_by'],$row['created_by']]))
            json_response(['ok'=>false,'message'=>'Not authorised to delete this attachment.'], 403);
    }
    $path = dirname(__DIR__).'/uploads/'.$stepId.'/'.$filename;
    if (file_exists($path)) unlink($path);
    try { $pdo->prepare("DELETE FROM step_attachments WHERE step_id=? AND filename=?")->execute([$stepId, $filename]); } catch(Exception $e) {}
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);
