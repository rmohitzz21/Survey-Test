<?php
/**
 * End-to-end feature integration test against the local Apache stack.
 * Run: C:\xampp\php\php.exe tests/feature-integration-test.php
 *
 * Uses cookie-jar sessions against http://localhost/... — requires Apache + MySQL.
 */
date_default_timezone_set('Asia/Kolkata');

$BASE = getenv('SP_BASE') ?: 'http://localhost/SurveyPacific/Survey_work_journey/Survey-test';
$pass = 0;
$fail = 0;
$results = [];
/** @var array<string,string> CSRF token per cookie-jar path */
$csrfByJar = [];

function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail, $results;
    if ($cond) {
        $pass++;
        $results[] = ['ok' => true, 'name' => $name, 'detail' => $detail];
        echo "  PASS  $name" . ($detail ? " — $detail" : '') . "\n";
    } else {
        $fail++;
        $results[] = ['ok' => false, 'name' => $name, 'detail' => $detail];
        echo "  FAIL  $name" . ($detail ? " — $detail" : '') . "\n";
    }
}

function http(string $cookieFile, string $method, string $url, $body = null, array $headers = []): array {
    global $csrfByJar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $hdrs = $headers;
    $csrf = $csrfByJar[$cookieFile] ?? '';
    if ($body !== null && !isset($headers['multipart'])) {
        $json = is_string($body) ? $body : json_encode($body);
        $hdrs[] = 'Content-Type: application/json';
        if ($csrf !== '' && strtoupper($method) !== 'GET') {
            $hdrs[] = 'X-CSRF-Token: ' . $csrf;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    } elseif (isset($headers['multipart'])) {
        unset($hdrs['multipart']);
        if ($csrf !== '' && strtoupper($method) !== 'GET') {
            $hdrs[] = 'X-CSRF-Token: ' . $csrf;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($hdrs) curl_setopt($ch, CURLOPT_HTTPHEADER, array_values(array_filter($hdrs, 'is_string')));
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsz  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($raw === false) return ['code' => 0, 'body' => '', 'json' => null, 'err' => $err];
    $respBody = substr($raw, $hsz);
    $json = json_decode($respBody, true);
    if (is_array($json) && !empty($json['csrf']) && is_string($json['csrf'])) {
        $csrfByJar[$cookieFile] = $json['csrf'];
    }
    return ['code' => $code, 'body' => $respBody, 'json' => $json, 'err' => ''];
}

function login(string $base, string $cookie, string $email, string $password): array {
    return http($cookie, 'POST', "$base/api/login.php", ['email' => $email, 'password' => $password]);
}

/**
 * Local disposable DB only (same convention as the late-close fixture below).
 * Refuses to proceed unless SELECT DATABASE() is exactly sp_work.
 */
function local_sp_work_pdo(): PDO {
    $pdo = new PDO('mysql:host=localhost;dbname=sp_work;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
    ]);
    $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($name !== 'sp_work') {
        throw new RuntimeException('Refusing fixture SQL: expected database sp_work, got '.$name);
    }
    return $pdo;
}

/** Delete only leave rows created by this suite (reason marker), for one account. */
function purge_qa_integration_leave(PDO $pdo, int $accountId): int {
    $stmt = $pdo->prepare(
        "DELETE FROM leave_requests WHERE account_id = ? AND reason LIKE ?"
    );
    $stmt->execute([$accountId, 'QA integration leave%']);
    return $stmt->rowCount();
}

/** Mirror api/leave.php remaining for a calendar year (entitlement 18). */
function leave_remaining_days(PDO $pdo, int $accountId, int $year): float {
    $q = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN status='approved' THEN days ELSE 0 END),0) AS taken,
            COALESCE(SUM(CASE WHEN status='pending'  THEN days ELSE 0 END),0) AS pending
        FROM leave_requests WHERE account_id=? AND YEAR(start_date)=?");
    $q->execute([$accountId, $year]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: ['taken' => 0, 'pending' => 0];
    return max(0.0, 18.0 - (float)$row['taken'] - (float)$row['pending']);
}

$tmp = sys_get_temp_dir();
$adminJar  = tempnam($tmp, 'sp_admin_');
$opsJar    = tempnam($tmp, 'sp_ops_');
$memberJar = tempnam($tmp, 'sp_mem_');
$clientJar = tempnam($tmp, 'sp_cli_');

echo "=== Survey Pacific feature integration ===\n";
echo "Base: $BASE\n\n";

// ── 1. Auth ──────────────────────────────────────────────────────────────────
echo "1. Authentication & roles\n";
$r = login($BASE, $adminJar, 'admin@surveypacific.com', 'Survey@404');
ok('Master Admin login', ($r['json']['ok'] ?? false) === true && ($r['json']['role'] ?? '') === 'Master Admin', $r['body']);

$r = login($BASE, $opsJar, 'opsadmin@surveypacific.com', 'Admin@123');
ok('Admin login', ($r['json']['ok'] ?? false) === true && ($r['json']['role'] ?? '') === 'Admin');

$r = login($BASE, $memberJar, 'member@surveypacific.com', 'Member@123');
ok('Member login', ($r['json']['ok'] ?? false) === true && ($r['json']['role'] ?? '') === 'Member', $r['body']);

$r = login($BASE, $clientJar, 'client@surveypacific.com', 'Client@123');
ok('Client login', ($r['json']['ok'] ?? false) === true, $r['body']);

$badJar = tempnam($tmp, 'sp_bad_');
$r = login($BASE, $badJar, 'admin@surveypacific.com', 'wrong-password');
ok('Bad password rejected', ($r['json']['ok'] ?? true) === false);
@unlink($badJar);

$r = http($adminJar, 'GET', "$BASE/api/ping.php");
ok('Ping authenticated', ($r['json']['ok'] ?? false) === true && isset($r['json']['onlineIds']));
ok('Ping returns CSRF token', !empty($r['json']['csrf']));

// Sync CSRF for jars that logged in (login response carries csrf)
foreach ([$adminJar, $opsJar, $memberJar, $clientJar] as $_jar) {
    http($_jar, 'GET', "$BASE/api/ping.php");
}

// ── 2. Inquiries CRUD + stage lifecycle ──────────────────────────────────────
echo "\n2. Inquiries — create, list, stage, ownership\n";
$uniq = 'QA Client ' . date('YmdHis');
$create = http($adminJar, 'POST', "$BASE/api/inquiries.php", [
    'date' => date('d M Y'),
    'inquiryType' => 'Client Project',
    'client' => $uniq,
    'company' => 'QA Test Co',
    'country' => 'India',
    'clientType' => 'New',
    'requirement' => 'Integration test inquiry — bathymetric survey.',
    'dueDate' => date('d M Y', strtotime('+7 days')),
    'dueTime' => '18:00',
    'email' => 'qa.client@example.com',
    'phone' => '9999999999',
    'assignTo' => 'Team Member',
    'taskInstruction' => 'Review and prepare proposal draft.',
]);
$inqId = $create['json']['id'] ?? null;
ok('Create inquiry', ($create['json']['ok'] ?? false) === true && is_string($inqId), (string)$inqId);

$list = http($adminJar, 'GET', "$BASE/api/inquiries.php");
$found = null;
if (($list['json']['ok'] ?? false) && is_array($list['json']['data'] ?? null)) {
    foreach ($list['json']['data'] as $row) {
        if (($row['id'] ?? '') === $inqId) { $found = $row; break; }
    }
}
ok('List includes new inquiry', $found !== null);
ok('Initial stage Inquiry Received', ($found['stage'] ?? '') === 'Inquiry Received');
ok('Initial assign created a step', !empty($found['steps']));

$firstStepStatus = $found['steps'][0]['status'] ?? '';
ok('Create-with-assign step defaults Pending', $firstStepStatus === 'Pending', $firstStepStatus);

$stage = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'stage_update',
    'id' => $inqId,
    'stage' => 'Communication / Proposal',
    'outcome' => 'In Progress',
    'remark' => 'Moved to proposal stage by QA',
]);
ok('Stage update to Communication/Proposal', ($stage['json']['ok'] ?? false) === true);

