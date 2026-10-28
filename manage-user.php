<?php
// Access: log in as Master Admin, then open /manage-user.php
require_once __DIR__.'/includes/auth.php';
require_utility_master_admin('login.php');
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$msg   = $_GET['msg']   ?? '';
$error = '';

// Inquiry dates are stored as '01 Sep 2026' strings; the form uses native <input type="date">.
function to_stored(string $v): string { return $v === '' ? 'TBD' : date('d M Y', strtotime($v)); }
function to_input(?string $v): string {
    $ts = ($v && strcasecmp(trim($v), 'TBD') !== 0) ? strtotime($v) : false;
    return $ts ? date('Y-m-d', $ts) : '';
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$acct = null;
if ($email !== '') {
    $s = $pdo->prepare("SELECT * FROM accounts WHERE email=?");
    $s->execute([$email]);
    $acct = $s->fetch();
    if (!$acct) $error = 'No account found for "'.h($email).'".';
}

// ─── Actions ─────────────────────────────────────────────────────────────────
// Every action redirects (POST/Redirect/GET) so a browser refresh cannot replay a delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acct && isset($_POST['action'])) {
    require_csrf();
    $done = '';
    $id   = trim($_POST['inquiry_id'] ?? '');

    // Create an inquiry *for* this member, at any date/time — including backdated. The portal
    // scopes its month tabs and calendar by created_at, so that column is set explicitly here;
    // leaving it to CURRENT_TIMESTAMP would file a backdated inquiry under today's month.
    if ($_POST['action'] === 'create_inquiry') {
        $ts      = strtotime(trim($_POST['created_at'] ?? '')) ?: time();
        $dueTime = trim($_POST['due_time'] ?? '');
        $type    = ($_POST['inquiry_type'] ?? '') === 'Internal Usage' ? 'Internal Usage' : 'Client Project';
        $client  = trim($_POST['client'] ?? '')  ?: 'Internal';
        $company = trim($_POST['company'] ?? '') ?: 'Internal';

        if ($dueTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $dueTime)) {
            header('Location: ?email='.urlencode($email).'&msg='.urlencode('Due time must be HH:MM.'));
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO inquiries
            (id,date,inquiry_type,client,company,country,is_new,client_type,requirement,
             created_by,current_owner,due_date,due_time,stage,outcome,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'Inquiry Received','In Progress',?)");
        $newId = '';
        for ($try = 0; ; $try++) {
            try {
                $newId = next_inquiry_id($pdo);
                $ins->execute([
                    $newId, date('d M Y', $ts), $type, $client, $company,
                    trim($_POST['country'] ?? '') ?: 'India',
                    ($_POST['client_type'] ?? 'New') === 'New' ? 1 : 0,
                    trim($_POST['client_type'] ?? '') ?: 'New',
                    trim($_POST['requirement'] ?? ''),
                    $acct['name'], $acct['name'],
                    to_stored(trim($_POST['due_date'] ?? '')),
                    $dueTime !== '' ? $dueTime : null,
                    date('Y-m-d H:i:s', $ts),
                ]);
                break;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000' || $try >= 2) throw $e;  // duplicate id — re-read and retry
            }
        }
        $pdo->prepare("INSERT INTO stage_history (inquiry_id,stage,outcome,by_user,date,remark) VALUES (?,?,?,?,?,?)")
            ->execute([$newId,'Inquiry Received','In Progress',$acct['name'],date('d M Y · H:i',$ts),'Inquiry logged by admin']);
        notify_user($pdo, $acct['name'], 'New inquiry — '.$newId,
            'An inquiry was created for you ('.$client.').', $newId);
        $done = 'Inquiry '.$newId.' created for '.$acct['name'].' on '.date('d M Y · H:i', $ts).'.';
    }

    if ($_POST['action'] === 'save_inquiry' && $id !== '') {
        $pdo->prepare("UPDATE inquiries SET date=?, due_date=?, due_time=?, client=?, company=?,
                       current_owner=?, stage=?, outcome=? WHERE id=?")->execute([
            to_stored(trim($_POST['date'] ?? '')),
            to_stored(trim($_POST['due_date'] ?? '')),
            trim($_POST['due_time'] ?? '') ?: null,
            trim($_POST['client'] ?? ''),
            trim($_POST['company'] ?? ''),
            trim($_POST['current_owner'] ?? ''),
            trim($_POST['stage'] ?? ''),
            trim($_POST['outcome'] ?? ''),
            $id,
        ]);
        $done = 'Inquiry '.$id.' updated.';
    }

    if ($_POST['action'] === 'delete_inquiry' && $id !== '') {
        delete_inquiry_cascade($pdo, $id, __DIR__);
        $done = 'Inquiry '.$id.' deleted permanently.';
    }

    if ($_POST['action'] === 'delete_leave') {
        // account_id in the WHERE so a tampered id cannot delete another member's leave.
        $pdo->prepare("DELETE FROM leave_requests WHERE id=? AND account_id=?")
            ->execute([(int)($_POST['leave_id'] ?? 0), $acct['id']]);
        $done = 'Leave request deleted.';
    }

    header('Location: ?email='.urlencode($email).'&msg='.urlencode($done));
    exit;
}

