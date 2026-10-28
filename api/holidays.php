<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
sp_session_start();
require_staff_json();

$user = current_user();

$TYPES = ['festival', 'leave', 'other'];
$DEFAULT_COLORS = [
    'festival' => '#F79009',
    'leave'    => '#7A5AF8',
    'other'    => '#667085',
];

try {
$b      = get_json_body();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') require_csrf($b);
$action = $b['action'] ?? ($_GET['action'] ?? '');

$pdo->exec("CREATE TABLE IF NOT EXISTS calendar_holidays (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date  DATE         NOT NULL,
    title         VARCHAR(150) NOT NULL,
    holiday_type  ENUM('festival','leave','other') NOT NULL DEFAULT 'leave',
    color         VARCHAR(7)   NULL,
    created_by    VARCHAR(100) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holiday_date_title (holiday_date, title),
    INDEX idx_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$normalize_color = static function (?string $c): ?string {
    $c = trim((string)$c);
    if ($c === '') return null;
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) return null;
    return strtoupper($c);
};

if ($action === 'list') {
    $year = (int)($b['year'] ?? $_GET['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) $year = (int)date('Y');
    $stmt = $pdo->prepare("SELECT * FROM calendar_holidays
        WHERE YEAR(holiday_date)=?
        ORDER BY holiday_date ASC, title ASC");
    $stmt->execute([$year]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        if (empty($r['color'])) {
            $r['color'] = $DEFAULT_COLORS[$r['holiday_type']] ?? $DEFAULT_COLORS['other'];
        }
    }
    unset($r);
    json_response(['ok'=>true, 'data'=>$rows, 'year'=>$year, 'types'=>$TYPES, 'defaults'=>$DEFAULT_COLORS]);
}

if ($action === 'save') {
    if (!is_admin()) json_response(['ok'=>false, 'error'=>'Admin only'], 403);
    $id    = (int)($b['id'] ?? 0);
    $date  = trim((string)($b['holiday_date'] ?? ''));
    $title = trim((string)($b['title'] ?? ''));
    $type  = trim((string)($b['holiday_type'] ?? 'leave'));
    $color = $normalize_color($b['color'] ?? null);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok'=>false, 'error'=>'Valid date required (YYYY-MM-DD)'], 400);
    }
    if ($title === '' || mb_strlen($title) > 150) {
        json_response(['ok'=>false, 'error'=>'Title required (max 150 chars)'], 400);
    }
    if (!in_array($type, $TYPES, true)) $type = 'leave';

    try {
        if ($id > 0) {
            $chk = $pdo->prepare("SELECT id FROM calendar_holidays WHERE id=?");
            $chk->execute([$id]);
            if (!$chk->fetch()) json_response(['ok'=>false, 'error'=>'Holiday not found'], 404);
            $stmt = $pdo->prepare("UPDATE calendar_holidays
                SET holiday_date=?, title=?, holiday_type=?, color=? WHERE id=?");
            $stmt->execute([$date, $title, $type, $color, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO calendar_holidays
                (holiday_date, title, holiday_type, color, created_by)
                VALUES (?,?,?,?,?)");
            $stmt->execute([$date, $title, $type, $color, $user['name']]);
            $id = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            json_response(['ok'=>false, 'error'=>'A holiday with that title already exists on this date'], 409);
        }
        throw $e;
    }

    $row = $pdo->prepare("SELECT * FROM calendar_holidays WHERE id=?");
    $row->execute([$id]);
    $saved = $row->fetch();
    if ($saved && empty($saved['color'])) {
        $saved['color'] = $DEFAULT_COLORS[$saved['holiday_type']] ?? $DEFAULT_COLORS['other'];
    }
    json_response(['ok'=>true, 'data'=>$saved]);
}

if ($action === 'delete') {
    if (!is_admin()) json_response(['ok'=>false, 'error'=>'Admin only'], 403);
    $id = (int)($b['id'] ?? 0);
    if ($id <= 0) json_response(['ok'=>false, 'error'=>'Invalid id'], 400);
    $stmt = $pdo->prepare("DELETE FROM calendar_holidays WHERE id=?");
    $stmt->execute([$id]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false, 'error'=>'Unknown action'], 400);

} catch (Throwable $e) {
    json_response(['ok'=>false, 'error'=>$e->getMessage()], 500);
}
