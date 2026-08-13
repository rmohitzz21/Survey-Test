<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$method = $_SERVER['REQUEST_METHOD'];
$user   = current_user();

// GET — list all (server-side filtered by role)
if ($method === 'GET') {
    json_response(['ok'=>true, 'data'=>load_inquiries($pdo, $user)]);
}

// POST — create new inquiry
if ($method === 'POST') {
    $b = get_json_body();
    $createdBy   = $user['name']; // always the logged-in user, not overridable
    $inquiryType = ($b['inquiryType'] ?? 'Client Project') === 'Internal Usage' ? 'Internal Usage' : 'Client Project';
    $clientType  = $b['clientType'] ?? 'New';
    if (!in_array($clientType, ['New','Existing Contact / Prospect','Repeat','Existing'])) $clientType = 'New';

    // Internal-use inquiries skip client details — fall back to placeholders so NOT NULL columns stay meaningful
    $client  = trim($b['client'] ?? '');
    $company = trim($b['company'] ?? '');
    if ($inquiryType === 'Internal Usage') {
        if ($client === '')  $client  = 'Internal';
        if ($company === '') $company = 'Internal';
    }

    // Server-side check: if client name already exists anywhere in DB, it can't be "New"
    if ($clientType === 'New' && $inquiryType === 'Client Project' && $client !== '') {
        $ck = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE LOWER(TRIM(client))=LOWER(TRIM(?))");
        $ck->execute([$client]);
        if ((int)$ck->fetchColumn() > 0) $clientType = 'Repeat';
    }

    $ins = $pdo->prepare("INSERT INTO inquiries
        (id,date,inquiry_type,client,designation,company,country,is_new,client_type,requirement,created_by,current_owner,due_date,stage,outcome,proposal_value,email,secondary_email,phone,website,email_subject)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Inquiry Received','In Progress',?,?,?,?,?,?)");

    // Retry up to 3 times on duplicate ID (concurrent inserts)
    $id = null;
    for ($try = 0; $try < 3; $try++) {
        try {
            $id = next_inquiry_id($pdo);
            $ins->execute([
                $id, $b['date'] ?? date('d M Y'), $inquiryType,
                $client, $b['designation'] ?? '', $company, $b['country'] ?? 'India',
                $clientType === 'New' ? 1 : 0, $clientType,
                $b['requirement'] ?? '', $createdBy, $b['assignTo'] ?? $createdBy,
                $b['dueDate'] ?? 'TBD', $b['proposalValue'] ?? '', $b['email'] ?? '', $b['emailSecondary'] ?? '', $b['phone'] ?? '', $b['website'] ?? '', $b['emailSubject'] ?? '',
            ]);
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $try === 2) throw $e; // rethrow if not duplicate or last retry
        }
    }

    // Initial history entry
    $pdo->prepare("INSERT INTO stage_history (inquiry_id,stage,outcome,by_user,date,remark) VALUES (?,?,?,?,?,?)")
        ->execute([$id,'Inquiry Received','In Progress',$createdBy,date('d M Y · H:i'),'Inquiry logged']);

    // First step if assignTo provided
    if (!empty($b['assignTo'])) {
        $instruction = $b['taskInstruction'] ?? 'Review inquiry and revert.';
        $stepIns = $pdo->prepare("INSERT INTO inquiry_steps (inquiry_id,assigned_by,assigned_to,instruction,status,due) VALUES (?,?,?,?,?,?)");
        $stepDue = $b['dueDate'] ?? 'TBD';
        if ($stepDue !== 'TBD' && !empty($b['dueTime'])) $stepDue .= ' · '.date('h:i A', strtotime($b['dueTime']));
        $stepIns->execute([$id,$createdBy,$b['assignTo'],$instruction,'New',$stepDue]);
        $preview = mb_strimwidth(strip_tags($instruction), 0, 80, '…');
        notify_user($pdo, $b['assignTo'], 'New task — '.$id, $createdBy.' assigned: '.$preview, $id);
    }

    // Return only the new inquiry — much faster than reloading everything
    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id=?");
    $stmt->execute([$id]);
    $inqRow = $stmt->fetch();
    $inqRow['is_new']      = (bool)$inqRow['is_new'];
    $inqRow['overdue']     = false;
    $inqRow['history']     = [];
    $inqRow['attachments'] = [];
    $sStmt = $pdo->prepare("SELECT s.* FROM inquiry_steps s WHERE s.inquiry_id=? ORDER BY s.id");
    $sStmt->execute([$id]);
    $inqRow['steps'] = array_map(fn($s) => array_merge($s, ['attachments'=>[], 'overdue'=>false]), $sStmt->fetchAll());

    json_response(['ok'=>true,'id'=>$id,'inquiry'=>$inqRow]);
}

json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
