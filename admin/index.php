<?php
/**
 * Master Admin only — /admin
 * Lookup an inquiry by ID: view details, modify, delete, clear overdue / update due.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_utility_master_admin('../login.php');
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function h($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** App stores dates as '01 Sep 2026'; forms use Y-m-d. */
function admin_to_stored(string $v): string {
    $v = trim($v);
    if ($v === '') return 'TBD';
    $ts = strtotime($v);
    return $ts ? date('d M Y', $ts) : $v;
}
function admin_to_input(?string $v): string {
    if (!$v || strcasecmp(trim($v), 'TBD') === 0) return '';
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : '';
}

$root = dirname(__DIR__);
$id   = trim($_GET['id'] ?? $_POST['id'] ?? '');
$msg  = trim($_GET['msg'] ?? '');
$err  = '';
$inq  = null;
$steps = [];
$history = [];

// ── POST actions (PRG) ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = trim($_POST['id'] ?? '');

    if ($id === '') {
        $err = 'Enter an Inquiry ID.';
    } else {
        $chk = $pdo->prepare('SELECT id FROM inquiries WHERE id=?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            $err = 'Inquiry "' . $id . '" was not found.';
        } else {
            $done = '';

            if ($action === 'save') {
                $dueDate = admin_to_stored(trim($_POST['due_date'] ?? ''));
                $dueTime = trim($_POST['due_time'] ?? '');
                if ($dueTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $dueTime)) {
                    $err = 'Due time must be HH:MM (24-hour).';
                } else {
                    $pdo->prepare(
                        "UPDATE inquiries SET
                            date=?, inquiry_type=?, client=?, company=?, designation=?,
                            country=?, client_type=?, requirement=?,
                            created_by=?, current_owner=?,
                            due_date=?, due_time=?,
                            stage=?, outcome=?,
                            proposal_value=?, email=?, phone=?, website=?, email_subject=?
                         WHERE id=?"
                    )->execute([
                        admin_to_stored(trim($_POST['date'] ?? '')),
                        in_array($_POST['inquiry_type'] ?? '', ['Client Project', 'Internal Usage'], true)
                            ? $_POST['inquiry_type'] : 'Client Project',
                        trim($_POST['client'] ?? ''),
                        trim($_POST['company'] ?? ''),
                        trim($_POST['designation'] ?? '') ?: null,
                        trim($_POST['country'] ?? '') ?: 'India',
                        trim($_POST['client_type'] ?? '') ?: 'New',
                        trim($_POST['requirement'] ?? ''),
                        trim($_POST['created_by'] ?? ''),
                        trim($_POST['current_owner'] ?? ''),
                        $dueDate,
                        $dueTime !== '' ? $dueTime : null,
                        trim($_POST['stage'] ?? ''),
                        trim($_POST['outcome'] ?? ''),
                        trim($_POST['proposal_value'] ?? '') ?: null,
                        trim($_POST['email'] ?? '') ?: null,
                        trim($_POST['phone'] ?? '') ?: null,
                        trim($_POST['website'] ?? '') ?: null,
                        trim($_POST['email_subject'] ?? '') ?: null,
                        $id,
                    ]);
                    $done = 'Inquiry ' . $id . ' saved.';
                }
            }

            if ($action === 'update_due' && $err === '') {
                $dueDate = admin_to_stored(trim($_POST['due_date'] ?? ''));
                $dueTime = trim($_POST['due_time'] ?? '');
                if ($dueTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $dueTime)) {
                    $err = 'Due time must be HH:MM (24-hour).';
                } else {
                    $pdo->prepare('UPDATE inquiries SET due_date=?, due_time=? WHERE id=?')->execute([
                        $dueDate,
                        $dueTime !== '' ? $dueTime : null,
                        $id,
                    ]);
                    $done = 'Due date for ' . $id . ' updated to ' . $dueDate
                        . ($dueTime !== '' ? ' · ' . $dueTime : '') . '.';
                }
            }

            if ($action === 'clear_overdue' && $err === '') {
                // Close it so it drops off overdue lists (same as app Closure).
                $pdo->prepare(
                    "UPDATE inquiries SET stage='Closure', outcome='Inquiry Closed',
                     closed_at=COALESCE(closed_at, NOW()) WHERE id=?"
                )->execute([$id]);
                $done = 'Inquiry ' . $id . ' marked closed — no longer overdue.';
            }

            if ($action === 'delete' && $err === '') {
                if (($_POST['confirm_delete'] ?? '') !== 'DELETE') {
                    $err = 'Type DELETE to confirm permanent removal.';
                } else {
                    delete_inquiry_cascade($pdo, $id, $root);
                    header('Location: ?msg=' . urlencode('Inquiry ' . $id . ' permanently deleted.'));
                    exit;
                }
            }

            if ($done !== '' && $err === '') {
                header('Location: ?id=' . urlencode($id) . '&msg=' . urlencode($done));
                exit;
            }
        }
    }
}

