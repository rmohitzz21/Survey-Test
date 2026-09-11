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

// ─── Due date + time → timestamp ─────────────────────────────────────────────

/**
 * "15 Aug 2026" + "14:30" → unix ts. Returns null for TBD / unparseable.
 * Steps store the time inline as "15 Aug 2026 · 03:00 PM" — strtotime chokes on
 * the separator, so strip it. A due with no time means end of that day.
 */
function due_ts(?string $date, ?string $time = null): ?int {
    $d = trim((string)$date);
    if ($d === '' || strcasecmp($d, 'TBD') === 0) return null;
    if (strpos($d, '·') !== false) {
        // Step rows carry the time inline: "01 Sep 2026 · 02:30 PM". A too-short column used to chop
        // that to "01 Sep 2026 · 02:30 ", and strtotime read the leftover as 02:30 AM — so a task due
        // this afternoon showed Overdue all day. A time missing its AM/PM can't be recovered, so
        // fall back to end of day rather than guessing the early half.
        [$d, $t] = array_map('trim', explode('·', $d, 2));
        $time = preg_match('/^\d{1,2}:\d{2}\s*[AP]M$/i', $t) ? $t : null;
    }
    $ts = strtotime($d . ' ' . ($time ?: '23:59'));
    return $ts === false ? null : $ts;
}

// ─── Closed / late ───────────────────────────────────────────────────────────

const CLOSED_OUTCOMES = ['Payment Received','Inquiry Closed','Completed','Lost','Cancelled'];
const CLOSED_STAGES   = ['Payment Received','Project Closed','Proposal Lost','Closure','Cancelled'];

function is_closed_inquiry(?string $stage, ?string $outcome): bool {
    return in_array((string)$outcome, CLOSED_OUTCOMES, true)
        || in_array((string)$stage,   CLOSED_STAGES,   true);
}

/**
 * True when work was *delivered after* its due moment. `$completedAt` is a DB TIMESTAMP
 * ('Y-m-d H:i:s'), or null while the work is still open — open work is overdue, not late.
 *
 * Overdue is a live state that vanishes the moment someone submits; late is the permanent
 * record of that miss, which is what the admin report has to count.
 */
function is_late(?string $completedAt, ?int $dueTs): bool {
    if ($dueTs === null || $completedAt === null || trim($completedAt) === '') return false;
    $ts = strtotime($completedAt);
    return $ts !== false && $ts > $dueTs;
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
            ORDER BY i.created_at DESC, i.id DESC");
        $stmt->execute([$n,$n,$n,$n]);
        $inqs = $stmt->fetchAll();
    } else {
        $inqs = $pdo->query("SELECT * FROM inquiries ORDER BY created_at DESC, id DESC")->fetchAll();
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
    $now = time();

    // Closing an inquiry closes its tasks with it — a step left at "In Progress" on a won/lost
    // inquiry is not work anyone still owes, so it must never read as overdue.
    $closedIds = [];
    foreach ($inqs as $i) {
        if (is_closed_inquiry($i['stage'], $i['outcome'])) $closedIds[$i['id']] = true;
    }

    $stepsBy = [];
    foreach ($steps->fetchAll() as $row) {
        $row['attachments'] = $row['attachments'] ? explode('||', $row['attachments']) : [];
        $dueTs = due_ts($row['due']);
        $row['overdue'] = ($dueTs !== null && $dueTs < $now
            && !in_array($row['status'], ['Done','Cancelled'])
            && !isset($closedIds[$row['inquiry_id']]));
        // Submitting late clears `overdue` — `late` keeps the miss on the record for the report.
        $row['late'] = is_late($row['completed_at'] ?? null, $dueTs);
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
        $dueTs = due_ts($inq['due_date'], $inq['due_time'] ?? null);
        // overdue computed here — not stored in DB
        $inq['overdue'] = ($dueTs !== null && $dueTs < $now && !isset($closedIds[$inq['id']]));
        $inq['late']    = is_late($inq['closed_at'] ?? null, $dueTs);
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
    // Year comes from the clock, not a literal — the sequence itself stays continuous across the
    // year boundary (…SP-2026-041 → SP-2027-042), so an ID is never reused and stays sortable.
    return 'SP-' . date('Y') . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}
