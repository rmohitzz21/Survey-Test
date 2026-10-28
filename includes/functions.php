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

// 'Project Lost' is the current name for what legacy rows call 'Lost' — both close the inquiry,
// a lost project is not open work. The Ownership Status picker in the stage-update modal offers
// exactly the outcomes listed here, so the picker and the reports cannot drift apart.
const CLOSED_OUTCOMES = ['Payment Received','Inquiry Closed','Completed','Project Lost','Lost','Cancelled'];
const CLOSED_STAGES   = ['Payment Received','Project Closed','Proposal Lost','Closure','Cancelled'];

function is_closed_inquiry(?string $stage, ?string $outcome): bool {
    return in_array((string)$outcome, CLOSED_OUTCOMES, true)
        || in_array((string)$stage,   CLOSED_STAGES,   true);
}

// Finishing an inquiry successfully requires its tasks to be finished too. Losing or cancelling
// one does not — abandoned work is not work anyone still owes, so those outcomes stay unblocked.
const COMPLETION_OUTCOMES = ['Payment Received','Inquiry Closed','Completed'];
const COMPLETION_STAGES   = ['Payment Received','Project Closed','Closure'];

function is_completing_inquiry(?string $stage, ?string $outcome): bool {
    return in_array((string)$outcome, COMPLETION_OUTCOMES, true)
        || in_array((string)$stage,   COMPLETION_STAGES,   true);
}

// A task still owes work until it reaches a terminal status. 'Completed' is the legacy spelling of
// 'Done' and still sits on older rows; 'Rejected' and 'Cancelled' are dead ends the assignee can no
// longer move. Matches the statuses the task UI already locks down.
const SETTLED_STEP_STATUSES = ['Done','Completed','Rejected','Cancelled'];

