<?php
// Demo data for the Reports view fills the last three months with inquiries, tasks and people
// so every block on the report has something to draw.
//
// Access: Master Admin only → /demo-data.php
// DELETE THIS FILE FROM THE SERVER WHEN YOU ARE DONE. Do not leave on live.
//
// Everything it writes is removable in one click:
//   • inquiries ids are prefixed  DEMO-        (steps cascade on delete)
//   • accounts  emails end with   @demo.invalid  (a reserved TLD can never be real)
// The id's last segment is non-numeric ("D01") on purpose: next_inquiry_id() takes
// MAX(CAST(SUBSTRING_INDEX(id,'-',-1) AS UNSIGNED)), so demo rows cast to 0 and cannot push the
// real inquiry sequence forward.
//
// Deliberately NOT part of migrate.php: migrate re-runs on every visit and would re-seed forever.
require_once __DIR__.'/includes/auth.php';
require_utility_master_admin('login.php');
require_utility_non_production();
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
$csrf = csrf_token();

// Defense in depth: env guard above already blocks SP_APP_ENV=production; refuse known live DB name too.
// Demo rows in the live portal would be indistinguishable from real work, and demo accounts have a published password.
if (DB_NAME === 'survevap_inquiry') {
    http_response_code(403);
    die('Refusing to run against the production database ('.DB_NAME.'). Demo data is for local and UAT only.');
}

const DEMO_PREFIX   = 'DEMO-';
const DEMO_MAIL     = '@demo.invalid';
const DEMO_PASSWORD = 'demo1234';

// Four demo members so the team table has something to rank. Real accounts are never touched —
// demo work is attributed only to these, which is what makes the cleanup total.
$demoMembers = ['Asha Mehta', 'Rahul Verma', 'Priya Nair', 'Imran Sheikh'];
$demoRoles   = ['Proposal Lead', 'Survey Coordinator', 'Client Relations', 'Field Operations'];

$clients = [
    ['Arjun Kapoor',   'Vertex Marine Pvt Ltd'],   ['Neha Sharma',   'Oceanic Survey Co'],
    ['David Fernandes','Coastal Dynamics Ltd'],    ['Meera Iyer',    'Harbour Infra Group'],
    ['Sanjay Rao',     'BlueWave Offshore'],       ['Fatima Khan',   'Delta Port Services'],
    ['Vikram Singh',   'Trident Engineering'],     ['Lakshmi Menon', 'Seabed Analytics'],
    ['Rohit Desai',    'Anchor Logistics'],        ['Ananya Bose',   'Meridian Hydrographics'],
];
$requirements = [
    'Bathymetric survey of the approach channel, 2 km stretch.',
    'Pre-dredge and post-dredge volume computation for berth 4.',
    'Hydrographic survey with tidal correction, monsoon window.',
    'Topographic survey and contour mapping of the yard area.',
    'Underwater inspection of quay wall and fender condition.',
    'Side-scan sonar sweep for debris clearance before piling.',
];
$taskInstructions = [
    'Prepare the technical proposal and cost sheet.',
    'Confirm vessel and crew availability for the survey window.',
    'Compile the tide gauge data and share the correction file.',
    'Draft the deliverable chart set for internal review.',
    'Follow up with the client on the revised scope.',
    'Issue the final invoice and close the documentation.',
];

// ─── What is already here ────────────────────────────────────────────────────
$have = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE id LIKE '".DEMO_PREFIX."%'")->fetchColumn();
$accStmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE email LIKE ?");
$accStmt->execute(['%'.DEMO_MAIL]);
$haveAcc = (int)$accStmt->fetchColumn();

$msg = ''; $err = ''; $summary = null;

// ─── Remove ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['remove'] ?? '') === '1') {
    $pdo->beginTransaction();
    // inquiry_steps / attachments / stage_history all cascade from inquiries
    $delInq = $pdo->prepare("DELETE FROM inquiries WHERE id LIKE ?");
    $delInq->execute([DEMO_PREFIX.'%']);
    $n = $delInq->rowCount();
    $delAcc = $pdo->prepare("DELETE FROM accounts WHERE email LIKE ?");
    $delAcc->execute(['%'.DEMO_MAIL]);
    $a = $delAcc->rowCount();
    $pdo->commit();
    $msg = "Removed $n demo inquiries and $a demo accounts. Nothing else was touched.";
    $have = 0; $haveAcc = 0;
}