// ── Load inquiry ─────────────────────────────────────────────────────────────
if ($id !== '' && $err === '') {
    $s = $pdo->prepare('SELECT * FROM inquiries WHERE id=?');
    $s->execute([$id]);
    $inq = $s->fetch();
    if (!$inq) {
        $err = 'Inquiry "' . $id . '" was not found.';
        $id = '';
    } else {
        $st = $pdo->prepare(
            'SELECT id, assigned_to, assigned_by, instruction, due, status, created_at
             FROM inquiry_steps WHERE inquiry_id=? ORDER BY id ASC'
        );
        $st->execute([$inq['id']]);
        $steps = $st->fetchAll();

        try {
            $hi = $pdo->prepare(
                'SELECT stage, outcome, by_user, date, remark
                 FROM stage_history WHERE inquiry_id=? ORDER BY id DESC LIMIT 20'
            );
            $hi->execute([$inq['id']]);
            $history = $hi->fetchAll();
        } catch (Throwable $e) {
            $history = [];
        }
    }
}

$owners = $pdo->query(
    "SELECT name FROM accounts WHERE status='approved' AND role <> 'Client' ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);
$stages = $pdo->query('SELECT DISTINCT stage FROM inquiries WHERE stage IS NOT NULL AND stage<>\'\' ORDER BY stage')
    ->fetchAll(PDO::FETCH_COLUMN);
$outcomes = $pdo->query('SELECT DISTINCT outcome FROM inquiries WHERE outcome IS NOT NULL AND outcome<>\'\' ORDER BY outcome')
    ->fetchAll(PDO::FETCH_COLUMN);

$csrf = csrf_token();
$user = $_SESSION['user'] ?? [];
$isOverdue = false;
if ($inq) {
    // Was a hand-copied pair of closed-stage/outcome lists that had drifted from the shared
    // constants — it was missing 'Completed' / 'Project Lost' outcomes and the 'Cancelled'
    // stage, so an inquiry closed with any of those still badged "Overdue" here while the app
    // showed it closed. Use the one definition instead so the two can never disagree again.
    $closed = is_closed_inquiry($inq['stage'] ?? '', $inq['outcome'] ?? '');
    $dueTs  = $closed ? null : due_ts($inq['due_date'] ?? null, $inq['due_time'] ?? null);
    $isOverdue = $dueTs !== null && $dueTs < time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Inquiry tools · Survey Pacific</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Inter,system-ui,-apple-system,sans-serif;background:#F4F6F8;color:#172B3A;min-height:100vh}
  a{color:#1268F3;text-decoration:none;font-weight:600}
  a:hover{text-decoration:underline}
  .top{background:#071D2B;color:#fff;padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px}
  .top h1{font-size:15px;font-weight:800}
  .top p{font-size:11px;opacity:.55;margin-top:2px}
  .top nav{display:flex;gap:14px;flex-wrap:wrap;font-size:12px}
  .top nav a{color:#B2DDFF;font-weight:700}
  .wrap{max-width:960px;margin:0 auto;padding:20px 16px 48px;display:flex;flex-direction:column;gap:14px}
  .card{background:#fff;border:1px solid #E4E7EC;border-radius:12px;box-shadow:0 1px 4px rgba(7,29,43,.04);overflow:hidden}
  .card-h{padding:14px 18px;border-bottom:1px solid #E4E7EC;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px}
  .card-h h2{font-size:13px;font-weight:800}
  .card-h span{font-size:11px;color:#667085}
  .card-b{padding:16px 18px}
  label{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#667085;margin-bottom:5px}
  input,select,textarea{width:100%;border:1px solid #E4E7EC;border-radius:8px;padding:9px 11px;font-size:13px;color:#172B3A;font-family:inherit;background:#fff}
  input:focus,select:focus,textarea:focus{outline:none;border-color:#1268F3}
  textarea{min-height:90px;resize:vertical}
  .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;border:none;border-radius:8px;padding:9px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn-primary{background:#1268F3;color:#fff}
  .btn-primary:hover{background:#0f55d6}
  .btn-muted{background:#F2F4F7;color:#344054;border:1px solid #E4E7EC}
  .btn-warn{background:#FFFAEB;color:#B54708;border:1px solid #FEDF89}
  .btn-danger{background:#B42318;color:#fff}
  .btn-danger:hover{background:#912018}
  .ok{background:#ECFDF3;border:1px solid #A9EFC5;color:#16803C;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:600}
  .bad{background:#FEF3F2;border:1px solid #FECDCA;color:#B42318;border-radius:10px;padding:12px 14px;font-size:13px;font-weight:600}
  .badge{display:inline-block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:3px 8px;border-radius:999px}
  .badge-od{background:#FEF3F2;color:#B42318}
  .badge-ok{background:#ECFDF3;color:#16803C}
  .meta{font-size:12px;color:#667085;line-height:1.5}
  .kv{display:grid;grid-template-columns:140px 1fr;gap:6px 12px;font-size:12px}
  .kv b{color:#667085;font-weight:700}
  .kv span{color:#172B3A;font-weight:600;word-break:break-word}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#98A2B3;padding:8px 6px;border-bottom:2px solid #E4E7EC}
  td{padding:8px 6px;border-bottom:1px solid #F2F4F7;vertical-align:top}
  .actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
  .danger-zone{border:1px solid #FECDCA;background:#FFFAFA;border-radius:10px;padding:14px}
  .hint{font-size:11px;color:#98A2B3;margin-top:6px}
  .search{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
  .search > div{flex:1;min-width:200px}
</style>
</head>
<body>
<header class="top">
  <div>
    <h1>Admin · Inquiry tools</h1>
    <p>Master Admin only · <?= h($user['name'] ?? '') ?></p>
  </div>
  <nav>
    <a href="../index.php">← App</a>
    <a href="../manage-user.php">Manage user</a>
    <a href="../migrate.php">Migrate</a>
    <a href="../api/logout.php">Sign out</a>
  </nav>
</header>

<main class="wrap">

  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-h">
      <h2>Find inquiry</h2>
      <span>Enter the Inquiry ID to view, edit, clear overdue, or delete</span>
    </div>
    <div class="card-b">
      <form method="get" class="search">
        <div>
          <label>Inquiry ID</label>
          <input type="text" name="id" placeholder="e.g. SP-2026-013" value="<?= h($id) ?>" autofocus required>
        </div>
        <button class="btn btn-primary" type="submit">View details</button>
      </form>
    </div>
  </div>

  <?php if ($inq): ?>

  <div class="card">
    <div class="card-h">
      <div>
        <h2><?= h($inq['id']) ?></h2>
        <span><?= h($inq['client'] ?? '') ?><?= !empty($inq['company']) ? ' · ' . h($inq['company']) : '' ?></span>
      </div>
      <div>
        <?php if ($isOverdue): ?>
          <span class="badge badge-od">Overdue</span>
        <?php else: ?>
          <span class="badge badge-ok">Not overdue</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-b">
      <div class="kv">
        <b>Type</b><span><?= h($inq['inquiry_type'] ?? '') ?></span>
        <b>Stage / Outcome</b><span><?= h($inq['stage'] ?? '') ?> · <?= h($inq['outcome'] ?? '') ?></span>
        <b>Owner</b><span><?= h($inq['current_owner'] ?? '') ?> <span class="meta">(created by <?= h($inq['created_by'] ?? '') ?>)</span></span>
        <b>Due</b><span><?= h($inq['due_date'] ?? 'TBD') ?><?= !empty($inq['due_time']) ? ' · ' . h($inq['due_time']) : '' ?></span>
        <b>Created</b><span><?= h($inq['created_at'] ?? $inq['date'] ?? '') ?></span>
        <b>Closed at</b><span><?= h($inq['closed_at'] ?? '—') ?></span>
        <b>Requirement</b><span><?= nl2br(h($inq['requirement'] ?? '')) ?></span>
      </div>
    </div>
  </div>

  <!-- Clear overdue / update due -->
  <div class="card">
    <div class="card-h">
      <h2>Due date / overdue</h2>
      <span>Extend the due date, or mark the inquiry closed so it leaves overdue lists</span>
    </div>
    <div class="card-b">
      <form method="post" class="row" style="align-items:end">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= h($inq['id']) ?>">
        <input type="hidden" name="action" value="update_due">
        <div>
          <label>New due date</label>
          <input type="date" name="due_date" required value="<?= h(admin_to_input($inq['due_date'] ?? '')) ?>">
        </div>
        <div>
          <label>Due time (optional)</label>
          <input type="time" name="due_time" value="<?= h($inq['due_time'] ?? '') ?>">
        </div>
        <div>
          <button class="btn btn-primary" type="submit">Update due</button>
        </div>
      </form>
      <form method="post" class="actions" onsubmit="return confirm('Mark this inquiry as Closed (Inquiry Closed)? It will no longer show as overdue.');">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= h($inq['id']) ?>">
        <input type="hidden" name="action" value="clear_overdue">
        <button class="btn btn-warn" type="submit">Clear overdue → mark closed</button>
      </form>
      <p class="hint">“Update due” keeps the inquiry open with a new deadline. “Mark closed” finishes it (same as Closure in the app).</p>
    </div>
  </div>

  <!-- Modify -->
  <div class="card">
    <div class="card-h">
      <h2>Modify inquiry</h2>
      <span>Edit core fields, then Save</span>
    </div>
    <div class="card-b">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= h($inq['id']) ?>">
        <input type="hidden" name="action" value="save">

        <div class="row" style="margin-bottom:12px">
          <div>
            <label>Inquiry date</label>
            <input type="date" name="date" value="<?= h(admin_to_input($inq['date'] ?? '')) ?>">
          </div>
          <div>
            <label>Type</label>
            <select name="inquiry_type">
              <option value="Client Project" <?= ($inq['inquiry_type'] ?? '') === 'Client Project' ? 'selected' : '' ?>>Client Project</option>
              <option value="Internal Usage" <?= ($inq['inquiry_type'] ?? '') === 'Internal Usage' ? 'selected' : '' ?>>Internal Usage</option>
            </select>
          </div>
          <div>
            <label>Due date</label>
            <input type="date" name="due_date" value="<?= h(admin_to_input($inq['due_date'] ?? '')) ?>">
          </div>
          <div>
            <label>Due time</label>
            <input type="time" name="due_time" value="<?= h($inq['due_time'] ?? '') ?>">
          </div>
        </div>

        <div class="row" style="margin-bottom:12px">
          <div>
            <label>Client</label>
            <input type="text" name="client" required value="<?= h($inq['client'] ?? '') ?>">
          </div>
          <div>
            <label>Company</label>
            <input type="text" name="company" required value="<?= h($inq['company'] ?? '') ?>">
          </div>
          <div>
            <label>Designation</label>
            <input type="text" name="designation" value="<?= h($inq['designation'] ?? '') ?>">
          </div>
          <div>
            <label>Country</label>
            <input type="text" name="country" value="<?= h($inq['country'] ?? 'India') ?>">
          </div>
        </div>

        <div class="row" style="margin-bottom:12px">
          <div>
            <label>Client type</label>
            <input type="text" name="client_type" value="<?= h($inq['client_type'] ?? 'New') ?>">
          </div>
          <div>
            <label>Created by</label>
            <select name="created_by">
              <?php foreach ($owners as $o): ?>
                <option value="<?= h($o) ?>" <?= ($inq['created_by'] ?? '') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Current owner</label>
            <select name="current_owner">
              <?php foreach ($owners as $o): ?>
                <option value="<?= h($o) ?>" <?= ($inq['current_owner'] ?? '') === $o ? 'selected' : '' ?>><?= h($o) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Proposal value</label>
            <input type="text" name="proposal_value" value="<?= h($inq['proposal_value'] ?? '') ?>">
          </div>
        </div>

        <div class="row" style="margin-bottom:12px">
          <div>
            <label>Stage</label>
            <input list="stage-list" name="stage" value="<?= h($inq['stage'] ?? '') ?>">
            <datalist id="stage-list">
              <?php foreach ($stages as $s): ?><option value="<?= h($s) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label>Outcome</label>
            <input list="outcome-list" name="outcome" value="<?= h($inq['outcome'] ?? '') ?>">
            <datalist id="outcome-list">
              <?php foreach ($outcomes as $o): ?><option value="<?= h($o) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email" value="<?= h($inq['email'] ?? '') ?>">
          </div>
          <div>
            <label>Phone</label>
            <input type="text" name="phone" value="<?= h($inq['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="row" style="margin-bottom:12px">
          <div>
            <label>Website</label>
            <input type="text" name="website" value="<?= h($inq['website'] ?? '') ?>">
          </div>
          <div>
            <label>Email subject</label>
            <input type="text" name="email_subject" value="<?= h($inq['email_subject'] ?? '') ?>">
          </div>
        </div>

        <div style="margin-bottom:12px">
          <label>Requirement</label>
          <textarea name="requirement"><?= h($inq['requirement'] ?? '') ?></textarea>
        </div>

        <button class="btn btn-primary" type="submit">Save changes</button>
      </form>
    </div>
  </div>

  <?php if ($steps): ?>
  <div class="card">
    <div class="card-h"><h2>Tasks / steps</h2><span><?= count($steps) ?> step(s)</span></div>
    <div class="card-b" style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Assignee</th><th>Due</th><th>Status</th><th>Instruction</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($steps as $st): ?>
          <tr>
            <td><?= (int) $st['id'] ?></td>
            <td><?= h($st['assigned_to'] ?? '') ?></td>
            <td><?= h($st['due'] ?? '') ?></td>
            <td><?= h($st['status'] ?? '') ?></td>
            <td><?= h(strlen((string)($st['instruction'] ?? '')) > 80 ? substr((string)$st['instruction'], 0, 77) . '…' : (string)($st['instruction'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($history): ?>
  <div class="card">
    <div class="card-h"><h2>Stage history</h2><span>Latest 20</span></div>
    <div class="card-b" style="overflow-x:auto">
      <table>
        <thead><tr><th>When</th><th>By</th><th>Stage</th><th>Outcome</th><th>Remark</th></tr></thead>
        <tbody>
          <?php foreach ($history as $hi): ?>
          <tr>
            <td><?= h($hi['date'] ?? '') ?></td>
            <td><?= h($hi['by_user'] ?? '') ?></td>
            <td><?= h($hi['stage'] ?? '') ?></td>
            <td><?= h($hi['outcome'] ?? '') ?></td>
            <td><?= h(strlen((string)($hi['remark'] ?? '')) > 60 ? substr((string)$hi['remark'], 0, 57) . '…' : (string)($hi['remark'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Delete -->
  <div class="card">
    <div class="card-h"><h2>Delete inquiry</h2><span>Permanent — cannot be undone</span></div>
    <div class="card-b">
      <div class="danger-zone">
        <form method="post" onsubmit="return confirm('Permanently delete <?= h($inq['id']) ?>? This cannot be undone.');">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id" value="<?= h($inq['id']) ?>">
          <input type="hidden" name="action" value="delete">
          <label>Type DELETE to confirm</label>
          <div class="search" style="margin-top:4px">
            <div><input type="text" name="confirm_delete" placeholder="DELETE" autocomplete="off" required></div>
            <button class="btn btn-danger" type="submit">Delete forever</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php endif; ?>

</main>
</body>
</html>
