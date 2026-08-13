<?php
// ─── Stage badge colour ───────────────────────────────────────────────────────

function stage_class(string $stage): string {
    $map = [
        'Inquiry Received'      => 'bg-[#EEF4FF] text-[#175CD3]',
        'Client Communication'  => 'bg-[#EFF8FF] text-[#026AA2]',
        'Client Decision Pending'=> 'bg-[#EFF8FF] text-[#026AA2]',
        'Client Not Replying'   => 'bg-[#EFF8FF] text-[#026AA2]',
        'Proposal Making'       => 'bg-[#F4F3FF] text-[#5925DC]',
        'Proposal Sent'         => 'bg-[#F4F3FF] text-[#5925DC]',
        'Meeting Scheduled'     => 'bg-[#FFF6ED] text-[#C4320A]',
        'Meeting Completed'     => 'bg-[#FFF6ED] text-[#C4320A]',
        'Proposal Won'          => 'bg-[#ECFDF3] text-[#16803C]',
        'Proposal Lost'         => 'bg-[#FEF3F2] text-[#B42318]',
        'Project Started'       => 'bg-[#F0F9FF] text-[#0369A1]',
        'Project in Progress'   => 'bg-[#F0F9FF] text-[#0369A1]',
        'Project Submitted'     => 'bg-[#F8FAFC] text-[#344054]',
        'Client Approval Pending'=> 'bg-[#F8FAFC] text-[#344054]',
        'Invoice Sent'          => 'bg-[#FFF7ED] text-[#C2410C]',
        'Payment Pending'       => 'bg-[#FFF7ED] text-[#C2410C]',
        'Payment Received'      => 'bg-[#ECFDF3] text-[#166534]',
        'Project Closed'        => 'bg-[#ECFDF3] text-[#166534]',
    ];
    return $map[$stage] ?? 'bg-[#F2F4F7] text-[#667085]';
}

// ─── Outcome badge colour ─────────────────────────────────────────────────────

function outcome_class(string $outcome): string {
    $map = [
        'In Progress'     => 'bg-[#EFF8FF] text-[#026AA2]',
        'Project Won'     => 'bg-[#ECFDF3] text-[#16803C]',
        'Payment Received'=> 'bg-[#ECFDF3] text-[#166534]',
        'Completed'       => 'bg-[#ECFDF3] text-[#166534]',
        'Invoice Sent'    => 'bg-[#EEF4FF] text-[#175CD3]',
        'Lost'            => 'bg-[#FEF3F2] text-[#B42318]',
        'No Reply'        => 'bg-[#FFFAEB] text-[#B54708]',
        'On Hold'         => 'bg-[#F2F4F7] text-[#344054]',
        'Cancelled'       => 'bg-[#F2F4F7] text-[#667085]',
    ];
    return $map[$outcome] ?? 'bg-[#F2F4F7] text-[#667085]';
}

// ─── Step status colour ───────────────────────────────────────────────────────

function step_status_class(string $status): string {
    $map = [
        'New'               => 'bg-[#EEF4FF] text-[#175CD3]',
        'Pending'           => 'bg-[#FFFAEB] text-[#B54708]',
        'In Progress'       => 'bg-[#EFF8FF] text-[#026AA2]',
        'Done'              => 'bg-[#ECFDF3] text-[#16803C]',
        'Blocked'           => 'bg-[#FEF3F2] text-[#B42318]',
        'On Hold'           => 'bg-[#F2F4F7] text-[#344054]',
        'Cancelled'         => 'bg-[#F2F4F7] text-[#667085]',
        'Checking Me'       => 'bg-[#F4F3FF] text-[#5925DC]',
        'Client Reply Pending'=> 'bg-[#FFF6ED] text-[#C4320A]',
    ];
    return $map[$status] ?? 'bg-[#F2F4F7] text-[#667085]';
}

// ─── Role badge HTML ─────────────────────────────────────────────────────────

function role_badge(string $role): string {
    return match(true) {
        $role === 'Master Admin' => '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#5925DC]"><i data-lucide="shield-check" class="w-3 h-3"></i>Master Admin</span>',
        $role === 'Admin'        => '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#175CD3]"><i data-lucide="shield-check" class="w-3 h-3"></i>Admin</span>',
        $role === 'Client'       => '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#B54708]"><i data-lucide="user" class="w-3 h-3"></i>Client</span>',
        $role === 'Member'       => '<span class="text-[10px] font-semibold text-[#344054]">Member</span>',
        default                  => '<span class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#667085] bg-[#F2F4F7] px-1.5 py-0.5 rounded-[4px]"><i data-lucide="tag" class="w-3 h-3"></i>' . htmlspecialchars($role) . '</span>',
    };
}

// ─── Status pill HTML ─────────────────────────────────────────────────────────

function status_pill(string $status): string {
    return match($status) {
        'approved' => '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#ECFDF3] text-[#16803C]"><i data-lucide="check-circle" class="w-2.5 h-2.5"></i>Active</span>',
        'rejected' => '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#FEF3F2] text-[#B42318]"><i data-lucide="x-circle" class="w-2.5 h-2.5"></i>Rejected</span>',
        'blocked'  => '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#F2F4F7] text-[#344054]"><i data-lucide="ban" class="w-2.5 h-2.5"></i>Blocked</span>',
        default    => '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#FFFAEB] text-[#B54708]"><i data-lucide="clock-3" class="w-2.5 h-2.5"></i>Pending</span>',
    };
}

// ─── Notifications ───────────────────────────────────────────────────────────

