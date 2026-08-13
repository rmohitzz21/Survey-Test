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

json_response(['ok'=>true,
    'latestInquiry'=>$latestInquiry,'latestStep'=>$latestStep,'latestStepUpd'=>$latestStepUpd,
    'latestAtt'=>$latestAtt,'latestInqAtt'=>$latestInqAtt,
    'latestHistory'=>$latestHistory,'latestFollowUp'=>$latestFollowUp,'latestFuCompleted'=>$latestFuCompleted,
    'pendingApprovals'=>$pendingApprovals,'unreadNotifications'=>$unreadNotifications
]);
