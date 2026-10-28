<?php
// Round-trip check for manage-user.php's date conversion: the DB stores '01 Sep 2026'
// strings while the form uses native <input type="date"> (Y-m-d). Both directions must
// survive the trip, and 'TBD' must stay TBD instead of becoming 01 Jan 1970.
// Run: php tests/manage-user-test.php

date_default_timezone_set('Asia/Kolkata');

function to_stored(string $v): string { return $v === '' ? 'TBD' : date('d M Y', strtotime($v)); }
function to_input(?string $v): string {
    $ts = ($v && strcasecmp(trim($v), 'TBD') !== 0) ? strtotime($v) : false;
    return $ts ? date('Y-m-d', $ts) : '';
}

assert(to_input('01 Sep 2026') === '2026-09-01', 'stored date -> input');
assert(to_input('TBD')         === '',           'TBD has no input value');
assert(to_input('tbd')         === '',           'TBD is case-insensitive');
assert(to_input(null)          === '',           'null has no input value');
assert(to_input('')            === '',           'empty has no input value');

assert(to_stored('2026-09-01') === '01 Sep 2026', 'input -> stored date');
assert(to_stored('')           === 'TBD',         'cleared date becomes TBD, not epoch');

// A full round trip must not shift the day in either direction.
foreach (['2026-01-01', '2026-09-17', '2026-12-31'] as $d) {
    assert(to_input(to_stored($d)) === $d, "round trip $d");
}

// ── create_inquiry "Logged at" ───────────────────────────────────────────────
// The portal scopes months by created_at, so a backdated inquiry must carry the picked
// timestamp — not today's — and its `date` column must name the same day.
function logged_at(string $v): array {
    $ts = strtotime($v) ?: time();
    return [date('Y-m-d H:i:s', $ts), date('d M Y', $ts)];
}

[$createdAt, $dateCol] = logged_at('2026-07-04T09:30');
assert($createdAt === '2026-07-04 09:30:00', 'backdated created_at keeps the picked time');
assert($dateCol   === '04 Jul 2026',         'date column matches the picked day');
assert(substr($createdAt, 0, 7) === '2026-07', 'inquiry files into the picked month');

[$createdAt] = logged_at('');            // empty field falls back to now, never to the epoch
assert(substr($createdAt, 0, 4) !== '1970', 'empty logged-at falls back to now');

echo "manage-user date conversion: all assertions passed\n";