function notify_user(PDO $pdo, string $name, string $title, string $body, ?string $inquiry_id = null): void {
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE name=?");
    $stmt->execute([$name]);
    $account = $stmt->fetch();
    if (!$account) return; // team name with no account (e.g. "Project Team") — skip silently
    $pdo->prepare("INSERT INTO notifications (account_id,title,body,inquiry_id) VALUES (?,?,?,?)")
        ->execute([$account['id'], $title, $body, $inquiry_id]);
}

function load_notifications(PDO $pdo, int $account_id): array {
    $stmt = $pdo->prepare("SELECT id,title,body,is_read,inquiry_id,created_at FROM notifications WHERE account_id=? ORDER BY created_at DESC LIMIT 50"); // time_label dropped; computed below
    $stmt->execute([$account_id]);
    $rows = $stmt->fetchAll();
    $now  = time();
    foreach ($rows as &$n) {
        $n['is_read'] = (bool)$n['is_read'];
        $diff = $now - strtotime($n['created_at']);
        if ($diff < 60)        $n['time_label'] = 'just now';
        elseif ($diff < 3600)  $n['time_label'] = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400) $n['time_label'] = floor($diff / 3600) . 'h ago';
        else                   $n['time_label'] = date('d M', strtotime($n['created_at']));
    }
    return $rows;
}

// ─── Load inquiries with steps + history ─────────────────────────────────────

function load_inquiries(PDO $pdo, ?array $user = null): array {
    $adminRoles = ['Master Admin', 'Admin'];
    if ($user && !in_array($user['role'], $adminRoles)) {
        // Members only receive inquiries they have any connection to
        $n    = $user['name'];
        $stmt = $pdo->prepare("SELECT DISTINCT i.* FROM inquiries i
            LEFT JOIN inquiry_steps s ON s.inquiry_id=i.id
            WHERE i.created_by=? OR i.current_owner=? OR s.assigned_to=? OR s.assigned_by=?
            ORDER BY i.created_at DESC");
        $stmt->execute([$n,$n,$n,$n]);
        $inqs = $stmt->fetchAll();
    } else {
        $inqs = $pdo->query("SELECT * FROM inquiries ORDER BY created_at DESC")->fetchAll();
    }

    if (empty($inqs)) return [];

    $ids  = array_column($inqs, 'id');
    $ph   = implode(',', array_fill(0, count($ids), '?'));

    try {
        $steps = $pdo->prepare("SELECT s.*, GROUP_CONCAT(a.filename ORDER BY a.id SEPARATOR '||') AS attachments
            FROM inquiry_steps s LEFT JOIN step_attachments a ON a.step_id = s.id
            WHERE s.inquiry_id IN ($ph) GROUP BY s.id ORDER BY s.id");
        $steps->execute($ids);
    } catch (Exception $e) {
        // step_attachments table missing — fall back to steps without attachments
        $steps = $pdo->prepare("SELECT s.*, NULL AS attachments FROM inquiry_steps s WHERE s.inquiry_id IN ($ph) ORDER BY s.id");
        $steps->execute($ids);
    }
    $today          = strtotime('today');
    $closedOutcomes = ['Payment Received','Completed','Cancelled'];
    $closedStages   = ['Payment Received','Project Closed','Proposal Lost','Cancelled'];

    $stepsBy = [];
    foreach ($steps->fetchAll() as $row) {
        $row['attachments'] = $row['attachments'] ? explode('||', $row['attachments']) : [];
        $dueTs = ($row['due'] && $row['due'] !== 'TBD') ? strtotime($row['due']) : false;
        $row['overdue'] = (bool)($dueTs && $dueTs < $today && !in_array($row['status'], ['Done','Cancelled']));
        $stepsBy[$row['inquiry_id']][] = $row;
    }

    $attBy = [];
    try {
        $atts = $pdo->prepare("SELECT id, inquiry_id, filename, uploaded_by FROM inquiry_attachments WHERE inquiry_id IN ($ph) ORDER BY id");
        $atts->execute($ids);
        foreach ($atts->fetchAll() as $row) {
            $attBy[$row['inquiry_id']][] = $row;
        }
    } catch (Exception $e) { /* inquiry_attachments table not yet created — skip */ }

    foreach ($inqs as &$inq) {
        $inq['is_new'] = (bool)$inq['is_new'];
        $dueTs = ($inq['due_date'] && $inq['due_date'] !== 'TBD') ? strtotime($inq['due_date']) : false;
        // overdue computed here — not stored in DB
        $inq['overdue'] = (bool)($dueTs && $dueTs < $today
            && !in_array($inq['outcome'], $closedOutcomes)
            && !in_array($inq['stage'], $closedStages));
        $inq['steps']       = $stepsBy[$inq['id']] ?? [];
        $inq['history']     = []; // lazy-loaded on-demand via api/inquiry.php?action=load_history
        $inq['attachments'] = $attBy[$inq['id']] ?? [];
    }

    return $inqs;
}

// ─── Load accounts ────────────────────────────────────────────────────────────

function load_accounts(PDO $pdo): array {
    return $pdo->query("SELECT id,name,email,role,status,requested_at FROM accounts ORDER BY id")->fetchAll();
}

// ─── Next inquiry ID ─────────────────────────────────────────────────────────

function next_inquiry_id(PDO $pdo): string {
    // Re-query inside the insert transaction to minimise race window
    $max = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(id,'-',-1) AS UNSIGNED)) FROM inquiries")->fetchColumn();
    return 'SP-2026-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}