$hist = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'load_history',
    'id' => $inqId,
]);
ok('Stage history recorded', ($hist['json']['ok'] ?? false) && count($hist['json']['data'] ?? []) >= 2, 'count=' . count($hist['json']['data'] ?? []));

$adminRemark = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'admin_remark',
    'inquiryId' => $inqId,
    'remark' => 'QA admin remark',
]);
ok('Admin remark', ($adminRemark['json']['ok'] ?? false) === true);

// ── 3. Tasks / steps ─────────────────────────────────────────────────────────
echo "\n3. Tasks (steps) — assign, status, ownership return, late stamp\n";
$addStep = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'add_step',
    'inquiryId' => $inqId,
    'assignedTo' => 'Team Member',
    'instruction' => 'Prepare site visit checklist',
    'due' => date('Y-m-d', strtotime('-2 days')),
    'dueTime' => '14:00',
]);
$stepId = $addStep['json']['stepId'] ?? null;
ok('Add step defaults Pending', ($addStep['json']['ok'] ?? false) === true && $stepId, "stepId=$stepId");

// Find step and check overdue + ownership
$list = http($adminJar, 'GET', "$BASE/api/inquiries.php");
$found = null; $step = null;
foreach (($list['json']['data'] ?? []) as $row) {
    if (($row['id'] ?? '') === $inqId) {
        $found = $row;
        foreach ($row['steps'] as $s) {
            if ((int)$s['id'] === (int)$stepId) { $step = $s; break; }
        }
        break;
    }
}
ok('New step status is Pending', ($step['status'] ?? '') === 'Pending', $step['status'] ?? 'missing');
ok('Past-due step is overdue (derived)', !empty($step['overdue']));
ok('Ownership moved to assignee', ($found['current_owner'] ?? '') === 'Team Member', $found['current_owner'] ?? '');

