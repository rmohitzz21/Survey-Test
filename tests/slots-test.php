<?php
/**
 * Inquiry time slots: 12 × 2-hour blocks from 10:00 covering 24 hours.
 * Run: php tests/slots-test.php
 */
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Asia/Kolkata');

function check(bool $ok, string $msg): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
}

$slots = inquiry_time_slots();
check(count($slots) === 12, 'exactly 12 slots');
check($slots[0]['start'] === '10:00' && $slots[0]['end'] === '12:00', 'first slot 10:00–12:00');
check($slots[11]['start'] === '08:00' && $slots[11]['end'] === '10:00', 'last slot 08:00–10:00 wraps to morning');
check($slots[6]['start'] === '22:00' && $slots[6]['end'] === '00:00', 'overnight wrap 22:00–00:00');

$starts = array_column($slots, 'start');
check(count($starts) === count(array_unique($starts)), 'slot starts are unique');

check(inquiry_slot_label('14:00') !== '', 'label for 14:00');
check(inquiry_slot_label('14:00') === $slots[2]['label'], '14:00 maps to third slot');
check(inquiry_slot_label('') === '', 'empty slot label');
check(inquiry_slot_label('99:99') === '99:99', 'unknown start echoes');

check(normalize_slot_date('2026-09-24') === '2026-09-24', 'ISO date');
check(normalize_slot_date('24 Sep 2026') === '2026-09-24', 'display date');
check(normalize_slot_date('') === null, 'blank date');

check(inquiry_slot_end('10:00') === '12:00', 'end of 10:00');
check(inquiry_slot_end('22:00') === '00:00', 'end of 22:00 wraps');
check(inquiry_slot_end('08:00') === '10:00', 'end of 08:00');

check(inquiry_slot_due_label('2026-09-24', '10:00') === '24 Sep 2026 · 12:00 PM', 'due from daytime slot');
check(inquiry_slot_due_label('2026-09-24', '22:00') === '25 Sep 2026 · 12:00 AM', 'due from overnight slot next day');

echo "slots-test: all passed (12 slots from 10:00)\n";
