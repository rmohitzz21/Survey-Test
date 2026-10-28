<?php
/**
 * The demo seeder writes only dates — every "overdue" / "late" flag on the report is then derived
 * by load_inquiries() from those dates. So the thing worth checking is that the dates it picks
 * actually produce the states it promises, judged by the real due_ts() / is_late() rules.
 *
 * Run: php tests/demo-data-test.php
 */
require_once __DIR__.'/../includes/functions.php';   // due_ts(), is_late(), is_closed_inquiry()

date_default_timezone_set('Asia/Kolkata');

function check(bool $cond, string $what): void {
    if (!$cond) { fwrite(STDERR, "FAIL: $what\n"); exit(1); }
}

// ── Mirror of the generator loop in demo-data.php ────────────────────────────
mt_srand(20260915);
$today = new DateTimeImmutable('today');
$now   = time();
$windowStart = $today->modify('first day of -2 months');
$spanDays    = (int)$windowStart->diff($today)->days;

$plan = array_merge(
    array_fill(0, 16, 'closed_ontime'),
    array_fill(0, 6,  'closed_late'),
    array_fill(0, 7,  'overdue'),
    array_fill(0, 13, 'on_track'),
);
shuffle($plan);

$seen = ['closed_ontime'=>0,'closed_late'=>0,'overdue'=>0,'on_track'=>0];
$stepsOverdue = 0; $stepsLate = 0; $stepsTotal = 0;

foreach ($plan as $n => $state) {
    $dueIn = mt_rand(5, 20);
    if ($state === 'on_track') {
        $ageDays = mt_rand(0, max(1, $dueIn - 2));
    } elseif ($state === 'overdue') {
        $ageDays = mt_rand($dueIn + 1, min($spanDays, $dueIn + 30));
    } else {
        $ageDays = mt_rand($dueIn + 1, max($dueIn + 2, $spanDays));
    }
    $created = $today->modify("-$ageDays days");
    $due     = $created->modify("+$dueIn days")->setTime(18, 0);

    $closedAt = null;
    if ($state === 'closed_ontime') {
        $closedAt = $created->modify('+'.mt_rand(1, max(1, $dueIn - 1)).' days')->setTime(mt_rand(10, 17), 0);
        $stage = 'Closure'; $outcome = ($n % 4 === 0) ? 'Project Lost' : 'Payment Received';
    } elseif ($state === 'closed_late') {
        $closedAt = $due->modify('+'.mt_rand(1, 9).' days')->setTime(mt_rand(10, 17), 0);
        if ($closedAt->getTimestamp() > $now) $closedAt = (new DateTimeImmutable())->modify('-1 hour');
        $stage = 'Closure'; $outcome = 'Payment Received';
    } else {
        $stage = ['Inquiry','Communication / Proposal','Decision','Project Execution'][$n % 4];
        $outcome = 'In Progress';
    }

    // ── Now judge it exactly as the app does ──
    $dueTs    = due_ts($due->format('d M Y'), '18:00');
    $isClosed = is_closed_inquiry($stage, $outcome);
    $isOver   = ($dueTs !== null && $dueTs < $now && !$isClosed);
    $isLate   = is_late($closedAt?->format('Y-m-d H:i:s'), $dueTs);

    check($dueTs !== null, "inquiry $n: the generated due_date parses ('d M Y' + 'HH:MM')");
    check($created->getTimestamp() <= $now, "inquiry $n: not created in the future");
    check($created >= $windowStart->modify('-1 day'), "inquiry $n: inside the three-month window");

    switch ($state) {
        case 'on_track':
            check(!$isClosed, "inquiry $n planned on_track is open");
            check(!$isOver,   "inquiry $n planned on_track is NOT overdue");
            break;
        case 'overdue':
            check(!$isClosed, "inquiry $n planned overdue is open");
            check($isOver,    "inquiry $n planned overdue really is past due");
            break;
        case 'closed_ontime':
            check($isClosed, "inquiry $n planned closed_ontime is closed");
            check(!$isLate,  "inquiry $n planned closed_ontime did NOT miss its due date");
            check(!$isOver,  "a closed inquiry never reads as overdue (n=$n)");
            break;
        case 'closed_late':
            check($isClosed, "inquiry $n planned closed_late is closed");
            check($isLate,   "inquiry $n planned closed_late really did miss its due date");
            check(!$isOver,  "closing late clears overdue but keeps late (n=$n)");
            break;
    }
    check($closedAt === null || $closedAt->getTimestamp() <= $now, "inquiry $n: not closed in the future");
    $seen[$state]++;

    // ── Tasks ──
    for ($s = 0, $stepCount = mt_rand(1, 3); $s < $stepCount; $s++) {
        $stepDue  = $created->modify('+'.mt_rand(2, max(3, $dueIn)).' days')->setTime(mt_rand(11, 17), 0);
        $stepDueS = $stepDue->format('d M Y').' · '.$stepDue->format('h:i A');

        if ($closedAt !== null) {
            $lateTask  = (($n + $s) % 4 === 0);
            $completed = $lateTask ? $stepDue->modify('+'.mt_rand(1, 5).' days')
                                   : $stepDue->modify('-'.mt_rand(1, 2).' days');
            if ($completed->getTimestamp() > $now) $completed = (new DateTimeImmutable())->modify('-2 hours');
            $status = 'Done'; $completedS = $completed->format('Y-m-d H:i:s');
        } else {
            $status = 'Pending'; $completedS = null;
        }

        // The step due string is the format that once broke: a too-short column chopped the AM/PM
        // and every afternoon task read as overdue all day. It must survive a round trip.
        $stepTs = due_ts($stepDueS);
        check($stepTs !== null, "step $n.$s: '$stepDueS' parses");
        check(abs($stepTs - $stepDue->getTimestamp()) < 60,
              "step $n.$s: due string round-trips to the same minute (AM/PM intact)");

        $stepOver = ($stepTs !== null && $stepTs < $now
                     && !in_array($status, ['Done','Cancelled'], true) && !$isClosed);
        $stepLate = is_late($completedS, $stepTs);

        check(!($stepOver && $isClosed), "step $n.$s: a closed inquiry owes nobody an overdue task");
        if ($stepOver) $stepsOverdue++;
        if ($stepLate) $stepsLate++;
        $stepsTotal++;
    }
}

