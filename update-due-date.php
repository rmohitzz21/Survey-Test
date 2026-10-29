<?php
// Access: log in as Master Admin, then open /update-due-date.php
require_once __DIR__.'/includes/auth.php';
require_utility_master_admin('login.php');
require_utility_non_production();
require_once __DIR__.'/includes/db.php';

$csrf  = csrf_token();
$msg   = '';
$error = '';
$inq   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = trim($_POST['inquiry_id'] ?? '');
    if (!$id) {
        $error = 'Please enter an Inquiry ID.';
    } else {
        $chk = $pdo->prepare("SELECT id, client, company, due_date FROM inquiries WHERE id=?");
        $chk->execute([$id]);
        $inq = $chk->fetch();

        if (!$inq) {
            $error = 'Inquiry "'.$id.'" not found.';
        } elseif (isset($_POST['new_due']) && $_POST['new_due'] !== '') {
            $newDue = trim($_POST['new_due']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDue)) {
                $newDue = date('d M Y', strtotime($newDue));
            }
            $pdo->prepare("UPDATE inquiries SET due_date=? WHERE id=?")->execute([$newDue, $id]);
            $msg = 'Due date for <strong>'.htmlspecialchars($id).'</strong> ('.htmlspecialchars($inq['client']).($inq['company'] ? ' · '.htmlspecialchars($inq['company']) : '').') updated to <strong>'.htmlspecialchars($newDue).'</strong>.';
            $inq = null;
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Update Due Date — Survey Pacific</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#F4F6F8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .card{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(7,29,43,0.12);width:100%;max-width:440px;overflow:hidden}
  .header{background:#071D2B;padding:1.25rem 1.5rem}
  .header h1{color:#fff;font-size:16px;font-weight:700;line-height:1.3}
  .header p{color:rgba(255,255,255,0.5);font-size:11px;margin-top:3px}
  .body{padding:1.5rem;display:flex;flex-direction:column;gap:1rem}
  label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#667085;margin-bottom:5px}
  input[type=text],input[type=date]{width:100%;border:1px solid #E4E7EC;border-radius:8px;padding:9px 12px;font-size:13px;color:#172B3A;outline:none;transition:border-color .15s}
  input[type=text]:focus,input[type=date]:focus{border-color:#1268F3}
  .btn-delete{width:100%;padding:10px;border:none;border-radius:8px;background:#1268F3;color:#fff;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s}
  .btn-delete:hover{background:#0e56cc}
  .confirm-box{background:#EFF8FF;border:1px solid #B2DDFF;border-radius:10px;padding:1rem}
  .confirm-box p{font-size:13px;color:#175CD3;font-weight:600;margin-bottom:.75rem}
  .confirm-box .inq-id{font-size:15px;font-weight:800;color:#175CD3}
  .confirm-box .meta{font-size:12px;color:#667085;margin-top:2px}
  .confirm-box .current{font-size:12px;color:#667085;margin-top:6px}
  .confirm-row{display:flex;gap:.5rem;margin-top:.75rem}
  .btn-cancel{flex:1;padding:9px;border:1px solid #E4E7EC;border-radius:8px;background:#F2F4F7;color:#344054;font-size:12px;font-weight:700;cursor:pointer}
  .btn-cancel:hover{background:#E4E7EC}
  .btn-confirm{flex:1;padding:9px;border:none;border-radius:8px;background:#1268F3;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
  .btn-confirm:hover{background:#0e56cc}
  .msg-ok{background:#ECFDF3;border:1px solid #A9EFC5;border-radius:10px;padding:1rem;font-size:13px;color:#16803C;font-weight:600}
  .msg-err{background:#FEF3F2;border:1px solid #FECDCA;border-radius:10px;padding:1rem;font-size:13px;color:#B42318;font-weight:600}
  .warning{font-size:11px;color:#98A2B3;text-align:center}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Update Due Date</h1>
    <p>Survey Pacific — Admin Tool</p>
  </div>
  <div class="body">

    <?php if ($msg): ?>
      <div class="msg-ok"><?= $msg ?></div>
      <a href="update-due-date.php" style="text-align:center;font-size:12px;color:#1268F3;font-weight:700;text-decoration:none">← Update another</a>

    <?php else: ?>

      <?php if ($error): ?>
        <div class="msg-err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($inq): ?>
        <!-- Edit step -->
        <div class="confirm-box">
          <p>Set a new due date for:</p>
          <div class="inq-id"><?= htmlspecialchars($inq['id']) ?></div>
          <div class="meta"><?= htmlspecialchars($inq['client']) ?><?= $inq['company'] ? ' · '.htmlspecialchars($inq['company']) : '' ?></div>
          <div class="current">Current due date: <strong><?= htmlspecialchars($inq['due_date'] ?: 'TBD') ?></strong></div>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="inquiry_id" value="<?= htmlspecialchars($inq['id']) ?>">
            <div style="margin-top:.75rem">
              <label>New Due Date</label>
              <input type="date" name="new_due" required
                     value="<?= ($inq['due_date'] && $inq['due_date'] !== 'TBD') ? date('Y-m-d', strtotime($inq['due_date'])) : '' ?>">
            </div>
            <div class="confirm-row">
              <a href="update-due-date.php" class="btn-cancel" style="display:flex;align-items:center;justify-content:center;text-decoration:none">Cancel</a>
              <button type="submit" class="btn-confirm">Save</button>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- Entry step -->
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div>
            <label>Inquiry ID</label>
            <input type="text" name="inquiry_id" placeholder="e.g. SP-2026-013" value="<?= htmlspecialchars($_POST['inquiry_id'] ?? '') ?>" autofocus>
          </div>
          <button type="submit" class="btn-delete" style="margin-top:.25rem">Find Inquiry →</button>
        </form>
        <p class="warning">⚠ This updates the live database directly. Pushing the due date forward will clear the inquiry's "overdue" status.</p>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
