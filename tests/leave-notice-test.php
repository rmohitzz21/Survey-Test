<?php
/**
 * Leave type notice window: Casual / WFH / Sick need ≥12h before start;
 * inside that window only Emergency Leave is allowed.
 * Run: php tests/leave-notice-test.php
 */
require_once __DIR__.'/../includes/functions.php';

date_default_timezone_set('Asia/Kolkata');

function eq($actual, $expected, string $msg): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $msg\n  expected: ".var_export($expected, true)."\n  actual:   ".var_export($actual, true)."\n");
        exit(1);
    }
}

// Fixed "now": Monday 21 Sep 2026, 12:00 IST
$now = strtotime('2026-09-21 12:00:00');

eq(leave_has_notice('2026-09-21', $now), false, 'same day — no notice left');
eq(leave_has_notice('2026-09-22', $now), true,  'tomorrow 00:00 is exactly 12h away — boundary passes');
eq(leave_has_notice('2026-09-23', $now), true,  'day after tomorrow — well past 12h');
eq(leave_has_notice('2026-10-01', $now), true,  'far future — ≥12h');

// 13:00 the same day: tomorrow 00:00 is 11h out, just inside the window.
eq(leave_has_notice('2026-09-22', strtotime('2026-09-21 13:00:00')), false, '11h notice — too short');

eq(leave_types_for_start('2026-09-21', $now), ['Emergency Leave'], 'short notice → Emergency only');
eq(leave_types_for_start('2026-09-22', $now), ['Casual Leave','WFH','Sick Leave'], '≥12h → advance types');

eq(leave_type_allowed('Casual Leave', '2026-09-23', $now), true,  'Casual with notice OK');
eq(leave_type_allowed('WFH', '2026-09-23', $now), true,          'WFH with notice OK');
eq(leave_type_allowed('Sick Leave', '2026-09-23', $now), true,    'Sick with notice OK');
eq(leave_type_allowed('Emergency Leave', '2026-09-23', $now), false, 'Emergency not for advance window');
eq(leave_type_allowed('Emergency Leave', '2026-09-21', $now), true,  'Emergency for short notice OK');
eq(leave_type_allowed('Casual Leave', '2026-09-21', $now), false,    'Casual blocked without 12h');
eq(leave_type_allowed('Half Day', '2026-09-23', $now), false,        'Half Day no longer offered');

echo "leave-notice-test: all passed\n";