$prog = http($memberJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'step_status',
    'stepId' => $stepId,
    'status' => 'In Progress',
]);
ok('Assignee can set In Progress', ($prog['json']['ok'] ?? false) === true);

$done = http($memberJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'step_status',
    'stepId' => $stepId,
    'status' => 'Done',
]);
ok('Assignee can mark Done', ($done['json']['ok'] ?? false) === true);

$list = http($adminJar, 'GET', "$BASE/api/inquiries.php");
$step = null; $found = null;
foreach (($list['json']['data'] ?? []) as $row) {
    if (($row['id'] ?? '') === $inqId) {
        $found = $row;
        foreach ($row['steps'] as $s) {
            if ((int)$s['id'] === (int)$stepId) { $step = $s; break; }
        }
        break;
    }
}
ok('Completed past-due step is late (permanent)', !empty($step['late']), 'late=' . var_export($step['late'] ?? null, true));
ok('completed_at stamped', !empty($step['completed_at']), $step['completed_at'] ?? '');
ok('Overdue clears after Done', empty($step['overdue']));

// Ownership return when no active steps remain — may still have the first create-assign step
$activeLeft = 0;
foreach ($found['steps'] as $s) {
    if (!in_array($s['status'], ['Done', 'Completed', 'Cancelled', 'Rejected'], true)) $activeLeft++;
}
if ($activeLeft === 0) {
    ok('Ownership returns to creator when all steps done', ($found['current_owner'] ?? '') === 'Admin', $found['current_owner'] ?? '');
} else {
    // Complete remaining active steps
    foreach ($found['steps'] as $s) {
        if (!in_array($s['status'], ['Done', 'Completed', 'Cancelled', 'Rejected'], true)) {
            http($adminJar, 'POST', "$BASE/api/inquiry.php", [
                'action' => 'step_status', 'stepId' => (int)$s['id'], 'status' => 'Done',
            ]);
        }
    }
    $list = http($adminJar, 'GET', "$BASE/api/inquiries.php");
    foreach (($list['json']['data'] ?? []) as $row) {
        if (($row['id'] ?? '') === $inqId) { $found = $row; break; }
    }
    ok('Ownership returns to creator when all steps done', ($found['current_owner'] ?? '') === 'Admin', $found['current_owner'] ?? '');
}

// Reject permission: assignee must NOT reject own work
$add2 = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'add_step',
    'inquiryId' => $inqId,
    'assignedTo' => 'Team Member',
    'instruction' => 'Reject-permission probe',
    'due' => date('Y-m-d', strtotime('+3 days')),
]);
$step2 = $add2['json']['stepId'] ?? 0;
$rej = http($memberJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'step_status', 'stepId' => $step2, 'status' => 'Rejected',
]);
ok('Assignee cannot reject own step', ($rej['json']['ok'] ?? true) === false && $rej['code'] === 403, $rej['body']);
$rejOk = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'step_status', 'stepId' => $step2, 'status' => 'Rejected',
]);
ok('Admin can reject step', ($rejOk['json']['ok'] ?? false) === true);

// ── 4. Follow-ups / reminders ────────────────────────────────────────────────
echo "\n4. Follow-ups (reminders)\n";
$fu = http($adminJar, 'POST', "$BASE/api/followup.php", [
    'action' => 'add',
    'inquiryId' => $inqId,
    'note' => 'Call client about proposal',
    'date' => date('Y-m-d', strtotime('+2 days')),
    'time' => '11:00',
    'assignedTo' => 'Team Member',
]);
$fuId = $fu['json']['id'] ?? null;
ok('Add follow-up', ($fu['json']['ok'] ?? false) === true && $fuId, "id=$fuId");

$fuLoad = http($adminJar, 'POST', "$BASE/api/followup.php", ['action' => 'load', 'inquiryId' => $inqId]);
ok('Load follow-ups for inquiry', ($fuLoad['json']['ok'] ?? false) && count($fuLoad['json']['data'] ?? []) >= 1);

$fuAll = http($adminJar, 'POST', "$BASE/api/followup.php", ['action' => 'loadAll']);
ok('Admin loadAll follow-ups', ($fuAll['json']['ok'] ?? false) === true);

