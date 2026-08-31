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

// No due
eq(due_ts('TBD'), null, 'TBD');
eq(due_ts('tbd'), null, 'TBD is case-insensitive');
eq(due_ts(''),    null, 'empty');
eq(due_ts(null),  null, 'null');
eq(due_ts('not a date'), null, 'unparseable');

echo "ok\n";
