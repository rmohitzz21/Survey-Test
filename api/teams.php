<?php
// Teams — a team is a name, a description, a lead, and its members. The lead (or any
// Admin) assigns tasks to members; each member works their own list and marks them Done.
//
// Tasks live in their own table rather than inquiry_steps: a step is always attached to an
// inquiry (FK, NOT NULL) and team work often isn't. The `due` column keeps the step's exact
// '01 Sep 2026 · 02:30 PM' shape so due_ts()/is_late() work on it unchanged.
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$user = current_user();

// Three is the whole lifecycle a team task needs: not started, being worked, finished.
// `const` has to sit outside the try block — PHP forbids it inside one.
const TEAM_STATUSES = ['Pending', 'In Progress', 'Done'];

try {
$b      = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? '');
$uid    = (int)$user['id'];

if (($user['role'] ?? '') === 'Client')
    json_response(['ok'=>false,'message'=>'Teams are not available for client accounts.'], 403);

$pdo->exec("CREATE TABLE IF NOT EXISTS teams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT         NULL,
    lead_id     INT          NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
    team_id    INT NOT NULL,
    account_id INT NOT NULL,
    PRIMARY KEY (team_id, account_id),
    FOREIGN KEY (team_id)    REFERENCES teams(id)    ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS team_tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    team_id      INT          NOT NULL,
    assigned_to  INT          NOT NULL,
    assigned_by  VARCHAR(100) NOT NULL,
    title        VARCHAR(200) NOT NULL,
    requirement  TEXT         NULL,
    status       VARCHAR(30)  NOT NULL DEFAULT 'Pending',
    remark       TEXT         NULL,
    due          VARCHAR(32)  NOT NULL DEFAULT 'TBD',
    completed_at TIMESTAMP    NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id)     REFERENCES teams(id)    ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_tt_assignee (assigned_to, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/** The team row, or null when this user may not even see it. */
$visible_team = function (int $id) use ($pdo, $uid): ?array {
    $stmt = $pdo->prepare("SELECT t.*, a.name AS lead_name FROM teams t
        JOIN accounts a ON a.id = t.lead_id WHERE t.id = ?");
    $stmt->execute([$id]);
    $team = $stmt->fetch();
    if (!$team) return null;
    if (is_admin() || (int)$team['lead_id'] === $uid) return $team;
    $m = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id=? AND account_id=?");
    $m->execute([$id, $uid]);
    return $m->fetchColumn() ? $team : null;
};

// Renaming a team, changing who is in it, and handing out work are all the lead's job.
// An Admin can do the same on any team so a team never gets stranded when a lead leaves.
$can_manage = fn(?array $team): bool => $team !== null && (is_admin() || (int)$team['lead_id'] === $uid);

$name_of = function (int $accountId) use ($pdo): ?string {
    $stmt = $pdo->prepare("SELECT name FROM accounts WHERE id=?");
    $stmt->execute([$accountId]);
    return $stmt->fetchColumn() ?: null;
};

// '2026-09-22' + '14:30' → '22 Sep 2026 · 02:30 PM', the shape inquiry_steps already stores.
$due_text = function (?string $date, ?string $time): string {
    $date = trim((string)$date);
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return 'TBD';
    return date('d M Y', strtotime($date)) . ($time ? ' · ' . date('h:i A', strtotime($time)) : '');
};

// ─── List ────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    if (is_admin()) {
        $teams = $pdo->query("SELECT t.*, a.name AS lead_name FROM teams t
            JOIN accounts a ON a.id = t.lead_id ORDER BY t.name")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT t.*, a.name AS lead_name FROM teams t
            JOIN accounts a ON a.id = t.lead_id
            LEFT JOIN team_members m ON m.team_id = t.id AND m.account_id = ?
            WHERE t.lead_id = ? OR m.account_id IS NOT NULL
            GROUP BY t.id ORDER BY t.name");
        $stmt->execute([$uid, $uid]);
        $teams = $stmt->fetchAll();
    }

    $ids = array_column($teams, 'id');
    $members = $tasks = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $ms = $pdo->prepare("SELECT m.team_id, m.account_id, a.name, a.role FROM team_members m
            JOIN accounts a ON a.id = m.account_id WHERE m.team_id IN ($in) ORDER BY a.name");
        $ms->execute($ids);
        foreach ($ms->fetchAll() as $row) $members[$row['team_id']][] = $row;

        $ts = $pdo->prepare("SELECT k.*, a.name AS assignee FROM team_tasks k
            JOIN accounts a ON a.id = k.assigned_to WHERE k.team_id IN ($in)
            ORDER BY k.created_at DESC, k.id DESC");
        $ts->execute($ids);
        foreach ($ts->fetchAll() as $row) {
            // Overdue is live state the browser flips as the clock passes; late is the permanent
            // record of a miss. Both derive from the same due_ts() the inquiry side uses.
            $row['due_ts']      = due_ts($row['due']);
            $row['is_late']     = is_late($row['completed_at'], $row['due_ts']);
            $row['assigned_to'] = (int)$row['assigned_to'];
            $tasks[$row['team_id']][] = $row;
        }
    }

    foreach ($teams as &$t) {
        $t['id']      = (int)$t['id'];
        $t['lead_id'] = (int)$t['lead_id'];
        $t['members'] = $members[$t['id']] ?? [];
        $t['tasks']   = $tasks[$t['id']]   ?? [];
        $t['can_manage'] = is_admin() || $t['lead_id'] === $uid;
    }
    unset($t);

    json_response(['ok'=>true, 'teams'=>$teams, 'statuses'=>TEAM_STATUSES]);
}

// ─── Create / rename a team ──────────────────────────────────────────────────
if ($action === 'save_team') {
    $id   = (int)($b['id'] ?? 0);
    $name = trim($b['name'] ?? '');
    $desc = trim($b['description'] ?? '');
    if ($name === '') json_response(['ok'=>false,'message'=>'Please give the team a name.'], 400);

    if ($id === 0) {
        $pdo->prepare("INSERT INTO teams (name,description,lead_id) VALUES (?,?,?)")
            ->execute([mb_substr($name, 0, 100), mb_substr($desc, 0, 2000), $uid]);
        $id = (int)$pdo->lastInsertId();
        // The lead is a member of their own team, so their name shows up in the assignee picker.
        $pdo->prepare("INSERT INTO team_members (team_id,account_id) VALUES (?,?)")->execute([$id, $uid]);
        json_response(['ok'=>true, 'id'=>$id]);
    }

    $team = $visible_team($id);
    if (!$can_manage($team))
        json_response(['ok'=>false,'message'=>'Only the team lead or an Admin can edit this team.'], 403);
    $pdo->prepare("UPDATE teams SET name=?, description=? WHERE id=?")
        ->execute([mb_substr($name, 0, 100), mb_substr($desc, 0, 2000), $id]);
    json_response(['ok'=>true, 'id'=>$id]);
}

if ($action === 'delete_team') {
    $team = $visible_team((int)($b['id'] ?? 0));
    if (!$can_manage($team))
        json_response(['ok'=>false,'message'=>'Only the team lead or an Admin can delete this team.'], 403);
    $pdo->prepare("DELETE FROM teams WHERE id=?")->execute([(int)$b['id']]); // members + tasks cascade
    json_response(['ok'=>true]);
}

// ─── Members ─────────────────────────────────────────────────────────────────
if ($action === 'set_members') {
    $id   = (int)($b['id'] ?? 0);
    $team = $visible_team($id);
    if (!$can_manage($team))
        json_response(['ok'=>false,'message'=>'Only the team lead or an Admin can change the members.'], 403);

    // The lead always stays in. Clients never join — they have no portal-side work.
    $wanted = array_map('intval', (array)($b['members'] ?? []));
    $wanted[] = (int)$team['lead_id'];
    $wanted = array_values(array_unique(array_filter($wanted)));
    $keep = [];
    if ($wanted) {
        $in = implode(',', array_fill(0, count($wanted), '?'));
        $ok = $pdo->prepare("SELECT id FROM accounts WHERE id IN ($in) AND status='approved' AND role<>'Client'");
        $ok->execute($wanted);
        $keep = array_map('intval', $ok->fetchAll(PDO::FETCH_COLUMN));
    }

    $before = $pdo->prepare("SELECT account_id FROM team_members WHERE team_id=?");
    $before->execute([$id]);
    $had = array_map('intval', $before->fetchAll(PDO::FETCH_COLUMN));

    // Dropping someone would orphan their open tasks, so block it until those are reassigned.
    $dropped = array_diff($had, $keep);
    if ($dropped) {
        $in = implode(',', array_fill(0, count($dropped), '?'));
        $open = $pdo->prepare("SELECT COUNT(*) FROM team_tasks
            WHERE team_id=? AND status<>'Done' AND assigned_to IN ($in)");
        $open->execute(array_merge([$id], array_values($dropped)));
        if ((int)$open->fetchColumn() > 0)
            json_response(['ok'=>false,'message'=>'That member still has open tasks. Finish or reassign them first.'], 400);
    }

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM team_members WHERE team_id=?")->execute([$id]);
    $add = $pdo->prepare("INSERT INTO team_members (team_id,account_id) VALUES (?,?)");
    foreach ($keep as $accountId) $add->execute([$id, $accountId]);
    $pdo->commit();

    foreach (array_diff($keep, $had) as $accountId) {
        if ($name = $name_of($accountId))
            notify_user($pdo, $name, 'Added to a team', $user['name'].' added you to '.$team['name'].'.');
    }
    json_response(['ok'=>true]);
}

// ─── Assign a task ───────────────────────────────────────────────────────────
if ($action === 'assign_task') {
    $teamId = (int)($b['teamId'] ?? 0);
    $team   = $visible_team($teamId);
    if (!$can_manage($team))
        json_response(['ok'=>false,'message'=>'Only the team lead or an Admin can assign tasks.'], 403);

    $to    = (int)($b['assignedTo'] ?? 0);
    $title = trim($b['title'] ?? '');
    if ($title === '') json_response(['ok'=>false,'message'=>'Please write what the task is.'], 400);

    $m = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id=? AND account_id=?");
    $m->execute([$teamId, $to]);
    if (!$m->fetchColumn())
        json_response(['ok'=>false,'message'=>'Pick someone who is in this team.'], 400);

    $pdo->prepare("INSERT INTO team_tasks (team_id,assigned_to,assigned_by,title,requirement,status,due)
                   VALUES (?,?,?,?,?,'Pending',?)")
        ->execute([$teamId, $to, $user['name'], mb_substr($title, 0, 200),
                   mb_substr(trim($b['requirement'] ?? ''), 0, 4000),
                   $due_text($b['due'] ?? '', $b['dueTime'] ?? '')]);
    // Read the id before notifying — notify_user INSERTs a notification row, and
    // lastInsertId() would then hand back that row's id instead of the task's.
    $taskId = (int)$pdo->lastInsertId();

    if ($name = $name_of($to))
        notify_user($pdo, $name, 'New team task — '.$team['name'], $user['name'].' assigned: '.$title);
    json_response(['ok'=>true, 'id'=>$taskId]);
}

// ─── Work a task ─────────────────────────────────────────────────────────────
if ($action === 'task_status') {
    $stmt = $pdo->prepare("SELECT * FROM team_tasks WHERE id=?");
    $stmt->execute([(int)($b['id'] ?? 0)]);
    $task = $stmt->fetch();
    if (!$task) json_response(['ok'=>false,'message'=>'Task not found.'], 404);

    $team = $visible_team((int)$task['team_id']);
    // The assignee owns the status of their own work; the lead and Admins can correct it.
    if (!$can_manage($team) && (int)$task['assigned_to'] !== $uid)
        json_response(['ok'=>false,'message'=>'This task is not yours to update.'], 403);

    $status = $b['status'] ?? $task['status'];
    if (!in_array($status, TEAM_STATUSES, true))
        json_response(['ok'=>false,'message'=>'Invalid status.'], 400);

    // completed_at is stamped once, on the first move to Done — it is the late/on-time record,
    // so re-marking an already-done task must not refresh it. Reopening clears it.
    $done   = $status === 'Done';
    $remark = array_key_exists('remark', $b) ? mb_substr(trim((string)$b['remark']), 0, 4000) : $task['remark'];
    $stamp  = $done ? ($task['completed_at'] ?: date('Y-m-d H:i:s')) : null;
    $pdo->prepare("UPDATE team_tasks SET status=?, remark=?, completed_at=? WHERE id=?")
        ->execute([$status, $remark, $stamp, (int)$task['id']]);

    if ($done && $task['status'] !== 'Done')
        notify_user($pdo, $task['assigned_by'], 'Team task completed',
                    $user['name'].' marked "'.$task['title'].'" as done.');
    json_response(['ok'=>true]);
}

if ($action === 'delete_task') {
    $stmt = $pdo->prepare("SELECT * FROM team_tasks WHERE id=?");
    $stmt->execute([(int)($b['id'] ?? 0)]);
    $task = $stmt->fetch();
    if (!$task) json_response(['ok'=>false,'message'=>'Task not found.'], 404);
    if (!$can_manage($visible_team((int)$task['team_id'])))
        json_response(['ok'=>false,'message'=>'Only the team lead or an Admin can remove a task.'], 403);
    $pdo->prepare("DELETE FROM team_tasks WHERE id=?")->execute([(int)$task['id']]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