// ─── Load this member's data ─────────────────────────────────────────────────
$inqs = $leaves = [];
if ($acct) {
    $n = $acct['name'];
    // Same "connected to me" rule load_inquiries() uses for members.
    $q = $pdo->prepare("SELECT DISTINCT i.* FROM inquiries i
        LEFT JOIN inquiry_steps s ON s.inquiry_id = i.id
        WHERE i.created_by=? OR i.current_owner=? OR s.assigned_to=? OR s.assigned_by=?
        ORDER BY i.created_at DESC, i.id DESC");
    $q->execute([$n, $n, $n, $n]);
    $inqs = $q->fetchAll();

    $l = $pdo->prepare("SELECT * FROM leave_requests WHERE account_id=? ORDER BY start_date DESC");
    $l->execute([$acct['id']]);
    $leaves = $l->fetchAll();
}

// Suggestions come from what the DB already holds, so this page can never drift out of sync
// with the stage/outcome lists the app itself uses.
$stages   = $pdo->query("SELECT DISTINCT stage   FROM inquiries ORDER BY stage")->fetchAll(PDO::FETCH_COLUMN);
$outcomes = $pdo->query("SELECT DISTINCT outcome FROM inquiries ORDER BY outcome")->fetchAll(PDO::FETCH_COLUMN);
$owners   = $pdo->query("SELECT name FROM accounts WHERE status='approved' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$members  = $pdo->query("SELECT name,email FROM accounts WHERE status='approved' AND role<>'Client' ORDER BY name")->fetchAll();
$csrf     = csrf_token();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage User — Survey Pacific</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#F4F6F8;color:#172B3A;padding:2rem 1rem}
  .wrap{max-width:1040px;margin:0 auto;display:flex;flex-direction:column;gap:1rem}
  .card{background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(7,29,43,.08);overflow:hidden}
  .header{background:#071D2B;padding:1.25rem 1.5rem}
  .header h1{color:#fff;font-size:16px;font-weight:700}
  .header p{color:rgba(255,255,255,.5);font-size:11px;margin-top:3px}
  .body{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem}
  label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#667085;margin-bottom:4px}
  input,select{width:100%;border:1px solid #E4E7EC;border-radius:8px;padding:8px 10px;font-size:13px;color:#172B3A;outline:none;font-family:inherit}
  input:focus,select:focus{border-color:#1268F3}
  .row{display:flex;gap:.6rem;flex-wrap:wrap}
  .row>div{flex:1;min-width:130px}
  button{border:none;border-radius:8px;padding:9px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
  .b-primary{background:#1268F3;color:#fff}
  .b-danger{background:#FEF3F2;color:#B42318;border:1px solid #FECDCA}
  .msg-ok{background:#ECFDF3;border:1px solid #A9EFC5;border-radius:10px;padding:.85rem 1rem;font-size:13px;color:#16803C;font-weight:600}
  .msg-err{background:#FEF3F2;border:1px solid #FECDCA;border-radius:10px;padding:.85rem 1rem;font-size:13px;color:#B42318;font-weight:600}
  .who{display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center;background:#F9FAFB;border:1px solid #E4E7EC;border-radius:10px;padding:.85rem 1rem}
  .who b{display:block;font-size:14px}
  .who span{font-size:11px;color:#667085}
  h2{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#667085}
  .inq{border:1px solid #E4E7EC;border-radius:10px;padding:.9rem}
  .inq-id{font-size:13px;font-weight:800;color:#071D2B;margin-bottom:.6rem}
  .inq-id small{font-weight:500;color:#98A2B3;margin-left:.4rem}
  .acts{display:flex;gap:.5rem;justify-content:flex-end;margin-top:.7rem}
  .empty{font-size:12px;color:#98A2B3;padding:.5rem 0}
  .lv{display:flex;gap:1rem;align-items:center;justify-content:space-between;border:1px solid #E4E7EC;border-radius:10px;padding:.7rem .9rem;font-size:13px}
  .lv span{font-size:11px;color:#667085;display:block}
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <div class="header">
      <h1>Manage User</h1>
      <p>Survey Pacific — Admin Tool ·
         <a href="admin/" style="color:#B2DDFF">Inquiry tools</a> ·
         <a href="index.php" style="color:#B2DDFF">App</a></p>
    </div>
    <div class="body">
      <?php if ($msg): ?><div class="msg-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="msg-err"><?= $error ?></div><?php endif; ?>
      <form method="get" class="row" style="align-items:flex-end">
        <div style="flex:3">
          <label>User</label>
          <select name="email" required>
            <option value="">— pick a user —</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= h($m['email']) ?>" <?= $m['email'] === $email ? 'selected' : '' ?>>
                <?= h($m['name']) ?> — <?= h($m['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:0"><button class="b-primary" type="submit">Load</button></div>
      </form>

      <?php if ($acct): ?>
        <div class="who">
          <div><b><?= h($acct['name']) ?></b><span><?= h($acct['email']) ?></span></div>
          <div><b><?= h($acct['role']) ?></b><span>Role</span></div>
          <div><b><?= h($acct['status']) ?></b><span>Status</span></div>
          <div><b><?= count($inqs) ?></b><span>Inquiries</span></div>
          <div><b><?= count($leaves) ?></b><span>Leave requests</span></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($acct): ?>
  <div class="card">
    <div class="body">
      <h2>Create inquiry for <?= h($acct['name']) ?></h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="email" value="<?= h($email) ?>">
        <div class="row">
          <div><label>Logged at (date &amp; time)</label>
            <input type="datetime-local" name="created_at" required value="<?= date('Y-m-d\TH:i') ?>"></div>
          <div><label>Due Date</label><input type="date" name="due_date"></div>
          <div><label>Due Time</label><input type="time" name="due_time"></div>
          <div><label>Type</label>
            <select name="inquiry_type">
              <option>Client Project</option><option>Internal Usage</option>
            </select></div>
        </div>
        <div class="row" style="margin-top:.6rem">
          <div><label>Client</label><input type="text" name="client" required></div>
          <div><label>Company</label><input type="text" name="company" required></div>
          <div><label>Country</label><input type="text" name="country" value="India"></div>
          <div><label>Client Type</label>
            <select name="client_type"><option>New</option><option>Repeat</option><option>Existing</option></select></div>
        </div>
        <div class="row" style="margin-top:.6rem">
          <div><label>Requirement</label><input type="text" name="requirement" placeholder="What has to be done"></div>
        </div>
        <div class="acts">
          <button class="b-primary" type="submit" name="action" value="create_inquiry">Create inquiry</button>
        </div>
      </form>
      <p class="empty">“Logged at” sets the creation timestamp — pick any past or future date/time and the
         inquiry files into that month in the portal and calendar.</p>
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h2>Inquiries</h2>
      <?php if (!$inqs): ?><p class="empty">No inquiries linked to this user.</p><?php endif; ?>

      <?php foreach ($inqs as $i): ?>
        <div class="inq">
          <div class="inq-id"><?= h($i['id']) ?><small><?= h($i['inquiry_type']) ?> · created by <?= h($i['created_by']) ?></small></div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="email" value="<?= h($email) ?>">
            <input type="hidden" name="inquiry_id" value="<?= h($i['id']) ?>">
            <div class="row">
              <div><label>Date</label><input type="date" name="date" value="<?= to_input($i['date']) ?>"></div>
              <div><label>Due Date</label><input type="date" name="due_date" value="<?= to_input($i['due_date']) ?>"></div>
              <div><label>Due Time</label><input type="time" name="due_time" value="<?= h($i['due_time']) ?>"></div>
              <div><label>Owner</label>
                <select name="current_owner">
                  <?php foreach (array_unique(array_merge($owners, [$i['current_owner']])) as $o): ?>
                    <option <?= $o === $i['current_owner'] ? 'selected' : '' ?>><?= h($o) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row" style="margin-top:.6rem">
              <div><label>Client</label><input type="text" name="client" value="<?= h($i['client']) ?>"></div>
              <div><label>Company</label><input type="text" name="company" value="<?= h($i['company']) ?>"></div>
              <div><label>Stage</label><input type="text" name="stage" list="stages" value="<?= h($i['stage']) ?>"></div>
              <div><label>Outcome</label><input type="text" name="outcome" list="outcomes" value="<?= h($i['outcome']) ?>"></div>
            </div>
            <div class="acts">
              <button class="b-danger" type="submit" name="action" value="delete_inquiry"
                onclick="return confirm('Permanently delete <?= h($i['id']) ?> with all its tasks, follow-ups, history and files?')">Delete</button>
              <button class="b-primary" type="submit" name="action" value="save_inquiry">Save</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="body">
      <h2>Leave Requests</h2>
      <?php if (!$leaves): ?><p class="empty">No leave requests for this user.</p><?php endif; ?>
      <?php foreach ($leaves as $lv): ?>
        <div class="lv">
          <div>
            <b><?= h($lv['leave_type']) ?> — <?= h($lv['status']) ?></b>
            <span><?= h(date('d M Y', strtotime($lv['start_date']))) ?> → <?= h(date('d M Y', strtotime($lv['end_date']))) ?>
                  · <?= h($lv['days']) ?> day(s) · <?= h($lv['reason']) ?></span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="email" value="<?= h($email) ?>">
            <input type="hidden" name="leave_id" value="<?= (int)$lv['id'] ?>">
            <button class="b-danger" type="submit" name="action" value="delete_leave"
              onclick="return confirm('Delete this leave request?')">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<datalist id="stages"><?php foreach ($stages as $s) echo '<option value="'.h($s).'">'; ?></datalist>
<datalist id="outcomes"><?php foreach ($outcomes as $o) echo '<option value="'.h($o).'">'; ?></datalist>
</body>
</html>