$fuDone = http($adminJar, 'POST', "$BASE/api/followup.php", ['action' => 'complete', 'id' => $fuId]);
ok('Complete follow-up', ($fuDone['json']['ok'] ?? false) === true);

// ── 4b. Create inquiry with optional follow-up ───────────────────────────────
echo "\n4b. Create inquiry ± optional follow-up\n";
$stampFu = date('YmdHis');
$qaNoFuClient = "QA NoFU $stampFu";
$qaWithFuClient = "QA WithFU $stampFu";
$qaBadFuClient = "QA BadFU $stampFu";
$inqNoFuId = null;
$inqWithFuId = null;
$createFuId = null;

$baseCreate = [
    'date' => date('d M Y'),
    'inquiryType' => 'Client Project',
    'company' => 'QA Follow-up Co',
    'country' => 'India',
    'clientType' => 'New',
    'requirement' => 'QA create-time follow-up coverage.',
    'dueDate' => date('d M Y', strtotime('+5 days')),
    'dueTime' => '16:00',
    'email' => 'qa.followup@example.com',
    'phone' => '9888888888',
    'assignTo' => 'Team Member',
    'taskInstruction' => 'QA follow-up create path.',
];

$createNoFu = http($adminJar, 'POST', "$BASE/api/inquiries.php", array_merge($baseCreate, [
    'client' => $qaNoFuClient,
    'scheduleFollowUp' => false,
]));
$inqNoFuId = $createNoFu['json']['id'] ?? null;
ok(
    'Create inquiry without follow-up',
    ($createNoFu['json']['ok'] ?? false) === true && is_string($inqNoFuId),
    (string)$inqNoFuId
);
ok('Create without follow-up returns null followUpId', ($createNoFu['json']['followUpId'] ?? null) === null);

$noFuLoad = http($adminJar, 'POST', "$BASE/api/followup.php", ['action' => 'load', 'inquiryId' => $inqNoFuId]);
ok(
    'No follow-up rows for scheduleFollowUp:false',
    ($noFuLoad['json']['ok'] ?? false) === true && count($noFuLoad['json']['data'] ?? []) === 0,
    'count=' . count($noFuLoad['json']['data'] ?? [])
);

$fuDate = date('Y-m-d', strtotime('+3 days'));
$fuNote = 'QA create-time reminder note';
$createWithFu = http($adminJar, 'POST', "$BASE/api/inquiries.php", array_merge($baseCreate, [
    'client' => $qaWithFuClient,
    'scheduleFollowUp' => true,
    'followUpNote' => $fuNote,
    'followUpDate' => $fuDate,
    'followUpTime' => '10:30',
    'followUpAssignedTo' => 'Team Member',
]));
$inqWithFuId = $createWithFu['json']['id'] ?? null;
$createFuId = $createWithFu['json']['followUpId'] ?? null;
ok(
    'Create inquiry with follow-up',
    ($createWithFu['json']['ok'] ?? false) === true && is_string($inqWithFuId) && !empty($createFuId),
    "inq=$inqWithFuId fu=$createFuId"
);

$withFuLoad = http($adminJar, 'POST', "$BASE/api/followup.php", ['action' => 'load', 'inquiryId' => $inqWithFuId]);
$matchedFu = null;
foreach (($withFuLoad['json']['data'] ?? []) as $row) {
    if ((int)($row['id'] ?? 0) === (int)$createFuId) {
        $matchedFu = $row;
        break;
    }
}
ok('Load create-time follow-up by inquiry', $matchedFu !== null);
ok(
    'Create-time follow-up linked to inquiry',
    $matchedFu
        && ($matchedFu['inquiry_id'] ?? '') === $inqWithFuId
        && ($matchedFu['note'] ?? '') === $fuNote
        && ($matchedFu['follow_up_date'] ?? '') === $fuDate
        && ($matchedFu['assigned_to'] ?? '') === 'Team Member',
    json_encode($matchedFu ?: [])
);

$createBadFu = http($adminJar, 'POST', "$BASE/api/inquiries.php", array_merge($baseCreate, [
    'client' => $qaBadFuClient,
    'scheduleFollowUp' => true,
    'followUpNote' => '',
    'followUpDate' => $fuDate,
]));
ok(
    'Missing follow-up note rejected',
    ($createBadFu['json']['ok'] ?? true) === false && $createBadFu['code'] === 400,
    $createBadFu['body']
);
ok(
    'Validation message for missing follow-up fields',
    stripos((string)($createBadFu['json']['message'] ?? ''), 'Follow-up note and date') !== false,
    (string)($createBadFu['json']['message'] ?? '')
);

