<?php
// Access: /delete-inquiry.php?token=sp_del_2026
if (($_GET['token'] ?? '') !== 'sp_del_2026') {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__.'/includes/db.php';

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['inquiry_id'] ?? '');
    if (!$id) {
        $error = 'Please enter an Inquiry ID.';
    } else {
        $chk = $pdo->prepare("SELECT id, client, company FROM inquiries WHERE id=?");
        $chk->execute([$id]);
        $inq = $chk->fetch();

        if (!$inq) {
            $error = 'Inquiry "'.$id.'" not found.';
        } elseif (isset($_POST['confirm']) && $_POST['confirm'] === '1') {
            // Cascade delete — same order as api/inquiry.php delete_inquiry
            $inqAtts = $pdo->prepare("SELECT filename FROM inquiry_attachments WHERE inquiry_id=?");
            $inqAtts->execute([$id]);
            foreach ($inqAtts->fetchAll() as $a) {
                $p = __DIR__.'/uploads/inq/'.$id.'/'.$a['filename'];
                if (file_exists($p)) unlink($p);
            }
            @rmdir(__DIR__.'/uploads/inq/'.$id);

            $stepIds = $pdo->prepare("SELECT id FROM inquiry_steps WHERE inquiry_id=?");
            $stepIds->execute([$id]);
            foreach ($stepIds->fetchAll() as $s) {
                $saStmt = $pdo->prepare("SELECT filename FROM step_attachments WHERE step_id=?");
                $saStmt->execute([$s['id']]);
                foreach ($saStmt->fetchAll() as $sa) {
                    $p = __DIR__.'/uploads/'.$s['id'].'/'.$sa['filename'];
                    if (file_exists($p)) unlink($p);
                }
                @rmdir(__DIR__.'/uploads/'.$s['id']);
            }

            $pdo->prepare("DELETE FROM notifications       WHERE inquiry_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM follow_ups          WHERE inquiry_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM inquiry_attachments WHERE inquiry_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM step_attachments    WHERE step_id IN (SELECT id FROM inquiry_steps WHERE inquiry_id=?)")->execute([$id]);
            $pdo->prepare("DELETE FROM inquiry_steps       WHERE inquiry_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM stage_history       WHERE inquiry_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM inquiries           WHERE id=?")->execute([$id]);

            $msg = 'Inquiry <strong>'.$id.'</strong> ('.$inq['client'].($inq['company'] ? ' · '.$inq['company'] : '').') has been permanently deleted.';
            $inq = null;
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delete Inquiry — Survey Pacific</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#F4F6F8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .card{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(7,29,43,0.12);width:100%;max-width:440px;overflow:hidden}
  .header{background:#071D2B;padding:1.25rem 1.5rem}
  .header h1{color:#fff;font-size:16px;font-weight:700;line-height:1.3}
  .header p{color:rgba(255,255,255,0.5);font-size:11px;margin-top:3px}
  .body{padding:1.5rem;display:flex;flex-direction:column;gap:1rem}
  label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#667085;margin-bottom:5px}
  input[type=text]{width:100%;border:1px solid #E4E7EC;border-radius:8px;padding:9px 12px;font-size:13px;color:#172B3A;outline:none;transition:border-color .15s}
  input[type=text]:focus{border-color:#1268F3}
  .btn-delete{width:100%;padding:10px;border:none;border-radius:8px;background:#B42318;color:#fff;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s}
  .btn-delete:hover{background:#9a1e14}
  .confirm-box{background:#FEF3F2;border:1px solid #FECDCA;border-radius:10px;padding:1rem}
  .confirm-box p{font-size:13px;color:#B42318;font-weight:600;margin-bottom:.75rem}
  .confirm-box .inq-id{font-size:15px;font-weight:800;color:#B42318}
  .confirm-box .meta{font-size:12px;color:#667085;margin-top:2px}
  .confirm-row{display:flex;gap:.5rem;margin-top:.75rem}
  .btn-cancel{flex:1;padding:9px;border:1px solid #E4E7EC;border-radius:8px;background:#F2F4F7;color:#344054;font-size:12px;font-weight:700;cursor:pointer}
  .btn-cancel:hover{background:#E4E7EC}
  .btn-confirm{flex:1;padding:9px;border:none;border-radius:8px;background:#B42318;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
  .btn-confirm:hover{background:#9a1e14}
  .msg-ok{background:#ECFDF3;border:1px solid #A9EFC5;border-radius:10px;padding:1rem;font-size:13px;color:#16803C;font-weight:600}
  .msg-err{background:#FEF3F2;border:1px solid #FECDCA;border-radius:10px;padding:1rem;font-size:13px;color:#B42318;font-weight:600}
  .warning{font-size:11px;color:#98A2B3;text-align:center}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Delete Inquiry</h1>
    <p>Survey Pacific — Admin Tool</p>
  </div>
  <div class="body">

    <?php if ($msg): ?>
      <div class="msg-ok"><?= $msg ?></div>
      <a href="?token=sp_del_2026" style="text-align:center;font-size:12px;color:#1268F3;font-weight:700;text-decoration:none">← Delete another</a>

    <?php else: ?>

      <?php if ($error): ?>
        <div class="msg-err"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (isset($inq) && $inq): ?>
        <!-- Confirm step -->
        <div class="confirm-box">
          <p>Are you sure you want to permanently delete:</p>
          <div class="inq-id"><?= htmlspecialchars($inq['id']) ?></div>
          <div class="meta"><?= htmlspecialchars($inq['client']) ?><?= $inq['company'] ? ' · '.htmlspecialchars($inq['company']) : '' ?></div>
          <p style="margin-top:.75rem;margin-bottom:0;font-size:11px;color:#B42318">This will delete all steps, history, attachments, follow-ups and notifications. This cannot be undone.</p>
          <form method="POST" class="confirm-row">
            <input type="hidden" name="inquiry_id" value="<?= htmlspecialchars($inq['id']) ?>">
            <input type="hidden" name="confirm" value="1">
            <a href="?token=sp_del_2026" class="btn-cancel" style="display:flex;align-items:center;justify-content:center;text-decoration:none">Cancel</a>
            <button type="submit" class="btn-confirm">Yes, Delete</button>
          </form>
        </div>

      <?php else: ?>
        <!-- Entry step -->
        <form method="POST">
          <div>
            <label>Inquiry ID</label>
            <input type="text" name="inquiry_id" placeholder="e.g. SP-2026-013" value="<?= htmlspecialchars($_POST['inquiry_id'] ?? '') ?>" autofocus>
          </div>
          <button type="submit" class="btn-delete" style="margin-top:.25rem">Find &amp; Delete →</button>
        </form>
        <p class="warning">⚠ This tool permanently deletes the inquiry and all related data from the live database.</p>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