// ── The demo has to actually demonstrate something ──
check($seen['closed_ontime'] === 16, 'planned mix is preserved: 16 closed on time');
check($seen['closed_late']   === 6,  'planned mix is preserved: 6 closed late');
check($seen['overdue']       === 7,  'planned mix is preserved: 7 overdue');
check($seen['on_track']      === 13, 'planned mix is preserved: 13 on track');
check($stepsOverdue > 0, 'at least one task is overdue, or the team table shows nothing');
check($stepsLate    > 0, 'at least one task was delivered late, or "Missed tasks" is always blank');
check($stepsTotal   >= count($plan), 'every inquiry got at least one task');

$total   = count($plan);
$closed  = $seen['closed_ontime'] + $seen['closed_late'];
$overdue = $seen['overdue'];
check($seen['on_track'] + $overdue + $closed === $total,
      'every inquiry lands in exactly one state: on track + overdue + closed === total');

// Needs attention lists overdue plus closed-late, so both halves must be non-empty or the block
// renders its "nothing is past due" state and the demo demonstrates nothing.
check($overdue > 0,               'the Needs-attention list has overdue rows');
check($seen['closed_late'] > 0,   'the Needs-attention list has closed-late rows');

// Created vs closed needs both bars on the chart, not just blue ones.
check($closed > 0 && $total > $closed, 'the chart has both created and closed bars to draw');

echo "demo-data-test: all passed ({$total} inquiries, {$stepsTotal} tasks, "
   . "{$overdue} overdue + {$seen['closed_late']} closed-late for Needs attention, "
   . "{$stepsOverdue} overdue tasks, {$stepsLate} late tasks)\n";