try {
    $qaPdo = local_sp_work_pdo();
    $chk = $qaPdo->prepare('SELECT COUNT(*) FROM inquiries WHERE client = ?');
    $chk->execute([$qaBadFuClient]);
    $badCount = (int)$chk->fetchColumn();
    ok('Failed follow-up create left no inquiry', $badCount === 0, "count=$badCount");
} catch (Exception $e) {
    ok('Failed follow-up create left no inquiry', false, $e->getMessage());
}

// ── 5. Member row-level filtering ────────────────────────────────────────────
echo "\n5. Authorisation — row-level filter\n";
$memList = http($memberJar, 'GET', "$BASE/api/inquiries.php");
$memSeesOurs = false;
$memSeesOthersOnly = true;
foreach (($memList['json']['data'] ?? []) as $row) {
    if (($row['id'] ?? '') === $inqId) $memSeesOurs = true;
}
ok('Member sees assigned inquiry', $memSeesOurs);
ok('Member list endpoint succeeds', ($memList['json']['ok'] ?? false) === true, 'count=' . count($memList['json']['data'] ?? []));

$memStage = http($memberJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'stage_update',
    'id' => $inqId,
    'stage' => 'Decision',
    'outcome' => 'In Progress',
]);
ok('Member cannot stage-update others\' inquiry', ($memStage['json']['ok'] ?? true) === false && $memStage['code'] === 403);

// ── 6. Accounts / approvals ──────────────────────────────────────────────────
echo "\n6. Accounts & approvals\n";
$signupEmail = 'qa.pending.' . time() . '@example.com';
$signup = http(tempnam($tmp, 'sp_su_'), 'POST', "$BASE/api/signup.php", [
    'name' => 'QA Pending User',
    'email' => $signupEmail,
    'password' => 'TestPass9',
]);
ok('Signup creates pending account', ($signup['json']['ok'] ?? false) === true, $signup['body']);

$acc = http($adminJar, 'GET', "$BASE/api/accounts.php");
ok('Accounts list (admin)', ($acc['json']['ok'] ?? false) === true && count($acc['json']['data'] ?? []) >= 1);
$pendingId = null;
foreach (($acc['json']['data'] ?? []) as $a) {
    if (($a['email'] ?? '') === $signupEmail) { $pendingId = (int)$a['id']; break; }
}
ok('Pending signup visible', $pendingId > 0, "id=$pendingId");

$approve = http($adminJar, 'POST', "$BASE/api/accounts.php", ['action' => 'approve', 'id' => $pendingId]);
ok('Approve pending user', ($approve['json']['ok'] ?? false) === true);

$desc = http($adminJar, 'POST', "$BASE/api/accounts.php", [
    'action' => 'role_description',
    'id' => $pendingId,
    'description' => 'QA Proposal Lead',
]);
$descSaved = false;
foreach (($desc['json']['data'] ?? []) as $a) {
    if ((int)($a['id'] ?? 0) === $pendingId && ($a['role_description'] ?? '') === 'QA Proposal Lead') $descSaved = true;
}
ok('Set role description', ($desc['json']['ok'] ?? false) === true && $descSaved, $descSaved ? 'saved' : substr($desc['body'], 0, 200));

$block = http($adminJar, 'POST', "$BASE/api/accounts.php", ['action' => 'block', 'id' => $pendingId]);
ok('Block user', ($block['json']['ok'] ?? false) === true);
$unblock = http($adminJar, 'POST', "$BASE/api/accounts.php", ['action' => 'unblock', 'id' => $pendingId]);
ok('Unblock user', ($unblock['json']['ok'] ?? false) === true);

// Member cannot list accounts
$memAcc = http($memberJar, 'GET', "$BASE/api/accounts.php");
ok('Member denied accounts API', $memAcc['code'] === 403 || ($memAcc['json']['ok'] ?? true) === false);

// ── 7. Messages ──────────────────────────────────────────────────────────────
echo "\n7. Messages\n";
$contacts = http($adminJar, 'GET', "$BASE/api/messages.php?action=contacts");
ok('Message contacts', ($contacts['json']['ok'] ?? false) === true && count($contacts['json']['data'] ?? []) >= 1);
$memberId = null;
foreach (($contacts['json']['data'] ?? []) as $c) {
    if (($c['name'] ?? '') === 'Team Member' || stripos($c['email'] ?? '', 'member@') !== false) {
        $memberId = (int)$c['id'];
        break;
    }
}
if (!$memberId) {
    // fallback: look up from accounts
    foreach (($acc['json']['data'] ?? []) as $a) {
        if (($a['email'] ?? '') === 'member@surveypacific.com') { $memberId = (int)$a['id']; break; }
    }
}
$send = http($adminJar, 'POST', "$BASE/api/messages.php", [
    'action' => 'send',
    'receiverId' => $memberId,
    'body' => 'QA integration ping ' . time(),
]);
ok('Send direct message', ($send['json']['ok'] ?? false) === true && !empty($send['json']['id']), $send['body']);