// ─── Seed ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
  if ($have > 0) {
    $err = 'Demo data is already loaded. Remove it first, then re-seed.';
  } else {
    // Fixed seed: re-running after a Remove gives byte-identical data, so a screenshot taken
    // today still matches the page tomorrow.
    mt_srand(20260915);

    $today = new DateTimeImmutable('today');
    $now   = time();

    // The report defaults to "This Month", and its KPI badges compare against the month before.
    // Three months of history means the delta badges say something instead of "no prior period",
    // and Quarter / Year have data too.
    $windowStart = $today->modify('first day of -2 months');
    $spanDays    = (int)$windowStart->diff($today)->days;

    // An explicit mix rather than random states a demo that sometimes has no overdue rows is a
    // demo that sometimes fails to demonstrate the thing you opened it for.
    $plan = array_merge(
        array_fill(0, 16, 'closed_ontime'),
        array_fill(0, 6,  'closed_late'),
        array_fill(0, 7,  'overdue'),
        array_fill(0, 13, 'on_track'),
    );
    shuffle($plan);   // seeded by mt_srand above

    $insInq = $pdo->prepare(
        "INSERT INTO inquiries
         (id,date,inquiry_type,client,company,country,is_new,client_type,requirement,
          created_by,current_owner,due_date,due_time,stage,outcome,closed_at,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insStep = $pdo->prepare(
        "INSERT INTO inquiry_steps
         (inquiry_id,assigned_by,assigned_to,instruction,status,completed_at,due,created_at)
         VALUES (?,?,?,?,?,?,?,?)");

    $pdo->beginTransaction();

    // Demo members. Real accounts stay untouched, so removal is total.
    // ON DUPLICATE: email is UNIQUE, and the accounts can outlive a manual inquiry wipe. Without
    // this, re-seeding after deleting only the inquiries dies on a duplicate key.
    $insAcc = $pdo->prepare(
        "INSERT INTO accounts (name,email,password_hash,role,role_description,status,requested_at)
         VALUES (?,?,?,?,?,'approved',?)
         ON DUPLICATE KEY UPDATE name=VALUES(name), role_description=VALUES(role_description),
                                 status='approved'");
    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    foreach ($demoMembers as $mi => $m) {
        $email = strtolower(str_replace(' ', '.', $m)).DEMO_MAIL;
        $insAcc->execute([$m, $email, $hash, 'Member', $demoRoles[$mi],
                          $windowStart->format('Y-m-d')]);
    }

    $counts = ['closed_ontime'=>0,'closed_late'=>0,'overdue'=>0,'on_track'=>0,
               'steps'=>0,'steps_late'=>0,'steps_overdue'=>0];

    foreach ($plan as $n => $state) {
        [$client, $company] = $clients[$n % count($clients)];
        $owner   = $demoMembers[$n % count($demoMembers)];
        $creator = $demoMembers[($n + 2) % count($demoMembers)];

        // Creation date, then a due date relative to it. The report's own overdue/late rules are
        // then left to derive the state from these dates the same code path real rows take.
        $dueIn = mt_rand(5, 20);                       // days from creation to due
        if ($state === 'on_track') {
            // Must still be inside its due date, so it has to be recent.
            $ageDays = mt_rand(0, max(1, $dueIn - 2));
            $created = $today->modify("-$ageDays days");
        } elseif ($state === 'overdue') {
            // Must be past due already, so it has to be older than its own due window.
            $ageDays = mt_rand($dueIn + 1, min($spanDays, $dueIn + 30));
            $created = $today->modify("-$ageDays days");
        } else {
            $ageDays = mt_rand($dueIn + 1, max($dueIn + 2, $spanDays));
            $created = $today->modify("-$ageDays days");
        }
        $due = $created->modify("+$dueIn days")->setTime(18, 0);

        $closedAt = null;
        if ($state === 'closed_ontime') {
            // Finished before the deadline anywhere from the day after creation up to due day.
            $closedAt = $created->modify('+'.mt_rand(1, max(1, $dueIn - 1)).' days')->setTime(mt_rand(10, 17), 0);
            $stage   = 'Closure';
            $outcome = ($n % 4 === 0) ? 'Project Lost' : 'Payment Received';
        } elseif ($state === 'closed_late') {
            // Finished, but after the deadline this is what keeps the miss on the record.
            $closedAt = $due->modify('+'.mt_rand(1, 9).' days')->setTime(mt_rand(10, 17), 0);
            if ($closedAt->getTimestamp() > $now) $closedAt = (new DateTimeImmutable())->modify('-1 hour');
            $stage   = 'Closure';
            $outcome = 'Payment Received';
        } else {
            $stage   = ['Inquiry','Communication / Proposal','Decision','Project Execution'][$n % 4];
            $outcome = 'In Progress';
        }

        $id = DEMO_PREFIX.$created->format('Y').'-D'.str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT);
        $insInq->execute([
            $id,
            $created->format('Y-m-d'),                 // `date` is en-CA in the app
            ($n % 7 === 0) ? 'Internal' : 'Client Project',
            $client, $company, 'India',
            ($n % 5 === 0) ? 1 : 0,
            ($n % 3 === 0) ? 'Repeat' : 'New',
            $requirements[$n % count($requirements)],
            $creator, $owner,
            $due->format('d M Y'),                     // due_date is 'd M Y'
            '18:00',                                   // due_time is 24h 'HH:MM'
            $stage, $outcome,
            $closedAt ? $closedAt->format('Y-m-d H:i:s') : null,
            $created->setTime(mt_rand(9, 17), mt_rand(0, 59))->format('Y-m-d H:i:s'),
        ]);
        $counts[$state]++;

        // ── Tasks ──
        // A closed inquiry closes its tasks with it, so those are all Done otherwise the demo
        // would show tasks that are "overdue" on work nobody still owes.
        $isClosed = $closedAt !== null;
        for ($s = 0; $s < mt_rand(1, 3); $s++) {
            $assignee = $demoMembers[($n + $s + 1) % count($demoMembers)];
            $stepDue  = $created->modify('+'.mt_rand(2, max(3, $dueIn)).' days')->setTime(mt_rand(11, 17), 0);
            $stepDueS = $stepDue->format('d M Y').' · '.$stepDue->format('h:i A');   // the format due_ts() parses

            if ($isClosed) {
                // Roughly one in four closed tasks was handed in after its own deadline.
                $lateTask  = (($n + $s) % 4 === 0);
                $completed = $lateTask
                    ? $stepDue->modify('+'.mt_rand(1, 5).' days')
                    : $stepDue->modify('-'.mt_rand(1, 2).' days');
                if ($completed->getTimestamp() > $now) $completed = (new DateTimeImmutable())->modify('-2 hours');
                $status = 'Done';
                $completedS = $completed->format('Y-m-d H:i:s');
                if ($completed->getTimestamp() > $stepDue->getTimestamp()) $counts['steps_late']++;
            } elseif ($stepDue->getTimestamp() < $now) {
                // Open task, deadline gone: this is what makes a member's "Tasks overdue" non-zero.
                $status = 'Pending';
                $completedS = null;
                $counts['steps_overdue']++;
            } else {
                $status = 'Pending';
                $completedS = null;
            }

            $insStep->execute([
                $id, $creator, $assignee,
                $taskInstructions[($n + $s) % count($taskInstructions)],
                $status, $completedS, $stepDueS,
                $created->format('Y-m-d H:i:s'),
            ]);
            $counts['steps']++;
        }
    }

    $pdo->commit();
    $summary = $counts;
    $have = count($plan); $haveAcc = count($demoMembers);
    $msg = 'Demo data loaded. Open Reports (This Month) — every section below has rows to explore.';
  }
}

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demo data — Reports</title>
<style>
  *{box-sizing:border-box}
  body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#F7F8FA;color:#172B3A;
       margin:0;padding:40px 20px;line-height:1.5}
  .card{max-width:720px;margin:0 auto;background:#fff;border:1px solid #E4E7EC;border-radius:12px;
        padding:28px 30px;box-shadow:0 1px 4px rgba(7,29,43,.05)}
  h1{font-size:19px;margin:0 0 6px}
  h2{font-size:13px;margin:18px 0 8px;color:#172B3A}
  p{font-size:13px;color:#667085;margin:0 0 14px}
  .note{font-size:12px;background:#F8FAFC;border:1px solid #E4E7EC;border-radius:8px;padding:12px 14px;margin:0 0 16px}
  .ok{background:#ECFDF3;border-color:#BBF7D0;color:#16803C}
  .bad{background:#FEF3F2;border-color:#FECDCA;color:#B42318}
  .warn{background:#FFFAEB;border-color:#FDE68A;color:#B54708}
  table{width:100%;border-collapse:collapse;font-size:12px;margin:0 0 16px}
  td{padding:6px 0;border-bottom:1px solid #F2F4F7;vertical-align:top}
  td:last-child{text-align:right;font-weight:700;font-variant-numeric:tabular-nums}
  table.guide td:last-child{text-align:left;font-weight:500;color:#344054}
  table.guide td:first-child{font-weight:700;color:#172B3A;white-space:nowrap;padding-right:14px;width:140px}
  button{font:inherit;font-size:13px;font-weight:700;border:0;border-radius:8px;padding:9px 16px;cursor:pointer}
  .go{background:#1268F3;color:#fff}
  .rm{background:#fff;color:#B42318;border:1px solid #FECDCA}
  a{color:#1268F3}
  code{background:#F2F4F7;border-radius:4px;padding:1px 5px;font-size:12px}
  ol{margin:0 0 14px;padding-left:18px;font-size:12px;color:#344054}
  ol li{margin:4px 0}
</style>
</head>
<body>
<div class="card">
  <h1>Reports sample data</h1>
  <p>Loads <strong>42 inquiries</strong>, tasks, and <strong>4 demo members</strong> across the last
     three months so Reports has volume bars, Needs attention, and the team table to explore.</p>

  <?php if ($msg): ?><div class="note ok"><?= $h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="note bad"><?= $h($err) ?></div><?php endif; ?>

  <h2>What each Reports section will show</h2>
  <table class="guide">
    <tr><td>Inquiry volume</td><td>Created / Closed / Overdue bar chart across the selected period</td></tr>
    <tr><td>Needs attention</td><td>~7 open overdue + ~6 closed late — click a row to jump to the inquiry</td></tr>
    <tr><td>Team performance</td><td>4 members ranked by risk — click a name to focus</td></tr>
    <tr><td>Member view</td><td>Sign in as a demo member to see personal scoped Reports</td></tr>
    <tr><td>Export CSV</td><td>Downloads the inquiry set for the selected period / member</td></tr>
  </table>

  <h2>Try this in Reports</h2>
  <ol>
    <li>Open <strong>Reports</strong> with period <strong>This Month</strong> (default).</li>
    <li>Switch to <strong>This Quarter</strong> / <strong>All Time</strong> and watch KPIs &amp; the chart update.</li>
    <li>As admin, pick a member in the filter (or click a Team row) — numbers narrow to that person.</li>
    <li>Click any <strong>Needs attention</strong> row — it opens that inquiry on the dashboard.</li>
    <li>Sign in as <code>asha.mehta@demo.invalid</code> / <code><?= $h(DEMO_PASSWORD) ?></code> to see the member Reports.</li>
  </ol>

  <?php if ($summary): ?>
    <h2>Just seeded</h2>
    <table>
      <tr><td>Closed on time</td><td><?= $summary['closed_ontime'] ?></td></tr>
      <tr><td>Closed late</td><td><?= $summary['closed_late'] ?></td></tr>
      <tr><td>Overdue (open, past due)</td><td><?= $summary['overdue'] ?></td></tr>
      <tr><td>On track (open, inside due)</td><td><?= $summary['on_track'] ?></td></tr>
      <tr><td>Tasks created</td><td><?= $summary['steps'] ?></td></tr>
      <tr><td>&nbsp;&nbsp;· delivered late</td><td><?= $summary['steps_late'] ?></td></tr>
      <tr><td>&nbsp;&nbsp;· still overdue</td><td><?= $summary['steps_overdue'] ?></td></tr>
    </table>
  <?php endif; ?>

  <div class="note">
    Currently loaded: <strong><?= $have ?></strong> demo inquiries,
    <strong><?= $haveAcc ?></strong> demo accounts.<br>
    Database: <code><?= $h(DB_NAME) ?></code>
  </div>

  <?php if ($have === 0): ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="confirm" value="1">
      <button class="go" type="submit">Load sample data for Reports</button>
    </form>
  <?php else: ?>
    <div class="note warn">
      Demo members password: <code><?= $h(DEMO_PASSWORD) ?></code><br>
      Examples: <code>asha.mehta<?= $h(DEMO_MAIL) ?></code>,
      <code>rahul.verma<?= $h(DEMO_MAIL) ?></code>,
      <code>priya.nair<?= $h(DEMO_MAIL) ?></code>,
      <code>imran.sheikh<?= $h(DEMO_MAIL) ?></code>
    </div>
    <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <a class="go" href="index.php" style="text-decoration:none;display:inline-block">Open portal → Reports</a>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="remove" value="1">
      <button class="rm" type="submit">Remove all demo data</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:20px;font-size:12px">
    Removal deletes every inquiry whose id starts with <code>DEMO-</code> (tasks cascade) and every
    account ending in <code><?= $h(DEMO_MAIL) ?></code>. Real data is never touched.
    Blocked on the production database. <strong>Delete this file from the server when you are finished.</strong>
  </p>
</div>
</body>
</html>
