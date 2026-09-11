<?php
// Self-check for due_ts() — the overdue calculation.  Run: php tests/due-ts-test.php
require_once __DIR__.'/../includes/functions.php';

function eq($actual, $expected, string $msg): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $msg\n  expected: ".var_export($expected, true)."\n  actual:   ".var_export($actual, true)."\n");
        exit(1);
    }
}

$noon = strtotime('2026-08-28 12:00');

// Date only → end of that day
eq(due_ts('28 Aug 2026'), strtotime('2026-08-28 23:59'), 'date only = end of day');
eq(due_ts('28 Aug 2026') < $noon, false, 'due today, no time → not overdue at noon');
eq(due_ts('27 Aug 2026') < $noon, true,  'yesterday → overdue');

// Date + inquiry due_time ('HH:MM')
eq(due_ts('28 Aug 2026', '14:30'), strtotime('2026-08-28 14:30'), 'date + HH:MM');
eq(due_ts('28 Aug 2026', '09:00') < $noon, true,  'time passed today → overdue');
eq(due_ts('28 Aug 2026', '18:00') < $noon, false, 'time still ahead → pending');

// Step format stores the time inline; the "·" separator used to make strtotime fail
eq(due_ts('28 Aug 2026 · 03:00 PM'), strtotime('2026-08-28 15:00'), 'step "·" format parses');
eq(due_ts('28 Aug 2026 · 09:00 AM') < $noon, true, 'step time passed → overdue');

// Legacy rows truncated by the old VARCHAR(20) column lost their AM/PM — must not read as overdue
eq(due_ts('28 Aug 2026 · 02:30'),    strtotime('2026-08-28 23:59'), 'truncated time → end of day');
eq(due_ts('28 Aug 2026 · 02:30 P'),  strtotime('2026-08-28 23:59'), 'half-truncated meridiem → end of day');
eq(due_ts('28 Aug 2026 · 02:30 PM') < $noon, false, 'full PM time today → not overdue at noon');

// No due
eq(due_ts('TBD'), null, 'TBD');
eq(due_ts('tbd'), null, 'TBD is case-insensitive');
eq(due_ts(''),    null, 'empty');
eq(due_ts(null),  null, 'null');
eq(due_ts('not a date'), null, 'unparseable');

// ─── is_late() — the permanent record a late submit leaves behind ─────────────
$due = due_ts('28 Aug 2026 · 03:00 PM');

eq(is_late('2026-08-28 15:30:00', $due), true,  'submitted after due → late');
eq(is_late('2026-08-29 09:00:00', $due), true,  'submitted next day → late');
eq(is_late('2026-08-28 14:59:00', $due), false, 'submitted before due → on time');
eq(is_late(null,  $due), false, 'still open → overdue, not late');
eq(is_late('',    $due), false, 'blank stamp → not late');
eq(is_late('2026-08-29 09:00:00', null),  false, 'no due date → never late');
eq(is_late('2026-09-01 10:00:00', due_ts('31 Aug 2026')), true, 'date-only due → late after end of that day');
eq(is_late('2026-08-31 23:58:00', due_ts('31 Aug 2026')), false, 'date-only due → on time until 23:59');

// ─── is_closed_inquiry() ─────────────────────────────────────────────────────
eq(is_closed_inquiry('Closure', 'Inquiry Closed'), true,  'closed stage + outcome');
eq(is_closed_inquiry('Proposal Sent', 'Lost'),     true,  'closing outcome alone');
eq(is_closed_inquiry('Project Closed', 'In Progress'), true, 'closing stage alone');
eq(is_closed_inquiry('Proposal Sent', 'In Progress'), false, 'still open');
eq(is_closed_inquiry(null, null), false, 'nulls are not closed');

echo "ok\n";