$thread = http($adminJar, 'GET', "$BASE/api/messages.php?action=thread&withId=$memberId");
ok('Load message thread', ($thread['json']['ok'] ?? false) === true && count($thread['json']['data'] ?? []) >= 1);

// ── 8. Leave ─────────────────────────────────────────────────────────────────
echo "\n8. Leave requests\n";
$leaveList = http($memberJar, 'GET', "$BASE/api/leave.php?action=list");
ok('Member leave list + summary', ($leaveList['json']['ok'] ?? false) === true && isset($leaveList['json']['summary']));

$start = date('Y-m-d', strtotime('+'.(200 + (int)date('s')).' days'));
$end   = date('Y-m-d', strtotime($start.' +1 day'));
$leaveYear = (int)substr($start, 0, 4);
$leaveNeedDays = (new DateTime($start))->diff(new DateTime($end))->days + 1;
$leaveReady = false;

try {
    if (!$memberId) {
        throw new RuntimeException('memberId not resolved; refusing leave fixture cleanup');
    }
    $leavePdo = local_sp_work_pdo();
    $purged = purge_qa_integration_leave($leavePdo, (int)$memberId);
    ok('Leave fixture: purged prior QA leave', true, 'removed='.$purged.' account='.$memberId);
    $rem = leave_remaining_days($leavePdo, (int)$memberId, $leaveYear);
    $leaveReady = $rem >= $leaveNeedDays;
    ok(
        'Leave fixture: entitlement headroom',
        $leaveReady,
        'year='.$leaveYear.' remaining='.$rem.' need='.$leaveNeedDays
            .($leaveReady ? '' : ' (non-QA leave may remain; not deleting)')
    );
} catch (Throwable $e) {
    ok('Leave fixture: local DB cleanup', false, $e->getMessage());
}

$leaveId = null;
if ($leaveReady) {
    $leaveReq = http($memberJar, 'POST', "$BASE/api/leave.php", [
        'action' => 'request',
        'leaveType' => 'Casual Leave',
        'startDate' => $start,
        'endDate' => $end,
        'reason' => 'QA integration leave request '.$uniq,
    ]);
    $leaveId = $leaveReq['json']['id'] ?? null;
    ok('Member request leave', ($leaveReq['json']['ok'] ?? false) === true && $leaveId, $leaveReq['body']);
} else {
    ok('Member request leave', false, 'skipped — leave fixture precondition failed');
}

$adminLeaveBlock = http($adminJar, 'POST', "$BASE/api/leave.php", [
    'action' => 'request',
    'leaveType' => 'Casual Leave',
    'startDate' => $start,
    'endDate' => $end,
    'reason' => 'should fail',
]);
ok('Admin cannot submit leave', ($adminLeaveBlock['json']['ok'] ?? true) === false);

if ($leaveId) {
    $decide = http($adminJar, 'POST', "$BASE/api/leave.php", [
        'action' => 'decide',
        'id' => $leaveId,
        'status' => 'approved',
        'note' => 'QA approved',
    ]);
    ok('Admin approve leave', ($decide['json']['ok'] ?? false) === true, $decide['body']);
} else {
    ok('Admin approve leave', false, 'skipped — no leave request id');
}

// Drop this suite's leave rows so the next run does not burn yearly entitlement.
try {
    if ($memberId) {
        $n = purge_qa_integration_leave(local_sp_work_pdo(), (int)$memberId);
        ok('Leave fixture: post-workflow cleanup', true, 'removed='.$n);
    } else {
        ok('Leave fixture: post-workflow cleanup', false, 'memberId missing');
    }
} catch (Throwable $e) {
    ok('Leave fixture: post-workflow cleanup', false, $e->getMessage());
}

// ── 9. Warnings (3-strike salary deduction) ──────────────────────────────────
echo "\n9. Warnings\n";
$wList = http($adminJar, 'GET', "$BASE/api/warnings.php?action=list");
ok('Warnings list', ($wList['json']['ok'] ?? false) === true);

$warnTarget = $memberId;
$issue = http($adminJar, 'POST', "$BASE/api/warnings.php", [
    'action' => 'issue',
    'accountId' => $warnTarget,
    'reason' => 'QA test warning ' . time(),
]);
ok('Issue warning', ($issue['json']['ok'] ?? false) === true, $issue['body']);
$warnCount = (int)($issue['json']['count'] ?? 0);
$deduction = (int)($issue['json']['deduction'] ?? -1);
ok('Deduction rule floor(count/3)*500', $deduction === (int)(floor($warnCount / 3) * 500), "count=$warnCount deduction=$deduction");

