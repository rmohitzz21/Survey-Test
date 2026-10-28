<?php
// Self-check for is_completing_inquiry() — the rule that blocks completing an inquiry while a
// task on it is still open.  Run: php tests/complete-guard-test.php
require_once __DIR__.'/../includes/functions.php';

function eq($actual, $expected, string $msg): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $msg\n  expected: ".var_export($expected, true)."\n  actual:   ".var_export($actual, true)."\n");
        exit(1);
    }
}

// Finishing the work — these must be guarded, open tasks block them
eq(is_completing_inquiry('Closure', 'Inquiry Closed'),        true, 'quick-complete modal is guarded');
eq(is_completing_inquiry('Payment Received', 'In Progress'),  true, 'payment received stage is guarded');
eq(is_completing_inquiry('', 'Completed'),                    true, 'Completed outcome is guarded');
eq(is_completing_inquiry('Project Closed', ''),               true, 'Project Closed stage is guarded');

// Abandoning the work — nobody owes those tasks any more, so closing must stay possible
eq(is_completing_inquiry('', 'Lost'),                false, 'lost inquiry closes with tasks open');
eq(is_completing_inquiry('', 'Project Lost'),        false, 'project lost closes with tasks open');
eq(is_completing_inquiry('', 'Cancelled'),           false, 'cancelled closes with tasks open');
eq(is_completing_inquiry('Proposal Lost', ''),       false, 'proposal lost closes with tasks open');

// Still in flight — guard must not fire at all
eq(is_completing_inquiry('Inquiry Received', 'In Progress'), false, 'open inquiry is not completing');
eq(is_completing_inquiry('', ''),                            false, 'empty stage/outcome is not completing');
eq(is_completing_inquiry(null, null),                        false, 'null stage/outcome is not completing');

// Every completion outcome must also count as closed, or closed_at would never be stamped
foreach (COMPLETION_OUTCOMES as $o) eq(is_closed_inquiry('', $o), true, "completion outcome '$o' also closes");
foreach (COMPLETION_STAGES   as $s) eq(is_closed_inquiry($s, ''), true, "completion stage '$s' also closes");

// A task only stops blocking once it reaches a terminal status
eq(in_array('Done',        SETTLED_STEP_STATUSES, true), true,  'Done settles a task');
eq(in_array('Completed',   SETTLED_STEP_STATUSES, true), true,  'legacy "Completed" settles a task');
eq(in_array('Rejected',    SETTLED_STEP_STATUSES, true), true,  'Rejected settles a task');
eq(in_array('Cancelled',   SETTLED_STEP_STATUSES, true), true,  'Cancelled settles a task');
eq(in_array('Pending',     SETTLED_STEP_STATUSES, true), false, 'Pending still blocks');
eq(in_array('In Progress', SETTLED_STEP_STATUSES, true), false, 'In Progress still blocks');
eq(in_array('On Hold',     SETTLED_STEP_STATUSES, true), false, 'On Hold still blocks');
eq(in_array('Blocked',     SETTLED_STEP_STATUSES, true), false, 'Blocked still blocks');
eq(in_array('Submitted for Review', SETTLED_STEP_STATUSES, true), false, 'awaiting review still blocks');

// Whatever the task dropdown offers must be either settled or blocking — never unknown
foreach (['New','Pending','In Progress','Client/Team Se Reply Pending','Checking Me','On Hold',
          'Blocked','Submitted for Review','Done','Rejected','Cancelled'] as $s) {
    eq(is_bool(in_array($s, SETTLED_STEP_STATUSES, true)), true, "status '$s' is classified");
}

echo "complete-guard-test: all assertions passed\n";