/** Instructions of the tasks still open on an inquiry; empty means nothing blocks completing it. */
function open_step_names(PDO $pdo, string $inquiryId): array {
    $ph = implode(',', array_fill(0, count(SETTLED_STEP_STATUSES), '?'));
    $st = $pdo->prepare("SELECT instruction FROM inquiry_steps WHERE inquiry_id=? AND status NOT IN ($ph) ORDER BY id");
    $st->execute(array_merge([$inquiryId], SETTLED_STEP_STATUSES));
    return $st->fetchAll(PDO::FETCH_COLUMN);
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

/**
 * Internal-usage booking board: 12 × 2-hour slots covering 24 hours, starting at 10:00.
 * Returns [ ['start'=>'10:00','end'=>'12:00','label'=>'10:00 AM – 12:00 PM'], ... ]
 */
function inquiry_time_slots(): array {
    $slots = [];
    for ($i = 0; $i < 12; $i++) {
        $startMin = (10 * 60 + $i * 120) % (24 * 60);
        $endMin   = ($startMin + 120) % (24 * 60);
        $sh = intdiv($startMin, 60); $sm = $startMin % 60;
        $eh = intdiv($endMin, 60);   $em = $endMin % 60;
        $start = sprintf('%02d:%02d', $sh, $sm);
        $end   = sprintf('%02d:%02d', $eh, $em);
        $slots[] = [
            'start' => $start,
            'end'   => $end,
            'label' => date('g:i A', strtotime($start)) . ' – ' . date('g:i A', strtotime($end)),
        ];
    }
    return $slots;
}

function inquiry_slot_label(?string $slotStart): string {
    if (!$slotStart || !preg_match('/^\d{2}:\d{2}$/', $slotStart)) return '';
    foreach (inquiry_time_slots() as $s) {
        if ($s['start'] === $slotStart) return $s['label'];
    }
    return $slotStart;
}

function normalize_slot_date(?string $raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** End time 'HH:MM' for a 2-hour slot start. */
function inquiry_slot_end(string $start): string {
    if (!preg_match('/^(\d{2}):(\d{2})$/', $start, $m)) return $start;
    $mins = ((int)$m[1] * 60 + (int)$m[2] + 120) % (24 * 60);
    return sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
}

/** Display due string from slot: end of block; next calendar day if the slot wraps past midnight. */
function inquiry_slot_due_label(string $slotDate, string $slotStart): string {
    $end = inquiry_slot_end($slotStart);
    $day = $slotDate;
    if ($end <= $slotStart) {
        $day = date('Y-m-d', strtotime($slotDate.' +1 day'));
    }
    return date('d M Y', strtotime($day)).' · '.date('h:i A', strtotime($end));
}

/**
 * True if this day+start is already held by an inquiry or a workflow step.
 * Pass except inquiry/step ids when moving an existing booking.
 */
function slot_is_taken(PDO $pdo, string $slotDate, string $slotStart, ?string $exceptInqId = null, ?int $exceptStepId = null): ?string {
    $q1 = "SELECT id FROM inquiries WHERE slot_date=? AND slot_start=?";
    $a1 = [$slotDate, $slotStart];
    if ($exceptInqId) { $q1 .= " AND id<>?"; $a1[] = $exceptInqId; }
    $q1 .= " LIMIT 1";
    $st = $pdo->prepare($q1);
    $st->execute($a1);
    $id = $st->fetchColumn();
    if ($id) return (string)$id;

    try {
        $q2 = "SELECT inquiry_id FROM inquiry_steps WHERE slot_date=? AND slot_start=?";
        $a2 = [$slotDate, $slotStart];
        if ($exceptStepId) { $q2 .= " AND id<>?"; $a2[] = $exceptStepId; }
        $q2 .= " LIMIT 1";
        $st2 = $pdo->prepare($q2);
        $st2->execute($a2);
        $iid = $st2->fetchColumn();
        if ($iid) return (string)$iid;
    } catch (Exception $e) { /* column may not exist yet */ }

    return null;
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
            && !in_array($row['status'], SETTLED_STEP_STATUSES, true)
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

/** True if the user may see/mutate this inquiry (admin, creator, owner, or step party). */
function can_access_inquiry(PDO $pdo, string $id, array $user): bool {
    if ($id === '') return false;
    if (in_array($user['role'] ?? '', ['Master Admin', 'Admin'], true)) return true;
    $n = $user['name'] ?? '';
    if ($n === '') return false;
    $stmt = $pdo->prepare("SELECT 1 FROM inquiries i
        LEFT JOIN inquiry_steps s ON s.inquiry_id = i.id
        WHERE i.id = ? AND (i.created_by = ? OR i.current_owner = ? OR s.assigned_to = ? OR s.assigned_by = ?)
        LIMIT 1");
    $stmt->execute([$id, $n, $n, $n, $n]);
    return (bool)$stmt->fetchColumn();
}

// ─── Load accounts ────────────────────────────────────────────────────────────

function load_accounts(PDO $pdo): array {
    // Keep this column list in sync with accounts_list() in api/accounts.php — if only one of the
    // two selects a field, it appears after a save and vanishes on reload.
    return $pdo->query("SELECT id,name,email,role,role_description,status,requested_at FROM accounts ORDER BY id")->fetchAll();
}

// ─── Next inquiry ID ─────────────────────────────────────────────────────────

function next_inquiry_id(PDO $pdo): string {
    // Re-query inside the insert transaction to minimise race window
    $max = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(id,'-',-1) AS UNSIGNED)) FROM inquiries")->fetchColumn();
    // Year comes from the clock, not a literal — the sequence itself stays continuous across the
    // year boundary (…SP-2026-041 → SP-2027-042), so an ID is never reused and stays sortable.
    return 'SP-' . date('Y') . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

// ─── Leave notice window ──────────────────────────────────────────────────────
// Casual Leave / WFH / Sick Leave need ≥12 hours before the leave start (00:00 IST).
// Inside that window the form offers only Emergency Leave. Admin still must approve
// before any request counts toward taken leave (see summary_for in api/leave.php).

const LEAVE_ADVANCE_TYPES = ['Casual Leave', 'WFH', 'Sick Leave'];
const LEAVE_SHORT_TYPES   = ['Emergency Leave'];
const LEAVE_ALL_TYPES     = ['Casual Leave', 'WFH', 'Sick Leave', 'Emergency Leave'];

const LEAVE_NOTICE_SECONDS = 12 * 3600;

/** True when leave start (midnight IST) is at least LEAVE_NOTICE_SECONDS from $now. */
function leave_has_notice(string $startDate, ?int $now = null): bool {
    $now = $now ?? time();
    $startTs = strtotime($startDate . ' 00:00:00');
    if ($startTs === false) return false;
    return ($startTs - $now) >= LEAVE_NOTICE_SECONDS;
}

/** Types the Request Leave dropdown may show for a given start date. */
function leave_types_for_start(string $startDate, ?int $now = null): array {
    return leave_has_notice($startDate, $now) ? LEAVE_ADVANCE_TYPES : LEAVE_SHORT_TYPES;
}

/** Server-side guard mirroring the dropdown. */
function leave_type_allowed(string $type, string $startDate, ?int $now = null): bool {
    return in_array($type, leave_types_for_start($startDate, $now), true);
}

// ─── Delete an inquiry and everything hanging off it ──────────────────────────

/**
 * Removes an inquiry's attachment files from disk, then its child rows, then the inquiry.
 * $root is the project root (the directory holding /uploads).
 *
 * ponytail: api/inquiry.php (delete_inquiry) and delete-inquiry.php still carry their own
 * identical copies of this cascade — swap both for this call next time either is touched.
 */
function delete_inquiry_cascade(PDO $pdo, string $id, string $root): void {
    $atts = $pdo->prepare("SELECT filename FROM inquiry_attachments WHERE inquiry_id=?");
    $atts->execute([$id]);
    foreach ($atts->fetchAll() as $a) {
        $p = $root.'/uploads/inq/'.$id.'/'.$a['filename'];
        if (file_exists($p)) unlink($p);
    }
    @rmdir($root.'/uploads/inq/'.$id);

    $steps = $pdo->prepare("SELECT id FROM inquiry_steps WHERE inquiry_id=?");
    $steps->execute([$id]);
    foreach ($steps->fetchAll() as $s) {
        $sa = $pdo->prepare("SELECT filename FROM step_attachments WHERE step_id=?");
        $sa->execute([$s['id']]);
        foreach ($sa->fetchAll() as $f) {
            $p = $root.'/uploads/'.$s['id'].'/'.$f['filename'];
            if (file_exists($p)) unlink($p);
        }
        @rmdir($root.'/uploads/'.$s['id']);
    }

    $pdo->prepare("DELETE FROM notifications       WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM follow_ups          WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiry_attachments WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM step_attachments    WHERE step_id IN (SELECT id FROM inquiry_steps WHERE inquiry_id=?)")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiry_steps       WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM stage_history       WHERE inquiry_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM inquiries           WHERE id=?")->execute([$id]);
}

/**
 * Validate one entry of a multi-file $_FILES upload. Returns the sanitised
 * filename to save as, or null if the file should be skipped.
 * Shared by api/upload.php (inquiry/step attachments) and api/resources.php.
 */
function sp_upload_ok(array $files, int $i, array $allowed, int $maxBytes): ?string {
    $tmp  = $files['tmp_name'][$i] ?? '';
    $name = $files['name'][$i] ?? '';
    $err  = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    $size = (int)($files['size'][$i] ?? 0);
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) return null;
    if ($size <= 0 || $size > $maxBytes) return null;
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    // MIME sniff when available — reject obvious mismatch for common types
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $fi ? finfo_file($fi, $tmp) : '';
        if ($fi) finfo_close($fi);
        $bad = ['text/html', 'application/x-httpd-php', 'application/x-php', 'text/x-php'];
        if ($mime && in_array($mime, $bad, true)) return null;
    }
    $stem = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo(basename($name), PATHINFO_FILENAME));
    return $stem.'.'.$ext;
}