// Find admin id
$acc2 = http($adminJar, 'GET', "$BASE/api/accounts.php");
$adminId = null;
foreach (($acc2['json']['data'] ?? []) as $a) {
    if (($a['email'] ?? '') === 'admin@surveypacific.com') { $adminId = (int)$a['id']; break; }
}
$selfWarn = http($adminJar, 'POST', "$BASE/api/warnings.php", [
    'action' => 'issue',
    'accountId' => $adminId,
    'reason' => 'self',
]);
ok('Cannot warn yourself', ($selfWarn['json']['ok'] ?? true) === false);

$memWarn = http($memberJar, 'POST', "$BASE/api/warnings.php", [
    'action' => 'issue',
    'accountId' => $adminId,
    'reason' => 'nope',
]);
ok('Member cannot issue warning', $memWarn['code'] === 403 || ($memWarn['json']['ok'] ?? true) === false);

// ── 10. Notifications ────────────────────────────────────────────────────────
echo "\n10. Notifications\n";
$notifs = http($memberJar, 'GET', "$BASE/api/notifications.php");
ok('Member notifications load', ($notifs['json']['ok'] ?? false) === true);
ok('Member has notifications from task assign', count($notifs['json']['data'] ?? []) >= 1, 'count=' . count($notifs['json']['data'] ?? []));

// ── 11. Announcements ────────────────────────────────────────────────────────
echo "\n11. Announcements\n";
$annPost = http($adminJar, 'POST', "$BASE/api/announcements.php", [
    'action' => 'post',
    'body' => 'QA integration announcement ' . time(),
]);
ok('Admin post announcement', ($annPost['json']['ok'] ?? false) === true && !empty($annPost['json']['id']), $annPost['body']);
$annId = (int)($annPost['json']['id'] ?? 0);

$annEdit = http($adminJar, 'POST', "$BASE/api/announcements.php", [
    'action' => 'edit',
    'id' => $annId,
    'body' => 'QA edited announcement ' . time(),
]);
ok('Admin edit announcement', ($annEdit['json']['ok'] ?? false) === true, $annEdit['body']);

$annList = http($adminJar, 'GET', "$BASE/api/announcements.php?action=list");
ok('List announcements', ($annList['json']['ok'] ?? false) === true && count($annList['json']['data'] ?? []) >= 1);

$annDel = http($adminJar, 'POST', "$BASE/api/announcements.php", [
    'action' => 'delete',
    'id' => $annId,
]);
ok('Admin delete announcement', ($annDel['json']['ok'] ?? false) === true, $annDel['body']);

$annSeen = http($memberJar, 'POST', "$BASE/api/announcements.php", ['action' => 'markSeen']);
ok('Member markSeen announcements', ($annSeen['json']['ok'] ?? false) === true);

$annMem = http($memberJar, 'POST', "$BASE/api/announcements.php", [
    'action' => 'post',
    'body' => 'should fail',
]);
ok('Member cannot post announcement', $annMem['code'] === 403 || ($annMem['json']['ok'] ?? true) === false);

$annMemEdit = http($memberJar, 'POST', "$BASE/api/announcements.php", [
    'action' => 'edit',
    'id' => 1,
    'body' => 'nope',
]);
ok('Member cannot edit announcement', $annMemEdit['code'] === 403 || ($annMemEdit['json']['ok'] ?? true) === false);

// ── 12. Reports page (Tracking removed — volume/KPIs live on Reports) ────────
echo "\n12. Reports\n";
$rpt = http($adminJar, 'GET', "$BASE/reports-view.php");
ok('Reports view loads for admin', $rpt['code'] === 200 && (stripos($rpt['body'], 'Team') !== false || stripos($rpt['body'], 'Reports') !== false || strlen($rpt['body']) > 500), 'len=' . strlen($rpt['body']));
ok('Reports has people directory / view report',
    stripos($rpt['body'], 'View report') !== false
    && (stripos($rpt['body'], 'Team members') !== false || stripos($rpt['body'], 'rptPeople') !== false),
    'missing people-first Reports UI');
ok('Reports has performance pie + volume',
    stripos($rpt['body'], 'Performance mix') !== false
    && stripos($rpt['body'], 'rpt-perf-chart') !== false
    && stripos($rpt['body'], 'Inquiry volume') !== false
    && (stripos($rpt['body'], 'Team performance') !== false || stripos($rpt['body'], 'rptTeamStats') !== false),
    'missing expected Reports charts');
ok('Reports summary strip has overdue/pending focus metrics',
    stripos($rpt['body'], 'rptSummary') !== false
    && stripos($rpt['body'], 'Late closed') !== false
    && stripos($rpt['body'], 'Internal late') !== false
    && stripos($rpt['body'], 'What each number means') !== false
    && stripos($rpt['body'], '11:30') !== false,
    'missing focus metrics');

