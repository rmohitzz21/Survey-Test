<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_auth_json();

$user = current_user();
$ENTITLEMENT = 18; // calendar days per year
$LEAVE_TYPES = ['Casual Leave','Half Day','Sick Leave','Emergency Leave','WFH'];

try {
$b      = get_json_body();
$action = $b['action'] ?? ($_GET['action'] ?? '');

$pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    account_id  INT         NOT NULL,
    leave_type  VARCHAR(40) NOT NULL DEFAULT 'Casual Leave',
    start_date  DATE        NOT NULL,
    end_date    DATE        NOT NULL,
    days        DECIMAL(4,1) NOT NULL,
    reason      TEXT        NOT NULL,
    status      ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    reviewed_by VARCHAR(100) NULL,
    reviewed_at TIMESTAMP   NULL,
    review_note VARCHAR(255) NULL,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_account (account_id, status),
    INDEX idx_leave_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
try { $pdo->exec("ALTER TABLE leave_requests ADD COLUMN leave_type VARCHAR(40) NOT NULL DEFAULT 'Casual Leave' AFTER account_id"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE leave_requests MODIFY days DECIMAL(4,1) NOT NULL"); } catch (Exception $e) {}

$uid  = (int)$user['id'];
$year = (int)date('Y');

$days_between = static function (string $start, string $end): int {
    $a = new DateTime($start);
    $b = new DateTime($end);
    if ($b < $a) return 0;
    return (int)$a->diff($b)->days + 1;
};

$summary_for = static function (PDO $pdo, int $accountId, int $year, int $entitlement): array {
    $q = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN status='approved' THEN days ELSE 0 END),0) AS taken,
            COALESCE(SUM(CASE WHEN status='pending'  THEN days ELSE 0 END),0) AS pending
        FROM leave_requests WHERE account_id=? AND YEAR(start_date)=?");
    $q->execute([$accountId, $year]);
    $row = $q->fetch() ?: ['taken'=>0,'pending'=>0];
    $taken   = (float)$row['taken'];
    $pending = (float)$row['pending'];
    return [
        'entitlement' => $entitlement,
        'taken'       => $taken,
        'pending'     => $pending,
        'remaining'   => max(0, $entitlement - $taken - $pending),
        'year'        => $year,
    ];
};

if ($action === 'list') {
    $summary = $summary_for($pdo, $uid, $year, $ENTITLEMENT);
    if (is_admin()) {
        $rows = $pdo->query("SELECT r.*, a.name FROM leave_requests r JOIN accounts a ON a.id=r.account_id ORDER BY r.status='pending' DESC, r.created_at DESC")->fetchAll();
        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT r.*, a.name FROM leave_requests r JOIN accounts a ON a.id=r.account_id WHERE r.account_id=? ORDER BY r.created_at DESC");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
        $pendingCount = 0;
    }
    json_response(['ok'=>true, 'summary'=>$summary, 'data'=>$rows, 'pendingCount'=>$pendingCount, 'types'=>$LEAVE_TYPES]);
}

if ($action === 'request') {
    if (($user['role'] ?? '') === 'Client') json_response(['ok'=>false,'message'=>'Clients cannot request leave.'], 403);
    if (is_admin()) json_response(['ok'=>false,'message'=>'Admins review leave requests and do not submit them.'], 403);
    $type   = trim($b['leaveType'] ?? 'Casual Leave');
    if (!in_array($type, $LEAVE_TYPES, true)) json_response(['ok'=>false,'message'=>'Please select a valid leave type.'], 400);
    $start  = $b['startDate'] ?? '';
    $end    = $b['endDate'] ?? '';
    $reason = trim($b['reason'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))
        json_response(['ok'=>false,'message'=>'Start and end dates are required.'], 400);
    if ($reason === '') json_response(['ok'=>false,'message'=>'Please add a reason for this leave.'], 400);
    if ($type === 'Half Day') $end = $start;
    $days = $type === 'Half Day' ? 0.5 : $days_between($start, $end);
    if ($days < 0.5) json_response(['ok'=>false,'message'=>'End date cannot be before start date.'], 400);
    if ($start < date('Y-m-d')) json_response(['ok'=>false,'message'=>'Leave cannot start in the past.'], 400);

    $ov = $pdo->prepare("SELECT id FROM leave_requests WHERE account_id=? AND status IN ('pending','approved') AND start_date<=? AND end_date>=?");
    $ov->execute([$uid, $end, $start]);
    if ($ov->fetch()) json_response(['ok'=>false,'message'=>'Those dates overlap an existing leave request.'], 400);

    $sum = $summary_for($pdo, $uid, (int)substr($start, 0, 4), $ENTITLEMENT);
    if ($days > $sum['remaining']) json_response(['ok'=>false,'message'=>'Not enough remaining leave ('.$sum['remaining'].' day'.($sum['remaining']==1?'':'s').' left).'], 400);

    $pdo->prepare("INSERT INTO leave_requests (account_id,leave_type,start_date,end_date,days,reason) VALUES (?,?,?,?,?,?)")
        ->execute([$uid, $type, $start, $end, $days, $reason]);

    $label = $start === $end ? date('d M Y', strtotime($start)) : date('d M Y', strtotime($start)).' – '.date('d M Y', strtotime($end));
    $dayTxt = $type === 'Half Day' ? 'half day' : ($days.' day'.($days==1?'':'s'));
    $admins = $pdo->prepare("SELECT name FROM accounts WHERE role IN ('Master Admin','Admin') AND status='approved' AND name<>?");
    $admins->execute([$user['name']]);
    foreach ($admins->fetchAll() as $row) {
        notify_user($pdo, $row['name'], 'Leave request — '.$user['name'], $user['name'].' requested '.$type.' ('.$dayTxt.', '.$label.').');
    }
    json_response(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
}

if ($action === 'decide') {
    if (!is_admin()) json_response(['ok'=>false,'message'=>'Only an Admin can approve leave.'], 403);
    $id     = (int)($b['id'] ?? 0);
    $status = $b['status'] ?? '';
    $note   = trim($b['note'] ?? '');
    if (!in_array($status, ['approved','rejected'], true)) json_response(['ok'=>false,'message'=>'Invalid decision.'], 400);

    $stmt = $pdo->prepare("SELECT r.*, a.name FROM leave_requests r JOIN accounts a ON a.id=r.account_id WHERE r.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['ok'=>false,'message'=>'Request not found.'], 404);
    if ($row['status'] !== 'pending') json_response(['ok'=>false,'message'=>'This request is no longer pending.'], 400);

    $pdo->prepare("UPDATE leave_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")
        ->execute([$status, $user['name'], $note !== '' ? $note : null, $id]);

    $label = $row['start_date'] === $row['end_date']
        ? date('d M Y', strtotime($row['start_date']))
        : date('d M Y', strtotime($row['start_date'])).' – '.date('d M Y', strtotime($row['end_date']));
    $title = $status === 'approved' ? 'Leave approved' : 'Leave declined';
    $body  = $status === 'approved'
        ? 'Your leave for '.$label.' ('.$row['days'].' day'.((int)$row['days']===1?'':'s').') has been approved.'
        : 'Your leave for '.$label.' was not approved.'.($note !== '' ? ' '.$note : '');
    notify_user($pdo, $row['name'], $title, $body);
    json_response(['ok'=>true]);
}

if ($action === 'cancel') {
    $id = (int)($b['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT account_id,status FROM leave_requests WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['ok'=>false,'message'=>'Request not found.'], 404);
    if ((int)$row['account_id'] !== $uid) json_response(['ok'=>false,'message'=>'You can only cancel your own request.'], 403);
    if ($row['status'] !== 'pending') json_response(['ok'=>false,'message'=>'Only pending requests can be cancelled.'], 400);
    $pdo->prepare("UPDATE leave_requests SET status='cancelled' WHERE id=?")->execute([$id]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Unknown action'], 400);

} catch (Throwable $e) {
    json_response(['ok'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
