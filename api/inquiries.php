<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_staff_json();

$method = $_SERVER['REQUEST_METHOD'];
$user   = current_user();

// GET — list all
if ($method === 'GET') {
    json_response(['ok'=>true, 'data'=>load_inquiries($pdo, $user)]);
}

// POST — create new inquiry
if ($method === 'POST') {
try {
    $b = get_json_body();
    require_csrf($b);
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
        // Same-day only: force due date to today; time may be any HH:MM within the day.
        $b['dueDate'] = date('d M Y');
    }

    // Server-side check: if client name already exists anywhere in DB, it can't be "New"
    if ($clientType === 'New' && $inquiryType === 'Client Project' && $client !== '') {
        $ck = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE LOWER(TRIM(client))=LOWER(TRIM(?))");
        $ck->execute([$client]);
        if ((int)$ck->fetchColumn() > 0) $clientType = 'Repeat';
    }

    // Prefer insert with slot columns (nullable) when present; fall back if not migrated.
    $dueTime = preg_match('/^\d{2}:\d{2}$/', $b['dueTime'] ?? '') ? $b['dueTime'] : null;
    $projectCategory = trim((string)($b['projectCategory'] ?? ''));
    if (mb_strlen($projectCategory) > 100) $projectCategory = mb_substr($projectCategory, 0, 100);
    // Shared category list is Admin-managed. Members may only pick existing names.
    if ($projectCategory !== '') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_categories (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                is_default TINYINT(1)   NOT NULL DEFAULT 0,
                created_by VARCHAR(100) NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_category_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $cChk = $pdo->prepare("SELECT name FROM project_categories WHERE LOWER(TRIM(name))=LOWER(TRIM(?))");
            $cChk->execute([$projectCategory]);
            $existingCat = $cChk->fetchColumn();
            if ($existingCat) {
                $projectCategory = $existingCat; // canonical casing
            } elseif (is_admin()) {
                $pdo->prepare("INSERT INTO project_categories (name, is_default, created_by) VALUES (?,?,?)")
                    ->execute([$projectCategory, 0, $createdBy]);
            } else {
                json_response(['ok'=>false, 'message'=>'Unknown project category. Ask an Admin to add it.'], 400);
            }
        } catch (Exception $e) {
            if ($e instanceof PDOException) throw $e;
            /* catalog check best-effort if table missing mid-migrate */
        }
    }

    $id = null;
    for ($try = 0; $try < 3; $try++) {
        try {
            $id = next_inquiry_id($pdo);
            try {
                $ins = $pdo->prepare("INSERT INTO inquiries
                    (id,date,inquiry_type,project_category,client,designation,company,country,is_new,client_type,requirement,created_by,current_owner,due_date,due_time,slot_date,slot_start,stage,outcome,proposal_value,email,secondary_email,phone,website,email_subject)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,'Inquiry Received','In Progress',?,?,?,?,?,?)");
                $ins->execute([
                    $id, $b['date'] ?? date('d M Y'), $inquiryType, $projectCategory !== '' ? $projectCategory : null,
                    $client, $b['designation'] ?? '', $company, $b['country'] ?? 'India',
                    $clientType === 'New' ? 1 : 0, $clientType,
                    $b['requirement'] ?? '', $createdBy, $b['assignTo'] ?? $createdBy,
                    $b['dueDate'] ?? 'TBD', $dueTime,
                    $b['proposalValue'] ?? '', $b['email'] ?? '', $b['emailSecondary'] ?? '', $b['phone'] ?? '', $b['website'] ?? '', $b['emailSubject'] ?? '',
                ]);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Unknown column') === false) throw $e;
                // Older DBs without project_category / slot columns
                $ins = $pdo->prepare("INSERT INTO inquiries
                    (id,date,inquiry_type,client,designation,company,country,is_new,client_type,requirement,created_by,current_owner,due_date,due_time,stage,outcome,proposal_value,email,secondary_email,phone,website,email_subject)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Inquiry Received','In Progress',?,?,?,?,?,?)");
                $ins->execute([
                    $id, $b['date'] ?? date('d M Y'), $inquiryType,
                    $client, $b['designation'] ?? '', $company, $b['country'] ?? 'India',
                    $clientType === 'New' ? 1 : 0, $clientType,
                    $b['requirement'] ?? '', $createdBy, $b['assignTo'] ?? $createdBy,
                    $b['dueDate'] ?? 'TBD', $dueTime,
                    $b['proposalValue'] ?? '', $b['email'] ?? '', $b['emailSecondary'] ?? '', $b['phone'] ?? '', $b['website'] ?? '', $b['emailSubject'] ?? '',
                ]);
            }
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $try === 2) throw $e;
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
        $stepIns->execute([$id,$createdBy,$b['assignTo'],$instruction,'Pending',$stepDue]);
        $preview = mb_strimwidth(strip_tags($instruction), 0, 80, '…');
        notify_user($pdo, $b['assignTo'], 'New task — '.$id, $createdBy.' assigned: '.$preview, $id);
    }

    // Return only the new inquiry — much faster than reloading everything
    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id=?");
    $stmt->execute([$id]);
    $inqRow = $stmt->fetch();
    $inqRow['is_new']      = (bool)$inqRow['is_new'];
    $inqRow['overdue']     = false;
    $inqRow['late']        = false;
    $inqRow['history']     = [];
    $inqRow['attachments'] = [];
    $sStmt = $pdo->prepare("SELECT s.* FROM inquiry_steps s WHERE s.inquiry_id=? ORDER BY s.id");
    $sStmt->execute([$id]);
    $inqRow['steps'] = array_map(fn($s) => array_merge($s, ['attachments'=>[], 'overdue'=>false, 'late'=>false]), $sStmt->fetchAll());

    json_response(['ok'=>true,'id'=>$id,'inquiry'=>$inqRow]);
} catch (Exception $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
}

json_response(['ok'=>false,'message'=>'Method not allowed'], 405);