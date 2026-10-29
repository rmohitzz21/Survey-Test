<?php
// One-off repair: inquiries created TODAY were shown as overdue because the old VARCHAR(20) `due`
// column chopped the AM/PM off "01 Sep 2026 · 02:30 PM". This resets those inquiries — and their
// open tasks — to tomorrow 6:00 PM so the team starts from a clean, honest due date.
// Access: Master Admin only → /fix-today-due.php
// DELETE THIS FILE FROM THE SERVER AFTERWARDS.
//
// Deliberately NOT part of migrate.php: migrate re-runs every visit, and a dated data patch there
// would keep shoving due dates forward each time someone opens it.
require_once __DIR__.'/includes/auth.php';
require_utility_master_admin('login.php');
require_utility_non_production();
require_once __DIR__.'/includes/db.php';
$csrf = csrf_token();

$closedOutcomes = ['Payment Received','Inquiry Closed','Completed','Lost','Cancelled'];
$closedStages   = ['Payment Received','Project Closed','Proposal Lost','Closure','Cancelled'];
$oPh = implode(',', array_fill(0, count($closedOutcomes), '?'));
$sPh = implode(',', array_fill(0, count($closedStages),   '?'));

$newDate = date('d M Y', strtotime('tomorrow'));   // 'd M Y' — the format the app writes
$newTime = '18:00';                                 // inquiries.due_time is 24h 'HH:MM'
$newStep = $newDate.' · '.date('h:i A', strtotime($newTime));

// Created today, still open — closed inquiries never read as overdue, so leave them alone.
$sel = $pdo->prepare(
    "SELECT id, client, company, due_date, due_time FROM inquiries
     WHERE DATE(created_at) = CURDATE() AND outcome NOT IN ($oPh) AND stage NOT IN ($sPh)
     ORDER BY id");
$sel->execute(array_merge($closedOutcomes, $closedStages));
$rows = $sel->fetchAll();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1' && $rows) {
    require_csrf();
    $ids = array_column($rows, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE inquiries SET due_date=?, due_time=? WHERE id IN ($ph)")
        ->execute(array_merge([$newDate, $newTime], $ids));
    $st = $pdo->prepare("UPDATE inquiry_steps SET due=? WHERE inquiry_id IN ($ph)
                         AND status NOT IN ('Done','Completed','Cancelled','Rejected')");
    $st->execute(array_merge([$newStep], $ids));
    $stepCount = $st->rowCount();
    $pdo->commit();
    $msg  = count($ids).' inquiries and '.$stepCount.' open tasks moved to <strong>'.$newStep.'</strong>.';
    $rows = [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Today's Due Dates — Survey Pacific</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#F4F6F8;padding:2rem;color:#172B3A}
  .card{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(7,29,43,0.12);max-width:640px;margin:0 auto;padding:1.75rem}
  h1{font-size:18px;margin-bottom:.35rem}
  .sub{font-size:13px;color:#667085;margin-bottom:1.25rem}
  table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:1.25rem}
  th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#667085;padding:.4rem .5rem;border-bottom:1px solid #E4E7EC}
  td{padding:.45rem .5rem;border-bottom:1px solid #F2F4F7}
  .id{font-weight:700}
  .old{color:#B42318}
  .ok{background:#ECFDF3;color:#16803C;padding:.75rem 1rem;border-radius:8px;font-size:13px;margin-bottom:1rem}
  .none{color:#667085;font-size:13px}
  .row{display:flex;gap:.6rem}
  button{flex:1;padding:.7rem;border:0;border-radius:8px;background:#1268F3;color:#fff;font-weight:700;font-size:13px;cursor:pointer}
  a.cancel{flex:1;padding:.7rem;border-radius:8px;background:#F2F4F7;color:#344054;font-weight:700;font-size:13px;text-align:center;text-decoration:none}
  .warn{font-size:12px;color:#B42318;margin-top:1rem}
</style>
</head>
<body>
<div class="card">
  <h1>Reset today's due dates</h1>
  <div class="sub">Inquiries created today, still open → due <strong><?= htmlspecialchars($newStep) ?></strong></div>

  <?php if ($msg): ?>
    <div class="ok"><?= $msg ?></div>
  <?php elseif (!$rows): ?>
    <p class="none">No open inquiries were created today. Nothing to change.</p>
  <?php else: ?>
    <table>
      <tr><th>Inquiry</th><th>Client</th><th>Current due</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="id"><?= htmlspecialchars($r['id']) ?></td>
        <td><?= htmlspecialchars($r['client'].($r['company'] ? ' · '.$r['company'] : '')) ?></td>
        <td class="old"><?= htmlspecialchars($r['due_date'].($r['due_time'] ? ' · '.date('h:i A', strtotime($r['due_time'])) : '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="POST" class="row">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="confirm" value="1">
      <a class="cancel" href="/">Cancel</a>
      <button type="submit">Move <?= count($rows) ?> inquiries to <?= htmlspecialchars($newStep) ?></button>
    </form>
  <?php endif; ?>

  <p class="warn">⚠ Writes to the live database. Delete this file from the server when you're done.</p>
</div>
</body>
</html>
