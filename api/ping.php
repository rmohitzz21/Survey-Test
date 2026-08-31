<?php
// Lightweight poll — returns only 3 values, no heavy data
require_once '../includes/auth.php';
require_once '../includes/db.php';
sp_session_start();
require_auth_json();

$user = current_user();

$latestInquiry  = $pdo->query("SELECT MAX(created_at) FROM inquiries")->fetchColumn() ?: '';
try { $latestStep        = $pdo->query("SELECT MAX(id) FROM inquiry_steps")->fetchColumn() ?: 0; }        catch(Exception $e) { $latestStep = 0; }
try { $latestStepUpd     = $pdo->query("SELECT MAX(updated_at) FROM inquiry_steps")->fetchColumn() ?: ''; } catch(Exception $e) { $latestStepUpd = ''; }
try { $latestAtt         = $pdo->query("SELECT MAX(id) FROM step_attachments")->fetchColumn() ?: 0; }      catch(Exception $e) { $latestAtt = 0; }
try { $latestInqAtt      = $pdo->query("SELECT MAX(id) FROM inquiry_attachments")->fetchColumn() ?: 0; }   catch(Exception $e) { $latestInqAtt = 0; }
try { $latestHistory     = $pdo->query("SELECT MAX(id) FROM stage_history")->fetchColumn() ?: 0; }         catch(Exception $e) { $latestHistory = 0; }
try { $latestFollowUp    = $pdo->query("SELECT MAX(id) FROM follow_ups")->fetchColumn() ?: 0; }            catch(Exception $e) { $latestFollowUp = 0; }
try { $latestFuCompleted = $pdo->query("SELECT MAX(completed_at) FROM follow_ups")->fetchColumn() ?: ''; } catch(Exception $e) { $latestFuCompleted = ''; }

$pendingApprovals = is_admin()
    ? (int)$pdo->query("SELECT COUNT(*) FROM accounts WHERE status='pending'")->fetchColumn()
    : 0;

$q = $pdo->prepare("SELECT COUNT(*) FROM notifications n JOIN accounts a ON a.id=n.account_id WHERE a.name=? AND n.is_read=0");
$q->execute([$user['name']]);
$unreadNotifications = (int)$q->fetchColumn();

try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
    $q->execute([$user['id']]);
    $unreadMessages = (int)$q->fetchColumn();
} catch (Exception $e) { $unreadMessages = 0; } // messages table not yet migrated

try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM announcements a
        LEFT JOIN announcement_reads r ON r.account_id=?
        WHERE a.created_at > COALESCE(r.last_seen_at, '1970-01-01')");
    $q->execute([$user['id']]);
    $unreadAnnouncements = (int)$q->fetchColumn();
} catch (Exception $e) { $unreadAnnouncements = 0; } // announcements table not yet migrated

$pendingLeaves = 0;
if (is_admin()) {
    try {
        $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
    } catch (Exception $e) { $pendingLeaves = 0; }
}

// ── Presence: stamp this user as active, return everyone seen in the last 2 min ──
$onlineIds = [];
$lastSeen  = new stdClass();
$stampPresence = static function (PDO $pdo, int $uid) use (&$onlineIds, &$lastSeen): void {
    $pdo->prepare("UPDATE accounts SET last_seen_at=NOW() WHERE id=?")->execute([$uid]);
    $onlineIds = array_map('intval', $pdo->query(
        "SELECT id FROM accounts WHERE last_seen_at > (NOW() - INTERVAL 2 MINUTE)"
    )->fetchAll(PDO::FETCH_COLUMN));
    foreach ($pdo->query("SELECT id, last_seen_at FROM accounts WHERE last_seen_at IS NOT NULL") as $row) {
        $lastSeen->{(string)(int)$row['id']} = $row['last_seen_at'];
    }
};
try {
    $stampPresence($pdo, (int)$user['id']);
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE accounts ADD COLUMN last_seen_at TIMESTAMP NULL AFTER requested_at");
        $stampPresence($pdo, (int)$user['id']);
    } catch (Exception $e2) { /* presence unavailable */ }
}

json_response(['ok'=>true,'onlineIds'=>$onlineIds,'lastSeen'=>$lastSeen,
    'latestInquiry'=>$latestInquiry,'latestStep'=>$latestStep,'latestStepUpd'=>$latestStepUpd,
    'latestAtt'=>$latestAtt,'latestInqAtt'=>$latestInqAtt,
    'latestHistory'=>$latestHistory,'latestFollowUp'=>$latestFollowUp,'latestFuCompleted'=>$latestFuCompleted,
    'pendingApprovals'=>$pendingApprovals,'unreadNotifications'=>$unreadNotifications,
    'unreadMessages'=>$unreadMessages,'unreadAnnouncements'=>$unreadAnnouncements,
    'pendingLeaves'=>$pendingLeaves
]);