$rptMem = http($memberJar, 'GET', "$BASE/reports-view.php");
ok('Reports view does not 500 for member', $rptMem['code'] !== 500, 'code=' . $rptMem['code']);
ok('Tracking page is gone', !is_file(__DIR__ . '/../tracking-view.php'));

// ── 13. Index (dashboard) SSR ────────────────────────────────────────────────
echo "\n13. Dashboard / index SSR\n";
$idx = http($adminJar, 'GET', "$BASE/index.php");
ok('Index loads when authenticated', $idx['code'] === 200 && strlen($idx['body']) > 5000, 'len=' . strlen($idx['body']) . ' code=' . $idx['code']);
ok('Index embeds Alpine state', stripos($idx['body'], 'x-data') !== false || stripos($idx['body'], 'inquiries') !== false);
ok('Calendar has yearly leave manager (admin)',
    stripos($idx['body'], 'Yearly leave') !== false
    && stripos($idx['body'], 'holManageOpen') !== false
    && stripos($idx['body'], 'is-sunday') !== false
    && stripos($idx['body'], 'cal-row') !== false,
    'missing calendar holiday UI');

$hol = http($adminJar, 'GET', "$BASE/api/holidays.php?action=list&year=2026");
ok('Holidays API lists for admin', ($hol['json']['ok'] ?? false) === true, $hol['body'] ?? '');

$idxAnonJar = tempnam($tmp, 'sp_anon_');
$idxAnon = http($idxAnonJar, 'GET', "$BASE/index.php");
ok('Index redirects unauthenticated', in_array($idxAnon['code'], [302, 301, 303], true) || stripos($idxAnon['body'], 'login') !== false, 'code=' . $idxAnon['code']);
@unlink($idxAnonJar);

// ── 14. Close inquiry (won path) + late closed_at ────────────────────────────
echo "\n14. Closure + late record on inquiry\n";
// Bump due into the past via local PDO (do not include includes/db.php — CLI host detection
// falls through to production credentials when SERVER_NAME is empty).
try {
    $cli = new PDO('mysql:host=localhost;dbname=sp_work;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
    ]);
    $cli->prepare("UPDATE inquiries SET due_date=?, due_time=? WHERE id=?")
        ->execute([date('d M Y', strtotime('-5 days')), '18:00', $inqId]);
    ok('Past-due stamp applied for late-close test', true);
} catch (Exception $e) {
    ok('Past-due stamp applied for late-close test', false, $e->getMessage());
}

$close = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'stage_update',
    'id' => $inqId,
    'stage' => 'Closure',
    'outcome' => 'Payment Received',
    'remark' => 'QA closed',
    'finalRemark' => 'Paid',
]);
ok('Close inquiry (Payment Received)', ($close['json']['ok'] ?? false) === true);

$list = http($adminJar, 'GET', "$BASE/api/inquiries.php");
$found = null;
foreach (($list['json']['data'] ?? []) as $row) {
    if (($row['id'] ?? '') === $inqId) { $found = $row; break; }
}
ok('Closed inquiry marked late', !empty($found['late']), 'late=' . var_export($found['late'] ?? null, true) . ' closed_at=' . ($found['closed_at'] ?? ''));
ok('Closed inquiry not overdue', empty($found['overdue']));

// ── 15. Cleanup test inquiry ─────────────────────────────────────────────────
echo "\n15. Cleanup\n";
$del = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
    'action' => 'delete_inquiry',
    'id' => $inqId,
]);
ok('Master Admin can delete inquiry', ($del['json']['ok'] ?? false) === true, $del['body']);

foreach ([$inqNoFuId, $inqWithFuId] as $qaFuInqId) {
    if (!is_string($qaFuInqId) || $qaFuInqId === '') continue;
    $delFuInq = http($adminJar, 'POST', "$BASE/api/inquiry.php", [
        'action' => 'delete_inquiry',
        'id' => $qaFuInqId,
    ]);
    ok(
        "Cleanup create-time FU inquiry $qaFuInqId",
        ($delFuInq['json']['ok'] ?? false) === true,
        $delFuInq['body']
    );
}

$delUser = http($adminJar, 'POST', "$BASE/api/accounts.php", [
    'action' => 'delete',
    'id' => $pendingId,
]);
ok('Cleanup pending QA user', ($delUser['json']['ok'] ?? false) === true || $delUser['code'] === 200, $delUser['body']);

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n=== SUMMARY ===\n";
echo "Passed: $pass\nFailed: $fail\nTotal:  " . ($pass + $fail) . "\n";

@unlink($adminJar);
@unlink($opsJar);
@unlink($memberJar);
@unlink($clientJar);

exit($fail > 0 ? 1 : 0);
