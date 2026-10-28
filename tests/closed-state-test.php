<?php
/**
 * "Closed" has exactly one definition, and overdue / late are exclusive states.
 * Usage: C:\xampp\php\php.exe tests/closed-state-test.php
 *
 * Guards the drift that made admin/index.php badge a closed inquiry "Overdue" and left the
 * "Complete Inquiry" button on an already-closed one: both carried hand-typed copies of the
 * closed-stage/outcome lists that had fallen behind includes/functions.php.
 */
require_once dirname(__DIR__).'/includes/functions.php';

$fail = 0;
function assert_true($cond, string $msg): void {
    global $fail;
    if ($cond) echo "  OK  $msg\n";
    else { echo " FAIL $msg\n"; $fail++; }
}

echo "closed-state-test\n";
$root = dirname(__DIR__);

// ── The three that were missing from the copies ──────────────────────────────
foreach ([['Closure','Completed'], ['Closure','Project Lost'], ['Cancelled','']] as [$stage,$outcome]) {
    assert_true(is_closed_inquiry($stage, $outcome),
        "stage '$stage' / outcome '$outcome' counts as closed");
}
// And the ones that must stay open.
foreach ([['Inquiry',''], ['Quotation',''], ['Proposal','Awaiting Reply']] as [$stage,$outcome]) {
    assert_true(!is_closed_inquiry($stage, $outcome),
        "stage '$stage' / outcome '$outcome' is still open");
}

// ── No file may keep its own copy of the lists ───────────────────────────────
$admin = file_get_contents($root.'/admin/index.php');
assert_true(str_contains($admin, 'is_closed_inquiry('),
    'admin/index.php uses the shared closed definition');
assert_true(!preg_match("/in_array\(\\\$inq\['outcome'\]/", $admin),
    'admin/index.php no longer hand-copies the closed-outcome list');

$idx = file_get_contents($root.'/index.php');
assert_true(str_contains($idx, 'isInquiryClosed(inq) { return this.isClosed(inq); }'),
    'isInquiryClosed() delegates to isClosed() instead of its own list');
assert_true(str_contains($idx, 'CLOSED_OUTCOMES = <?= json_encode(CLOSED_OUTCOMES) ?>'),
    'the front end is handed the real constants, so it cannot drift');

// ── Overdue and late never apply to the same inquiry ─────────────────────────
$due = due_ts('10 Sep 2026', '17:00');
assert_true($due !== null, 'due_ts parses a dated + timed deadline');
assert_true(is_late('2026-09-10 18:00:00', $due), 'closed one hour past due is late');
assert_true(!is_late('2026-09-10 16:59:00', $due), 'closed just inside the deadline is not late');
assert_true(!is_late(null, $due), 'an inquiry still open is overdue, never late');
assert_true(!is_late('2026-09-10 18:00:00', null), 'no deadline means no late');

$now = strtotime('2026-09-11 09:00:00');
$overdueOf = fn(array $i) => !is_closed_inquiry($i['stage'], $i['outcome'])
    && due_ts($i['due_date'], $i['due_time']) < $now;
$lateOf = fn(array $i) => is_late($i['closed_at'], due_ts($i['due_date'], $i['due_time']));

$closedLate = ['stage'=>'Closure','outcome'=>'Inquiry Closed','due_date'=>'10 Sep 2026',
               'due_time'=>'17:00','closed_at'=>'2026-09-10 18:00:00'];
$closedOnTime = ['stage'=>'Closure','outcome'=>'Completed','due_date'=>'10 Sep 2026',
                 'due_time'=>'17:00','closed_at'=>'2026-09-10 16:00:00'];
$stillOpen = ['stage'=>'Quotation','outcome'=>'','due_date'=>'10 Sep 2026',
              'due_time'=>'17:00','closed_at'=>null];

assert_true($lateOf($closedLate) && !$overdueOf($closedLate),
    'closed late = late only, never overdue too');
assert_true($overdueOf($stillOpen) && !$lateOf($stillOpen),
    'still open past due = overdue only, never late');
assert_true(!$lateOf($closedOnTime) && !$overdueOf($closedOnTime),
    'closed inside its deadline is neither');

// ── Warnings stay a manual, admin-only action ────────────────────────────────
$warnApi = file_get_contents($root.'/api/warnings.php');
assert_true(str_contains($warnApi, "if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can issue a warning.'], 403)"),
    'issuing a warning is admin-only');
foreach (['api/inquiry.php', 'admin/index.php', 'clear-overdue.php', 'includes/functions.php'] as $f) {
    assert_true(!str_contains(file_get_contents($root.'/'.$f), 'warn_late_closure'),
        "$f does not warn automatically — warnings are issued by hand");
}

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
