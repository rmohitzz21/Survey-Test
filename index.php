<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
sp_session_start();
require_auth('login.php');

$user      = current_user();
$inquiries = load_inquiries($pdo, $user);
$accounts  = load_accounts($pdo);
$nextId    = next_inquiry_id($pdo);

// Today's and overdue follow-ups for dashboard widget  only assigned to current user
$_fuToday = date('Y-m-d');
$_fuStmt  = $pdo->prepare("SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id WHERE f.completed=0 AND f.follow_up_date<=? AND f.assigned_to=? ORDER BY f.follow_up_date ASC,f.follow_up_time ASC");
$_fuStmt->execute([$_fuToday, $user['name']]);
$todayFollowUps = $_fuStmt->fetchAll();

// All follow-ups for Reminders view. Admins also get everyone else's so the calendar's
// user picker works; the Reminders view narrows back to its own rows client-side.
$_rmSql = "SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id";
$_rmOrd = " ORDER BY f.completed ASC,f.follow_up_date ASC,f.follow_up_time ASC";
if (is_admin()) {
    $allFollowUps = $pdo->query($_rmSql.$_rmOrd)->fetchAll();
} else {
    $_rmStmt = $pdo->prepare($_rmSql." WHERE f.assigned_to=? OR f.created_by=?".$_rmOrd);
    $_rmStmt->execute([$user['name'], $user['name']]);
    $allFollowUps = $_rmStmt->fetchAll();
}
foreach ($allFollowUps as &$_rfu) { $_rfu['completed'] = (int)$_rfu['completed']; } unset($_rfu);

// Warning count on this user's own record seeds the sidebar badge before the view is opened.
try {
    $_wStmt = $pdo->prepare("SELECT COUNT(*) FROM warnings WHERE account_id=?");
    $_wStmt->execute([$user['id']]);
    $myWarnings = (int)$_wStmt->fetchColumn();
} catch (Exception $e) { $myWarnings = 0; } // warnings table not yet migrated

// Client-name autocomplete: admins see all; members only names from inquiries they can access
if (is_admin()) {
    $_allClientNames = $pdo->query("SELECT DISTINCT client FROM inquiries WHERE client!='' ORDER BY client")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $_cn = $pdo->prepare("SELECT DISTINCT i.client FROM inquiries i
        LEFT JOIN inquiry_steps s ON s.inquiry_id=i.id
        WHERE i.client!='' AND (i.created_by=? OR i.current_owner=? OR s.assigned_to=? OR s.assigned_by=?)
        ORDER BY i.client");
    $_cn->execute([$user['name'], $user['name'], $user['name'], $user['name']]);
    $_allClientNames = $_cn->fetchAll(PDO::FETCH_COLUMN);
}

$allStages = ['Inquiry','Communication / Proposal','Decision','Project Execution','Closure'];
$allOutcomes = ['Inquiry Received','Inquiry Created','Initial Communication','Proposal Submitted','Follow-up Sent','Awaiting Client Response','Project Won','Project Lost','Questionnaire','Link Testing','Field Work','Project Delivered','Invoice Sent','Payment Received','Inquiry Closed'];
$stepStatuses = ['New','Pending','In Progress','Client/Team Se Reply Pending','Checking Me','On Hold','Blocked','Submitted for Review','Done','Rejected','Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Survey Pacific Work Journey</title>
<link rel="icon" type="image/png" href="image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="assets/dark-mode.css?v=3">
<link rel="stylesheet" href="assets/mobile-polish.css?v=10">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/portal.css?v=1">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.3/dist/purify.min.js" defer></script>
<script src="assets/vendor/chart.umd.min.js?v=1"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="h-full flex app-shell" style="background:#F4F6F8;font-family:'Inter',system-ui,sans-serif"
      x-data="spApp()" x-init="init()" :class="[darkMode?'dark-mode':'', _mob?'sp-drawer-open':'']">

<!-- ═══ TOAST NOTIFICATIONS ══════════════════════════════════════════════════ -->
<div class="sp-toast-stack fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
  <template x-for="t in toasts" :key="t.id">
    <div class="pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-[10px] text-[12px] font-semibold shadow-lg border min-w-[220px] max-w-[320px] toast-enter"
         :class="t.type==='success'?'bg-[#ECFDF3] text-[#16803C] border-[#BBF7D0]':t.type==='error'?'bg-[#FEF3F2] text-[#B42318] border-[#FECDCA]':'bg-white text-[#172B3A] border-[#E4E7EC]'">
      <svg x-show="t.type==='success'" class="w-3.5 h-3.5 shrink-0"><use href="#icon-check-circle"/></svg>
      <svg x-show="t.type==='error'" class="w-3.5 h-3.5 shrink-0"><use href="#icon-alert-circle"/></svg>
      <span x-text="t.msg" class="flex-1"></span>
      <button @click="toasts=toasts.filter(x=>x.id!==t.id)" class="opacity-50 hover:opacity-100 ml-1">✕</button>
    </div>
  </template>
</div>

<!-- ═══ DUE-SOON WARNING ════════════════════════════════════════════════════ -->
<div x-show="dueWarnOpen" x-cloak
     class="sp-due-warn-backdrop fixed inset-0 z-[110]"
     style="background:rgba(7,29,43,0.55)"
     @click.self="dismissDueWarn()"
     @keydown.escape.window="dueWarnOpen && dismissDueWarn()">
  <div class="sp-due-warn-card" @click.stop
       style="width:100%;max-width:400px;border-radius:14px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,.28)">
    <div class="sp-due-warn-head" style="padding:16px 18px;display:flex;align-items:flex-start;gap:12px">
      <div class="sp-due-warn-icon" style="width:40px;height:40px;border-radius:999px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg class="w-5 h-5" style="color:#B42318"><use href="#icon-clock"/></svg>
      </div>
      <div style="min-width:0;flex:1">
        <div class="sp-due-warn-title" style="font-size:15px;font-weight:800;line-height:1.3">Due in a few minutes</div>
        <div class="sp-due-warn-sub" style="font-size:12px;margin-top:4px;line-height:1.4">Finish or update these before they go overdue.</div>
      </div>
      <button type="button" @click.prevent.stop="dismissDueWarn()" class="sp-due-warn-x" style="border:0;background:transparent;font-size:18px;line-height:1;cursor:pointer;padding:2px 4px;flex-shrink:0" aria-label="Close">✕</button>
    </div>
    <div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px;max-height:min(42vh,300px);overflow-y:auto">
      <template x-for="item in dueWarnList" :key="item.key">
        <button type="button" @click.prevent="openDueWarnItem(item)" class="sp-due-warn-item"
                style="width:100%;text-align:left;border-radius:10px;padding:10px 12px;cursor:pointer;border:1px solid transparent">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
            <span class="sp-due-warn-id" style="font-size:12px;font-weight:800;min-width:0;overflow:hidden;text-overflow:ellipsis" x-text="item.id"></span>
            <span class="sp-due-warn-badge" style="font-size:10px;font-weight:800;padding:3px 8px;border-radius:999px;white-space:nowrap;flex-shrink:0"
                  x-text="item.mins+' min left'"></span>
          </div>
          <div class="sp-due-warn-label" style="font-size:12px;font-weight:600;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="item.label"></div>
          <div class="sp-due-warn-hint" style="font-size:10px;margin-top:2px" x-text="item.kind==='task' ? 'Tap to open task steps' : 'Tap to open inquiry'"></div>
        </button>
      </template>
    </div>
    <div class="sp-due-warn-foot" style="padding:12px 16px;display:flex;justify-content:flex-end;gap:8px">
      <button type="button" @click.prevent.stop="dismissDueWarn()" class="sp-due-warn-dismiss"
              style="font-size:12px;font-weight:700;padding:8px 16px;border-radius:8px;border:0;cursor:pointer">Dismiss</button>
    </div>
  </div>
</div>

<!-- Mobile sidebar backdrop -->
<div x-show="_mob" @click="_mob=false" class="fixed inset-0 z-30 md:hidden" style="background:rgba(0,0,0,0.45)" x-cloak></div>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════════
     Grouped like Asana/Linear: Work → Insight → Team → Comms.
     Active = soft blue pill; badges for unread / pending. -->
<aside class="sp-sidebar h-full flex flex-col bg-white border-r border-[#E4E7EC] transition-all duration-200 shrink-0 fixed md:relative inset-y-0 left-0 z-40"
       :class="[
         _mob ? 'translate-x-0 shadow-xl md:shadow-none' : '-translate-x-full md:translate-x-0',
         (collapsed && !_mob) ? 'is-collapsed' : ''
       ]"
       :style="(collapsed && !_mob) ? 'width:64px' : 'width:240px'">

  <!-- Brand. height:56px inline, NOT h-[56px] — that class is absent from the stale
       app.css build, so the bar collapsed to the logo's height and its bottom border
       sat above the topbar's. The two must be the same 56px to line up. -->
  <div class="border-b border-[#E4E7EC] shrink-0 flex items-center"
       :class="collapsed ? 'justify-center px-2' : ''"
       :style="'height:56px'+(collapsed ? '' : ';padding-left:18px;padding-right:18px')">
    <template x-if="collapsed">
      <svg width="28" height="28" viewBox="0 0 40 40" fill="none" aria-hidden="true"><circle cx="20" cy="20" r="17" stroke="#1D3461" stroke-width="3"/><circle cx="20" cy="20" r="7" fill="#1D3461"/></svg>
    </template>
    <template x-if="!collapsed">
      <svg width="168" height="32" viewBox="0 0 300 52" fill="none" aria-hidden="true"><circle cx="26" cy="26" r="22" stroke="#1D3461" stroke-width="3.2"/><circle cx="26" cy="26" r="9" fill="#1D3461"/><text x="58" y="31" font-family="'Inter',system-ui" font-size="28" font-weight="700" fill="#1D3461" letter-spacing="-0.3">Survey Pacific</text><text x="59" y="46" font-family="'Inter',system-ui" font-size="10.5" font-weight="400" fill="#1D3461" letter-spacing="1.4">Global Research Execution Partner</text></svg>
    </template>
  </div>

  <?php
  // Shared nav-button markup. $badgeAlpine = Alpine expr for count, or null.
  // $badgeTone = 'danger' | 'warn'.
  $navBtn = function (string $id, string $label, string $icon, ?string $badgeAlpine = null, string $badgeTone = 'danger') {
    $toneCls = $badgeTone === 'warn'
      ? 'bg-[#FFFAEB] text-[#B54708]'
      : 'bg-[#FEF3F2] text-[#B42318]';
    $dotCls = $badgeTone === 'warn' ? 'bg-[#B54708]' : 'bg-[#B42318]';
    ?>
    <button type="button" @click="view='<?= $id ?>'; _mob=false; openNavForView('<?= $id ?>')"
            :title="collapsed ? '<?= htmlspecialchars($label, ENT_QUOTES) ?>' : undefined"
            class="sp-nav-link flex items-center gap-2.5 rounded-[8px] text-[12px] font-semibold transition-colors relative h-9"
            :class="[
              view==='<?= $id ?>' ? 'bg-[#EEF4FF] text-[#175CD3]' : 'text-[#344054] hover:bg-[#F9FAFB]',
              collapsed ? 'justify-center px-0 w-9 mx-auto' : 'px-2.5 w-full'
            ]">
      <span class="relative shrink-0 flex items-center justify-center">
        <svg class="w-4 h-4"><use href="#icon-<?= $icon ?>"/></svg>
        <?php if ($badgeAlpine): ?>
        <span x-show="<?= $badgeAlpine ?> > 0" class="absolute -top-1 -right-1.5 text-[8px] font-extrabold h-3.5 flex items-center justify-center rounded-full text-white <?= $dotCls ?>"
              style="min-width:14px;padding:0 2px" x-text="<?= $badgeAlpine ?>"></span>
        <?php endif; ?>
      </span>
      <span x-show="!collapsed" class="flex-1 text-left truncate"><?= htmlspecialchars($label) ?></span>
      <?php if ($badgeAlpine): ?>
      <span x-show="!collapsed && <?= $badgeAlpine ?> > 0"
            class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full tabular-nums <?= $toneCls ?>"
            x-text="<?= $badgeAlpine ?>"></span>
      <?php endif; ?>
    </button>
    <?php
  };
  // Expandable group: header toggles inner links with height + chevron animation.
  // When the sidebar is icon-collapsed, children stay visible (icon rail).
  $navGroup = function (string $key, string $label, string $icon, ?string $badgeAlpine = null, string $badgeTone = 'danger') {
    $toneCls = $badgeTone === 'warn'
      ? 'bg-[#FFFAEB] text-[#B54708]'
      : 'bg-[#FEF3F2] text-[#B42318]';
    $dotCls = $badgeTone === 'warn' ? 'bg-[#B54708]' : 'bg-[#B42318]';
    ?>
    <div class="sp-nav-group" style="margin-top:4px">
      <!-- Group header (hidden on icon-rail; children stay reachable there) -->
      <button type="button" x-show="!collapsed" @click="toggleNavGroup('<?= $key ?>')"
              class="flex items-center gap-2.5 rounded-[8px] text-[12px] font-bold transition-colors relative h-9 w-full px-2.5"
              :class="navGroupHasActive('<?= $key ?>') && !navOpen.<?= $key ?> ? 'bg-[#F5F8FF] text-[#175CD3]' : 'text-[#344054] hover:bg-[#F9FAFB]'">
        <span class="relative shrink-0 flex items-center justify-center">
          <svg class="w-4 h-4"><use href="#icon-<?= $icon ?>"/></svg>
          <?php if ($badgeAlpine): ?>
          <span x-show="!navOpen.<?= $key ?> && (<?= $badgeAlpine ?>) > 0"
                class="absolute -top-1 -right-1.5 text-[8px] font-extrabold h-3.5 flex items-center justify-center rounded-full text-white <?= $dotCls ?>"
                style="min-width:14px;padding:0 2px" x-text="<?= $badgeAlpine ?>"></span>
          <?php endif; ?>
        </span>
        <span class="flex-1 text-left truncate"><?= htmlspecialchars($label) ?></span>
        <?php if ($badgeAlpine): ?>
        <span x-show="!navOpen.<?= $key ?> && (<?= $badgeAlpine ?>) > 0"
              class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full tabular-nums <?= $toneCls ?>"
              x-text="<?= $badgeAlpine ?>"></span>
        <?php endif; ?>
        <svg class="sp-nav-chevron w-3.5 h-3.5 text-[#98A2B3] shrink-0"
             :class="navOpen.<?= $key ?> ? 'is-open' : ''"><use href="#icon-chevron-right"/></svg>
      </button>
      <!-- Collapsed rail divider between groups -->
      <div x-show="collapsed" class="w-5 border-t border-[#E4E7EC]" style="margin:8px auto"></div>
      <div class="sp-nav-panel" :class="(navOpen.<?= $key ?> || collapsed) ? 'is-open' : ''">
        <div class="sp-nav-panel-inner">
          <div class="sp-nav-children space-y-0.5">
    <?php
  };
  $navGroupEnd = function () {
    ?>
          </div>
        </div>
      </div>
    </div>
    <?php
  };
  $remindBadge = !empty($todayFollowUps) ? (string)count($todayFollowUps) : '0';
  $workBadgeExpr = $remindBadge === '0' ? null : $remindBadge;
  ?>

  <!-- Nav (scrolls when the list is long) -->
  <nav class="sp-scroll flex-1 overflow-y-auto py-2 px-2 space-y-0.5">

    <?php $navGroup('work', 'Work', 'layout-dashboard', $workBadgeExpr); ?>
    <?php $navBtn('dashboard', 'Dashboard', 'layout-dashboard'); ?>
    <?php $navBtn('tasks', 'My Tasks', 'check-square'); ?>
    <?php $navBtn('calendar', 'Calendar', 'calendar'); ?>
    <?php $navBtn('reminders', 'Reminders', 'clock', $remindBadge === '0' ? null : $remindBadge); ?>
    <?php $navBtn('notes', 'Notes', 'sticky-note'); ?>
    <?php $navBtn('resources', 'Resources', 'folder'); ?>
    <?php $navGroupEnd(); ?>

    <?php $navGroup('insight', 'Insight', 'bar-chart-2'); ?>
    <?php $navBtn('reports', 'Reports', 'file-text'); ?>
    <?php $navGroupEnd(); ?>

    <?php if (($user['role'] ?? '') !== 'Client'): ?>
    <?php
      $teamBadge = is_admin()
        ? '(pendingLeaves||0)+(pendingCount||0)'
        : 'myWarnings';
      $navGroup('team', 'Team', 'users', $teamBadge, 'warn');
    ?>
    <?php $navBtn('teams', 'Teams', 'users'); ?>
    <?php $navBtn('leave', 'Leave', 'briefcase', is_admin() ? 'pendingLeaves' : null, 'warn'); ?>
    <?php $navBtn('warnings', 'Warnings', 'alert-circle', is_admin() ? null : 'myWarnings'); ?>
    <?php if (is_admin()): ?>
    <?php $navBtn('approvals', 'Users', 'shield-check', 'pendingCount', 'warn'); ?>
    <?php endif; ?>
    <?php $navGroupEnd(); ?>
    <?php endif; ?>

    <?php $navGroup('comms', 'Comms', 'message-circle', '(unreadMessages||0)+(unreadAnnouncements||0)'); ?>
    <?php $navBtn('messages', 'Messages', 'message-circle', 'unreadMessages'); ?>
    <?php $navBtn('announcements', 'Announcements', 'megaphone', 'unreadAnnouncements'); ?>
    <?php $navGroupEnd(); ?>

  </nav>

  <!-- User footer. padding:10px inline (p-2.5 is absent from the build) so the avatar
       lands on the same 18px rail as the nav icons: 10px here + 8px on the row. -->
  <div class="border-t border-[#E4E7EC] shrink-0" style="padding:10px">
    <div class="flex items-center gap-2.5 rounded-[8px] px-2 py-2"
         :class="collapsed ? 'justify-center' : ''">
      <div class="w-8 h-8 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0"
           style="box-shadow:0 0 0 1px #E4E7EC">
        <span class="text-[11px] font-extrabold text-[#175CD3]"><?= strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $user['name']), 0, 2)))) ?></span>
      </div>
      <div x-show="!collapsed" class="flex-1 min-w-0">
        <div class="text-[12px] font-semibold text-[#172B3A] truncate"><?= htmlspecialchars($user['name']) ?></div>
        <div class="text-[10px] text-[#667085] truncate"><?= htmlspecialchars($user['role']) ?></div>
      </div>
    </div>
    <!-- px-2 (not px-2.5) so the icon shares the avatar's 18px rail directly above it. -->
    <a href="api/logout.php"
       class="mt-1 w-full flex items-center gap-2.5 text-[11px] font-semibold text-[#667085] hover:text-[#B42318] transition-colors rounded-[8px] px-2 py-2 hover:bg-[#FEF3F2]"
       :class="collapsed ? 'justify-center' : ''" :title="collapsed ? 'Sign out' : undefined">
      <svg class="w-4 h-4 shrink-0"><use href="#icon-log-out"/></svg>
      <span x-show="!collapsed">Sign out</span>
    </a>
  </div>
</aside>

<!-- ═══ MAIN CONTENT AREA ═══════════════════════════════════════════════════ -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

  <!-- Topbar -->
  <div class="sp-topbar bg-white border-b border-[#E4E7EC] flex items-center justify-between gap-2 px-3 sm:px-6 shrink-0" style="height:56px">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
      <button type="button" @click="toggleMenu()" class="text-[#667085] hover:text-[#172B3A] transition-colors shrink-0 p-1" aria-label="Menu">
        <svg class="w-[18px] h-[18px]"><use href="#icon-menu"/></svg>
      </button>
      <h1 class="text-[18px] font-bold text-[#071D2B] truncate" x-text="viewTitle"></h1>
    </div>
    <div class="sp-topbar-actions flex items-center gap-1.5 sm:gap-2 shrink-0">
      <button x-show="view==='reminders'" @click="rmAddOpen=true" title="Add Follow-up" class="sp-cta text-[11px] font-bold px-2.5 sm:px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-plus"/></svg><span class="sp-cta-label">Add Follow-up</span>
      </button>
      <button x-show="view==='leave' && !isAdmin" @click="openLeaveForm()" title="Request Leave" class="sp-cta text-[11px] font-bold px-2.5 sm:px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-plus"/></svg><span class="sp-cta-label">Request Leave</span>
      </button>
      <button x-show="view==='teams'" @click="openTeamForm()" title="New Team" class="sp-cta text-[11px] font-bold px-2.5 sm:px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-plus"/></svg><span class="sp-cta-label">New Team</span>
      </button>
      <button x-show="view==='notes'" @click="focusNoteComposer()" title="New Note" class="sp-cta text-[11px] font-bold px-2.5 sm:px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-plus"/></svg><span class="sp-cta-label">New Note</span>
      </button>
      <button x-show="view!=='reminders' && view!=='leave' && view!=='teams' && view!=='notes'" @click="addInquiryOpen=true" title="Add Inquiry" class="sp-cta text-[11px] font-bold px-2.5 sm:px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-plus"/></svg><span class="sp-cta-label">Add Inquiry</span>
      </button>
      <button @click="notifsOpen=!notifsOpen; if(notifsOpen) markNotifsRead()" class="relative text-[#667085] hover:text-[#172B3A] transition-colors p-1.5">
        <svg class="w-4 h-4"><use href="#icon-bell"/></svg>
        <span x-show="unreadCount>0" class="absolute top-0 right-0 text-[8px] font-extrabold w-3.5 h-3.5 flex items-center justify-center rounded-full bg-[#1268F3] text-white" x-text="unreadCount"></span>
      </button>
      <button type="button" @click="toggleDarkMode()" class="text-[#667085] hover:text-[#172B3A] transition-colors p-1.5" :title="darkMode?'Switch to light mode':'Switch to dark mode'">
        <svg x-show="!darkMode" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <svg x-show="darkMode" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="flex items-center gap-2 border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 text-[11px] text-[#344054] font-semibold">
        <div class="w-5 h-5 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[9px] font-extrabold text-[#175CD3]">
          <?= strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $user['name']), 0, 2)))) ?>
        </div>
        <span class="hidden sm:inline"><?= htmlspecialchars($user['name']) ?></span>
      </div>
    </div>
  </div>

  <!-- Content -->
  <div class="sp-scroll sp-main-scroll flex-1 overflow-y-auto overflow-x-hidden p-3 sm:p-6">

    <!-- ── DASHBOARD / INQUIRIES VIEW ─────────────────────────────────────── -->
    <div x-show="view==='dashboard' || view==='inquiries'">

      <!-- Restricted banner -->
      <?php if (!is_admin()): ?>
      <!-- <div class="flex items-center gap-2 text-[11px] text-[#026AA2] bg-[#EFF8FF] border border-[#BAE6FD] rounded-[8px] px-3 py-2 mb-3">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-user"/></svg>
        Showing inquiries you created or are assigned to. Admins see all inquiries.
      </div> -->
      <?php endif; ?> 

      <!-- Today's Follow-ups widget -->
      <div x-show="todayFollowUps.length > 0" x-cloak class="bg-white rounded-[10px] border border-[#E4E7EC] mb-4 overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="px-4 py-3 border-b border-[#E4E7EC] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4 text-[#B42318]"><use href="#icon-bell"/></svg>
              <span class="text-[13px] font-bold text-[#172B3A]">Today's Follow-ups</span>
              <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded-full bg-[#FEF3F2] text-[#B42318]" x-text="todayFollowUps.length"></span>
            </div>
            <button @click="view='reminders'" class="text-[11px] font-semibold text-[#1268F3] hover:underline">View All →</button>
          </div>
          <div class="divide-y divide-[#F2F4F7]">
            <template x-for="fu in todayFollowUps" :key="fu.id">
              <div class="flex items-start gap-3 px-4 py-3">
                <div class="shrink-0 min-w-[60px] text-right">
                  <div class="text-[11px] font-extrabold" :class="fu.follow_up_date < new Date().toLocaleDateString('en-CA') ? 'text-[#B42318]' : 'text-[#1268F3]'" x-text="fu.follow_up_time ? fuTimeLabel(fu.follow_up_time) : 'Today'"></div>
                  <div x-show="fu.follow_up_date < new Date().toLocaleDateString('en-CA')" class="text-[9px] font-extrabold text-[#B42318] bg-[#FEF3F2] px-1.5 py-0.5 rounded-full mt-0.5 inline-block">Overdue</div>
                </div>
                <div class="w-px self-stretch bg-[#E4E7EC] shrink-0"></div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[12px] font-extrabold text-[#1268F3]" x-text="fu.inquiry_id"></span>
                    <span class="text-[12px] font-bold text-[#172B3A] truncate" x-text="fu.client"></span>
                  </div>
                  <div class="text-[11px] text-[#344054] mt-0.5" x-text="fu.note"></div>
                  <div class="text-[10px] text-[#667085] mt-0.5" x-text="fu.created_by===currentUser ? 'Self-assigned' : 'Assigned by: '+fu.created_by"></div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <button @click="completeFuDashboard(fu)" class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">
                    <svg class="w-3 h-3"><use href="#icon-check-circle"/></svg> Done
                  </button>
                  <button @click="openFuInquiry(fu)" class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">
                    Open
                  </button>
                </div>
              </div>
            </template>
          </div>
      </div>

      <!-- Month dropdown every month that has inquiries, newest first. Nothing is ever deleted:
           picking an older month just re-scopes the stats, list and pagination to it. -->
      <div class="flex items-center gap-2 mb-3">
        <div class="relative shrink-0" x-data="{open:false}">
          <button type="button" @click="open=!open" @click.outside="open=false"
                  class="flex items-center gap-2 pl-3 pr-2.5 py-1.5 rounded-[8px] border bg-white text-[12px] font-semibold hover:bg-[#F9FAFB] transition-colors"
                  :class="inqMonth===todayStr.slice(0,7) ? 'border-[#E4E7EC] text-[#344054]' : 'border-[#1268F3] text-[#175CD3]'"
                  style="min-width:190px">
            <svg class="w-3.5 h-3.5 text-[#98A2B3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="flex-1 text-left" x-text="inqMonth ? monthLabel(inqMonth) : 'All months'"></span>
            <span class="text-[11px] text-[#98A2B3] tabular-nums" x-text="monthInquiries.length"></span>
            <span x-show="otherMonthsOpen>0" title="Open inquiries in other months open this list to reach them"
                  class="text-[9px] font-extrabold tabular-nums text-[#B54708] bg-[#FFFAEB] px-1.5 py-0.5 rounded-full shrink-0" x-text="otherMonthsOpen"></span>
            <svg class="w-3.5 h-3.5 text-[#98A2B3] transition-transform shrink-0" :class="open?'rotate-180':''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </button>

          <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
               class="absolute left-0 top-full mt-1.5 bg-white border border-[#E4E7EC] rounded-[10px] py-1 z-30 w-[230px] overflow-y-auto"
               style="box-shadow:0 8px 24px rgba(7,29,43,0.10);max-height:320px">
            <template x-for="m in monthTabs" :key="m.key">
              <button type="button" @click="inqMonth=m.key; _inqPage=0; open=false"
                      class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[12px] font-semibold text-left transition-colors hover:bg-[#F9FAFB]"
                      :class="inqMonth===m.key ? 'text-[#172B3A] bg-[#F2F4F7]' : 'text-[#344054]'">
                <span class="flex-1" x-text="m.label"></span>
                <span x-show="m.key===todayStr.slice(0,7)" class="text-[9px] font-extrabold uppercase tracking-[0.04em] text-[#175CD3] bg-[#EEF4FF] px-1.5 py-0.5 rounded-full shrink-0">Now</span>
                <span x-show="m.open>0 && m.key!==todayStr.slice(0,7)" title="Still open in this month pick it to close them"
                      class="text-[9px] font-extrabold tabular-nums text-[#B54708] bg-[#FFFAEB] px-1.5 py-0.5 rounded-full shrink-0" x-text="m.open+' open'"></span>
                <span class="text-[11px] text-[#98A2B3] tabular-nums" x-text="m.count"></span>
                <svg x-show="inqMonth===m.key" class="w-3 h-3 text-[#1268F3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
            </template>
            <div class="h-px bg-[#E4E7EC] my-1"></div>
            <button type="button" @click="inqMonth=''; _inqPage=0; open=false"
                    class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[12px] font-semibold text-left transition-colors hover:bg-[#F9FAFB]"
                    :class="!inqMonth ? 'text-[#172B3A] bg-[#F2F4F7]' : 'text-[#344054]'">
              <span class="flex-1">All months</span>
              <span class="text-[11px] text-[#98A2B3] tabular-nums" x-text="visibleInquiries.length"></span>
              <svg x-show="!inqMonth" class="w-3 h-3 text-[#1268F3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
          </div>
        </div>
        <button type="button" x-show="inqMonth!==todayStr.slice(0,7)" @click="inqMonth=todayStr.slice(0,7); _inqPage=0"
                class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors shrink-0">Back to this month</button>
      </div>

      <!-- Stats bar (4 tiles clickable filters) -->
      <div class="sp-stat-wrap overflow-x-auto">
      <div class="sp-stat-grid">
        <button type="button" @click="statFilter=''" class="stat-tile bg-white rounded-[10px] border border-[#E4E7EC] text-left w-full cursor-pointer"
          :style="statFilter==='' ? 'border-color:transparent;box-shadow:0 0 0 2px #667085' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-[#667085]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Total Inquiries</div></div>
          <div class="stat-num" x-text="stats.total"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='inProgress' ? '' : 'inProgress'" class="stat-tile bg-white rounded-[10px] border border-[#E4E7EC] text-left w-full cursor-pointer"
          :style="statFilter==='inProgress' ? 'border-color:transparent;box-shadow:0 0 0 2px #1268F3' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-[#1268F3]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">In Progress</div></div>
          <div class="stat-num" x-text="stats.inProgress"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='done' ? '' : 'done'" class="stat-tile bg-white rounded-[10px] border border-[#E4E7EC] text-left w-full cursor-pointer"
          :style="statFilter==='done' ? 'border-color:transparent;box-shadow:0 0 0 2px #16803C' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-[#16803C]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Closed</div></div>
          <div class="stat-num" x-text="stats.done"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='overdue' ? '' : 'overdue'" class="stat-tile bg-white rounded-[10px] border border-[#E4E7EC] text-left w-full cursor-pointer"
          :style="statFilter==='overdue' ? 'border-color:transparent;box-shadow:0 0 0 2px #B42318' : (stats.overdue>0 ? 'box-shadow:0 1px 4px rgba(7,29,43,0.04);border-color:'+(darkMode?'#5C2323':'#FECDCA') : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)')">
          <div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full bg-[#B42318]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Overdue</div></div>
          <div class="stat-num" :style="stats.overdue>0?'color:#B42318':''" x-text="stats.overdue"></div>
        </button>
      </div>
      </div>

      <!-- Filter bar -->
      <div class="sp-filter-wrap overflow-x-auto">
      <div class="sp-filter-bar bg-white rounded-[10px] border border-[#E4E7EC] px-3.5 py-3" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <!-- Search -->
        <div class="sp-filter-search flex items-center gap-2 border border-[#E4E7EC] rounded-[8px] px-2.5 flex-1 min-w-[180px]" style="height:38px">
          <svg class="w-3 h-3 text-[#667085] shrink-0"><use href="#icon-search"/></svg>
          <input x-model="search" placeholder="Search ID, client, requirement, person, date…" title="Searches every field on the inquiry and its tasks. Multiple words must all match." class="flex-1 text-[12px] text-[#172B3A] placeholder:text-[#667085] bg-transparent focus:outline-none" />
          <button x-show="search" @click="search=''" class="text-[#667085]">✕</button>
        </div>
        <button @click="dateFilter = dateFilter==='today' ? '' : 'today'" class="text-[11px] font-bold px-3 rounded-[8px] transition-colors shrink-0" :class="dateFilter==='today'?'bg-[#1268F3] text-white':'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'" style="height:38px">Today</button>
        <!-- Employee filter (admin only) -->
        <?php if (is_admin()): ?>
        <div class="relative">
          <select x-model="employee" class="text-[12px] font-semibold text-[#344054] border border-[#E4E7EC] rounded-[8px] pl-3 pr-7 bg-white focus:outline-none cursor-pointer" style="height:38px;min-width:140px">
            <option value="">All Users</option>
            <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
              <option :value="a.name" x-text="a.name"></option>
            </template>
          </select>
          <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
        <?php endif; ?>
        <!-- Project category filter -->
        <div class="relative">
          <select x-model="categoryFilter" class="text-[12px] font-semibold text-[#344054] border border-[#E4E7EC] rounded-[8px] pl-3 pr-7 bg-white focus:outline-none cursor-pointer" style="height:38px;min-width:150px">
            <option value="">Project Categories</option>
            <template x-for="c in projectCategories" :key="c.id">
              <option :value="c.name" x-text="c.name"></option>
            </template>
          </select>
          <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
        <!-- Inquiry type filter -->
        <div class="sp-filter-divider w-px h-5 bg-[#E4E7EC] shrink-0" aria-hidden="true"></div>
        <div class="sp-filter-types flex items-center gap-2 shrink-0">
          <button type="button" @click="typeFilter = typeFilter==='Client Project' ? '' : 'Client Project'" class="text-[11px] font-bold px-3 rounded-[8px] transition-colors" :class="typeFilter==='Client Project'?'bg-[#1268F3] text-white':'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'" style="height:34px">Client Project</button>
          <button type="button" @click="typeFilter = typeFilter==='Internal Usage' ? '' : 'Internal Usage'" class="text-[11px] font-bold px-3 rounded-[8px] transition-colors" :class="typeFilter==='Internal Usage'?'bg-[#1268F3] text-white':'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'" style="height:34px">Internal Usage</button>
        </div>
      </div>
      </div>

      <!-- Active filter chips -->
      <div x-show="chips.length" class="flex flex-wrap items-center gap-2 mb-3">
        <template x-for="chip in chips" :key="chip.label">
          <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#175CD3] bg-[#EEF4FF] rounded-full px-2.5 py-1">
            <span x-text="chip.label"></span>
            <button @click="chip.clear()" class="hover:text-[#B42318] transition-colors">✕</button>
          </span>
        </template>
        <button @click="resetFilters()" class="text-[10px] font-bold text-[#667085] hover:text-[#B42318]">Clear All</button>
      </div>

      <!-- Read-only banner when admin is viewing another user's inquiries -->
      <!-- <div x-show="viewingEmployee" class="flex items-center gap-2.5 mb-3 px-3 py-2.5 rounded-[10px] bg-[#FFFAEB] border border-[#FEF0C7] text-[#B54708]">
        <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-alert-circle"/></svg>
        <span class="text-[11px] font-semibold">Viewing <span class="font-bold" x-text="employee"></span>'s inquiries read only. Select "All Users" to take actions.</span>
      </div> -->

      <!-- Inquiry list -->
      <div x-show="filtered.length===0" class="flex flex-col items-center justify-center bg-white border border-dashed border-[#E4E7EC] rounded-[12px] text-center" style="padding:60px 40px">
        <svg class="w-10 h-10 text-[#D1D9E6] mb-3"><use href="#icon-file-text"/></svg>
        <div class="text-[15px] font-bold text-[#344054] mb-1">No inquiries found</div>
        <div class="text-[13px] text-[#667085] mb-4">Try adjusting your filters or search query.</div>
        <button @click="resetFilters()" class="text-[11px] font-bold px-4 py-2 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">Clear Filters</button>
      </div>

      <template x-if="filtered.length>0">
        <div>
          <!-- Inquiry list: table on desktop, cards on mobile -->
          <div class="inq-table-wrap">
            <!-- Frozen header row -->
            <div class="inq-col-header bg-[#F2F4F7]" style="border-bottom:2px solid #C8D2DF">
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#98A2B3]" style="width:32px;align-items:center;justify-content:center;flex-direction:row">#</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:86px">Inquiry ID</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:92px">Created</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:118px">Client</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:100px">Company</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="flex:1;min-width:80px">Requirement</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:104px">Created By</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:104px">Currently With</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:110px">Due</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:78px">Ownership</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:86px">Team Status</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:100px;border-right:none">Actions</div>
            </div>
          <template x-for="(inq, i) in pagedInqs" :key="inq.id">
            <div :data-inq="inq.id"
                 class="inq-card transition-colors"
                 :style="inqRowStyle(inq, i)">

                <!-- Horizontal row -->
                <div class="inq-row flex cursor-pointer" @click="toggleInquiry(inq)">
                  <!-- # -->
                  <div class="inq-cell inq-cell-num" style="width:32px;align-items:center;justify-content:center;flex-direction:row">
                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-extrabold"
                         :class="isOverdue(inq) ? 'bg-[#EEF4FF] text-[#B42318]' : 'bg-[#EEF4FF] text-[#175CD3]'"
                         x-text="_inqPage*15+i+1"></div>
                  </div>
                  <!-- Inquiry ID -->
                  <div class="inq-cell inq-cell-id" style="width:86px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Inquiry ID</div>
                    <div class="inq-ellipsis text-[11px] font-bold text-[#071D2B]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.id"></div>
                  </div>
                  <!-- Created: date + time when inquiry was generated -->
                  <div class="inq-cell" style="width:92px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Created</div>
                    <div class="text-[11px] font-semibold text-[#344054] leading-tight" x-text="inqCreatedDate(inq)"></div>
                    <div x-show="inqCreatedTime(inq)" class="text-[10px] text-[#667085] mt-0.5 tabular-nums" x-text="inqCreatedTime(inq)"></div>
                  </div>
                  <!-- Client (+ type under name) -->
                  <div class="inq-cell" style="width:118px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Client</div>
                    <div class="text-[11px] font-bold text-[#172B3A]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.client"></div>
                    <span class="inline-flex mt-0.5 text-[9px] font-extrabold px-1.5 py-0.5 rounded-full truncate"
                          style="max-width:100%"
                          :class="inq.is_new ? 'bg-[#FFFAEB] text-[#B54708]' : 'bg-[#ECFDF3] text-[#16803C]'"
                          :title="inq.client_type || 'New'"
                          x-text="inq.client_type || 'New'"></span>
                  </div>
                  <!-- Company -->
                  <div class="inq-cell" style="width:100px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Company</div>
                    <div class="text-[11px] text-[#344054]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:3px">
                      <svg class="w-2.5 h-2.5 shrink-0 text-[#98A2B3]"><use href="#icon-building2"/></svg>
                      <span x-text="inq.company"></span>
                    </div>
                    <span x-show="inq.project_category" class="inline-flex mt-0.5 text-[9px] font-extrabold px-1.5 py-0.5 rounded-full truncate bg-[#F4F3FF] text-[#5925DC]"
                          style="max-width:100%"
                          :title="inq.project_category"
                          x-text="inq.project_category"></span>
                  </div>
                  <!-- Requirement (popup on hover) -->
                  <div class="inq-cell inq-cell-req" style="flex:1;min-width:80px"
                       @mouseenter="showReqPopup($event, inq.requirement)"
                       @mouseleave="hideReqPopup()">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Requirement</div>
                    <div class="inq-ellipsis text-[11px] text-[#667085]" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="stripHtml(inq.requirement)"></div>
                  </div>
                  <!-- Created By -->
                  <div class="inq-cell" style="width:104px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Created By</div>
                    <div class="inq-name text-[11px] font-bold text-[#172B3A]" x-text="inq.created_by"></div>
                  </div>
                  <!-- Currently With -->
                  <div class="inq-cell" style="width:104px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Currently With</div>
                    <div class="inq-name text-[11px] font-bold text-[#172B3A]" x-text="inq.current_owner"></div>
                  </div>
                  <!-- Due: date + time -->
                  <div class="inq-cell" style="width:110px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Due</div>
                    <div class="text-[11px] font-semibold leading-tight" :class="isOverdue(inq)?'text-[#B42318]':'text-[#344054]'" x-text="dueDateOnly(inq)"></div>
                    <div x-show="dueTimeOnly(inq)" class="text-[10px] mt-0.5 tabular-nums" :class="isOverdue(inq)?'text-[#B42318] font-bold':'text-[#667085]'" x-text="dueTimeOnly(inq)"></div>
                  </div>
                  <!-- Ownership status -->
                  <div class="inq-cell" style="width:78px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Ownership</div>
                    <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-[3px] text-[10px] w-fit"
                          :class="isClosed(inq)?'bg-[#ECFDF3] text-[#16803C]':'bg-[#EFF8FF] text-[#1268F3]'"
                          x-text="isClosed(inq)?'Closed':'Open'"></span>
                  </div>
                  <!-- Team Status -->
                  <div class="inq-cell inq-cell-status" style="width:86px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Team Status</div>
                    <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-0.5 text-[9px] w-fit"
                          :class="teamStatusClass(teamStatus(inq))" x-text="teamStatus(inq)"></span>
                  </div>
                  <!-- Actions: wide enough for Summary CTA -->
                  <div class="inq-cell inq-cell-actions" style="width:100px;border-right:none;flex-direction:row;align-items:center;justify-content:center;padding-left:8px;padding-right:12px" @click.stop>
                    <span x-show="isClient" class="text-[9px] font-bold px-1.5 py-1 rounded-[6px] bg-[#F2F4F7] text-[#667085]" x-text="stageLabel(inq.stage)"></span>
                    <button x-show="!isClient" @click="openSummary(inq)" class="text-[10px] font-bold px-2.5 py-1.5 rounded-[7px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors whitespace-nowrap">Summary</button>
                  </div>
                </div>

                <!-- Expanded Journey hidden for Clients -->
                <div x-show="inq._open && !isClient" x-cloak class="px-4 pt-3 pb-4"
                     :style="{ background: darkMode ? '#16223A' : '#EFF6FF' }">

                  <!-- ── Inquiry Info panel: attachments, admin remark, follow-ups  grouped so it reads as one zone ── -->
                  <div class="bg-white border border-[#E4E7EC] rounded-[10px] p-3 mb-4">

                  <!-- Action bar: attachments left, buttons right -->
                  <div class="inq-action-bar flex items-start justify-between gap-3 mb-3">
                    <!-- Inquiry Attachments -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]">Inquiry Attachments</div>
                        <span x-show="inq.attachments&&inq.attachments.length>0"
                              class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#EEF4FF] text-[#175CD3]"
                              x-text="inq.attachments.length"></span>
                      </div>
                      <div x-show="!inq.attachments||inq.attachments.length===0"
                           class="text-[11px] text-[#B8CCED]">No attachments yet.</div>
                      <div class="flex flex-wrap gap-2">
                        <template x-for="att in (inq.attachments||[])" :key="att.id">
                          <div class="inline-flex items-center gap-1.5 text-[10px] bg-[#F8FAFC] border border-[#E4E7EC] rounded-[6px] px-2 py-1.5 max-w-[220px]">
                            <svg class="w-3 h-3 text-[#98A2B3] shrink-0"><use href="#icon-paperclip"/></svg>
                            <a :href="'api/serve.php?inquiry_id='+inq.id+'&file='+encodeURIComponent(att.filename)"
                               target="_blank"
                               class="text-[#175CD3] font-semibold hover:underline truncate flex-1"
                               x-text="att.filename.length>22?att.filename.slice(0,22)+'…':att.filename"></a>
                            <span class="text-[9px] text-[#B8CCED] whitespace-nowrap shrink-0" x-text="att.uploaded_by.split(' ')[0]"></span>
                            <button x-show="isAdmin || att.uploaded_by===currentUser"
                                    @click="deleteInquiryAttachment(inq,att)"
                                    title="Remove attachment"
                                    class="w-4 h-4 flex items-center justify-center rounded text-[#98A2B3] hover:text-[#B42318] hover:bg-[#FEF3F2] transition-colors shrink-0 text-[10px] font-bold">✕</button>
                          </div>
                        </template>
                      </div>
                    </div>
                    <!-- Buttons -->
                    <div class="inq-action-btns flex items-center gap-2 shrink-0 flex-wrap justify-end">
                      <button x-show="isAdmin"
                        @click="adminRemarkFor=inq" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#FFFAEB] text-[#B54708] hover:bg-[#B42318] hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5"><use href="#icon-shield-check"/></svg> Admin Remark
                        <span x-show="inq.admin_remark" class="w-1.5 h-1.5 rounded-full bg-[#B54708]"></span>
                      </button>
                      <button x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser || inq.steps.some(s=>s.assigned_to===currentUser||s.assigned_by===currentUser))"
                        @click="teamRemarkFor=inq" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5"><use href="#icon-message-circle"/></svg> Remark
                        <span x-show="inq.team_remark" class="w-1.5 h-1.5 rounded-full bg-[#1268F3]"></span>
                      </button>
                      <button x-show="!viewingEmployee && !isClient"
                        @click.stop="openFuForm(inq)" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5"><use href="#icon-bell"/></svg> Follow Up
                        <span x-show="(followUps[inq.id]||[]).some(f=>!f.completed)" class="w-1.5 h-1.5 rounded-full bg-[#1268F3]"></span>
                      </button>
                      <button x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser) && !isInquiryClosed(inq)"
                        @click="openCompleteInquiry(inq)" :disabled="openSteps(inq).length>0"
                        :title="openSteps(inq).length ? openSteps(inq).length+' task(s) still open — finish them before completing this inquiry' : 'Complete Inquiry'"
                        class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-[#ECFDF3] disabled:hover:text-[#16803C]">
                        <svg class="w-3.5 h-3.5"><use href="#icon-check-circle"/></svg> Complete Inquiry
                      </button>
                      <button x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser || inq.steps.some(s=>s.assigned_to===currentUser))"
                        @click="openAddTask(inq)" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5"><use href="#icon-plus"/></svg> Add Team / New Task
                      </button>
                      <button x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser)"
                        @click="openStageUpdate(inq)" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#175CD3] hover:text-white transition-colors">
                        Update Stage
                      </button>
                    </div>
                  </div>

                  <!-- Non-admin: read-only remark, shown only if one exists -->
                  <div x-show="!isAdmin && inq.admin_remark" class="mb-3">
                    <div class="bg-[#FFFAEB] border border-[#FDE68A] rounded-[10px] px-3.5 py-3">
                      <div class="flex items-center gap-2 mb-1.5">
                        <svg class="w-3.5 h-3.5 text-[#B45309] shrink-0"><use href="#icon-shield-check"/></svg>
                        <span class="text-[10px] font-extrabold uppercase tracking-[0.08em] text-[#B45309]">Admin Remark</span>
                      </div>
                      <p class="text-[12px] text-[#172B3A] leading-relaxed whitespace-pre-line" x-text="inq.admin_remark"></p>
                    </div>
                  </div>

                  <!-- Team remark, attributed to whoever wrote it, shown only if one exists -->
                  <div x-show="inq.team_remark" class="mb-3">
                    <div class="bg-[#EEF4FF] border border-[#DBEAFE] rounded-[10px] px-3.5 py-3">
                      <div class="flex items-center justify-between gap-2 mb-1.5">
                        <div class="flex items-center gap-2 min-w-0">
                          <svg class="w-3.5 h-3.5 text-[#175CD3] shrink-0"><use href="#icon-message-circle"/></svg>
                          <span class="text-[10px] font-extrabold uppercase tracking-[0.08em] text-[#175CD3] truncate" x-text="'Remark by ' + inq.team_remark_by"></span>
                        </div>
                        <div x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser || inq.steps.some(s=>s.assigned_to===currentUser||s.assigned_by===currentUser))"
                             class="flex items-center gap-1 shrink-0">
                          <button @click="teamRemarkFor=inq" title="Edit remark" class="w-5 h-5 flex items-center justify-center rounded text-[#175CD3] hover:bg-[#DBEAFE] transition-colors">
                            <svg class="w-3 h-3"><use href="#icon-edit-2"/></svg>
                          </button>
                          <button @click="deleteTeamRemark(inq)" title="Delete remark" class="w-5 h-5 flex items-center justify-center rounded text-[#B42318] hover:bg-[#FEF3F2] transition-colors">
                            <svg class="w-3 h-3"><use href="#icon-trash-2"/></svg>
                          </button>
                        </div>
                      </div>
                      <p class="text-[12px] text-[#172B3A] leading-relaxed whitespace-pre-line" x-text="inq.team_remark"></p>
                    </div>
                  </div>

                  </div><!-- /Inquiry Info panel -->

                  <!-- ── Workflow Steps panel  separate zone from the info panel above ── -->
                  <div class="bg-white border border-[#E4E7EC] rounded-[10px] p-3">

                  <!-- Label + all-done badge -->
                  <div class="flex items-center gap-2.5 mb-2">
                    <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#1268F3]">Workflow Steps</div>
                    <div x-show="inq.steps.length>0 && inq.steps.every(s=>s.status==='Done')"
                         class="inline-flex items-center gap-1 text-[9px] font-extrabold text-[#16803C] bg-[#ECFDF3] px-2 py-0.5 rounded-full">
                      <svg class="w-2.5 h-2.5"><use href="#icon-check-circle"/></svg>All steps completed
                    </div>
                  </div>

                  <!-- No steps -->
                  <div x-show="inq.steps.length===0" class="flex flex-col items-center py-6 text-center">
                    <svg class="w-7 h-7 text-[#D1D9E6] mb-2"><use href="#icon-file-text"/></svg>
                    <div class="text-[12px] font-semibold text-[#344054]">No steps yet</div>
                    <div class="text-[11px] text-[#667085]">Click " Add Team / New Task" to assign the first step.</div>
                  </div>

                  <!-- Step table -->
                  <div x-show="inq.steps.length>0" class="sp-hscroll overflow-x-auto">

                    <!-- Column headers -->
                    <div class="grid gap-2 px-1 mb-1.5" style="grid-template-columns:46px 150px 1fr 1fr 120px 155px 165px;min-width:800px">
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Step</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Assigned (By → To)</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Task Instruction / Email Chain</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Work Update / Remark</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Task Status</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Due Date & Time</div>
                      <div class="text-[9px] font-bold uppercase tracking-[0.05em] text-[#B8CCED]">Attachments</div>
                    </div>

                    <template x-for="(step, si) in inq.steps" :key="step.id">
                      <div class="grid gap-2 px-1 py-2.5 rounded-[8px] mb-2 border items-start relative"
                           :data-step="step.id"
                           :class="[
                             step.overdue?'border-[#FECDCA] bg-[#FFF8F8]':(['Done','Completed'].includes(step.status)?'border-[#D1FAE5] bg-[#F0FDF4]':'border-[#E4E7EC] bg-white'),
                             String(highlightedStep)===String(step.id) ? 'sp-step-flash' : ''
                           ]"
                           style="grid-template-columns:46px 150px 1fr 1fr 120px 155px 165px;min-width:800px">

                        <!-- Connector to next step -->
                        <div x-show="si < inq.steps.length - 1"
                             class="absolute"
                             style="left:27px;top:36px;bottom:-9px;width:0;border-left:2px dotted #16803C;transform:translateX(-50%)"></div>

                        <!-- Step number -->
                        <div class="flex flex-col items-center gap-1 pt-0.5">
                          <div class="w-[34px] h-[34px] rounded-full flex items-center justify-center text-white text-xs font-extrabold shrink-0"
                               :class="['Done','Completed'].includes(step.status)?'bg-[#16803C]':(['In Progress','New'].includes(step.status))?'bg-[#1268F3]':'bg-[#071D2B]'"
                               x-text="si+1"></div>
                          <span x-show="step.overdue" class="text-[8px] font-extrabold text-[#B42318] text-center leading-tight">Over<br>due</span>
                        </div>

                        <!-- Assigned By → To (merged into one column) -->
                        <div class="flex flex-col gap-1.5 justify-center">
                          <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[8px] font-bold uppercase tracking-[0.04em] shrink-0"
                                  :class="step.assigned_by===currentUser ? 'text-[#1268F3]' : 'text-[#B8CCED]'"
                                  x-text="step.assigned_by===currentUser ? 'You' : 'By'"></span>
                            <span class="text-[11px] font-semibold truncate"
                                  :class="step.assigned_by===currentUser ? 'text-[#175CD3]' : 'text-[#344054]'"
                                  x-text="step.assigned_by"></span>
                          </div>
                          <div class="flex items-center gap-1 min-w-0">
                            <svg class="w-2.5 h-2.5 text-[#B8CCED] shrink-0"><use href="#icon-arrow-right"/></svg>
                            <span class="text-[8px] font-bold uppercase tracking-[0.04em] shrink-0"
                                  :class="step.assigned_to===currentUser ? 'text-[#16803C]' : 'text-[#B8CCED]'"
                                  x-text="step.assigned_to===currentUser ? 'You' : 'To'"></span>
                            <span class="text-[11px] font-semibold truncate"
                                  :class="step.assigned_to===currentUser ? 'text-[#16803C]' : 'text-[#344054]'"
                                  x-text="step.assigned_to"></span>
                          </div>
                        </div>

                        <!-- Task Instruction locked after creation, always read-only -->
                        <div class="flex flex-col gap-1">
                          <span class="text-[8px] text-[#B8CCED]" x-text="'by ' + step.assigned_by"></span>
                          <div x-html="linkify(step._instruction)"
                            class="text-[11px] border border-[#E4E7EC] rounded-[6px] px-2 py-1.5 leading-[1.5] w-full bg-[#F8FAFC] text-[#172B3A] rich-editor min-h-[56px]"></div>
                        </div>

                        <!-- Work Update / Remark auto-saves on input, required before status change -->
                        <div class="flex flex-col gap-1">
                          <div class="flex items-center gap-1 min-h-[14px]">
                            <span x-show="!viewingEmployee && step.assigned_to===currentUser && !['Done','Completed'].includes(step.status)"
                                  class="text-[8px] font-extrabold px-1.5 py-0.5 rounded-[4px] bg-[#ECFDF3] text-[#16803C]">✎ Your update <span class="text-[#B42318]">*</span></span>
                            <span x-show="(viewingEmployee || step.assigned_to!==currentUser || ['Done','Completed'].includes(step.status)) && (step._remark||'').trim()"
                                  class="text-[8px] font-bold text-[#344054]" x-text="'Remark by ' + step.assigned_to"></span>
                            <span x-show="(viewingEmployee || step.assigned_to!==currentUser || ['Done','Completed'].includes(step.status)) && !(step._remark||'').trim()"
                                  class="text-[8px] text-[#B8CCED]" x-text="'No remark yet from ' + step.assigned_to"></span>
                            <span x-show="step._remarkSaved" class="text-[8px] font-bold text-[#16803C] ml-1">✓ Saved</span>
                          </div>
                          <textarea rows="3" x-model="step._remark"
                            @input.debounce.1000ms="autoSaveRemark(inq.id,step.id,step._remark,step)"
                            :placeholder="(step.assigned_to===currentUser && !['Done','Completed'].includes(step.status) && !viewingEmployee) ? 'Required add at least 20 characters before changing status…' : ''"
                            :readonly="viewingEmployee || ['Done','Completed'].includes(step.status) || step.assigned_to!==currentUser"
                            :class="(!viewingEmployee && step.assigned_to===currentUser && !['Done','Completed'].includes(step.status)) ? 'bg-white text-[#172B3A] border-l-[3px] ' + ((step._remark||'').trim().length >= 20 ? 'border-l-[#16803C]' : 'border-l-[#B42318]') : 'bg-[#F8FAFC] text-[#667085] cursor-default'"
                            class="text-[11px] border border-[#E4E7EC] rounded-[6px] px-2 py-1.5 resize-none placeholder:text-[#B8CCED] focus:outline-none focus:border-[#059669] leading-[1.5] w-full"></textarea>
                        </div>

                        <!-- Task Status -->
                        <div class="flex flex-col gap-1">

                          <!-- Current status, shown to everyone. Pending is the default and
                               Overdue is derived, so neither is ever chosen by hand. -->
                          <span x-show="!['Done','Completed','Rejected'].includes(step.status)"
                            class="inline-flex text-[10px] font-bold px-2 py-1 rounded-[6px] w-fit"
                            :class="stepStatusClass(step.status)"
                            x-text="step.status"></span>

                          <!-- Completing is the only state anyone sets by hand, so it is the only
                               control here. Still gated on the 20-character update. -->
                          <button type="button"
                            x-show="!viewingEmployee && step.assigned_to===currentUser && !['Done','Completed','Rejected','Cancelled'].includes(step.status)"
                            :disabled="(step._remark||'').trim().length < 20"
                            :title="(step._remark||'').trim().length < 20 ? 'Write your update first 20 characters minimum' : 'Mark this task completed'"
                            @click="updateStepStatus(inq.id,step.id,'Completed')"
                            class="inline-flex items-center justify-center gap-1 text-[10px] font-bold px-2 py-1.5 rounded-[6px] bg-[#16803C] text-white hover:bg-[#136832] transition-colors w-full disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-2.5 h-2.5"><use href="#icon-check-circle"/></svg> Mark Completed
                          </button>

                          <p x-show="!viewingEmployee && step.assigned_to===currentUser && !['Done','Completed','Rejected'].includes(step.status)"
                             class="text-[8px] font-semibold mt-0.5"
                             :class="(step._remark||'').trim().length < 20 ? 'text-[#B54708]' : 'text-[#16803C]'"
                             x-text="(()=>{ const w=(step._remark||'').trim().length; return w>=20 ? '✓ Ready' : w+' / 20 Keywords'; })()"></p>

                          <span x-show="['Done','Completed'].includes(step.status)"
                            class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#ECFDF3] text-[#16803C] w-fit">
                            <svg class="w-2.5 h-2.5"><use href="#icon-check-circle"/></svg>Completed
                          </span>
                          <span x-show="step.status==='Rejected'"
                            class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#FEF3F2] text-[#B42318] w-fit">
                            ✕ Rejected
                          </span>
                          <span x-show="step.overdue&&!['Done','Completed','Rejected','Cancelled'].includes(step.status)"
                            class="text-[9px] font-extrabold text-[#B42318] px-2 py-0.5 rounded-[4px] bg-[#FEF3F2] w-fit">Overdue</span>
                        </div>

                        <!-- Due Date & Time read-only, set via Add Task form only -->
                        <div class="flex flex-col gap-1">
                          <template x-if="step.due && step.due !== 'TBD'">
                            <div class="flex flex-col gap-0 pl-2 border-l-2 border-[#D1D9E6]">
                              <span class="text-[11px] font-semibold text-[#344054]" x-text="stepDueDate(step)"></span>
                              <span x-show="stepDueTime(step)" class="text-[10px] font-semibold text-[#667085]" x-text="stepDueTime(step)"></span>
                            </div>
                          </template>
                          <template x-if="!step.due || step.due === 'TBD'">
                            <span class="text-[11px] text-[#B8CCED]">TBD</span>
                          </template>
                        </div>

                        <!-- Attachments -->
                        <div class="flex flex-col gap-1">
                          <span x-show="!(step.attachments&&step.attachments.length)" class="text-[10px] text-[#B8CCED]">None</span>
                          <template x-for="f in (step.attachments||[])" :key="f">
                            <div class="inline-flex items-center gap-0.5 bg-[#EEF4FF] rounded-[4px] pl-1.5 pr-0.5 py-0.5 max-w-[170px]">
                              <svg class="w-2 h-2 shrink-0 text-[#175CD3]"><use href="#icon-paperclip"/></svg>
                              <a :href="'api/serve.php?step_id='+step.id+'&file='+encodeURIComponent(f)"
                                 target="_blank"
                                 class="text-[10px] font-semibold text-[#175CD3] hover:text-[#1268F3] transition-colors truncate"
                                 x-text="f.length>18?f.slice(0,18)+'…':f"></a>
                              <button @click.prevent="deleteStepAttachment(step.id,f,inq.id)"
                                      class="ml-0.5 shrink-0 w-3.5 h-3.5 rounded flex items-center justify-center text-[#98A2B3] hover:bg-[#FEE2E2] hover:text-[#B42318] transition-colors"
                                      title="Remove attachment">
                                <svg class="w-2.5 h-2.5"><use href="#icon-x"/></svg>
                              </button>
                            </div>
                          </template>
                          <button @click.stop="openStepUpload(step.id,inq.id)"
                                  class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#344054] border border-dashed border-[#D1D9E6] rounded-[4px] px-2 py-0.5 hover:border-[#1268F3] hover:text-[#175CD3] transition-colors w-fit">
                            <svg class="w-2.5 h-2.5"><use href="#icon-paperclip"/></svg>
                            Add Files
                          </button>
                        </div>

                      </div>
                    </template>
                  </div>

                  </div><!-- /Workflow Steps panel -->

                </div>
            </div>
          </template>
          </div><!-- /excel-grid -->

          <!-- Pagination bar — compact page window so numbers never spill outside -->
          <div x-show="filtered.length > 15" class="inq-pager">
            <div class="text-[11px] text-[#667085]">
              Showing <span class="font-semibold text-[#344054]" x-text="(_inqPage*15+1)+' – '+Math.min(_inqPage*15+15, filtered.length)"></span>
              of <span class="font-semibold text-[#344054]" x-text="filtered.length"></span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <button type="button" @click="_inqPage--" :disabled="_inqPage===0"
                      class="flex items-center gap-1 text-[11px] font-semibold px-3 py-1.5 rounded-[8px] border transition-all"
                      :class="_inqPage===0 ? 'border-[#E4E7EC] text-[#C4CAD4] cursor-not-allowed' : 'border-[#E4E7EC] text-[#344054] hover:border-[#1268F3] hover:text-[#1268F3]'">
                <svg class="w-3.5 h-3.5"><use href="#icon-chevron-left"/></svg>
                Prev
              </button>
              <div class="inq-pager-pages">
                <template x-for="(p, pi) in inqPageList" :key="'pg-'+pi+'-'+p">
                  <button type="button" @click="p!=='...' && (_inqPage=p-1)"
                          class="min-w-[28px] h-7 px-1 flex items-center justify-center rounded-[6px] text-[11px] font-bold transition-all"
                          :class="p==='...' ? 'text-[#98A2B3] cursor-default' : (p-1===_inqPage ? 'bg-[#1268F3] text-white' : 'text-[#667085] hover:bg-[#EEF4FF] hover:text-[#175CD3]')"
                          :disabled="p==='...'"
                          x-text="p"></button>
                </template>
              </div>
              <button type="button" @click="_inqPage++" :disabled="_inqPage>=Math.ceil(filtered.length/15)-1"
                      class="flex items-center gap-1 text-[11px] font-semibold px-3 py-1.5 rounded-[8px] border transition-all"
                      :class="_inqPage>=Math.ceil(filtered.length/15)-1 ? 'border-[#E4E7EC] text-[#C4CAD4] cursor-not-allowed' : 'border-[#E4E7EC] text-[#344054] hover:border-[#1268F3] hover:text-[#1268F3]'">
                Next
                <svg class="w-3.5 h-3.5"><use href="#icon-chevron-right"/></svg>
              </button>
            </div>
          </div>

        </div>
      </template>
    </div><!-- /dashboard+inquiries -->

    <!-- ── MY TASKS VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view==='tasks'" x-cloak>

      <!-- Tabs: To Me / By Me -->
      <div class="flex items-center gap-2 mb-4">
        <button @click="taskViewFilter='to_me'"
                class="flex items-center gap-1.5 text-[12px] font-bold px-3.5 py-2 rounded-[8px] border transition-colors"
                :class="taskViewFilter==='to_me' ? 'bg-[#1268F3] border-[#1268F3] text-white' : 'bg-white border-[#E4E7EC] text-[#344054] hover:border-[#1268F3]'">
          Assigned to Me
          <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded-full"
                :class="taskViewFilter==='to_me' ? 'bg-white/20 text-white' : 'bg-[#F2F4F7] text-[#667085]'"
                x-text="tasksAssignedToMe.length"></span>
        </button>
        <button @click="taskViewFilter='by_me'"
                class="flex items-center gap-1.5 text-[12px] font-bold px-3.5 py-2 rounded-[8px] border transition-colors"
                :class="taskViewFilter==='by_me' ? 'bg-[#1268F3] border-[#1268F3] text-white' : 'bg-white border-[#E4E7EC] text-[#344054] hover:border-[#1268F3]'">
          Assigned by Me
          <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded-full"
                :class="taskViewFilter==='by_me' ? 'bg-white/20 text-white' : 'bg-[#F2F4F7] text-[#667085]'"
                x-text="tasksAssignedByMe.length"></span>
        </button>
      </div>

      <template x-if="myTasks.length===0">
        <div class="flex flex-col items-center justify-center bg-white border border-dashed border-[#E4E7EC] rounded-[12px] text-center" style="padding:60px 40px">
          <svg class="w-10 h-10 text-[#D1D9E6] mb-3"><use href="#icon-check-circle"/></svg>
          <div class="text-[15px] font-bold text-[#344054] mb-1" x-text="taskViewFilter==='to_me' ? 'No open tasks' : 'You haven\'t assigned anyone a task'"></div>
          <div class="text-[13px] text-[#667085]" x-text="taskViewFilter==='to_me' ? 'All steps are completed or none assigned to you yet.' : 'Tasks you hand off to others will show up here until they\'re done.'"></div>
        </div>
      </template>
      <template x-if="myTasks.length>0">
        <div class="space-y-5">
         <template x-for="g in myTaskGroups" :key="g.key">
          <div>
            <!-- Group heading doubles as the fold control the count lives here rather than in a
                 tile, so there is exactly one place that says how many are late. -->
            <button type="button" @click="toggleTaskGroup(g.key)" class="flex items-center gap-2 w-full mb-2 text-left">
              <svg class="w-3 h-3 text-[#98A2B3]" style="transition:transform .15s"
                   :style="taskGroupsClosed.includes(g.key) ? 'transform:rotate(-90deg)' : ''"><use href="#icon-chevron-down"/></svg>
              <span class="text-[11px] font-extrabold uppercase tracking-[0.05em]"
                    :class="{ 'text-[#B42318]': g.key==='overdue', 'text-[#B54708]': g.key==='today',
                              'text-[#175CD3]': g.key==='upcoming', 'text-[#667085]': g.key==='none' }"
                    x-text="g.label"></span>
              <span class="text-[10px] font-bold text-[#667085] bg-[#F2F4F7] rounded-full px-1.5 py-0.5" x-text="g.items.length"></span>
              <span class="flex-1 border-t border-[#E4E7EC]"></span>
            </button>

            <div x-show="!taskGroupsClosed.includes(g.key)"
                 class="bg-white rounded-[10px] border border-[#E4E7EC]" style="overflow:hidden;box-shadow:0 1px 4px rgba(7,29,43,0.04)">
              <template x-for="(t, ti) in g.items" :key="t.inq.id+'-'+t.step.id">
                <!-- One task = one row. Lateness is said once in the due chip; the left bar only
                     echoes it as colour, and the row itself stays a single scannable line. -->
                <div class="task-row flex items-center gap-3 px-4 py-2.5 cursor-pointer"
                     :style="ti ? 'border-top:1px solid '+(darkMode?'#232A33':'#F2F4F7') : ''"
                     @click="goToInquiry(t.inq, t.step.id)">

                  <span class="shrink-0" style="width:3px;height:30px;border-radius:2px"
                        :style="'background:'+({overdue:'#B42318',today:'#B54708',upcoming:'#1268F3',none:'#D1D9E6'}[stepDueState(t.step)])"></span>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-[11px] font-extrabold text-[#1268F3] shrink-0" x-text="t.inq.id"></span>
                      <span class="text-[12px] font-bold text-[#172B3A] truncate" x-text="t.inq.client"></span>
                      <span x-show="t.inq.company && t.inq.company!==t.inq.client"
                            class="text-[11px] text-[#667085] truncate" x-text="'· '+t.inq.company"></span>
                    </div>
                    <div class="text-[11px] text-[#667085] truncate mt-0.5" x-text="stripHtml(t.step.instruction)"></div>
                  </div>

                  <div class="task-row-meta flex items-center gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-[5px] whitespace-nowrap"
                          :class="{ 'bg-[#FEF3F2] text-[#B42318]': stepDueState(t.step)==='overdue',
                                    'bg-[#FFFAEB] text-[#B54708]': stepDueState(t.step)==='today',
                                    'bg-[#F2F4F7] text-[#667085]': stepDueState(t.step)==='upcoming' || stepDueState(t.step)==='none' }">
                      <svg class="w-3 h-3 shrink-0"><use href="#icon-calendar"/></svg>
                      <span x-text="stepDueLabel(t.step)"></span>
                      <span x-show="stepDueTime(t.step)" class="font-semibold" x-text="stepDueTime(t.step)"></span>
                    </span>
                    <span class="inline-flex items-center rounded-[5px] font-bold whitespace-nowrap px-2 py-0.5 text-[10px]"
                          :class="stepStatusClass(t.step.status)" x-text="t.step.status"></span>
                    <span class="task-row-who text-[11px] text-[#667085] whitespace-nowrap"
                          x-text="(taskViewFilter==='to_me' ? 'from ' : 'with ') + (taskViewFilter==='to_me' ? t.step.assigned_by : t.step.assigned_to)"></span>
                    <svg class="w-3.5 h-3.5 text-[#D1D9E6] shrink-0"><use href="#icon-chevron-right"/></svg>
                  </div>

                </div>
              </template>
            </div>
          </div>
         </template>
        </div>
      </template>
    </div>

    <!-- ── APPROVALS VIEW ─────────────────────────────────────────────────── -->
    <?php if (is_admin()): ?>
    <div x-show="view==='approvals'" x-cloak class="space-y-5 min-w-0">

      <!-- Header -->
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <div class="text-[17px] font-bold text-[#172B3A]">Team &amp; Access</div>
          <div class="text-[11px] text-[#667085] mt-0.5">Review sign-up requests, set roles and responsibilities, control who gets in.</div>
        </div>
        <?php if (!is_master_admin()): ?>
        <div class="flex items-center gap-1.5 text-[10px] font-semibold text-[#175CD3] bg-[#EEF4FF] border border-[#DBEAFE] rounded-full px-3 py-1.5">
          <svg class="w-3 h-3 shrink-0"><use href="#icon-shield-check"/></svg>
          Admin changing roles and deleting accounts is Master Admin only
        </div>
        <?php endif; ?>
      </div>

      <!-- Pending Requests: an amber card while there is something to do, a slim strip when clear -->
      <template x-if="pendingCount===0">
        <div class="flex items-center gap-2 rounded-[10px] border border-[#BBF7D0] bg-[#ECFDF3] px-4 py-2.5">
          <svg class="w-3.5 h-3.5 text-[#16803C] shrink-0"><use href="#icon-shield-check"/></svg>
          <span class="text-[11px] font-semibold text-[#16803C]">No sign-up requests waiting</span>
          <span class="text-[11px] text-[#667085]">new registrations land here for approval.</span>
        </div>
      </template>

      <template x-if="pendingCount>0">
        <div class="bg-white rounded-[12px] border-2 border-[#FEF0C7] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="px-5 py-3.5 bg-[#FFFAEB] border-b border-[#FEF0C7] flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-6 h-6 rounded-full bg-[#FFFAEB] flex items-center justify-center shrink-0 text-[11px] font-extrabold text-[#B54708]" style="border:1px solid #FEF0C7" x-text="pendingCount"></span>
              <div class="min-w-0">
                <div class="text-[13px] font-bold text-[#172B3A]">Pending Requests</div>
                <div class="text-[11px] text-[#B54708]" x-text="pendingCount+' person'+(pendingCount>1?'s are':' is')+' waiting for access'"></div>
              </div>
            </div>
          </div>
          <div class="divide-y divide-[#F2F4F7]">
            <template x-for="a in accounts.filter(a=>a.status==='pending')" :key="a.id">
              <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-9 h-9 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
                  <span class="text-[11px] font-extrabold text-[#175CD3]" x-text="initials(a.name)"></span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-[12px] font-bold text-[#172B3A] truncate" x-text="a.name"></div>
                  <div class="text-[11px] text-[#667085] flex items-center gap-1 truncate">
                    <svg class="w-2.5 h-2.5 shrink-0"><use href="#icon-mail"/></svg><span class="truncate" x-text="a.email"></span>
                  </div>
                </div>
                <div class="text-[10px] text-[#98A2B3] shrink-0 hidden sm:flex items-center gap-1">
                  <svg class="w-2.5 h-2.5"><use href="#icon-clock"/></svg><span x-text="a.requested_at"></span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <button @click="accountAction('approve',a.id,a.name)" class="flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#16803C] text-white hover:bg-[#136832] transition-colors">✓ Approve</button>
                  <button @click="accountAction('reject',a.id,a.name)"  class="flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#B42318] hover:text-white transition-colors">✕ Reject</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Team Directory -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC] flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="text-[14px] font-bold text-[#172B3A]">Team Directory</div>
            <div class="text-[11px] text-[#667085] mt-0.5"
                 x-text="acctRows.length===acctStats.total-acctStats.pending
                         ? acctRows.length+' account'+(acctRows.length===1?'':'s')
                         : acctRows.length+' of '+(acctStats.total-acctStats.pending)+' shown'"></div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <!-- Search covers name, email, role and role description -->
            <!-- inline geometry: the prebuilt app.css has no left-2.5 / pl-7 / w-[240px] utilities -->
            <div class="relative">
              <svg class="w-3 h-3 absolute top-1/2 -translate-y-1/2 text-[#98A2B3] pointer-events-none" style="left:10px"><use href="#icon-search"/></svg>
              <input x-model="acctSearch" type="search" placeholder="Search name, email, responsibilities…"
                     style="width:240px;max-width:100%;padding-left:28px;padding-right:10px"
                     class="text-[11px] border border-[#E4E7EC] rounded-[8px] py-1.5 bg-white focus:outline-none focus:border-[#1268F3]" />
            </div>
            <div class="flex items-center gap-1">
              <template x-for="f in [{k:'',l:'All',n:acctStats.total-acctStats.pending},{k:'approved',l:'Active',n:acctStats.active},{k:'blocked',l:'Blocked',n:acctStats.blocked}]" :key="f.l">
                <!-- selected state is inline, same as the Reminders chips: app.css has no bg-[#172B3A] -->
                <button @click="acctStatusFilter=f.k"
                        class="text-[10px] font-bold px-2.5 py-1.5 rounded-[8px] border transition-colors"
                        :style="acctStatusFilter===f.k ? 'background:#344054;border-color:#344054;color:#fff' : ''"
                        :class="acctStatusFilter===f.k ? '' : 'bg-white border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7]'">
                  <span x-text="f.l"></span>
                  <span class="ml-1 opacity-60" x-text="f.n"></span>
                </button>
              </template>
            </div>
          </div>
        </div>
        <div class="p-5">
          <div class="rounded-[10px] border border-[#E4E7EC] overflow-hidden overflow-x-auto">
            <table class="w-full text-[11px]" style="min-width:640px">
              <thead><tr class="bg-[#F8FAFC] border-b border-[#E4E7EC]">
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">User</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Role</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Status</th>
                <th class="text-right px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Actions</th>
              </tr></thead>
              <tbody>
                <template x-if="acctRows.length===0">
                  <tr><td colspan="4" class="px-4 text-center" style="padding-top:2.5rem;padding-bottom:2.5rem">
                    <div class="text-[12px] font-semibold text-[#344054]">No accounts match</div>
                    <div class="text-[11px] text-[#667085] mt-0.5">Try a different search or filter.</div>
                  </td></tr>
                </template>
                <template x-for="(a,i) in acctRows" :key="a.id">
                  <tr class="border-b border-[#E4E7EC] last:border-0 hover:bg-[#F5F8FF] transition-colors" :class="i%2===0?'bg-white':'bg-[#FAFBFC]'">
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
                          <span class="text-[10px] font-extrabold text-[#175CD3]" x-text="initials(a.name)"></span>
                        </div>
                        <div class="min-w-0">
                          <div class="font-semibold text-[#172B3A] flex items-center gap-1.5">
                            <span class="truncate" x-text="a.name"></span>
                            <span x-show="a.email==='<?= $user['email'] ?>'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#EEF4FF] text-[#175CD3] shrink-0">You</span>
                          </div>
                          <div class="text-[10px] text-[#667085] truncate" x-text="a.email"></div>
                          <!-- The description itself lives in the profile drawer; this is just a marker -->
                          <div x-show="a.role_description" class="flex items-center gap-1 text-[9px] font-semibold text-[#16803C] mt-0.5">
                            <svg class="w-2.5 h-2.5 shrink-0"><use href="#icon-check-circle"/></svg> Responsibilities set
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <?php if (is_master_admin()): ?>
                      <template x-if="customRoleFor && customRoleFor.id===a.id">
                        <div class="flex items-center gap-1">
                          <input x-model="customRoleFor.value" placeholder="Role name…" class="text-[10px] border border-[#175CD3] rounded-[6px] px-2 py-1 w-24 focus:outline-none" />
                          <button @click="saveCustomRole(a.id)" class="text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#175CD3] text-white hover:bg-[#1349a8]">✓</button>
                          <button @click="customRoleFor=null" class="text-[10px] px-2 py-1 rounded-[6px] bg-[#F2F4F7] text-[#344054]">✕</button>
                        </div>
                      </template>
                      <template x-if="!customRoleFor || customRoleFor.id!==a.id">
                        <select x-show="a.email!=='<?= $user['email'] ?>'"
                                @change="changeRole(a.id,$event.target.value,a.name)"
                                class="text-[10px] font-semibold border border-[#E4E7EC] rounded-[6px] px-2 py-1 bg-white focus:outline-none text-[#344054] cursor-pointer">
                          <option value="Client" :selected="a.role==='Client'">Client</option>
                          <option value="Member" :selected="a.role==='Member'">Member</option>
                          <option value="Master Admin" :selected="a.role==='Master Admin'">Master Admin</option>
                          <template x-for="cr in customRoles" :key="cr">
                            <option :value="cr" x-text="cr" :selected="a.role===cr"></option>
                          </template>
                          <option value="__custom__">Custom role…</option>
                        </select>
                      </template>
                      <?php else: ?>
                      <span x-html="roleBadge(a.role)"></span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-3" x-html="statusPill(a.status)"></td>
                    <td class="px-4 py-3">
                      <div class="flex items-center justify-end gap-1.5 flex-wrap">
                        <button @click="openProfile(a)"
                          class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#F2F4F7] text-[#344054] hover:bg-[#344054] hover:text-white transition-colors">
                          <svg class="w-2.5 h-2.5"><use href="#icon-user"/></svg> Profile
                        </button>
                        <button @click="employee=a.name; view='dashboard'"
                          class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">
                          <svg class="w-2.5 h-2.5"><use href="#icon-file-text"/></svg> View Inquiries
                        </button>
                        <template x-if="a.email!=='<?= $user['email'] ?>'">
                          <div class="flex items-center gap-1.5">
                            <template x-if="a.status==='blocked'">
                              <button @click="accountAction('unblock',a.id,a.name)" class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">✓ Unblock</button>
                            </template>
                            <template x-if="a.status!=='blocked'">
                              <button @click="accountAction('block',a.id,a.name)" class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#F2F4F7] text-[#344054] hover:bg-[#344054] hover:text-white transition-colors">⊘ Block</button>
                            </template>
                            <?php if (is_master_admin()): ?>
                            <button @click="confirmDeleteAccount=a" class="flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-[6px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#B42318] hover:text-white transition-colors">✕ Delete</button>
                            <?php endif; ?>
                          </div>
                        </template>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Add New User (Master Admin only) -->
      <?php if (is_master_admin()): ?>
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC] flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-[#175CD3]"><use href="#icon-user"/></svg>
          </div>
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-[#172B3A]">Add New User</div>
            <div class="text-[11px] text-[#667085] mt-0.5">Creates an approved account straight away it skips the sign-up queue entirely.</div>
          </div>
        </div>
        <!-- Inner width cap: the view is full-bleed now, and full-width text inputs read badly -->
        <form class="p-5 space-y-5" style="max-width:760px" @submit.prevent="directAddUser()" autocomplete="off">

          <!-- Live preview: exactly how this person will appear in the directory above -->
          <div class="flex items-center gap-3 rounded-[10px] border border-[#E4E7EC] bg-[#F8FAFC] px-4 py-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                 :class="newUser.name.trim() ? 'bg-[#EEF4FF]' : 'bg-[#F2F4F7]'">
              <span class="text-[11px] font-extrabold"
                    :class="newUser.name.trim() ? 'text-[#175CD3]' : 'text-[#D1D9E6]'"
                    x-text="newUser.name.trim() ? initials(newUser.name) : '??'"></span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-[12px] font-bold truncate"
                   :class="newUser.name.trim() ? 'text-[#172B3A]' : 'text-[#98A2B3]'"
                   x-text="newUser.name.trim() || 'New team member'"></div>
              <div class="text-[10px] truncate"
                   :class="newUser.email.trim() ? 'text-[#667085]' : 'text-[#98A2B3]'"
                   x-text="newUser.email.trim() || 'their email address'"></div>
            </div>
            <span x-show="newUserRole" class="shrink-0" x-html="roleBadge(newUserRole||'Member')"></span>
          </div>

          <!-- ── Person ── -->
          <div>
            <div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#98A2B3] mb-2">Person</div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-[10px] font-bold text-[#344054] mb-1">Full Name</label>
                <input x-model="newUser.name" placeholder="e.g. Riya Patel" autocomplete="off"
                       class="w-full text-[12px] border rounded-[8px] px-3 py-2 focus:outline-none bg-white"
                       :class="newUserErr.name ? 'border-[#B42318]' : 'border-[#E4E7EC] focus:border-[#175CD3]'" />
                <p class="text-[10px] mt-1" :class="newUserErr.name ? 'text-[#B42318] font-semibold' : 'text-[#98A2B3]'"
                   x-text="newUserErr.name || 'Shown on inquiries and tasks they own.'"></p>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-[#344054] mb-1">Email</label>
                <input type="email" x-model="newUser.email" placeholder="e.g. riya@client.com" autocomplete="off"
                       class="w-full text-[12px] border rounded-[8px] px-3 py-2 focus:outline-none bg-white"
                       :class="newUserErr.email ? 'border-[#B42318]' : 'border-[#E4E7EC] focus:border-[#175CD3]'" />
                <p class="text-[10px] mt-1" :class="newUserErr.email ? 'text-[#B42318] font-semibold' : 'text-[#98A2B3]'"
                   x-text="newUserErr.email || 'Used to sign in. Must be unique.'"></p>
              </div>
            </div>
          </div>

          <!-- ── Access ── -->
          <div>
            <div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#98A2B3] mb-2">Access</div>

            <label class="block text-[10px] font-bold text-[#344054] mb-1">Role</label>
            <!-- Chips rather than a <select>: three or four options is faster to scan than a dropdown -->
            <div class="flex items-center gap-1.5 flex-wrap">
              <template x-for="r in ['Client','Member','Master Admin', ...customRoles]" :key="r">
                <button type="button" @click="newUser.role=r; newUser.customRole=''"
                        class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] border transition-colors"
                        :style="newUser.role===r ? 'background:#344054;border-color:#344054;color:#fff' : ''"
                        :class="newUser.role===r ? '' : 'bg-white border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7]'"
                        x-text="r"></button>
              </template>
              <button type="button" @click="newUser.role='__custom__'"
                      class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] border transition-colors"
                      :style="newUser.role==='__custom__' ? 'background:#344054;border-color:#344054;color:#fff' : ''"
                      :class="newUser.role==='__custom__' ? '' : 'bg-white border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7]'">+ Custom</button>
            </div>
            <input x-show="newUser.role==='__custom__'" x-model="newUser.customRole" placeholder="Type role name…"
                   style="max-width:280px"
                   class="w-full mt-2 text-[12px] border border-[#175CD3] rounded-[8px] px-3 py-2 focus:outline-none bg-white" />
            <p class="text-[10px] text-[#98A2B3] mt-1">Responsibilities are set afterwards from their profile.</p>

            <label class="block text-[10px] font-bold text-[#344054] mb-1 mt-3">Password</label>
            <div class="flex items-center gap-2">
              <div class="relative flex-1" style="max-width:340px">
                <input :type="newUser.showPass ? 'text' : 'password'" x-model="newUser.password" placeholder="Min. 6 characters" autocomplete="new-password"
                       style="padding-right:64px"
                       class="w-full text-[12px] border rounded-[8px] px-3 py-2 focus:outline-none bg-white"
                       :class="newUserErr.pass ? 'border-[#B42318]' : 'border-[#E4E7EC] focus:border-[#175CD3]'" />
                <div class="absolute top-1/2 -translate-y-1/2 flex items-center gap-1" style="right:8px">
                  <button type="button" @click="copyNewPassword()" :disabled="!newUser.password" tabindex="-1"
                          :title="newUser.copied ? 'Copied' : 'Copy password'"
                          class="text-[#667085] hover:text-[#344054] disabled:opacity-50">
                    <svg x-show="!newUser.copied" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/></svg>
                    <svg x-show="newUser.copied" class="w-3.5 h-3.5 text-[#16803C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                  </button>
                  <button type="button" @click="newUser.showPass=!newUser.showPass" tabindex="-1"
                          :title="newUser.showPass ? 'Hide' : 'Show'" class="text-[#667085] hover:text-[#344054]">
                    <svg x-show="!newUser.showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="newUser.showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.05-3.293M6.939 6.939A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.398 2.567M6.939 6.939L3 3m3.939 3.939l10.122 10.122M3 3l18 18"/></svg>
                  </button>
                </div>
              </div>
              <button type="button" @click="genPassword()"
                      class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-2 rounded-[8px] border border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7] transition-colors shrink-0">
                <svg class="w-3 h-3"><use href="#icon-refresh-cw"/></svg> Generate
              </button>
            </div>
            <p class="text-[10px] mt-1" :class="newUserErr.pass ? 'text-[#B42318] font-semibold' : 'text-[#98A2B3]'"
               x-text="newUserErr.pass || (newUser.copied ? 'Copied to clipboard send it to them.' : 'Minimum 6 characters. You will need to share it with them.')"></p>
          </div>

          <div x-show="newUser.error" class="flex items-start gap-2 text-[11px] font-semibold text-[#B42318] bg-[#FEF3F2] border border-[#FECDCA] rounded-[8px] px-3 py-2">
            <svg class="w-3 h-3 shrink-0 mt-px"><use href="#icon-alert-circle"/></svg><span x-text="newUser.error"></span>
          </div>
          <div x-show="newUser.success" class="flex items-start gap-2 text-[11px] font-semibold text-[#16803C] bg-[#ECFDF3] border border-[#BBF7D0] rounded-[8px] px-3 py-2">
            <svg class="w-3 h-3 shrink-0 mt-px"><use href="#icon-check-circle"/></svg><span x-text="newUser.success"></span>
          </div>

          <div class="flex items-center justify-between gap-3 border-t border-[#E4E7EC]" style="padding-top:14px">
            <span class="text-[10px] text-[#98A2B3]">The account is active immediately no approval step.</span>
            <button type="submit" :disabled="!newUserReady || newUser.saving"
                    class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#175CD3] text-white hover:bg-[#1349a8] transition-colors shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
              <template x-if="newUser.saving">
                <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              </template>
              <template x-if="!newUser.saving"><svg class="w-3 h-3"><use href="#icon-plus"/></svg></template>
              <span x-text="newUser.saving ? 'Adding…' : 'Add User'"></span>
            </button>
          </div>
        </form>
      </div>
      <?php endif; ?>

    </div><!-- /approvals -->
    <?php endif; ?>

    <!-- ── REMINDERS VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view==='reminders'" x-cloak>

      <div class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">

        <!-- Card header -->
        <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex items-center justify-between gap-4">
          <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-[#1268F3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="text-[14px] font-bold text-[#172B3A]">My Follow-ups</span>
            <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums" x-text="rmFiltered.length+' shown'"></span>
          </div>

          <div class="flex items-center gap-2">

            <!-- Filter dropdown (All / Today / Upcoming / Overdue) -->
            <div class="relative shrink-0" x-data="{open:false}">
              <button @click="open=!open" @click.outside="open=false"
                      class="flex items-center gap-2 pl-3 pr-2.5 py-1.5 rounded-[8px] border border-[#E4E7EC] bg-white text-[12px] font-semibold text-[#344054] hover:bg-[#F9FAFB] transition-colors"
                      style="min-width:136px">
                <span class="w-2 h-2 rounded-full shrink-0"
                      :class="{'bg-[#1268F3]':rmFilter==='today','bg-[#B42318]':rmFilter==='overdue','bg-[#667085]':rmFilter==='upcoming','bg-[#98A2B3]':rmFilter==='all'||rmFilter==='done'}"></span>
                <span class="flex-1 text-left"
                      x-text="rmFilter==='today'?'Today':rmFilter==='upcoming'?'Upcoming':rmFilter==='overdue'?'Overdue':'All'"></span>
                <span class="text-[11px] text-[#98A2B3] tabular-nums" x-text="rmFilter==='done'?rmMine.filter(f=>!f.completed).length:rmFiltered.length"></span>
                <svg class="w-3.5 h-3.5 text-[#98A2B3] transition-transform" :class="open?'rotate-180':''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
              </button>

              <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                   class="absolute right-0 top-full mt-1.5 bg-white border border-[#E4E7EC] rounded-[10px] py-1 z-30 w-44"
                   style="box-shadow:0 8px 24px rgba(7,29,43,0.10)">
                <template x-for="[key,label,dot] in [['all','All','#98A2B3'],['today','Today','#1268F3'],['upcoming','Upcoming','#667085'],['overdue','Overdue','#B42318']]" :key="key">
                  <button @click="rmFilter=key; open=false"
                          class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[12px] font-semibold text-left transition-colors hover:bg-[#F9FAFB]"
                          :class="(rmFilter===key||(rmFilter==='done'&&key==='all')) ? 'text-[#172B3A] bg-[#F2F4F7]' : 'text-[#344054]'">
                    <span class="w-2 h-2 rounded-full shrink-0" :style="'background:'+dot"></span>
                    <span class="flex-1" x-text="label"></span>
                    <span class="text-[11px] text-[#98A2B3] tabular-nums"
                          x-text="key==='all'?rmMine.filter(f=>!f.completed).length:key==='today'?rmMine.filter(f=>!f.completed&&f.follow_up_date===new Date().toLocaleDateString('en-CA')).length:key==='upcoming'?rmMine.filter(f=>!f.completed&&f.follow_up_date>new Date().toLocaleDateString('en-CA')).length:rmMine.filter(f=>!f.completed&&f.follow_up_date<new Date().toLocaleDateString('en-CA')).length"></span>
                    <svg x-show="rmFilter===key||(rmFilter==='done'&&key==='all')" class="w-3 h-3 text-[#1268F3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                </template>
              </div>
            </div>

            <!-- History  standalone toggle CTA -->
            <button @click="rmFilter = rmFilter==='done' ? 'all' : 'done'"
                    class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] text-[12px] font-semibold border transition-all"
                    :style="rmFilter==='done' ? 'background:#344054;border-color:#344054;color:#fff' : (darkMode ? 'background:#151B23;border-color:#2A323D;color:#93A0B4' : 'background:#fff;border-color:#E4E7EC;color:#667085')">
              <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              History
              <span class="text-[10px] font-extrabold tabular-nums px-1.5 py-0.5 rounded-full"
                    :style="rmFilter==='done' ? 'background:rgba(255,255,255,0.2);color:#fff' : (darkMode ? 'background:#232A33;color:#6B7685' : 'background:#F2F4F7;color:#98A2B3')"
                    x-text="rmMine.filter(f=>f.completed).length"></span>
            </button>

          </div>
        </div>

        <!-- Table -->
        <div class="sp-hscroll overflow-x-auto" x-show="rmFiltered.length>0">
          <table class="w-full min-w-[700px] border-collapse">
            <thead>
              <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3] w-[100px]">Priority</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3] w-[120px]">Date / Time</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3] w-[110px]">Inquiry</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Client</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Note</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3] w-[120px]">Assigned To</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3] w-[90px]">Status</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="fu in rmFiltered" :key="fu.id">
                <tr class="group transition-colors hover:bg-[#F9FAFB]"
                    style="border-bottom:1px solid #F2F4F7"
                    :style="fuStatus(fu)==='overdue'?(darkMode?'background:#241A1B':'background:#FFFAFA'):fuStatus(fu)==='today'?(darkMode?'background:#16223A':'background:#F5F8FF'):''">

                  <!-- Status -->
                  <td class="px-5 py-3.5">
                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2.5 py-1 rounded-full whitespace-nowrap"
                          :class="fuStatus(fu)==='overdue'?'bg-[#FEF3F2] text-[#B42318]':fuStatus(fu)==='today'?'bg-[#EEF4FF] text-[#175CD3]':fuStatus(fu)==='upcoming'?'bg-[#F2F4F7] text-[#344054]':'bg-[#ECFDF3] text-[#16803C]'">
                      <span :class="fuStatus(fu)==='overdue'?'text-[#B42318]':fuStatus(fu)==='today'?'text-[#1268F3]':fuStatus(fu)==='upcoming'?'text-[#667085]':'text-[#16803C]'">●</span>
                      <span x-text="fuStatus(fu)==='overdue'?'Overdue':fuStatus(fu)==='today'?'Today':fuStatus(fu)==='upcoming'?'Upcoming':'Done'"></span>
                    </span>
                  </td>

                  <!-- Date / Time -->
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="text-[12px] font-semibold text-[#344054]" x-text="fu.follow_up_date"></div>
                    <div x-show="fu.follow_up_time" class="text-[10px] text-[#98A2B3] mt-0.5" x-text="fuTimeLabel(fu.follow_up_time)"></div>
                  </td>

                  <!-- Inquiry -->
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <button @click="openFuInquiry(fu)" class="text-[12px] font-extrabold text-[#1268F3] hover:underline" x-text="fu.inquiry_id"></button>
                  </td>

                  <!-- Client -->
                  <td class="px-5 py-3.5">
                    <div class="text-[12px] font-semibold text-[#172B3A] truncate max-w-[140px]" x-text="fu.client||''"></div>
                    <div class="text-[10px] text-[#98A2B3] truncate max-w-[140px]" x-text="fu.company"></div>
                  </td>

                  <!-- Note -->
                  <td class="px-5 py-3.5">
                    <div class="text-[12px] text-[#344054] leading-snug max-w-[260px]"
                         :class="fu.completed?'line-through text-[#B8CCED]':''"
                         x-text="fu.note"></div>
                  </td>

                  <!-- Assigned To -->
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <div class="w-5 h-5 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
                        <span class="text-[8px] font-extrabold text-[#175CD3]" x-text="fu.assigned_to.split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase()"></span>
                      </div>
                      <div>
                        <div class="text-[12px] text-[#344054]" x-text="fu.assigned_to"></div>
                        <div class="text-[10px] text-[#98A2B3]" x-text="fu.created_by===fu.assigned_to ? 'Self-assigned' : 'By: '+fu.created_by"></div>
                      </div>
                    </div>
                  </td>

                  <!-- Actions  only for the assignee -->
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <template x-if="!fu.completed && (fu.assigned_to===currentUser || fu.created_by===currentUser)">
                      <button @click="rmComplete(fu)"
                              class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">
                        <svg class="w-3 h-3"><use href="#icon-check-circle"/></svg> Done
                      </button>
                    </template>
                  </td>

                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Empty state -->
        <div x-show="rmFiltered.length===0"
             class="flex flex-col items-center justify-center text-center px-6"
             style="min-height:260px">
          <div class="w-14 h-14 rounded-full bg-[#F2F4F7] flex items-center justify-center mb-4">
            <svg class="w-7 h-7 text-[#C4CAD4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </div>
          <div class="text-[14px] font-bold text-[#344054] mb-1"
               x-text="rmFilter==='all'?'No follow-ups yet':rmFilter==='done'?'No history yet':'No '+rmFilter+' follow-ups'"></div>
          <div class="text-[12px] text-[#667085] leading-relaxed" x-show="rmFilter==='all'">
            Click <span class="font-semibold text-[#344054]">Add Follow-up</span> to schedule one.
          </div>
          <button x-show="rmFilter!=='all'" @click="rmFilter='all'"
                  class="mt-3 text-[11px] font-bold px-3 py-1.5 rounded-[6px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">
            Show all follow-ups
          </button>
        </div>

      </div>

    </div><!-- /reminders -->

    <!-- ── MESSAGES VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view==='messages'" x-cloak>
      <div class="msg-shell bg-white rounded-[12px] border border-[#E4E7EC] flex overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <!-- Contact list -->
        <div class="msg-list-pane border-r border-[#E4E7EC] overflow-y-auto shrink-0" :class="{'msg-hide': activeContact}">
          <div class="px-3.5 py-3 border-b border-[#E4E7EC] space-y-2">
            <span class="text-[14px] font-bold text-[#172B3A]">Conversations</span>
            <div class="flex items-center gap-1.5 border border-[#E4E7EC] rounded-[8px] px-2.5 focus-within:border-[#1268F3] transition-colors" style="height:34px">
              <svg class="w-3 h-3 text-[#667085] shrink-0"><use href="#icon-search"/></svg>
              <input type="text" x-model="msgSearch" placeholder="Search members…"
                     class="flex-1 min-w-0 text-[11px] text-[#172B3A] placeholder:text-[#667085] bg-transparent focus:outline-none">
              <button x-show="msgSearch" @click="msgSearch=''" class="text-[#667085] hover:text-[#172B3A] shrink-0 text-[11px]">✕</button>
            </div>
          </div>
          <template x-if="messagingContacts.length===0">
            <div class="flex flex-col items-center justify-center p-8 text-center">
              <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-message-circle"/></svg>
              <div class="text-[12px] text-[#667085]">No contacts</div>
            </div>
          </template>
          <template x-if="messagingContacts.length>0 && filteredContacts.length===0">
            <div class="flex flex-col items-center justify-center p-8 text-center">
              <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-search"/></svg>
              <div class="text-[12px] text-[#667085]">No members match <span class="font-bold" x-text="'“'+msgSearch+'”'"></span></div>
            </div>
          </template>
          <div class="divide-y divide-[#E4E7EC]">
            <template x-for="c in filteredContacts" :key="c.id">
              <div class="flex items-center gap-2.5 px-3.5 py-3 cursor-pointer transition-colors"
                   :class="activeContact && activeContact.id===c.id ? 'bg-[#EEF4FF]' : 'hover:bg-[#F2F4F7]'"
                   @click="selectContact(c)">
                <div class="relative shrink-0">
                  <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[9px] font-extrabold text-[#175CD3]" x-text="c._initials"></div>
                  <span :title="c.online ? 'Online' : 'Offline'"
                        :style="'position:absolute;top:-3px;right:-3px;width:10px;height:10px;border-radius:999px;border:2px solid #fff;background:'+(c.online?'#25D366':'#D0D5DD')"></span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <div class="text-[12px] font-bold text-[#172B3A] truncate" x-text="c.name"></div>
                    <div x-show="c._lastTime" class="text-[9px] text-[#98A2B3] shrink-0" x-text="c._lastTime"></div>
                  </div>
                  <div class="text-[10px] text-[#667085] truncate" x-text="c.last_body || c.role"></div>
                </div>
                <span x-show="c.unread>0" class="text-[8px] font-extrabold w-3.5 h-3.5 flex items-center justify-center rounded-full bg-[#1268F3] text-white shrink-0" x-text="c.unread"></span>
              </div>
            </template>
          </div>
        </div>
        <!-- Thread -->
        <div class="msg-thread-pane flex-1 flex flex-col min-w-0" :class="{'msg-hide': !activeContact}">
          <template x-if="!activeContact">
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
              <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-message-circle"/></svg>
              <div class="text-[14px] font-bold text-[#344054] mb-1">Select a conversation</div>
              <div class="text-[12px] text-[#667085]">Pick someone from the list to start texting.</div>
            </div>
          </template>
          <template x-if="activeContact">
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
              <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#E4E7EC] shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                  <button @click="activeContact=null" class="md:hidden text-[#667085] hover:text-[#172B3A] transition-colors p-1 -ml-1 shrink-0" title="Back to conversations">
                    <svg class="w-4 h-4"><use href="#icon-chevron-left"/></svg>
                  </button>
                  <div class="relative shrink-0">
                    <div class="w-8 h-8 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[10px] font-extrabold text-[#175CD3]"
                         x-text="activeContact.name.split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase()"></div>
                    <span :style="'position:absolute;top:-3px;right:-3px;width:10px;height:10px;border-radius:999px;border:2px solid #fff;background:'+(isOnline(activeContact.id)?'#25D366':'#D0D5DD')"></span>
                  </div>
                  <div class="min-w-0">
                    <div class="text-[14px] font-bold text-[#172B3A] truncate" x-text="activeContact.name"></div>
                    <div class="text-[11px] flex items-center gap-1.5">
                      <span class="text-[#667085]" x-text="activeContact.role"></span>
                      <span x-show="isOnline(activeContact.id)" class="flex items-center gap-1 font-semibold" style="color:#25D366">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:#25D366"></span> Online
                      </span>
                      <span x-show="!isOnline(activeContact.id)" class="text-[#98A2B3]" x-text="lastSeenLabel(activeContact.id)"></span>
                    </div>
                  </div>
                </div>
                <button @click="refreshThread()" class="text-[#667085] hover:text-[#172B3A] transition-colors p-1" title="Refresh">
                  <svg class="w-3.5 h-3.5"><use href="#icon-refresh-cw"/></svg>
                </button>
              </div>
              <div class="flex-1 overflow-y-auto p-4 flex flex-col space-y-2" x-ref="threadScroll">
                <template x-if="!threadLoading && thread.length===0">
                  <div class="text-[12px] text-[#667085] text-center">No messages yet  say hello.</div>
                </template>
                <template x-for="m in thread" :key="m.id">
                  <div class="flex flex-col" :class="m.sender_id===currentUserId ? 'self-end' : 'self-start'">
                    <div class="max-w-[320px] rounded-[12px] px-3.5 py-2.5 text-[12px] space-y-1.5"
                         :class="m.sender_id===currentUserId ? 'bg-[#1268F3] text-white' : 'bg-[#F2F4F7] text-[#172B3A]'">
                      <a x-show="m.attachment" :href="'api/serve.php?msg_id='+m.id+'&file='+encodeURIComponent(m.attachment)" target="_blank"
                         class="flex items-center gap-1.5 rounded-[8px] px-2 py-1.5 transition-colors"
                         :class="m.sender_id===currentUserId ? 'bg-white/15 hover:bg-white/25' : 'bg-white hover:bg-[#EEF4FF] border border-[#E4E7EC]'">
                        <svg class="w-3 h-3 shrink-0"><use href="#icon-paperclip"/></svg>
                        <span class="truncate underline decoration-dotted" x-text="m.attachment"></span>
                      </a>
                      <div x-show="m.body" x-text="m.body"></div>
                    </div>
                    <div class="text-[10px] text-[#B8CCED] mt-1" x-text="m._time"></div>
                  </div>
                </template>
              </div>
              <div class="border-t border-[#E4E7EC] p-3 flex items-center gap-2 shrink-0">
                <input type="file" x-ref="chatFile" class="sr-only"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.csv,.txt"
                       @change="sendChatAttachment($event)">
                <button @click="$refs.chatFile.click()" :disabled="_sendingAttachment"
                        class="text-[#667085] hover:text-[#1268F3] transition-colors p-2 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed" title="Attach a file">
                  <svg class="w-4 h-4" x-show="!_sendingAttachment"><use href="#icon-paperclip"/></svg>
                  <svg x-show="_sendingAttachment" class="w-4 h-4 animate-spin"><use href="#icon-refresh-cw"/></svg>
                </button>
                <input type="text" x-model="messageText" @keydown.enter="sendMessage()" placeholder="Type a message…"
                       class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
                <button @click="sendMessage()" :disabled="!messageText.trim()"
                        class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                  <svg class="w-3 h-3"><use href="#icon-send"/></svg> Send
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div><!-- /messages -->

    <!-- ── CALENDAR VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view==='calendar'" x-cloak class="space-y-4">

      <!-- Month grid -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4 sm:p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="cal-month-bar">
          <div class="cal-month-bar-left">
            <div class="flex items-center gap-2.5 flex-wrap">
              <h2 class="text-[18px] font-bold text-[#071D2B] leading-none m-0" style="font-size:20px" x-text="calMonthLabel"></h2>
              <span class="inline-flex items-center rounded-full font-bold px-2 py-0.5 text-[10px] bg-[#EEF4FF] text-[#175CD3] tabular-nums"
                    x-text="calMonthTotal+(calMonthTotal===1?' inquiry':' inquiries')"></span>
            </div>
            <div class="cal-legend" style="margin-top:10px">
              <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#16803C"></span>Created</span>
              <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#B54708"></span>Follow-ups</span>
              <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#7A5AF8"></span>Sunday / leave</span>
              <span class="cal-legend-item"><span class="cal-legend-dot" style="background:#F79009"></span>Festival</span>
            </div>
          </div>
          <div class="cal-month-bar-right">
            <?php if (is_admin()): ?>
            <div class="relative">
              <select x-model="calUser" class="text-[12px] font-semibold text-[#344054] border border-[#E4E7EC] rounded-[8px] pl-3 pr-7 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer"
                      :class="calUser && 'border-[#1268F3] text-[#175CD3]'" style="height:34px;min-width:140px">
                <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
                  <option :value="a.name" x-text="a.name===currentUser ? a.name+' (me)' : a.name"></option>
                </template>
              </select>
              <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
            </div>
            <?php endif; ?>
            <button type="button" @click="calMonth = todayStr.slice(0,7); calDate=''"
                    class="text-[11px] font-bold px-3 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors" style="height:34px">Today</button>
            <div class="flex items-center gap-0.5 rounded-[9px] bg-[#F2F4F7] p-0.5" style="height:34px">
              <button type="button" @click="calShift(-1)" title="Previous month" class="w-8 h-full flex items-center justify-center rounded-[7px] text-[#344054] hover:bg-white transition-colors"><svg class="w-3 h-3"><use href="#icon-chevron-left"/></svg></button>
              <button type="button" @click="calShift(1)" title="Next month" class="w-8 h-full flex items-center justify-center rounded-[7px] text-[#344054] hover:bg-white transition-colors"><svg class="w-3 h-3"><use href="#icon-chevron-right"/></svg></button>
            </div>
          </div>
        </div>

        <div class="cal-weekdays">
          <template x-for="(d, di) in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="'h'+di">
            <div class="cal-head" :class="(di===0||di===6) && 'is-weekend'" x-text="d"></div>
          </template>
        </div>
        <div class="cal-grid">
          <template x-for="(c, ci) in calCells" :key="'c'+ci">
            <div class="cal-slot">
              <template x-if="c">
                <button type="button" @click="calDate = (calDate===c.key ? '' : c.key)"
                        :title="calCellTitle(c)"
                        class="cal-cell"
                        :class="{
                          'has-inq': c.count>0,
                          'is-sel': calDate===c.key,
                          'is-today': c.key===todayStr,
                          'is-weekend': c.isSat,
                          'is-sunday': c.isSun && !c.holiday,
                          'is-festival': c.holiday && c.holiday.holiday_type==='festival',
                          'is-holiday': c.holiday && c.holiday.holiday_type!=='festival'
                        }"
                        :style="c.holiday && c.holiday.color ? {borderColor: c.holiday.color+'66'} : {}">
                  <div class="cal-cell-top">
                    <span class="cal-num" x-text="c.day"></span>
                  </div>
                  <span class="cal-hol-label" x-show="c.isSun || c.holiday"
                        x-text="c.holiday ? c.holiday.title : (c.isSun ? 'Sunday' : '')"></span>
                  <span class="cal-marks">
                    <span x-show="c.count" class="cal-pill" x-text="c.count"></span>
                    <span x-show="c.fu" class="cal-pill is-fu"><svg><use href="#icon-bell"/></svg><span x-text="c.fu"></span></span>
                  </span>
                </button>
              </template>
            </div>
          </template>
        </div>
      </div>

      <!-- Admin: yearly leave (inline, no popup) -->
      <?php if (is_admin()): ?>
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4 sm:p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="cal-sec-head mb-0">
          <div class="cal-sec-title">
            <svg class="w-4 h-4 text-[#7A5AF8] shrink-0"><use href="#icon-calendar"/></svg>
            <div class="min-w-0">
              <h3>Yearly leave & festivals</h3>
              <p class="text-[11px] text-[#667085] m-0 mt-0.5 leading-snug">Sundays are automatic. Add festivals or company leave by year.</p>
            </div>
          </div>
          <button type="button" @click="holToggleManage()"
                  class="text-[11px] font-bold px-3 rounded-[8px] transition-colors shrink-0" style="height:34px"
                  :class="holManageOpen ? 'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]' : 'bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white'"
                  x-text="holManageOpen ? 'Done' : 'Manage'"></button>
        </div>

        <div x-show="holManageOpen" x-cloak class="mt-4 pt-4 border-t border-[#E4E7EC] space-y-4">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-bold text-[#667085] w-9">Year</span>
              <div class="flex items-center gap-0.5 rounded-[9px] bg-[#F2F4F7] p-0.5" style="height:34px">
                <button type="button" @click="holShiftYear(-1)" class="w-8 h-full flex items-center justify-center rounded-[7px] text-[#344054] hover:bg-white transition-colors"><svg class="w-3 h-3"><use href="#icon-chevron-left"/></svg></button>
                <span class="text-[13px] font-bold text-[#172B3A] tabular-nums px-2 min-w-[3.25rem] text-center" x-text="holManageYear"></span>
                <button type="button" @click="holShiftYear(1)" class="w-8 h-full flex items-center justify-center rounded-[7px] text-[#344054] hover:bg-white transition-colors"><svg class="w-3 h-3"><use href="#icon-chevron-right"/></svg></button>
              </div>
              <button type="button" x-show="holManageYear !== (new Date().getFullYear())" @click="holSetYear(new Date().getFullYear())"
                      class="text-[10px] font-bold px-2.5 rounded-[7px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors" style="height:28px">This year</button>
            </div>
            <button type="button" @click="holResetForm()" x-show="!holFormOpen"
                    class="text-[11px] font-bold px-3 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0B4FC7] transition-colors" style="height:34px">
              + Add for <span x-text="holManageYear"></span>
            </button>
          </div>

          <!-- Inline add/edit form -->
          <div x-show="holFormOpen" class="rounded-[10px] border border-[#E4E7EC] bg-[#F9FAFB] p-3 sm:p-4">
            <div class="text-[12px] font-bold text-[#344054] mb-3" x-text="holForm.id ? 'Update holiday' : ('New holiday · ' + holManageYear)"></div>
            <div class="cal-hol-form">
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Date</label>
                <input type="date" x-model="holForm.holiday_date" :min="holManageYear+'-01-01'" :max="holManageYear+'-12-31'"
                       class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" style="height:38px">
              </div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Title</label>
                <input type="text" x-model="holForm.title" maxlength="150" placeholder="e.g. Diwali / Company leave"
                       class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" style="height:38px">
              </div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Type</label>
                <select x-model="holForm.holiday_type" @change="holForm.color = holDefaults[holForm.holiday_type] || holForm.color"
                        class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" style="height:38px">
                  <option value="leave">Yearly leave</option>
                  <option value="festival">Festival</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="flex items-end gap-2">
                <div class="flex-1 min-w-0">
                  <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Color</label>
                  <div class="flex items-center gap-2">
                    <input type="color" x-model="holForm.color" class="h-[38px] w-10 border border-[#E4E7EC] rounded-[8px] p-0.5 bg-white cursor-pointer shrink-0">
                    <input type="text" x-model="holForm.color" maxlength="7"
                           class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-2 py-2 bg-white font-mono focus:outline-none focus:border-[#1268F3]" style="height:38px">
                  </div>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-[#E4E7EC]">
              <button type="button" @click="holSave()" :disabled="holSaving"
                      class="text-[11px] font-bold px-4 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0B4FC7] disabled:opacity-50 transition-colors" style="height:34px"
                      x-text="holSaving ? 'Saving…' : (holForm.id ? 'Update' : 'Save')"></button>
              <button type="button" @click="holFormOpen=false; holErr=''"
                      class="text-[11px] font-bold px-4 rounded-[8px] bg-white border border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7] transition-colors" style="height:34px">Cancel</button>
              <span class="text-[11px] text-[#B42318]" x-show="holErr" x-text="holErr"></span>
            </div>
          </div>

          <div x-show="!holManageList.length" class="text-[12px] text-[#667085] py-1">No leave or festivals for <span class="font-bold text-[#344054]" x-text="holManageYear"></span> yet.</div>
          <div class="space-y-1.5" x-show="holManageList.length">
            <template x-for="h in holManageList" :key="h.id">
              <div class="cal-row" style="grid-template-columns:minmax(0,1fr) auto">
                <div class="cal-row-main">
                  <div class="cal-row-meta">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:'+(h.color||'#7A5AF8')"></span>
                    <span class="text-[12px] font-bold text-[#172B3A] tabular-nums" x-text="h.holiday_date"></span>
                    <span class="text-[12px] font-semibold text-[#344054]" x-text="h.title"></span>
                    <span class="text-[9px] font-extrabold uppercase tracking-wide px-1.5 py-0.5 rounded-full"
                          :class="h.holiday_type==='festival' ? 'bg-[#FFFAEB] text-[#B54708]' : 'bg-[#F4F3FF] text-[#5925DC]'"
                          x-text="h.holiday_type==='festival' ? 'Festival' : (h.holiday_type==='leave' ? 'Leave' : 'Other')"></span>
                  </div>
                </div>
                <div class="cal-row-actions">
                  <button type="button" @click="holEdit(h)" class="text-[11px] font-bold px-2.5 py-1.5 rounded-[7px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]">Edit</button>
                  <button type="button" @click="holDelete(h)" class="text-[11px] font-bold px-2.5 py-1.5 rounded-[7px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#FEE4E2]">Delete</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Inquiries for selected day / month -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4 sm:p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="cal-sec-head">
          <div class="cal-sec-title">
            <svg class="w-4 h-4 text-[#16803C] shrink-0"><use href="#icon-calendar"/></svg>
            <h3 x-text="calDate ? 'Inquiries · '+calDateLabel : 'Inquiries · '+calMonthLabel"></h3>
            <span class="cal-sec-count" x-show="calInquiries.length" x-text="'('+calInquiries.length+')'"></span>
          </div>
          <button type="button" x-show="calDate" @click="calDate=''"
                  class="text-[11px] font-bold px-2.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors shrink-0" style="height:30px">Whole month</button>
        </div>

        <div x-show="calInquiries.length===0" class="flex flex-col items-center justify-center border border-dashed border-[#E4E7EC] rounded-[10px] text-center py-10 px-6">
          <svg class="w-7 h-7 text-[#D1D9E6] mb-2"><use href="#icon-calendar"/></svg>
          <div class="text-[13px] font-bold text-[#344054]">No inquiries created</div>
          <div class="text-[12px] text-[#667085] mt-1" x-text="calDate ? 'Nothing on this date.' : 'Nothing this month.'"></div>
        </div>

        <div class="space-y-2">
          <template x-for="inq in calInquiries" :key="inq.id">
            <div class="cal-row cursor-pointer" @click="goToInquiry(inq)">
              <div class="cal-row-main">
                <div class="cal-row-meta">
                  <span class="text-[11px] font-extrabold text-[#1268F3]" x-text="inq.id"></span>
                  <span class="text-[11px] font-semibold text-[#667085] tabular-nums" x-text="calCreatedLabel(inq)"></span>
                  <span class="text-[10px] font-bold text-[#344054] bg-[#F2F4F7] rounded-full px-2 py-0.5" x-text="inq.created_by || '—'"></span>
                  <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-0.5 text-[9px]" :class="stageClass(inq.stage)" x-text="stageLabel(inq.stage)"></span>
                </div>
                <div class="flex items-baseline gap-2 min-w-0">
                  <span class="text-[13px] font-bold text-[#172B3A] truncate" x-text="inq.client"></span>
                  <span class="text-[11px] text-[#98A2B3] truncate" x-text="inq.company"></span>
                </div>
                <div class="text-[11px] text-[#667085] truncate" x-text="stripHtml(inq.requirement)"></div>
              </div>
              <div class="cal-row-actions" @click.stop>
                <span class="text-[11px] tabular-nums" :class="isOverdue(inq)?'text-[#B42318] font-bold':'text-[#667085]'" x-text="'Due '+dueLabel(inq)"></span>
                <button type="button" @click="openSummary(inq)" class="text-[11px] font-bold px-2.5 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Summary</button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Follow-ups for selected day / month -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4 sm:p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="cal-sec-head">
          <div class="cal-sec-title">
            <svg class="w-4 h-4 text-[#B54708] shrink-0"><use href="#icon-bell"/></svg>
            <h3 x-text="calDate ? 'Follow-ups · '+calDateLabel : 'Follow-ups · '+calMonthLabel"></h3>
            <span class="cal-sec-count" x-show="calFollowUps.length" x-text="'('+calFollowUps.length+')'"></span>
          </div>
          <button type="button" x-show="calDate" @click="calDate=''"
                  class="text-[11px] font-bold px-2.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors shrink-0" style="height:30px">Whole month</button>
        </div>

        <div x-show="calFollowUps.length===0" class="flex flex-col items-center justify-center border border-dashed border-[#E4E7EC] rounded-[10px] text-center py-8 px-6">
          <svg class="w-6 h-6 text-[#D1D9E6] mb-2"><use href="#icon-bell"/></svg>
          <div class="text-[12px] text-[#667085]" x-text="calDate ? 'No follow-ups on this date.' : 'No follow-ups this month.'"></div>
        </div>

        <div class="space-y-2">
          <template x-for="fu in calFollowUps" :key="fu.id">
            <div class="cal-row">
              <div class="cal-row-main">
                <div class="cal-row-meta">
                  <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full whitespace-nowrap"
                        :class="fuStatus(fu)==='overdue'?'bg-[#FEF3F2] text-[#B42318]':fuStatus(fu)==='today'?'bg-[#EEF4FF] text-[#175CD3]':fuStatus(fu)==='upcoming'?'bg-[#F2F4F7] text-[#344054]':'bg-[#ECFDF3] text-[#16803C]'"
                        x-text="fuStatus(fu)==='overdue'?'Overdue':fuStatus(fu)==='today'?'Today':fuStatus(fu)==='upcoming'?'Upcoming':'Done'"></span>
                  <span class="text-[11px] font-semibold text-[#667085] tabular-nums"
                        x-text="(calDate ? '' : fu.follow_up_date+' · ') + (fu.follow_up_time ? fuTimeLabel(fu.follow_up_time) : 'All day')"></span>
                  <button type="button" @click="openFuInquiry(fu)" class="text-[11px] font-extrabold text-[#1268F3] hover:underline" x-text="fu.inquiry_id"></button>
                  <span class="text-[10px] font-semibold text-[#98A2B3]" x-text="fu.assigned_to"></span>
                </div>
                <div class="flex items-baseline gap-2 min-w-0">
                  <span class="text-[13px] font-bold text-[#172B3A] truncate" x-text="fu.client||''"></span>
                  <span class="text-[11px] text-[#667085] truncate" :class="fu.completed && 'line-through text-[#B8CCED]'" x-text="fu.note"></span>
                </div>
              </div>
              <div class="cal-row-actions">
                <template x-if="!fu.completed && (fu.assigned_to===currentUser || fu.created_by===currentUser)">
                  <button type="button" @click="rmComplete(fu)"
                          class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">
                    <svg class="w-3 h-3"><use href="#icon-check-circle"/></svg> Done
                  </button>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div><!-- /calendar -->

    <!-- ── ANNOUNCEMENTS VIEW ─────────────────────────────────────────────── -->
    <div x-show="view==='announcements'" x-cloak class="space-y-4">

      <!-- Composer Admin / Master Admin only -->
      <div x-show="isAdmin" class="bg-white rounded-[12px] border border-[#E4E7EC] p-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <textarea x-model="announcementText" rows="3" placeholder="Share an update with the whole team…"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
        <div class="flex justify-end mt-2">
          <button @click="postAnnouncement()" :disabled="!announcementText.trim() || _postingAnnouncement"
                  class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
            <svg class="w-3 h-3"><use href="#icon-megaphone"/></svg>
            <span x-text="_postingAnnouncement ? 'Posting…' : 'Post Announcement'"></span>
          </button>
        </div>
      </div>

      <!-- Feed -->
      <template x-if="announcements.length===0">
        <div class="bg-white rounded-[12px] border border-[#E4E7EC] flex flex-col items-center justify-center p-8 text-center" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-megaphone"/></svg>
          <div class="text-[14px] font-bold text-[#344054] mb-1">No announcements yet</div>
          <div class="text-[12px] text-[#667085]">Team updates will show up here.</div>
        </div>
      </template>

      <div class="space-y-3.5">
        <template x-for="a in announcements" :key="a.id">
          <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
            <div class="flex items-center gap-2.5 mb-2">
              <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[9px] font-extrabold text-[#175CD3] shrink-0" x-text="a._initials"></div>
              <div class="flex-1 min-w-0">
                <div class="text-[12px] font-bold text-[#172B3A]" x-text="a.created_by"></div>
                <div class="text-[10px] text-[#98A2B3]" x-text="a._time"></div>
              </div>
              <div x-show="isAdmin" class="flex items-center gap-1 shrink-0">
                <button type="button" @click="startEditAnnouncement(a)" title="Edit"
                        class="w-7 h-7 flex items-center justify-center rounded-[6px] text-[#667085] hover:bg-[#F2F4F7] hover:text-[#1268F3] transition-colors">
                  <svg class="w-3.5 h-3.5"><use href="#icon-edit-2"/></svg>
                </button>
                <button type="button" @click="deleteAnnouncement(a)" title="Delete"
                        class="w-7 h-7 flex items-center justify-center rounded-[6px] text-[#667085] hover:bg-[#FEF3F2] hover:text-[#B42318] transition-colors">
                  <svg class="w-3.5 h-3.5"><use href="#icon-trash-2"/></svg>
                </button>
              </div>
            </div>
            <template x-if="annEditId!==a.id">
              <div class="text-[13px] text-[#344054] leading-[1.55]" style="white-space:pre-line" x-text="a.body"></div>
            </template>
            <template x-if="annEditId===a.id">
              <div>
                <textarea x-model="annEditText" rows="3"
                          class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
                <div class="flex justify-end gap-2 mt-2">
                  <button type="button" @click="annEditId=null;annEditText=''"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]">Cancel</button>
                  <button type="button" @click="saveAnnouncement()" :disabled="!annEditText.trim() || _savingAnnouncement"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] disabled:opacity-50"
                          x-text="_savingAnnouncement ? 'Saving…' : 'Save'"></button>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>

    </div><!-- /announcements -->

    <!-- ── RESOURCES (shared docs — admin uploads, everyone downloads) ────── -->
    <div x-show="view==='resources'" x-cloak class="space-y-4 min-w-0">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3 min-w-0">
        <div class="flex items-center gap-2.5 min-w-0">
          <svg class="w-4 h-4 text-[#1268F3] shrink-0"><use href="#icon-folder"/></svg>
          <span class="text-[14px] font-bold text-[#172B3A]">Resources</span>
          <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums shrink-0"
                x-show="resources.length" x-text="filteredResources.length+(resSearch.trim()?' of '+resources.length:'')+' file'+(filteredResources.length===1?'':'s')"></span>
        </div>
        <div class="sp-note-search flex items-center gap-2 border border-[#E4E7EC] rounded-[8px] px-2.5 bg-white" style="height:36px;box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <svg class="w-3.5 h-3.5 text-[#98A2B3] shrink-0"><use href="#icon-search"/></svg>
          <input type="search" x-model="resSearch" placeholder="Search resources…"
                 class="flex-1 min-w-0 text-[12px] bg-transparent focus:outline-none text-[#344054] placeholder:text-[#667085]">
        </div>
      </div>

      <!-- Uploader — Admin / Master Admin only -->
      <div x-show="isAdmin" class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-3.5 border-b border-[#E4E7EC] text-[14px] font-bold text-[#172B3A]">Add a resource</div>
        <div class="p-5 space-y-3.5">
          <input type="text" x-model="resTitle" maxlength="200" placeholder="Title — e.g. Site Survey Checklist (optional)"
                 class="w-full text-[14px] font-bold border border-[#E4E7EC] rounded-[8px] px-3 py-2.5 focus:outline-none focus:border-[#1268F3] bg-white">
          <input type="file" multiple x-ref="resFiles" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.csv,.txt"
                 class="w-full text-[12px] text-[#344054] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white file:mr-3 file:text-[11px] file:font-bold file:border-0 file:rounded-[6px] file:bg-[#EEF4FF] file:text-[#175CD3] file:px-2.5 file:py-1 file:cursor-pointer cursor-pointer" />
          <div class="text-[10px] text-[#B8CCED]">PDF, Word, Excel, PowerPoint, images, ZIP, CSV, TXT — max 10 MB each. Title applies when one file is chosen.</div>
          <div class="flex justify-end">
            <button type="button" @click="uploadResources()" :disabled="_resUploading"
                    class="text-[11px] font-bold px-3.5 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
              <svg class="w-3 h-3"><use href="#icon-plus"/></svg>
              <span x-text="_resUploading ? 'Uploading…' : 'Upload'"></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <template x-if="filteredResources.length===0">
        <div class="bg-white rounded-[12px] border border-dashed border-[#D1D9E6] flex flex-col items-center justify-center p-8 text-center">
          <div class="w-9 h-9 rounded-full bg-[#EEF4FF] flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[#1268F3]"><use href="#icon-folder"/></svg>
          </div>
          <div class="text-[14px] font-bold text-[#344054] mb-1" x-text="resSearch.trim() ? 'No matching resources' : 'No resources yet'"></div>
          <div class="text-[12px] text-[#667085] leading-relaxed"
               x-text="resSearch.trim() ? 'Try a different search.' : (isAdmin ? 'Upload a document above to share it with the team.' : 'Shared documents will show up here.')"></div>
        </div>
      </template>

      <!-- List -->
      <div class="space-y-3">
        <template x-for="r in filteredResources" :key="r.id">
          <div class="bg-white rounded-[12px] border border-[#E4E7EC] p-4 flex flex-wrap items-center gap-3" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
            <div class="w-9 h-9 rounded-[8px] bg-[#EEF4FF] flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-[#175CD3]"><use href="#icon-file-text"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <template x-if="resEditId!==r.id">
                <div>
                  <div class="text-[13px] font-bold text-[#172B3A] truncate" x-text="r.title"></div>
                  <div class="text-[10px] text-[#98A2B3] truncate"
                       x-text="r.filename+' · '+resSize(r.size)+' · '+r.uploaded_by+' · '+r._time"></div>
                </div>
              </template>
              <template x-if="resEditId===r.id">
                <div class="flex items-center gap-2">
                  <input type="text" x-model="resEditTitle" maxlength="200"
                         class="flex-1 min-w-0 text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-1.5 focus:outline-none focus:border-[#1268F3] bg-white">
                  <button type="button" @click="saveResourceTitle()" :disabled="!resEditTitle.trim()"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] disabled:opacity-50">Save</button>
                  <button type="button" @click="resEditId=null;resEditTitle=''"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]">Cancel</button>
                </div>
              </template>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <a x-show="resCanView(r)" :href="'api/serve.php?resource_id='+r.id+'&inline=1'" target="_blank" rel="noopener"
                 class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors flex items-center gap-1.5">
                <svg class="w-3 h-3"><use href="#icon-eye"/></svg> View
              </a>
              <a :href="'api/serve.php?resource_id='+r.id"
                 class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors flex items-center gap-1.5">
                <svg class="w-3 h-3"><use href="#icon-download"/></svg> Download
              </a>
              <button x-show="isAdmin" type="button" @click="resEditId=r.id;resEditTitle=r.title" title="Rename"
                      class="w-7 h-7 flex items-center justify-center rounded-[6px] text-[#667085] hover:bg-[#F2F4F7] hover:text-[#1268F3] transition-colors">
                <svg class="w-3.5 h-3.5"><use href="#icon-edit-2"/></svg>
              </button>
              <button x-show="isAdmin" type="button" @click="deleteResource(r)" title="Delete"
                      class="w-7 h-7 flex items-center justify-center rounded-[6px] text-[#667085] hover:bg-[#FEF3F2] hover:text-[#B42318] transition-colors">
                <svg class="w-3.5 h-3.5"><use href="#icon-trash-2"/></svg>
              </button>
            </div>
          </div>
        </template>
      </div>

    </div><!-- /resources -->

    <!-- ── CALL NOTES (private per user) ──────────────────────────────────── -->
    <div x-show="view==='notes'" x-cloak class="space-y-4 min-w-0">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3 min-w-0">
        <div class="flex items-center gap-2.5 min-w-0">
          <svg class="w-4 h-4 text-[#1268F3] shrink-0"><use href="#icon-sticky-note"/></svg>
          <span class="text-[14px] font-bold text-[#172B3A]">Notes</span>
          <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums shrink-0"
                x-show="callNotes.length" x-text="filteredNotes.length+(noteSearch.trim()?' of '+callNotes.length:'')+' note'+(filteredNotes.length===1?'':'s')"></span>
        </div>
        <div class="sp-note-search flex items-center gap-2 border border-[#E4E7EC] rounded-[8px] px-2.5 bg-white" style="height:36px;box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <svg class="w-3.5 h-3.5 text-[#98A2B3] shrink-0"><use href="#icon-search"/></svg>
          <input type="search" x-model="noteSearch" placeholder="Search notes…"
                 class="flex-1 min-w-0 text-[12px] bg-transparent outline-none text-[#344054] placeholder-[#98A2B3]">
        </div>
      </div>

      <!-- Composer -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)" x-ref="noteComposer">
        <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex items-center justify-between gap-3">
          <div class="text-[14px] font-bold text-[#172B3A]">New note</div>
          <span class="text-[11px] text-[#98A2B3] tabular-nums shrink-0"
                x-show="(noteForm.body||'').length > 8000"
                x-text="(noteForm.body||'').length+' / 10000'"></span>
        </div>
        <div class="p-5 space-y-3.5">
          <input type="text" x-model="noteForm.title" maxlength="200" placeholder="Title — e.g. Follow-up with Acme"
                 class="w-full text-[14px] font-bold border border-[#E4E7EC] rounded-[8px] px-3 py-2.5 focus:outline-none focus:border-[#1268F3] bg-white">
          <textarea x-model="noteForm.body" rows="3" maxlength="10000"
                    placeholder="Write what you want to remember…"
                    class="block w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2.5 focus:outline-none focus:border-[#1268F3] bg-white resize-y min-h-[72px] leading-relaxed"></textarea>
          <div class="flex justify-end">
            <button type="button" @click="saveNote()" :disabled="_noteSaving || !noteForm.title.trim() || !noteForm.body.trim()"
                    class="text-[11px] font-bold px-3.5 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
              <svg class="w-3 h-3"><use href="#icon-plus"/></svg>
              <span x-text="_noteSaving ? 'Saving…' : 'Save note'"></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <template x-if="filteredNotes.length===0">
        <div class="bg-white rounded-[12px] border border-dashed border-[#D1D9E6] flex flex-col items-center justify-center py-14 px-6 text-center">
          <div class="w-11 h-11 rounded-full bg-[#EEF4FF] flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[#1268F3]"><use href="#icon-sticky-note"/></svg>
          </div>
          <div class="text-[14px] font-bold text-[#344054] mb-1" x-text="noteSearch.trim() ? 'No matching notes' : 'No notes yet'"></div>
          <div class="text-[12px] text-[#667085] max-w-xs leading-relaxed"
               x-text="noteSearch.trim() ? 'Try a different search.' : 'Add a title and note above to get started.'"></div>
        </div>
      </template>

      <!-- Notes list -->
      <div class="sp-notes-grid">
        <template x-for="n in filteredNotes" :key="n.id">
          <div class="sp-note-card bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden transition-shadow hover:shadow-md min-w-0"
               style="box-shadow:0 1px 4px rgba(7,29,43,0.04)"
               :class="noteEditId===n.id ? 'sp-note-editing ring-2 ring-[#1268F3] border-[#B2CCFF]' : ''">
            <div class="flex">
              <div class="shrink-0" style="width:4px;background:#1268F3;opacity:.85"></div>
              <div class="flex-1 min-w-0 px-5 py-4 flex flex-col">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <template x-if="noteEditId!==n.id">
                      <div>
                        <div class="sp-note-title text-[14px] font-bold text-[#172B3A] leading-snug line-clamp-2 cursor-pointer" :title="n.title"
                             @click="noteViewFor=n" x-text="n.title"></div>
                        <div class="text-[11px] text-[#98A2B3] mt-0.5 tabular-nums" x-text="n._time"></div>
                      </div>
                    </template>
                    <template x-if="noteEditId===n.id">
                      <input type="text" x-model="noteEditTitle" maxlength="200"
                             class="w-full text-[14px] font-bold border border-[#E4E7EC] rounded-[8px] px-3 py-2.5 focus:outline-none focus:border-[#1268F3] bg-[#F9FAFB]">
                    </template>
                  </div>
                  <div class="flex items-center gap-1 shrink-0" x-show="noteEditId!==n.id">
                    <button type="button" @click="startEditNote(n)" title="Edit note"
                            class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[#667085] hover:bg-[#EEF4FF] hover:text-[#1268F3] transition-colors">
                      <svg class="w-3.5 h-3.5"><use href="#icon-edit-2"/></svg>
                    </button>
                    <button type="button" @click="deleteNote(n)" title="Delete note"
                            class="w-8 h-8 flex items-center justify-center rounded-[8px] text-[#667085] hover:bg-[#FEF3F2] hover:text-[#B42318] transition-colors">
                      <svg class="w-3.5 h-3.5"><use href="#icon-trash-2"/></svg>
                    </button>
                  </div>
                </div>

                <div class="mt-3 pt-3 border-t border-[#F2F4F7]" style="flex:1;min-height:0">
                  <template x-if="noteEditId!==n.id">
                    <div class="sp-note-body text-[13px] text-[#344054] leading-relaxed" x-text="n.body"></div>
                  </template>
                  <template x-if="noteEditId===n.id">
                    <div class="space-y-2">
                      <textarea x-model="noteEditBody" rows="5" maxlength="10000"
                                class="block w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2.5 focus:outline-none focus:border-[#1268F3] bg-[#F9FAFB] resize-y leading-relaxed"></textarea>
                      <div class="flex justify-end gap-2">
                        <button type="button" @click="cancelEditNote()"
                                class="text-[11px] font-bold px-3.5 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]">Cancel</button>
                        <button type="button" @click="updateNote()" :disabled="_noteSaving || !noteEditTitle.trim() || !noteEditBody.trim()"
                                class="text-[11px] font-bold px-3.5 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] disabled:opacity-50"
                                x-text="_noteSaving ? 'Saving…' : 'Save changes'"></button>
                      </div>
                    </div>
                  </template>
                </div>

                <div class="pt-2 shrink-0" x-show="noteEditId!==n.id && noteIsLong(n)">
                  <button type="button" @click="noteViewFor=n"
                          class="text-[11px] font-bold text-[#1268F3] hover:underline">View details →</button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

    </div><!-- /notes -->

    <!-- ── LEAVE VIEW ─────────────────────────────────────────────────────── -->
    <div x-show="view==='leave'" x-cloak class="space-y-4">

      <div x-show="!isAdmin" class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC]">
          <div class="text-[14px] font-bold text-[#172B3A]">Request Leave</div>
          <div class="text-[12px] text-[#667085] mt-0.5">Admin will confirm the request before it is counted as leave.</div>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Leave type <span class="text-[#B42318]">*</span></label>
            <select x-model="leaveForm.leaveType"
                    class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
              <template x-for="t in leaveRequestTypes" :key="t">
                <option :value="t" x-text="t"></option>
              </template>
            </select>
            <p class="text-[11px] text-[#667085] mt-1.5" x-text="leaveNoticeHint"></p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">From <span class="text-[#B42318]">*</span></label>
              <input x-ref="leaveStart" x-model="leaveForm.startDate" type="date" :min="new Date().toLocaleDateString('en-CA')"
                     @change="onLeaveStartChange()"
                     class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
            </div>
            <div>
              <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">To <span class="text-[#B42318]">*</span></label>
              <input x-model="leaveForm.endDate" type="date" :min="leaveForm.startDate || new Date().toLocaleDateString('en-CA')"
                     class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
            </div>
          </div>
          <div class="text-[12px] font-semibold text-[#175CD3]"
               x-text="leaveDays ? leaveDays+' calendar day'+(leaveDays===1?'':'s') : 'Select dates to see duration'"></div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Reason <span class="text-[#B42318]">*</span></label>
            <textarea x-model="leaveForm.reason" rows="3" placeholder="e.g. Family function, medical appointment…"
                      class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
          </div>
          <p x-show="leaveErr" class="text-[12px] font-semibold text-[#B42318]" x-text="leaveErr"></p>
          <div class="flex justify-end">
            <button type="button" @click="submitLeave()" :disabled="_leaveSaving || !leaveForm.startDate || !leaveForm.endDate || !leaveForm.reason.trim()"
                    class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    x-text="_leaveSaving ? 'Submitting…' : 'Submit request'"></button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-[#1268F3] shrink-0"><use href="#icon-calendar"/></svg>
            <span class="text-[14px] font-bold text-[#172B3A]" x-text="isAdmin ? 'Team requests' : 'My requests'"></span>
            <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums" x-text="filteredLeaves.length+' shown'"></span>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <select x-show="isAdmin" x-model="leaveUserFilter"
                    class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
              <option value="">All users</option>
              <template x-for="n in leaveUserOptions" :key="n">
                <option :value="n" x-text="n"></option>
              </template>
            </select>
            <select x-model="leaveTypeFilter"
                    class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
              <option value="">All types</option>
              <template x-for="t in leaveTypes" :key="t">
                <option :value="t" x-text="t"></option>
              </template>
            </select>
            <select x-model="leaveFilter"
                    class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
              <option value="all">All</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
            <input x-show="isAdmin" type="month" x-model="leaveMonth"
                   class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054]">
            <button x-show="isAdmin" type="button" @click="exportLeaveCSV()"
                    class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap">
              <svg class="w-3.5 h-3.5"><use href="#icon-download"/></svg> Download CSV
            </button>
          </div>
        </div>

        <div class="sp-hscroll overflow-x-auto" x-show="filteredLeaves.length>0">
          <table class="w-full border-collapse" style="min-width:850px">
            <thead>
              <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                <th x-show="isAdmin" class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:140px">Member</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:130px">Type</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:180px">Dates</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:150px">Applied On</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:70px">Days</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Reason</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:110px">Status</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="row in filteredLeaves" :key="row.id">
                <tr class="group transition-colors hover:bg-[#F9FAFB]" style="border-bottom:1px solid #F2F4F7"
                    :style="row.status==='pending'?(darkMode?'background:#241F14':'background:#FFFCF5'):''">
                  <td x-show="isAdmin" class="px-5 py-3.5">
                    <div class="text-[12px] font-semibold text-[#172B3A]" x-text="row.name"></div>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="text-[12px] font-semibold text-[#344054]" x-text="row.leave_type || 'Casual Leave'"></div>
                  </td>
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="text-[12px] font-semibold text-[#344054]" x-text="leaveDateLabel(row)"></div>
                  </td>
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="text-[12px] text-[#344054]" x-text="leaveSubmittedLabel(row.created_at) || '—'"></div>
                  </td>
                  <td class="px-5 py-3.5">
                    <span class="text-[12px] font-bold text-[#172B3A] tabular-nums" x-text="leaveDaysLabel(row.days)"></span>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="text-[12px] text-[#344054] leading-snug" x-text="row.reason"></div>
                    <div x-show="row.review_note" class="text-[10px] text-[#667085] mt-1" x-text="'Note: '+row.review_note"></div>
                  </td>
                  <td class="px-5 py-3.5">
                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2.5 py-1 rounded-full whitespace-nowrap"
                          :class="row.status==='approved'?'bg-[#ECFDF3] text-[#16803C]':row.status==='rejected'?'bg-[#FEF3F2] text-[#B42318]':row.status==='pending'?'bg-[#FFFAEB] text-[#B54708]':'bg-[#F2F4F7] text-[#344054]'">
                      <span x-text="row.status==='approved'?'Approved':row.status==='rejected'?'Rejected':row.status==='pending'?'Pending':'Cancelled'"></span>
                    </span>
                    <div x-show="row.reviewed_by" class="text-[10px] text-[#98A2B3] mt-1" x-text="row.reviewed_by"></div>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-1.5">
                      <template x-if="isAdmin && row.status==='pending'">
                        <div class="flex items-center gap-1.5">
                          <button type="button" @click="decideLeave(row,'approved')" class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">Approve</button>
                          <button type="button" @click="openLeaveReject(row)" class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#B42318] hover:text-white transition-colors">Decline</button>
                        </div>
                      </template>
                      <button type="button" x-show="!isAdmin && row.status==='pending' && Number(row.account_id)===currentUserId"
                              @click="cancelLeave(row)"
                              class="inline-flex items-center text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div x-show="filteredLeaves.length===0" class="flex flex-col items-center justify-center p-10 text-center">
          <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-calendar"/></svg>
          <div class="text-[14px] font-bold text-[#344054] mb-1">No leave requests</div>
          <div class="text-[12px] text-[#667085]" x-text="isAdmin ? 'Team requests will show here for approval.' : 'Submit a request and Admin will confirm it before it is counted as leave.'"></div>
        </div>
      </div>
    </div><!-- /leave -->

    <!-- ── WARNINGS VIEW ──────────────────────────────────────────────────── -->
    <div x-show="view==='warnings'" x-cloak class="space-y-4">

      <!-- Page header -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] px-5 py-4"
           style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-[10px] bg-[#FEF3F2] flex items-center justify-center shrink-0">
                <svg class="w-[18px] h-[18px] text-[#B42318]"><use href="#icon-alert-circle"/></svg>
              </div>
              <div>
                <div class="text-[17px] font-bold text-[#172B3A]"
                     x-text="isAdmin ? 'Warnings' : 'My warnings'"></div>
                <div class="text-[12px] text-[#667085] mt-0.5"
                     x-text="warnStrike+' warnings trigger a ₹'+warnAmount+' salary deduction'"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Member: own standing -->
      <div x-show="!isAdmin" class="bg-white rounded-[12px] border border-[#E4E7EC] p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="flex items-center gap-2.5 mb-2">
          <svg class="w-4 h-4 shrink-0" :class="myWarnings>0 ? 'text-[#B42318]' : 'text-[#16803C]'"><use href="#icon-alert-circle"/></svg>
          <span class="text-[14px] font-bold text-[#172B3A]"
                x-text="myWarnings===0 ? 'No warnings on your record' : (myWarnings+' warning'+(myWarnings===1?'':'s')+' on your record')"></span>
        </div>
        <div class="text-[12px] text-[#667085] leading-[1.6]">
          Every <span class="font-bold" x-text="warnStrike"></span> warnings deduct
          <span class="font-bold" x-text="'₹'+warnAmount"></span> from salary.
          <template x-if="myWarnings>0">
            <span x-text="' Deducted so far: ₹'+myDeduction+'.'"></span>
          </template>
          <template x-if="myWarnings>0 && myWarnings % warnStrike !== 0">
            <span x-text="' '+(warnStrike - (myWarnings % warnStrike))+' more until the next ₹'+warnAmount+' deduction.'"></span>
          </template>
        </div>
      </div>

      <!-- Admin: issue a warning -->
      <div x-show="isAdmin" class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC]">
          <div class="text-[14px] font-bold text-[#172B3A]">Issue a warning</div>
          <div class="text-[12px] text-[#667085] mt-0.5">The member is notified straight away. Every <span x-text="warnStrike"></span> warnings deduct <span x-text="'₹'+warnAmount"></span> from their salary.</div>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Member <span class="text-[#B42318]">*</span></label>
            <select x-model="warnForm.accountId"
                    class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
              <option value="">Select a member…</option>
              <template x-for="a in warnableUsers" :key="a.id">
                <option :value="a.id" x-text="a.name+' · '+a.role+(warnCountFor(a.name) ? ' · '+warnCountFor(a.name)+' warning'+(warnCountFor(a.name)===1?'':'s') : '')"></option>
              </template>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Reason <span class="text-[#B42318]">*</span></label>
            <textarea x-model="warnForm.reason" rows="3" placeholder="e.g. Repeated missed deadlines on assigned tasks…"
                      class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
          </div>
          <p x-show="warnErr" class="text-[12px] font-semibold text-[#B42318]" x-text="warnErr"></p>
          <div class="flex justify-end">
            <button type="button" @click="issueWarning()" :disabled="_warnSaving || !warnForm.accountId || !warnForm.reason.trim()"
                    class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#B42318] text-white hover:bg-[#912018] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    x-text="_warnSaving ? 'Saving…' : 'Issue warning'"></button>
          </div>
        </div>
      </div>

      <!-- Admin: per-user standing -->
      <div x-show="isAdmin && warnSummary.length>0" class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex items-center gap-2.5">
          <svg class="w-4 h-4 text-[#B42318] shrink-0"><use href="#icon-alert-circle"/></svg>
          <span class="text-[14px] font-bold text-[#172B3A]">Salary deductions</span>
        </div>
        <div class="sp-hscroll overflow-x-auto">
          <table class="w-full border-collapse" style="min-width:420px">
            <thead>
              <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Member</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:110px">Warnings</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:140px">Deduction</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="s in warnSummary" :key="s.name">
                <tr style="border-bottom:1px solid #F2F4F7">
                  <td class="px-5 py-3.5 text-[12px] font-semibold text-[#172B3A]" x-text="s.name"></td>
                  <td class="px-5 py-3.5">
                    <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-full"
                          :class="s.deduction>0 ? 'bg-[#FEF3F2] text-[#B42318]' : 'bg-[#FFFAEB] text-[#B54708]'"
                          x-text="s.count"></span>
                  </td>
                  <td class="px-5 py-3.5 text-[12px] font-bold tabular-nums"
                      :class="s.deduction>0 ? 'text-[#B42318]' : 'text-[#98A2B3]'" x-text="'₹'+s.deduction"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Warning log -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)" x-data="{warnOpen:true}">
        <div @click="warnOpen=!warnOpen"
             class="px-5 py-3.5 flex items-center justify-between gap-3 flex-wrap cursor-pointer select-none hover:bg-[#F9FAFB] transition-colors"
             :class="warnOpen ? 'border-b border-[#E4E7EC]' : ''">
          <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 text-[#1268F3] shrink-0"><use href="#icon-list"/></svg>
            <span class="text-[14px] font-bold text-[#172B3A]" x-text="isAdmin ? 'Warning ledger' : 'Warning history'"></span>
            <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums" x-text="filteredWarnings.length+' shown'"></span>
          </div>
          <div class="flex items-center gap-2.5">
            <select x-show="isAdmin && warnOpen" x-model="warnUserFilter" @click.stop
                    class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
              <option value="">All members</option>
              <template x-for="s in warnSummary" :key="s.name">
                <option :value="s.name" x-text="s.name"></option>
              </template>
            </select>
            <svg class="w-4 h-4 text-[#98A2B3] shrink-0 transition-transform" :class="warnOpen ? 'rotate-180' : ''"><use href="#icon-chevron-down"/></svg>
          </div>
        </div>

        <div class="sp-hscroll overflow-x-auto" x-show="warnOpen && filteredWarnings.length>0">
          <table class="w-full border-collapse" style="min-width:640px">
            <thead>
              <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                <th x-show="isAdmin" class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:140px">Member</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:120px">Level</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Reason</th>
                <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:150px">Issued by</th>
                <th x-show="isAdmin" class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:110px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template x-for="row in filteredWarnings" :key="row.id">
                <tr class="transition-colors hover:bg-[#F9FAFB]" style="border-bottom:1px solid #F2F4F7">
                  <td x-show="isAdmin" class="px-5 py-3.5 text-[12px] font-semibold text-[#172B3A]" x-text="row.name"></td>
                  <td class="px-5 py-3.5">
                    <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-full whitespace-nowrap"
                          :class="row._nth % warnStrike === 0 ? 'bg-[#FEF3F2] text-[#B42318]' : 'bg-[#FFFAEB] text-[#B54708]'"
                          x-text="warnLevelLabel(row._nth)"></span>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="text-[12px] text-[#344054] leading-snug" x-text="row.reason"></div>
                    <div x-show="row._nth % warnStrike === 0" class="text-[10px] font-bold text-[#B42318] mt-1"
                         x-text="'₹'+warnAmount+' deducted from salary'"></div>
                  </td>
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <div class="text-[12px] font-semibold text-[#344054]" x-text="row.issued_by"></div>
                    <div class="text-[10px] text-[#98A2B3] mt-0.5" x-text="leaveSubmittedLabel(row.created_at)"></div>
                  </td>
                  <td x-show="isAdmin" class="px-5 py-3.5">
                    <button type="button" @click="revokeWarning(row)"
                            class="inline-flex items-center text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Withdraw</button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div x-show="warnOpen && filteredWarnings.length===0" class="flex flex-col items-center justify-center p-10 text-center">
          <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-check-circle"/></svg>
          <div class="text-[14px] font-bold text-[#344054] mb-1">No warnings</div>
          <div class="text-[12px] text-[#667085]" x-text="isAdmin ? 'Warnings you issue will be listed here.' : 'Your record is clear.'"></div>
        </div>
      </div>
    </div><!-- /warnings -->

    <?php include __DIR__.'/reports-view.php'; ?>
    <?php if (($user['role'] ?? '') !== 'Client') include __DIR__.'/teams-view.php'; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ═══ MODALS ════════════════════════════════════════════════════════════════ -->

<!-- ── Step File Upload Modal ──────────────────────────────────────────────── -->
<div x-show="_stepUploadFor" x-cloak
     @click.self="_stepUploadFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[380px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18)">

    <!-- Header  matches Schedule Follow-up exactly -->
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[16px] font-bold text-white leading-tight">Upload Documents</h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)">Attach files to this step</p>
      </div>
      <button @click="_stepUploadFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>

    <!-- Body -->
    <div class="p-4 space-y-2">

      <!-- Drop zone -->
      <label class="flex items-center gap-2.5 w-full border border-dashed border-[#D1D9E6] rounded-[8px] px-3 py-2.5 cursor-pointer hover:border-[#1268F3] hover:bg-[#F5F9FF] transition-colors"
             @dragover.prevent @drop.prevent="_stepUploadFiles=[..._stepUploadFiles,...Array.from($event.dataTransfer.files)]">
        <svg class="w-4 h-4 text-[#98A2B3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span class="text-[13px] text-[#98A2B3]">Browse or drag & drop files…</span>
        <input type="file" class="sr-only" multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.csv,.txt"
               @change="_stepUploadFiles=[..._stepUploadFiles,...Array.from($event.target.files)];$event.target.value=''">
      </label>

      <!-- Selected files -->
      <div x-show="_stepUploadFiles.length > 0" class="space-y-1 max-h-[130px] overflow-y-auto">
        <template x-for="(f,fi) in _stepUploadFiles" :key="fi">
          <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-[6px] border border-[#E4E7EC] bg-[#F9FAFB]">
            <svg class="w-3 h-3 text-[#98A2B3] shrink-0"><use href="#icon-paperclip"/></svg>
            <span class="flex-1 truncate text-[12px] text-[#344054] font-semibold" x-text="f.name"></span>
            <span class="text-[10px] text-[#98A2B3] shrink-0" x-text="f.size>1048576?(f.size/1048576).toFixed(1)+'MB':(f.size/1024).toFixed(0)+'KB'"></span>
            <button @click="_stepUploadFiles=_stepUploadFiles.filter((_,i)=>i!==fi)"
                    class="w-4 h-4 flex items-center justify-center rounded text-[#98A2B3] hover:bg-[#FEF3F2] hover:text-[#B42318] transition-colors text-[10px] font-bold shrink-0">✕</button>
          </div>
        </template>
      </div>

    </div>

    <!-- Footer  matches Schedule Follow-up exactly -->
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="_stepUploadFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="submitStepUpload()"
              :disabled="_stepUploading || !_stepUploadFiles.length"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
        <svg x-show="_stepUploading" class="w-3 h-3 spin"><use href="#icon-refresh-cw"/></svg>
        <svg x-show="!_stepUploading" class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
        <span x-text="_stepUploading ? 'Uploading…' : 'Upload '+_stepUploadFiles.length+(_stepUploadFiles.length===1?' file':' files')"></span>
      </button>
    </div>

  </div>
</div>

<!-- Reminders  Add Follow-up Modal -->
<div x-show="rmAddOpen" x-cloak @click.self="rmAddOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[700px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[16px] font-bold text-white leading-tight">Schedule Follow-up</h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)">Select an inquiry and fill in the details below</p>
      </div>
      <button @click="rmAddOpen=false" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>

    <!-- Fields -->
    <div class="overflow-y-auto flex-1 p-5 space-y-4">

      <!-- Inquiry -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Inquiry <span class="text-[#B42318]">*</span></label>
        <div class="relative">
          <select x-model="rmAddForm.inquiryId"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 pr-8 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
            <option value="">Select inquiry…</option>
            <template x-for="iq in inquiries" :key="iq.id">
              <option :value="iq.id" x-text="iq.id+' · '+iq.client+(iq.company?' · '+iq.company:'')"></option>
            </template>
          </select>
          <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
      </div>

      <!-- Note -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Note <span class="text-[#B42318]">*</span></label>
        <input x-model="rmAddForm.note" type="text"
               placeholder="e.g. Call client for proposal feedback"
               class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
      </div>

      <!-- Date + Time -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Date <span class="text-[#B42318]">*</span></label>
          <input x-model="rmAddForm.date" type="date"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Time <span class="font-normal normal-case text-[#B8CCED]">(optional)</span></label>
          <input x-model="rmAddForm.time" type="time"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
        </div>
      </div>

      <!-- Assigned To -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assigned To</label>
        <div class="relative">
          <select x-model="rmAddForm.assignedTo"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
            <option value="">Me (<?= htmlspecialchars($user['name']) ?>)</option>
            <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
              <option :value="a.name" x-text="a.name"></option>
            </template>
          </select>
          <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
      </div>

      <div x-show="rmAddErr" class="text-[11px] text-[#B42318] font-semibold" x-text="rmAddErr"></div>
    </div>

    <!-- Footer -->
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="rmAddOpen=false" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="rmAdd()" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        Schedule <svg class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
      </button>
    </div>

  </div>
</div>

<!-- Stage Update Modal -->
<div x-show="stageUpdateFor" x-cloak @click.self="stageUpdateFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[900px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <!-- Dark header -->
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[17px] font-bold text-white leading-tight" x-text="stageUpdateFor?(stageUpdateFor.id+' Update Inquiry Stage'):'Update Stage'"></h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.6)" x-text="stageUpdateFor?(stageUpdateFor.client+' · '+stageUpdateFor.company):''"></p>
      </div>
      <button @click="stageUpdateFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <!-- Body -->
    <div class="overflow-y-auto flex-1 p-5 space-y-5">

      <!-- Stage Pipeline Stepper -->
      <div class="bg-[#F8FAFC] rounded-[10px] p-4">
        <div class="flex items-start">
          <template x-for="s in [1,2,3,4,5]" :key="s">
            <div class="flex items-start flex-1 last:flex-none">
              <div class="flex flex-col items-center gap-1 shrink-0">
                <!-- read-only: position follows the chosen Stage Type, it is not clicked directly -->
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[12px] font-extrabold transition-colors"
                  :class="stageForm.stageNum > s ? 'bg-[#16803C] text-white' : stageForm.stageNum === s ? 'bg-[#1268F3] text-white' : 'bg-[#E4E7EC] text-[#667085]'"
                  x-text="s"></div>
                <span class="text-[9px] font-bold text-center leading-tight w-14"
                  :class="stageForm.stageNum >= s ? 'text-[#172B3A]' : 'text-[#B8CCED]'"
                  x-text="['Inquiry','Communication','Decision','Execution','Closed'][s-1]"></span>
              </div>
              <div x-show="s < 5" class="flex-1 h-[2px] mt-4 mx-1 rounded-full transition-colors"
                :class="stageForm.stageNum > s ? 'bg-[#16803C]' : 'bg-[#E4E7EC]'"></div>
            </div>
          </template>
        </div>
      </div>

      <!-- Stage + Type selectors -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Ownership Status *</label>
          <select x-model="stageForm.ownership" @change="onOwnershipChange()"
            class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
            <option value="Open">Open still being worked</option>
            <option value="Closed">Closed finished</option>
          </select>
          <p class="text-[10px] text-[#98A2B3] mt-0.5"
             x-text="stageForm.ownership==='Closed'
                     ? 'Drops off overdue and open counts everywhere.'
                     : 'Stays in the open pipeline pick where it sits.'"></p>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Stage Type *</label>
          <select x-model="stageForm.stageStep" @change="syncStageNum()"
            class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none bg-white cursor-pointer"
            :class="stageForm.stageStep ? 'border-[#E4E7EC] focus:border-[#1268F3]' : 'border-[#E4E7EC] focus:border-[#1268F3]'">
            <option value="">Select type</option>
            <template x-for="opt in currentStageSteps" :key="opt">
              <option :value="opt" x-text="opt"></option>
            </template>
          </select>
          <p class="text-[10px] text-[#98A2B3] mt-0.5"
             x-text="currentStageSteps.length+' '+(stageForm.ownership==='Closed'?'closing':'open')+' type'+(currentStageSteps.length===1?'':'s')+' available'"></p>
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Expected Delivery Date</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <input type="text" placeholder="Select date" class="hidden" x-init="_fpDate($el,()=>stageForm.deliveryDate,v=>stageForm.deliveryDate=v,'stageForm.deliveryDate')" />
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Next Follow-up Date</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <input type="text" placeholder="Select date" class="hidden" x-init="_fpDate($el,()=>stageForm.followUpDate,v=>stageForm.followUpDate=v,'stageForm.followUpDate')" />
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Proposal Value (₹)</label>
          <input type="number" x-model="stageForm.proposalValue" placeholder="e.g. 325000" class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Final Project Value (₹)</label>
          <input type="number" x-model="stageForm.finalValue" placeholder="e.g. 325000" class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
        </div>
        <div class="col-span-2">
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Stage Remark</label>
          <textarea x-model="stageForm.remark" rows="2" placeholder="What changed? Notes for the team…" class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
        </div>
        <div class="col-span-2">
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Final Updated Status <span class="text-[#B42318]">*</span></label>
          <textarea x-model="stageForm.finalRemark" rows="2" :class="stageForm._finalRemarkErr ? 'border-[#B42318]' : 'border-[#E4E7EC]'" @input="if(stageForm.finalRemark.trim()) stageForm._finalRemarkErr=false" class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
          <p x-show="stageForm._finalRemarkErr" class="text-[10px] font-semibold text-[#B42318] mt-0.5">Final Updated Status is required.</p>
        </div>
      </div>
      <!-- Stage Change History -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] pb-1.5 border-b border-[#E4E7EC] mb-3">Stage Change History</div>
        <div class="overflow-y-auto space-y-1.5" style="max-height:180px">
          <template x-for="(h,i) in (stageUpdateFor?.history||[])" :key="i">
            <div class="flex items-center gap-3 px-3 py-2 rounded-[8px] bg-[#F8FAFC] border border-[#E4E7EC] text-[11px]">
              <!-- Ownership first, then the Stage Type it moved to, so no detail is lost -->
              <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px] shrink-0"
                    :class="isClosed(h)?'bg-[#ECFDF3] text-[#16803C]':'bg-[#EFF8FF] text-[#1268F3]'"
                    x-text="isClosed(h)?'Closed':'Open'"></span>
              <span class="font-semibold text-[#344054] shrink-0" x-text="h.outcome"></span>
              <span class="font-semibold text-[#172B3A] shrink-0" x-text="h.by_user"></span>
              <span class="text-[#667085] shrink-0" x-text="h.date"></span>
              <span class="text-[#667085] flex-1 truncate" x-text="h.remark"></span>
            </div>
          </template>
        </div>
      </div>
    </div>
    <!-- Footer -->
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="stageUpdateFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="saveStageUpdate()" :disabled="_stageSaving" class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors disabled:opacity-60">
        <span x-text="_stageSaving?'Saving…':'Save Changes'"></span>
        <svg x-show="!_stageSaving" class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Add Task Modal -->
<div x-show="addTaskFor" x-cloak @click.self="addTaskFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[700px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[17px] font-bold text-white leading-tight">Add Team / New Task</h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.6)" x-text="addTaskFor?(addTaskFor.id+' · '+addTaskFor.client):''"></p>
      </div>
      <button @click="addTaskFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assigned By</label>
          <input type="text" :value="currentUser" readonly class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none bg-[#F7F8FA] text-[#667085] cursor-not-allowed" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assign To *</label>
          <select x-model="taskForm.assignedTo"
            :class="taskForm.errors.assignedTo ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
            @change="if(taskForm.assignedTo) delete taskForm.errors.assignedTo"
            class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
            <option value="">Select user </option>
            <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
              <option :value="a.name" x-text="a.name + ' (' + a.role + ')'"></option>
            </template>
          </select>
          <p x-show="taskForm.errors.assignedTo" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="taskForm.errors.assignedTo"></p>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Due Date *</label>
          <template x-if="addTaskFor && addTaskFor.inquiry_type==='Internal Usage'">
            <div>
              <div class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-[#F7F8FA] text-[#344054] font-semibold flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-[#98A2B3]"><use href="#icon-calendar"/></svg>
                <span x-text="todayLabel()"></span>
                <span class="text-[10px] font-bold text-[#175CD3] bg-[#EEF4FF] rounded-full px-2 py-0.5 ml-auto">Today only</span>
              </div>
              <p class="text-[10px] text-[#667085] mt-1">Internal inquiry — task must be due today.</p>
            </div>
          </template>
          <template x-if="!addTaskFor || addTaskFor.inquiry_type!=='Internal Usage'">
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
              <input type="text" placeholder="Select date" class="hidden" x-init="_fpDate($el,()=>taskForm.due,v=>taskForm.due=v,'taskForm.due',false,{minDate:'today'})" />
            </div>
          </template>
          <p x-show="taskForm.errors.due" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="taskForm.errors.due"></p>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Due Time *</label>
          <input type="time" x-model="taskForm.dueTime"
            :class="taskForm.errors.dueTime ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
            class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white text-[#344054]" />
          <p x-show="taskForm.errors.dueTime" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="taskForm.errors.dueTime"></p>
        </div>
        <div class="col-span-2">
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Task Instruction *</label>
          <div :class="taskForm.errors.instruction ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
               class="border rounded-[8px] overflow-hidden focus-within:border-[#1268F3] transition-colors bg-white">
            <div class="flex items-center gap-0.5 px-2 py-1.5 border-b border-[#E4E7EC] bg-[#F8FAFC]">
              <button type="button" @mousedown.prevent="document.execCommand('bold')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] font-extrabold text-[#344054] hover:bg-[#E4E7EC] transition-colors">B</button>
              <button type="button" @mousedown.prevent="document.execCommand('italic')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] italic text-[#344054] hover:bg-[#E4E7EC] transition-colors">I</button>
              <button type="button" @mousedown.prevent="document.execCommand('underline')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] underline text-[#344054] hover:bg-[#E4E7EC] transition-colors">U</button>
              <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
              <button type="button" @mousedown.prevent="document.execCommand('insertUnorderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">• List</button>
              <button type="button" @mousedown.prevent="document.execCommand('insertOrderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">1. List</button>
              <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
              <button type="button" @mousedown.prevent="const u=prompt('Enter link URL:');if(u)document.execCommand('createLink',false,u)" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Link</button>
              <button type="button" @mousedown.prevent="$refs.taskInstrEd.innerHTML='';taskForm.instruction='';delete taskForm.errors.instruction" class="px-2 h-7 flex items-center rounded text-[12px] text-[#B42318] hover:bg-[#FEF3F2] transition-colors">Clear</button>
            </div>
            <div contenteditable="true" x-ref="taskInstrEd"
                 x-init="$el.innerHTML=taskForm.instruction||'';$el.addEventListener('input',()=>{taskForm.instruction=$el.innerHTML;if(stripHtml(taskForm.instruction).trim())delete taskForm.errors.instruction});$watch('addTaskFor',()=>{$nextTick(()=>{$el.innerHTML=taskForm.instruction||''})})"
                 @paste.prevent="document.execCommand('insertText',false,$event.clipboardData.getData('text/plain'))"
                 data-placeholder="Describe what needs to be done, or paste email chain…"
                 class="min-h-[130px] p-3 text-[13px] text-[#172B3A] focus:outline-none leading-relaxed rich-editor"></div>
          </div>
          <p x-show="taskForm.errors.instruction" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="taskForm.errors.instruction"></p>
        </div>
        <div class="col-span-2">
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Attachments</label>
          <input type="file" multiple x-ref="taskFiles" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.csv,.txt"
            class="w-full text-[12px] text-[#344054] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white file:mr-3 file:text-[11px] file:font-bold file:border-0 file:rounded-[6px] file:bg-[#EEF4FF] file:text-[#175CD3] file:px-2.5 file:py-1 file:cursor-pointer cursor-pointer" />
          <div class="text-[10px] text-[#B8CCED] mt-1">PDF, Word, Excel, images, ZIP max 10 MB each</div>
        </div>
      </div>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="addTaskFor=null" :disabled="_taskSaving" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="saveTask()" :disabled="_taskSaving" class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
        <template x-if="_taskSaving">
          <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        </template>
        <span x-text="_taskSaving ? 'Saving…' : 'Add Task'"></span>
        <svg x-show="!_taskSaving" class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Admin Remark Modal -->
<template x-if="adminRemarkFor">
<div @click.self="adminRemarkFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(181,71,8,0.25)">
          <svg class="w-4 h-4" style="color:#FBBF24"><use href="#icon-shield-check"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight">Admin Remark</h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="adminRemarkFor?(adminRemarkFor.id+' · '+adminRemarkFor.client):''"></p>
        </div>
      </div>
      <button @click="adminRemarkFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5">
      <p class="text-[11px] text-[#B54708] mb-2">Visible to all members of this inquiry.</p>
      <textarea x-model="adminRemarkFor._adminRemark" rows="5"
                placeholder="Add a remark visible to everyone involved in this inquiry…"
                @keydown.ctrl.enter="saveAdminRemark(adminRemarkFor)"
                class="w-full text-[13px] text-[#172B3A] bg-white border border-[#FDE68A] rounded-[8px] px-3 py-2 resize-none focus:outline-none focus:border-[#F59E0B] placeholder:text-[#D1D9E6]"></textarea>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="adminRemarkFor=null" :disabled="adminRemarkFor && adminRemarkFor._adminRemarkSaving" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="saveAdminRemark(adminRemarkFor)" :disabled="adminRemarkFor && adminRemarkFor._adminRemarkSaving" class="flex items-center justify-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#B54708] text-white hover:bg-[#B42318] transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
        <svg x-show="adminRemarkFor && adminRemarkFor._adminRemarkSaving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        <span x-text="adminRemarkFor && adminRemarkFor._adminRemarkSaving ? 'Saving…' : 'Save Remark'"></span>
      </button>
    </div>
  </div>
</div>
</template>

<!-- Team Remark Modal -->
<template x-if="teamRemarkFor">
<div @click.self="teamRemarkFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(18,104,243,0.25)">
          <svg class="w-4 h-4" style="color:#60A5FA"><use href="#icon-message-circle"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight">Remark</h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="teamRemarkFor?(teamRemarkFor.id+' · '+teamRemarkFor.client):''"></p>
        </div>
      </div>
      <button @click="teamRemarkFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5">
      <p class="text-[11px] text-[#175CD3] mb-2">Visible to everyone on this inquiry, including <span class="font-bold" x-text="teamRemarkFor?.created_by"></span> who created it. Shown with your name attached.</p>
      <textarea x-model="teamRemarkFor._teamRemark" rows="5"
                placeholder="Add a remark for the inquiry creator and team…"
                @keydown.ctrl.enter="saveTeamRemark(teamRemarkFor)"
                class="w-full text-[13px] text-[#172B3A] bg-white border border-[#DBEAFE] rounded-[8px] px-3 py-2 resize-none focus:outline-none focus:border-[#1268F3] placeholder:text-[#D1D9E6]"></textarea>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="teamRemarkFor=null" :disabled="teamRemarkFor && teamRemarkFor._teamRemarkSaving" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="saveTeamRemark(teamRemarkFor)" :disabled="teamRemarkFor && teamRemarkFor._teamRemarkSaving" class="flex items-center justify-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
        <svg x-show="teamRemarkFor && teamRemarkFor._teamRemarkSaving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        <span x-text="teamRemarkFor && teamRemarkFor._teamRemarkSaving ? 'Saving…' : 'Save Remark'"></span>
      </button>
    </div>
  </div>
</div>
</template>

<!-- Schedule Follow-up Modal -->
<template x-if="fuOpenFor">
<div @click.self="fuOpenFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(18,104,243,0.25)">
          <svg class="w-4 h-4 text-[#B8CCED]"><use href="#icon-bell"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight">Schedule Follow-up</h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="fuOpenFor?(fuOpenFor.id+' · '+fuOpenFor.client):''"></p>
        </div>
      </div>
      <button @click="fuOpenFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5 space-y-4">
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Note <span class="text-[#B42318]">*</span></label>
        <input x-model="fuForm.note" type="text" placeholder="e.g. Call client for proposal feedback"
               class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Date <span class="text-[#B42318]">*</span></label>
          <input x-model="fuForm.date" type="date"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Time <span class="font-normal normal-case text-[#B8CCED]">(optional)</span></label>
          <input x-model="fuForm.time" type="time"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
        </div>
      </div>
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assigned To</label>
        <select x-model="fuForm.assignedTo"
                class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
          <option value="">Me (<?= htmlspecialchars($user['name']) ?>)</option>
          <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
            <option :value="a.name" x-text="a.name"></option>
          </template>
        </select>
      </div>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="fuOpenFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="addFollowUp(fuOpenFor)" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        Schedule <svg class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
      </button>
    </div>
  </div>
</div>
</template>

<!-- Note Details Modal -->
<div x-show="noteViewFor" x-cloak @click.self="noteViewFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full flex flex-col" style="max-width:640px;max-height:90vh;box-shadow:0 20px 60px rgba(7,29,43,0.18)">
    <div class="flex items-start justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="min-w-0">
        <h2 class="sp-note-title text-[16px] font-bold text-white leading-snug" x-text="noteViewFor?.title"></h2>
        <p class="text-[11px] mt-0.5 tabular-nums" style="color:rgba(255,255,255,0.55)" x-text="noteViewFor?._time"></p>
      </div>
      <button @click="noteViewFor=null" class="hover:text-white transition-colors ml-4 text-xl shrink-0" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5">
      <div class="sp-note-body text-[13px] text-[#344054] leading-relaxed" x-text="noteViewFor?.body"></div>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="noteViewFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Close</button>
      <button @click="startEditNote(noteViewFor); noteViewFor=null"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors">Edit note</button>
    </div>
  </div>
</div>

<!-- Decline Leave Modal -->
<div x-show="leaveRejectFor" x-cloak @click.self="leaveRejectFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[420px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18)">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[16px] font-bold text-white leading-tight">Decline leave</h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="leaveRejectFor ? (leaveRejectFor.name+' · '+leaveDateLabel(leaveRejectFor)) : ''"></p>
      </div>
      <button @click="leaveRejectFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="p-5">
      <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Note <span class="font-normal normal-case text-[#98A2B3]">(optional)</span></label>
      <textarea x-model="leaveRejectNote" rows="3" placeholder="Reason for declining, if you want to share one…"
                class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="leaveRejectFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Back</button>
      <button @click="decideLeave(leaveRejectFor,'rejected')" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#B42318] text-white hover:bg-[#912018] transition-colors">Decline request</button>
    </div>
  </div>
</div>

<!-- Complete Inquiry Modal -->
<div x-show="completeInquiryFor" x-cloak @click.self="completeInquiryFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[700px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(22,128,60,0.25)">
          <svg class="w-4 h-4 text-[#3DDC84]"><use href="#icon-check-circle"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight">Complete Inquiry</h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="completeInquiryFor?(completeInquiryFor.id+' · '+completeInquiryFor.client):''"></p>
        </div>
      </div>
      <button @click="completeInquiryFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5">
      <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Final Status *</label>
      <textarea x-model="completeInquiryRemark" rows="4" placeholder="Summarize the final outcome of this inquiry…"
        @input="_completeInquiryErr=false"
        :class="_completeInquiryErr ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
        class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] resize-none"></textarea>
      <p x-show="_completeInquiryErr" class="text-[10px] font-semibold text-[#B42318] mt-0.5">Final status is required.</p>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="completeInquiryFor=null" :disabled="_completingInquiry" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="submitCompleteInquiry()" :disabled="_completingInquiry" class="flex items-center justify-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#16803C] text-white hover:bg-[#106430] transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
        <template x-if="_completingInquiry">
          <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        </template>
        <span x-text="_completingInquiry ? 'Completing…' : 'Complete'"></span>
      </button>
    </div>
  </div>
</div>

<!-- Add Inquiry Modal -->
<div x-show="addInquiryOpen" x-cloak @click.self="addInquiryOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[980px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[17px] font-bold text-white leading-tight">Add Inquiry</h2>
      </div>
      <button @click="addInquiryOpen=false;addInquiryForm.errors={}" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1 p-5 space-y-5">
      <!-- Inquiry Details -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] pb-1.5 border-b border-[#E4E7EC] mb-3">Inquiry Details</div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Inquiry ID</label>
            <input type="text" value="<?= htmlspecialchars($nextId) ?>" readonly class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none bg-[#F7F8FA] text-[#667085] cursor-not-allowed" />
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Inquiry Date</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
              <input type="text" placeholder="Select date" class="hidden" x-init="_fpDate($el,()=>addInquiryForm.date,v=>addInquiryForm.date=v,'addInquiryForm.date',false,{minDate:'today'})" />
            </div>
          </div>
        </div>
      </div>
      <!-- Inquiry Type -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Inquiry Type</label>
        <div class="inline-flex rounded-[8px] border border-[#E4E7EC] p-0.5 bg-[#F7F8FA]">
          <button type="button" @click="setInquiryType('Client Project')"
            :class="addInquiryForm.inquiryType==='Client Project' ? 'bg-white shadow-sm text-[#175CD3]' : 'text-[#667085]'"
            class="px-3 py-1.5 rounded-[7px] text-[12px] font-semibold transition-colors">Client Project</button>
          <button type="button" @click="setInquiryType('Internal Usage')"
            :class="addInquiryForm.inquiryType==='Internal Usage' ? 'bg-white shadow-sm text-[#175CD3]' : 'text-[#667085]'"
            class="px-3 py-1.5 rounded-[7px] text-[12px] font-semibold transition-colors">Internal Usage</button>
        </div>
      </div>
      <!-- Project Category (shared catalog: Online / Offline + user-added names) -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Project Category</label>
          <button type="button" x-show="isAdmin" @click="addProjectCategory()" class="text-[11px] font-bold text-[#1268F3] hover:underline">+ Add category</button>
        </div>
        <select x-model="addInquiryForm.projectCategory"
          :class="addInquiryForm.errors.projectCategory ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
          @change="if(addInquiryForm.projectCategory) delete addInquiryForm.errors.projectCategory"
          class="w-full max-w-xs text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
          <option value="">Select category</option>
          <template x-for="c in projectCategories" :key="c.id">
            <option :value="c.name" x-text="c.name"></option>
          </template>
        </select>
        <p x-show="addInquiryForm.errors.projectCategory" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.projectCategory"></p>
      </div>
      <div x-show="addInquiryForm.inquiryType==='Client Project'" class="space-y-5">
      <!-- Client Details -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] pb-1.5 border-b border-[#E4E7EC] mb-3">Client Details</div>
        <div class="grid grid-cols-4 gap-3">
          <div class="relative">
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Client Name *</label>
            <input type="text" x-model="addInquiryForm.client" placeholder="Full name"
              :class="addInquiryForm.errors.client ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              @focus="_clientAcOpen=true"
              @blur="setTimeout(()=>_clientAcOpen=false, 180)"
              @input="if(addInquiryForm.client.trim()) delete addInquiryForm.errors.client"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <!-- Suggestions dropdown -->
            <div x-show="_clientAcOpen && clientSuggestions.length > 0"
                 class="absolute left-0 right-0 top-full mt-1 bg-white border border-[#E4E7EC] rounded-[10px] py-1 z-50 overflow-hidden"
                 style="box-shadow:0 8px 24px rgba(7,29,43,0.10)">
              <template x-for="s in clientSuggestions" :key="s">
                <button type="button"
                        @mousedown.prevent="addInquiryForm.client=s; _clientAcOpen=false"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2 text-left hover:bg-[#F5F8FF] transition-colors">
                  <svg class="w-3.5 h-3.5 text-[#98A2B3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <span class="text-[12px] font-semibold text-[#172B3A]" x-text="s"></span>
                </button>
              </template>
            </div>
            <p x-show="addInquiryForm.errors.client" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.client"></p>
            <p x-show="addInquiryForm._clientExists && !addInquiryForm.errors.client" class="text-[10px] font-semibold text-[#B54708] mt-0.5">⚠ Existing client fields auto-filled, type set to Repeat</p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Designation</label>
            <input type="text" x-model="addInquiryForm.designation" placeholder="e.g. Manager, Director"
              class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Client Company *</label>
            <input type="text" x-model="addInquiryForm.company" placeholder="Company name"
              :class="addInquiryForm.errors.company ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <p x-show="addInquiryForm.errors.company" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.company"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Client Type *</label>
            <select x-model="addInquiryForm.clientType" class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
              <option>New</option><option>Existing</option><option>Repeat</option>
            </select>
          </div>
          <!-- Client check message -->
          <div class="col-span-4" x-show="clientCheck">
            <div x-show="clientCheck?.kind==='success'" class="flex items-center gap-2 text-[11px] font-semibold text-[#16803C] bg-[#ECFDF3] border border-[#BBF7D0] rounded-[8px] px-3 py-2">
              <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-check-circle"/></svg>
              <span x-text="clientCheck?.text"></span>
            </div>
            <div x-show="clientCheck?.kind==='warning'" class="flex items-center gap-2 text-[11px] font-semibold text-[#B54708] bg-[#FFFAEB] border border-[#FEF0C7] rounded-[8px] px-3 py-2">
              <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-alert-circle"/></svg>
              <span x-text="clientCheck?.text"></span>
            </div>
            <div x-show="clientCheck?.kind==='info'" class="flex items-center gap-2 text-[11px] font-semibold text-[#026AA2] bg-[#EFF8FF] border border-[#BAE6FD] rounded-[8px] px-3 py-2">
              <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-check-circle"/></svg>
              <span x-text="clientCheck?.text"></span>
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Primary Email *</label>
            <input type="email" x-model="addInquiryForm.email" placeholder="email@company.com"
              :class="addInquiryForm.errors.email ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              @blur="if(addInquiryForm.email.trim()&&/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(addInquiryForm.email.trim())) delete addInquiryForm.errors.email"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <p x-show="addInquiryForm.errors.email" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.email"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Secondary Email</label>
            <input type="email" x-model="addInquiryForm.emailSecondary" placeholder="alt@company.com"
              :class="addInquiryForm.errors.emailSecondary ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              @blur="if(!addInquiryForm.emailSecondary.trim()||/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(addInquiryForm.emailSecondary.trim())) delete addInquiryForm.errors.emailSecondary"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <p x-show="addInquiryForm.errors.emailSecondary" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.emailSecondary"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Phone</label>
            <input type="tel" x-model="addInquiryForm.phone" placeholder="+XX XXXX XXXXXX" class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Country *</label>
            <input type="text" list="sp-countries" x-model="addInquiryForm.country" placeholder="Type country name…"
              :class="addInquiryForm.errors.country ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <datalist id="sp-countries">
              <option>Afghanistan</option><option>Albania</option><option>Algeria</option><option>Andorra</option><option>Angola</option><option>Antigua and Barbuda</option><option>Argentina</option><option>Armenia</option><option>Australia</option><option>Austria</option><option>Azerbaijan</option><option>Bahamas</option><option>Bahrain</option><option>Bangladesh</option><option>Barbados</option><option>Belarus</option><option>Belgium</option><option>Belize</option><option>Benin</option><option>Bhutan</option><option>Bolivia</option><option>Bosnia and Herzegovina</option><option>Botswana</option><option>Brazil</option><option>Brunei</option><option>Bulgaria</option><option>Burkina Faso</option><option>Burundi</option><option>Cabo Verde</option><option>Cambodia</option><option>Cameroon</option><option>Canada</option><option>Central African Republic</option><option>Chad</option><option>Chile</option><option>China</option><option>Colombia</option><option>Comoros</option><option>Congo, Democratic Republic of the</option><option>Congo, Republic of the</option><option>Costa Rica</option><option>Côte d'Ivoire</option><option>Croatia</option><option>Cuba</option><option>Cyprus</option><option>Czechia</option><option>Denmark</option><option>Djibouti</option><option>Dominica</option><option>Dominican Republic</option><option>Ecuador</option><option>Egypt</option><option>El Salvador</option><option>Equatorial Guinea</option><option>Eritrea</option><option>Estonia</option><option>Eswatini</option><option>Ethiopia</option><option>Fiji</option><option>Finland</option><option>France</option><option>Gabon</option><option>Gambia</option><option>Georgia</option><option>Germany</option><option>Ghana</option><option>Greece</option><option>Grenada</option><option>Guatemala</option><option>Guinea</option><option>Guinea-Bissau</option><option>Guyana</option><option>Haiti</option><option>Honduras</option><option>Hungary</option><option>Iceland</option><option>India</option><option>Indonesia</option><option>Iran</option><option>Iraq</option><option>Ireland</option><option>Israel</option><option>Italy</option><option>Jamaica</option><option>Japan</option><option>Jordan</option><option>Kazakhstan</option><option>Kenya</option><option>Kiribati</option><option>Kuwait</option><option>Kyrgyzstan</option><option>Laos</option><option>Latvia</option><option>Lebanon</option><option>Lesotho</option><option>Liberia</option><option>Libya</option><option>Liechtenstein</option><option>Lithuania</option><option>Luxembourg</option><option>Madagascar</option><option>Malawi</option><option>Malaysia</option><option>Maldives</option><option>Mali</option><option>Malta</option><option>Marshall Islands</option><option>Mauritania</option><option>Mauritius</option><option>Mexico</option><option>Micronesia</option><option>Moldova</option><option>Monaco</option><option>Mongolia</option><option>Montenegro</option><option>Morocco</option><option>Mozambique</option><option>Myanmar</option><option>Namibia</option><option>Nauru</option><option>Nepal</option><option>Netherlands</option><option>New Zealand</option><option>Nicaragua</option><option>Niger</option><option>Nigeria</option><option>North Korea</option><option>North Macedonia</option><option>Norway</option><option>Oman</option><option>Pakistan</option><option>Palau</option><option>Panama</option><option>Papua New Guinea</option><option>Paraguay</option><option>Peru</option><option>Philippines</option><option>Poland</option><option>Portugal</option><option>Qatar</option><option>Romania</option><option>Russia</option><option>Rwanda</option><option>Saint Kitts and Nevis</option><option>Saint Lucia</option><option>Saint Vincent and the Grenadines</option><option>Samoa</option><option>San Marino</option><option>São Tomé and Príncipe</option><option>Saudi Arabia</option><option>Senegal</option><option>Serbia</option><option>Seychelles</option><option>Sierra Leone</option><option>Singapore</option><option>Slovakia</option><option>Slovenia</option><option>Solomon Islands</option><option>Somalia</option><option>South Africa</option><option>South Korea</option><option>South Sudan</option><option>Spain</option><option>Sri Lanka</option><option>Sudan</option><option>Suriname</option><option>Sweden</option><option>Switzerland</option><option>Syria</option><option>Taiwan</option><option>Tajikistan</option><option>Tanzania</option><option>Thailand</option><option>Timor-Leste</option><option>Togo</option><option>Tonga</option><option>Trinidad and Tobago</option><option>Tunisia</option><option>Türkiye</option><option>Turkmenistan</option><option>Tuvalu</option><option>Uganda</option><option>Ukraine</option><option>United Arab Emirates</option><option>United Kingdom</option><option>United States</option><option>Uruguay</option><option>Uzbekistan</option><option>Vanuatu</option><option>Vatican City</option><option>Venezuela</option><option>Vietnam</option><option>Yemen</option><option>Zambia</option><option>Zimbabwe</option>
            </datalist>
            <p x-show="addInquiryForm.errors.country" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.country"></p>
          </div>
          <div class="col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Client Website *</label>
            <input type="url" x-model="addInquiryForm.website" placeholder="https://www.company.com"
              :class="addInquiryForm.errors.website ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
            <p x-show="addInquiryForm.errors.website" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.website"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Created By</label>
            <input type="text" :value="currentUser" readonly class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-[#F7F8FA] text-[#667085] cursor-not-allowed" />
          </div>
        </div>
      </div>
      </div><!-- /Client Project fields -->
      <!-- Email Subject -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Email Subject</label>
        <input type="text" x-model="addInquiryForm.emailSubject" placeholder="e.g. Re: Survey Requirement for Q3 Project"
          class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
      </div>
      <!-- Requirement -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] pb-1.5 border-b border-[#E4E7EC] mb-3" x-text="addInquiryForm.inquiryType==='Internal Usage' ? 'Requirement *' : 'Client Requirement *'"></div>
        <div :class="addInquiryForm.errors.requirement ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
             class="border rounded-[8px] overflow-hidden focus-within:border-[#1268F3] transition-colors bg-white">
          <div class="flex items-center gap-0.5 px-2 py-1.5 border-b border-[#E4E7EC] bg-[#F8FAFC]">
            <button type="button" @mousedown.prevent="document.execCommand('bold')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] font-extrabold text-[#344054] hover:bg-[#E4E7EC] transition-colors">B</button>
            <button type="button" @mousedown.prevent="document.execCommand('italic')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] italic text-[#344054] hover:bg-[#E4E7EC] transition-colors">I</button>
            <button type="button" @mousedown.prevent="document.execCommand('underline')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] underline text-[#344054] hover:bg-[#E4E7EC] transition-colors">U</button>
            <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
            <button type="button" @mousedown.prevent="document.execCommand('insertUnorderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">• List</button>
            <button type="button" @mousedown.prevent="document.execCommand('insertOrderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">1. List</button>
            <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
            <button type="button" @mousedown.prevent="const u=prompt('Enter link URL:');if(u)document.execCommand('createLink',false,u)" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Link</button>
            <button type="button" @mousedown.prevent="$refs.reqEd.innerHTML='';addInquiryForm.requirement='';delete addInquiryForm.errors.requirement" class="px-2 h-7 flex items-center rounded text-[12px] text-[#B42318] hover:bg-[#FEF3F2] transition-colors">Clear</button>
          </div>
          <div contenteditable="true" x-ref="reqEd"
               x-init="$el.innerHTML=addInquiryForm.requirement||'';$el.addEventListener('input',()=>{addInquiryForm.requirement=$el.innerHTML;if(stripHtml(addInquiryForm.requirement).trim())delete addInquiryForm.errors.requirement});$watch('addInquiryOpen',()=>{$nextTick(()=>{$el.innerHTML=addInquiryForm.requirement||''})})"
               @paste.prevent="document.execCommand('insertText',false,$event.clipboardData.getData('text/plain'))"
               data-placeholder="Paste full email chain or describe the requirement in detail…"
               class="min-h-[140px] p-3 text-[13px] text-[#172B3A] focus:outline-none leading-relaxed rich-editor"></div>
        </div>
        <p x-show="addInquiryForm.errors.requirement" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.requirement"></p>
      </div>
      <!-- First Task Assignment -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] pb-1.5 border-b border-[#E4E7EC] mb-3">First Task Assignment</div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assign First Task To *</label>
            <select x-model="addInquiryForm.assignTo"
              :class="addInquiryForm.errors.assignTo ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              @change="if(addInquiryForm.assignTo) delete addInquiryForm.errors.assignTo"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
              <option value="">Select user </option>
              <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
                <option :value="a.name" x-text="a.name + ' (' + a.role + ')'"></option>
              </template>
            </select>
            <p x-show="addInquiryForm.errors.assignTo" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.assignTo"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Due Date *</label>
            <template x-if="addInquiryForm.inquiryType==='Internal Usage'">
              <div>
                <div class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-[#F7F8FA] text-[#344054] font-semibold flex items-center gap-2">
                  <svg class="w-3.5 h-3.5 text-[#98A2B3]"><use href="#icon-calendar"/></svg>
                  <span x-text="todayLabel()"></span>
                  <span class="text-[10px] font-bold text-[#175CD3] bg-[#EEF4FF] rounded-full px-2 py-0.5 ml-auto">Today only</span>
                </div>
                <p class="text-[10px] text-[#667085] mt-1">Internal tasks are same-day. Pick any time today.</p>
              </div>
            </template>
            <template x-if="addInquiryForm.inquiryType!=='Internal Usage'">
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                <input type="text" placeholder="Select date" class="hidden"
                  x-init="_fpDate($el,()=>addInquiryForm.dueDate,v=>{addInquiryForm.dueDate=v;if(v)delete addInquiryForm.errors.dueDate},'addInquiryForm.dueDate',false,{minDate:'today'})" />
                <p class="text-[10px] text-[#667085] mt-1">Client projects can run across future days.</p>
              </div>
            </template>
            <p x-show="addInquiryForm.errors.dueDate" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.dueDate"></p>
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Due Time *</label>
            <input type="time" x-model="addInquiryForm.dueTime"
              :class="addInquiryForm.errors.dueTime ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
              class="w-full text-[13px] border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white text-[#344054]" />
            <p x-show="addInquiryForm.errors.dueTime" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.dueTime"></p>
          </div>
          <div class="col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Task Instruction *</label>
            <div :class="addInquiryForm.errors.taskInstruction ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
                 class="border rounded-[8px] overflow-hidden focus-within:border-[#1268F3] transition-colors bg-white">
              <div class="flex items-center gap-0.5 px-2 py-1.5 border-b border-[#E4E7EC] bg-[#F8FAFC]">
                <button type="button" @mousedown.prevent="document.execCommand('bold')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] font-extrabold text-[#344054] hover:bg-[#E4E7EC] transition-colors">B</button>
                <button type="button" @mousedown.prevent="document.execCommand('italic')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] italic text-[#344054] hover:bg-[#E4E7EC] transition-colors">I</button>
                <button type="button" @mousedown.prevent="document.execCommand('underline')" class="w-7 h-7 flex items-center justify-center rounded text-[13px] underline text-[#344054] hover:bg-[#E4E7EC] transition-colors">U</button>
                <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
                <button type="button" @mousedown.prevent="document.execCommand('insertUnorderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">• List</button>
                <button type="button" @mousedown.prevent="document.execCommand('insertOrderedList')" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">1. List</button>
                <span class="w-px h-4 bg-[#E4E7EC] mx-1"></span>
                <button type="button" @mousedown.prevent="const u=prompt('Enter link URL:');if(u)document.execCommand('createLink',false,u)" class="px-2 h-7 flex items-center rounded text-[12px] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Link</button>
                <button type="button" @mousedown.prevent="$refs.taskInstrEd2.innerHTML='';addInquiryForm.taskInstruction='';delete addInquiryForm.errors.taskInstruction" class="px-2 h-7 flex items-center rounded text-[12px] text-[#B42318] hover:bg-[#FEF3F2] transition-colors">Clear</button>
              </div>
              <div contenteditable="true" x-ref="taskInstrEd2"
                   x-init="$el.innerHTML=addInquiryForm.taskInstruction||'';$el.addEventListener('input',()=>{addInquiryForm.taskInstruction=$el.innerHTML;if(stripHtml(addInquiryForm.taskInstruction).trim())delete addInquiryForm.errors.taskInstruction});$watch('addInquiryOpen',()=>{$nextTick(()=>{$el.innerHTML=addInquiryForm.taskInstruction||''})})"
                   @paste.prevent="document.execCommand('insertText',false,$event.clipboardData.getData('text/plain'))"
                   data-placeholder="What should the assignee do first?"
                   class="min-h-[100px] p-3 text-[13px] text-[#172B3A] focus:outline-none leading-relaxed rich-editor"></div>
            </div>
            <p x-show="addInquiryForm.errors.taskInstruction" class="text-[10px] font-semibold text-[#B42318] mt-0.5" x-text="addInquiryForm.errors.taskInstruction"></p>
          </div>
        </div>
      </div>
      <!-- Attachments -->
      <div class="px-5 pb-4 pt-2">
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1.5">Attachments</label>
        <label class="inline-flex items-center gap-2 text-[12px] font-semibold text-[#344054] border border-dashed border-[#D1D9E6] rounded-[8px] px-4 py-2 cursor-pointer hover:border-[#1268F3] hover:text-[#175CD3] transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          Choose Files
          <input type="file" multiple x-ref="inqFiles" class="sr-only" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.csv,.txt"
            @change="Array.from($event.target.files).forEach(f=>{if(!addInquiryForm._files)addInquiryForm._files=[];addInquiryForm._files.push(f)})" />
        </label>
        <template x-if="addInquiryForm._files && addInquiryForm._files.length">
          <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="(f,i) in (addInquiryForm._files||[])" :key="i">
              <span class="inline-flex items-center gap-1 text-[11px] font-medium text-[#344054] bg-[#F2F4F7] rounded-[5px] px-2 py-0.5">
                <span x-text="f.name.length>22?f.name.slice(0,22)+'…':f.name"></span>
                <button type="button" @click="addInquiryForm._files=addInquiryForm._files.filter((_,j)=>j!==i)" class="text-[#98A2B3] hover:text-[#B42318] ml-0.5">✕</button>
              </span>
            </template>
          </div>
        </template>
        <p class="text-[10px] text-[#98A2B3] mt-1.5">PDF, Word, Excel, images, ZIP max 10 MB each</p>
      </div>
      <div x-show="addInquiryForm.errors._api" class="text-[11px] text-[#B42318] font-semibold px-5 pb-2" x-text="addInquiryForm.errors._api"></div>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="addInquiryOpen=false;addInquiryForm.errors={}" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="addInquiry()" :disabled="_addingInquiry"
        :class="_inquirySave==='saved' ? 'bg-[#16803C] hover:bg-[#16803C]' : 'bg-[#1268F3] hover:bg-[#0f55d6]'"
        class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] text-white transition-colors disabled:cursor-not-allowed">
        <template x-if="_inquirySave==='saving'">
          <span class="flex items-center gap-1.5"><svg class="w-3 h-3 spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>Saving…</span>
        </template>
        <template x-if="_inquirySave==='saved'">
          <span class="flex items-center gap-1.5">✓ Saved!</span>
        </template>
        <template x-if="_inquirySave===''">
          <span class="flex items-center gap-1.5">Save Inquiry <svg class="w-3 h-3"><use href="#icon-arrow-right"/></svg></span>
        </template>
      </button>
    </div>
  </div>
</div>

<!-- Summary Modal -->
<div id="summary-print-area" x-show="summaryFor" x-cloak @click.self="summaryFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[1100px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:92vh">

    <!-- ── Title bar ── -->
    <div class="flex items-center justify-between px-5 py-3.5 rounded-t-[14px] shrink-0 gap-3" style="background:#071D2B">
      <h2 class="text-[16px] font-bold text-white leading-tight shrink-0" x-text="summaryFor?(summaryFor.id+' Journey Summary'):''"></h2>
      <div class="flex items-center gap-2 flex-wrap justify-end flex-1">
        <template x-if="summaryFor && !(summaryFor.steps||[]).some(s=>!['Done','Cancelled'].includes(s.status))">
          <span class="text-[10px] font-bold px-2.5 py-1 rounded-full max-w-[420px]"
                :class="summaryFor?.outcome_reason ? '' : 'whitespace-nowrap'"
                style="background:rgba(22,101,52,0.35);color:#86efac"
                x-text="summaryFor?.outcome_reason ? ('Final Status: '+summaryFor.outcome_reason) : 'No pending task. Journey is complete or cancelled.'"></span>
        </template>
        <button @click="summaryFor=null" class="print-hide hover:text-white transition-colors text-[18px] leading-none" style="color:rgba(255,255,255,0.45)">✕</button>
      </div>
    </div>

    <div class="overflow-y-auto flex-1 p-5 flex flex-col gap-4">

      <!-- ── Client header ── -->
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <div class="text-[16px] font-bold text-[#071D2B] leading-tight" x-text="summaryFor?.client"></div>
          <div x-show="summaryFor?.designation" class="text-[12px] font-semibold text-[#1268F3] mt-0.5" x-text="summaryFor?.designation"></div>
          <div class="text-[12px] text-[#667085] mt-0.5" x-text="[summaryFor?.company, summaryFor?.country].filter(Boolean).join(' · ')"></div>
          <div x-show="summaryFor?.project_category" class="mt-1.5">
            <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px] bg-[#F4F3FF] text-[#5925DC]"
                  x-text="'Category: '+(summaryFor?.project_category||'')"></span>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px]" :class="summaryFor?stageClass(summaryFor.stage):''" x-text="summaryFor?stageLabel(summaryFor.stage):''"></span>
          <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px]" :class="summaryFor?outcomeClass(summaryFor.outcome):''" x-text="summaryFor?.outcome||''"></span>
          <span class="text-[11px] text-[#667085]" x-text="'Owner: '+(summaryFor?.current_owner||'')"></span>
          <span class="text-[11px]" :class="summaryFor&&isOverdue(summaryFor)?'text-[#B42318] font-bold':'text-[#667085]'" x-text="'Due: '+(summaryFor?dueLabel(summaryFor):'TBD')"></span>
          <span x-show="summaryFor && dueStatus(summaryFor)!=='none'" class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-0.5 text-[10px]"
                :class="summaryFor&&dueStatus(summaryFor)==='overdue'?'bg-[#FEF3F2] text-[#B42318]':summaryFor&&dueStatus(summaryFor)==='completed'?'bg-[#ECFDF3] text-[#16803C]':'bg-[#FFFAEB] text-[#B54708]'"
                x-text="summaryFor?dueCountdown(summaryFor):''"></span>
        </div>
      </div>

      <!-- ── Email Subject ── -->
      <template x-if="summaryFor?.email_subject">
        <div class="border border-[#E4E7EC] rounded-[10px] px-4 py-3">
          <div class="text-[9px] font-bold uppercase tracking-[0.06em] text-[#98A2B3] mb-1">Email Subject</div>
          <div class="text-[13px] font-semibold text-[#172B3A]" x-text="summaryFor?.email_subject"></div>
        </div>
      </template>

      <!-- ── Requirement ── -->
      <template x-if="stripHtml(summaryFor?.requirement||'').trim()">
        <div class="border border-[#E4E7EC] rounded-[10px] px-4 py-3 overflow-hidden">
          <div class="text-[9px] font-bold uppercase tracking-[0.06em] text-[#98A2B3] mb-1.5">Requirement</div>
          <div class="text-[12px] text-[#344054] leading-[1.7] rich-editor min-w-0"
               :style="_reqExpanded ? 'overflow-wrap:anywhere;word-break:break-word;max-height:320px;overflow-y:auto;padding-right:4px' : 'overflow-wrap:anywhere;word-break:break-word'"
               :class="!_reqExpanded ? 'line-clamp-6' : ''"
               x-html="linkify(summaryFor?.requirement||'')"></div>
          <button x-show="stripHtml(summaryFor?.requirement||'').length>200" @click="_reqExpanded=!_reqExpanded" class="text-[11px] font-semibold mt-1.5 text-[#1268F3] hover:underline" x-text="_reqExpanded?'Show less':'Read more'"></button>
        </div>
      </template>

      <!-- Start-to-End Summary commented out
      <div class="border border-[#E4E7EC] rounded-[10px] overflow-hidden">
        <div class="px-4 py-2.5 border-b border-[#E4E7EC] bg-[#F8FAFC]">
          <span class="text-[9px] font-bold uppercase tracking-[0.06em] text-[#667085]">Start-to-End Summary</span>
        </div>
        <div class="px-4 py-3 flex flex-col gap-3">
          ...
        </div>
      </div>
      -->

      <!-- ── Journey Steps ── -->
      <div>
        <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085] mb-2">Journey Steps</div>
        <div class="rounded-[10px] border border-[#E4E7EC] overflow-hidden">
          <table class="w-full text-[11px]">
            <thead><tr class="bg-[#F8FAFC] border-b border-[#E4E7EC]">
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] w-6">#</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Assigned By</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Assigned To</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Instruction</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Remark</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] whitespace-nowrap">Due</th>
              <th class="text-left px-3 py-2 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Status</th>
            </tr></thead>
            <tbody>
              <template x-for="(s,i) in (summaryFor?.steps||[])" :key="s.id">
                <tr class="border-b border-[#E4E7EC] last:border-0" :class="i%2===0?'bg-white':'bg-[#FAFBFC]'">
                  <td class="px-3 py-2.5 font-bold text-[#667085]" x-text="i+1"></td>
                  <td class="px-3 py-2.5 font-semibold text-[#344054] whitespace-nowrap" x-text="s.assigned_by||''"></td>
                  <td class="px-3 py-2.5 font-semibold text-[#172B3A] whitespace-nowrap" x-text="s.assigned_to||''"></td>
                  <td class="px-3 py-2.5 text-[#172B3A] max-w-[200px]"><div class="line-clamp-2 break-words rich-editor" x-html="linkify(s.instruction||'')"></div></td>
                  <td class="px-3 py-2.5 text-[#667085] max-w-[150px]"><div class="line-clamp-2" x-text="s.remark||''"></div></td>
                  <td class="px-3 py-2.5 text-[#667085] whitespace-nowrap" x-text="s.due||'TBD'"></td>
                  <td class="px-3 py-2.5"><span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[9px]" :class="stepStatusClass(s.status)" x-text="s.status"></span></td>
                </tr>
              </template>
              <template x-if="!(summaryFor?.steps||[]).length">
                <tr><td colspan="7" class="px-3 py-4 text-center text-[11px] text-[#B8CCED]">No steps recorded.</td></tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white print-hide">
      <button @click="summaryFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Close</button>
      <button onclick="window.print()" class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#071D2B] text-white hover:bg-[#0a2d43] transition-colors">
        <svg class="w-3 h-3"><use href="#icon-printer"/></svg>Print Summary
      </button>
    </div>
  </div>
</div>

<!-- Notifications Modal -->
<div x-show="notifsOpen" x-cloak @click.self="notifsOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div>
        <h2 class="text-[17px] font-bold text-white leading-tight">Notifications</h2>
        <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.6)" x-text="unreadCount>0 ? unreadCount+' unread' : 'All caught up'"></p>
      </div>
      <button @click="notifsOpen=false" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>
    <div class="overflow-y-auto flex-1">
      <template x-if="notifications.length===0">
        <div class="flex flex-col items-center justify-center p-8 text-center">
          <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-bell"/></svg>
          <div class="text-[14px] font-bold text-[#344054] mb-1">No notifications</div>
          <div class="text-[12px] text-[#667085]">You're all caught up.</div>
        </div>
      </template>
      <template x-if="notifications.length>0">
        <div class="divide-y divide-[#E4E7EC]">
          <template x-for="n in notifications" :key="n.id">
            <div class="flex gap-3 px-5 py-3.5 transition-colors"
                 :class="[n.is_read?'bg-white':'bg-[#EEF4FF]', n.inquiry_id?'cursor-pointer hover:bg-[#F2F4F7]':'']"
                 @click="n.inquiry_id && openNotifInquiry(n)">
              <div class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center mt-0.5"
                   :class="n.is_read?'bg-[#F2F4F7]':'bg-[#1268F3]'">
                <svg class="w-3.5 h-3.5" :class="n.is_read?'text-[#667085]':'text-white'"><use href="#icon-bell"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-[12px] font-bold text-[#172B3A] mb-0.5" x-text="n.title"></div>
                <div class="text-[11px] text-[#667085] line-clamp-2" x-text="n.body"></div>
                <div class="text-[10px] text-[#B8CCED] mt-1" x-text="n.time_label"></div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex items-center justify-between shrink-0 rounded-b-[14px] bg-white">
      <button x-show="notifications.length > 0" @click="clearAllNotifs()"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] text-[#B42318] hover:bg-[#FEF3F2] transition-colors">
        Clear All
      </button>
      <button @click="notifsOpen=false" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Close</button>
    </div>
  </div>
</div>

<!-- Member Profile Drawer -->
<div x-show="profileFor" x-cloak @click.self="profileFor=null" @keydown.escape.window="profileFor=null"
     class="fixed inset-0 z-50 flex justify-end" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <!-- max-width inline: the prebuilt app.css has no max-w-[440px] utility -->
  <div class="bg-white w-full h-full flex flex-col" style="max-width:440px;box-shadow:-8px 0 40px rgba(7,29,43,0.18)">
    <!-- inline geometry: the prebuilt app.css has no py-5 / w-12 / h-12 utilities -->
    <div class="px-5 shrink-0" style="background:#071D2B;padding-top:20px;padding-bottom:20px">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="rounded-full flex items-center justify-center shrink-0"
               style="width:48px;height:48px;background:rgba(255,255,255,0.12);border:2px solid rgba(255,255,255,0.18)">
            <span class="text-[14px] font-extrabold text-white" x-text="initials(profileFor?.name)"></span>
          </div>
          <div class="min-w-0">
            <h2 class="text-[17px] font-bold text-white leading-tight truncate" x-text="profileFor?.name"></h2>
            <p class="text-[11px] mt-0.5 truncate flex items-center gap-1" style="color:rgba(255,255,255,0.55)">
              <svg class="w-2.5 h-2.5 shrink-0"><use href="#icon-mail"/></svg>
              <span class="truncate" x-text="profileFor?.email"></span>
            </p>
          </div>
        </div>
        <button @click="profileFor=null" class="hover:text-white transition-colors text-xl shrink-0" style="color:rgba(255,255,255,0.5)">✕</button>
      </div>
      <!-- Chips are inline-styled: the dark header needs its own palette, and the light
           roleBadge()/statusPill() markup is unreadable on it. -->
      <div class="flex items-center gap-1.5 mt-3 flex-wrap">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
              style="background:rgba(255,255,255,0.14);color:#fff" x-text="profileFor?.role || 'Member'"></span>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
              :style="profileFor?.status==='approved' ? 'background:rgba(61,220,132,0.16);color:#3DDC84'
                     : profileFor?.status==='blocked' ? 'background:rgba(255,255,255,0.12);color:#D1D9E6'
                     : 'background:rgba(254,223,137,0.16);color:#FEDF89'"
              x-text="profileFor?.status==='approved' ? 'Active' : (profileFor?.status||'')"></span>
        <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.6)"
              x-text="'Joined '+(profileFor?.requested_at||'')"></span>
      </div>
    </div>

    <!-- Workload at a glance inline grid, the prebuilt app.css has no grid-cols-4 utility -->
    <div class="shrink-0 border-b border-[#E4E7EC]" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr))">
      <template x-for="(s,si) in (profileFor ? [
            {l:'Inquiries', v:memberStats(profileFor.name).inqs,   c:darkMode?'#E6EAF0':'#172B3A'},
            {l:'Open',      v:memberStats(profileFor.name).open,   c:'#1268F3'},
            {l:'Closed',    v:memberStats(profileFor.name).closed, c:'#16803C'},
            {l:'Open tasks',v:memberStats(profileFor.name).tasks,  c:'#B54708'}] : [])" :key="s.l">
        <!-- no divider after the last cell, and the divider follows the theme -->
        <div class="px-3 py-3 text-center"
             :style="si<3 ? 'border-right:1px solid '+(darkMode?'#232A33':'#E4E7EC') : ''">
          <div class="text-[17px] font-bold leading-none" :style="'color:'+s.c" x-text="s.v"></div>
          <div class="text-[9px] font-bold uppercase tracking-[0.04em] text-[#98A2B3] mt-1" x-text="s.l"></div>
        </div>
      </template>
    </div>

    <div class="overflow-y-auto flex-1 p-5 space-y-4">
      <!-- Role & Responsibilities -->
      <div class="border border-[#E4E7EC] rounded-[10px] p-4">
        <div class="flex items-center justify-between gap-2 mb-2.5">
          <div class="text-[12px] font-bold text-[#172B3A]">Role &amp; Responsibilities</div>
          <span x-html="roleBadge(profileFor?.role||'Member')"></span>
        </div>

        <template x-if="!profileDescEdit">
          <div>
            <!-- One line reads as prose; several read as a list. A lone bullet looks like a bug. -->
            <template x-if="roleDescLines(profileFor?.role_description).length===1">
              <p class="text-[12px] text-[#344054] leading-snug" x-text="roleDescLines(profileFor?.role_description)[0]"></p>
            </template>
            <template x-if="roleDescLines(profileFor?.role_description).length>1">
              <ul class="space-y-1.5">
                <template x-for="(line,li) in roleDescLines(profileFor?.role_description)" :key="li">
                  <li class="flex gap-2 text-[12px] text-[#344054] leading-snug">
                    <span class="text-[#98A2B3] shrink-0">•</span><span x-text="line"></span>
                  </li>
                </template>
              </ul>
            </template>
            <template x-if="!roleDescLines(profileFor?.role_description).length">
              <div class="text-[11px] text-[#98A2B3] italic">No responsibilities described yet.</div>
            </template>
            <?php if (is_admin()): ?>
            <button @click="profileDescDraft=profileFor?.role_description||''; profileDescEdit=true"
                    class="mt-3 text-[11px] font-bold px-3 py-1.5 rounded-[8px] border border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7] transition-colors">
              <span x-text="profileFor?.role_description ? 'Edit description' : 'Add description'"></span>
            </button>
            <?php endif; ?>
          </div>
        </template>

        <?php if (is_admin()): ?>
        <template x-if="profileDescEdit">
          <div>
            <textarea x-model="profileDescDraft" maxlength="600" rows="6"
                      placeholder="One responsibility per line…"
                      class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white resize-none"></textarea>
            <div class="flex items-center justify-between mt-1.5">
              <span class="text-[10px]" style="font-variant-numeric:tabular-nums"
                    :class="profileDescDraft.length>=600?'text-[#B42318] font-bold':'text-[#98A2B3]'"
                    x-text="profileDescDraft.length+' / 600'"></span>
              <div class="flex gap-2">
                <button @click="profileDescEdit=false" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
                <button @click="saveRoleDescription()" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#175CD3] text-white hover:bg-[#1349a8] transition-colors">Save</button>
              </div>
            </div>
          </div>
        </template>
        <?php endif; ?>
      </div>

      <!-- Account -->
      <div class="border border-[#E4E7EC] rounded-[10px] overflow-hidden">
        <div class="px-4 py-2.5 bg-[#F8FAFC] border-b border-[#E4E7EC] text-[12px] font-bold text-[#172B3A]">Account</div>
        <div class="divide-y divide-[#E4E7EC]">
          <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[11px]">
            <span class="text-[#667085]">Status</span><span x-html="statusPill(profileFor?.status||'pending')"></span>
          </div>
          <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[11px]">
            <span class="text-[#667085]">Role</span><span x-html="roleBadge(profileFor?.role||'Member')"></span>
          </div>
          <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[11px]">
            <span class="text-[#667085]">Email</span>
            <a :href="'mailto:'+(profileFor?.email||'')" class="font-semibold text-[#175CD3] hover:underline truncate" x-text="profileFor?.email"></a>
          </div>
          <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[11px]">
            <span class="text-[#667085]">Joined</span><span class="font-semibold text-[#344054]" x-text="profileFor?.requested_at"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer stays pinned so the primary action is reachable without scrolling -->
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 shrink-0 bg-white">
      <button @click="employee=profileFor.name; view='dashboard'; profileFor=null"
              class="w-full flex items-center justify-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">
        <svg class="w-3 h-3"><use href="#icon-file-text"/></svg> View their inquiries
      </button>
    </div>
  </div>
</div>

<!-- Delete Account Confirm -->
<?php if (is_master_admin()): ?>
<div x-show="confirmDeleteAccount" x-cloak @click.self="confirmDeleteAccount=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.55);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[12px] w-full max-w-[380px] p-5" style="box-shadow:0 20px 60px rgba(7,29,43,0.25)">
    <div class="flex items-center gap-2.5 mb-2">
      <div class="w-8 h-8 rounded-full bg-[#FEF3F2] flex items-center justify-center shrink-0 text-[#B42318]">✕</div>
      <h3 class="text-[15px] font-bold text-[#071D2B]">Delete user?</h3>
    </div>
    <p class="text-[12px] text-[#667085] mb-4 leading-[1.5]">
      This permanently removes <span class="font-bold text-[#172B3A]" x-text="confirmDeleteAccount?.name"></span>
      (<span x-text="confirmDeleteAccount?.email"></span>) from the system. This cannot be undone.
    </p>
    <div class="flex justify-end gap-2">
      <button @click="confirmDeleteAccount=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] border border-[#E4E7EC] text-[#344054] hover:bg-[#F2F4F7] transition-colors">Cancel</button>
      <button @click="accountAction('delete',confirmDeleteAccount.id,confirmDeleteAccount.name);confirmDeleteAccount=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#B42318] text-white hover:bg-[#9a1e14] transition-colors">Delete User</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Requirement popup -->
<div x-show="reqPopup.show" x-cloak
     :style="{ position:'fixed', left:reqPopup.x+'px', top:reqPopup.y+'px', zIndex:9999, maxWidth:'320px', maxHeight:'280px', overflowY:'auto', background:darkMode?'#151B23':'#fff', border:'1px solid '+(darkMode?'#2A323D':'#D1D9E6'), borderRadius:'10px', padding:'12px 14px', boxShadow:'0 8px 28px rgba(7,29,43,0.14)', pointerEvents:'none' }">
  <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#98A2B3] mb-1.5">Requirement</div>
  <div class="text-[12px] text-[#344054] leading-[1.7]" style="overflow-wrap:anywhere;word-break:break-word" x-text="reqPopup.text"></div>
</div>

<!-- ═══ INLINE ICON SPRITES (no external request) ════════════════════════════ -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <symbol id="icon-layout-dashboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></symbol>
  <symbol id="icon-check-square" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></symbol>
  <symbol id="icon-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></symbol>
  <symbol id="icon-bar-chart-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></symbol>
  <symbol id="icon-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
  <symbol id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="icon-shield-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></symbol>
  <symbol id="icon-log-out" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></symbol>
  <symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></symbol>
  <symbol id="icon-refresh-cw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></symbol>
  <symbol id="icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
  <symbol id="icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></symbol>
  <symbol id="icon-message-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></symbol>
  <symbol id="icon-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></symbol>
  <symbol id="icon-megaphone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></symbol>
  <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>
  <symbol id="icon-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></symbol>
  <symbol id="icon-chevron-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></symbol>
  <symbol id="icon-chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></symbol>
  <symbol id="icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
  <symbol id="icon-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
  <symbol id="icon-file-text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></symbol>
  <symbol id="icon-folder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2Z"/></symbol>
  <symbol id="icon-sticky-note" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.5 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3z"/><polyline points="14 3 14 9 20 9"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="12" y2="17"/></symbol>
  <symbol id="icon-trash-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></symbol>
  <symbol id="icon-edit-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></symbol>
  <symbol id="icon-paperclip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></symbol>
  <symbol id="icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></symbol>
  <symbol id="icon-building2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="18"/><path d="M16 8h4l3 3v10H16V8z"/><line x1="5" y1="7" x2="9" y2="7"/><line x1="5" y1="11" x2="9" y2="11"/><line x1="5" y1="15" x2="9" y2="15"/></symbol>
  <symbol id="icon-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></symbol>
  <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></symbol>
  <symbol id="icon-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
  <symbol id="icon-briefcase" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="12.01"/></symbol>
  <symbol id="icon-save" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></symbol>
  <symbol id="icon-chevron-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></symbol>
  <symbol id="icon-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></symbol>
  <symbol id="icon-alert-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></symbol>
  <symbol id="icon-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
  <symbol id="icon-printer" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
</svg>

<!-- ═══ ALPINE APP ════════════════════════════════════════════════════════════ -->
<script>
const ALL_STAGES   = <?= json_encode($allStages) ?>;
const ALL_OUTCOMES = <?= json_encode($allOutcomes) ?>;
// Exported from includes/functions.php so the Ownership picker, the dashboard and the report all
// read one definition of "closed" instead of three hand-copied variants that drift apart.
const CLOSED_OUTCOMES = <?= json_encode(CLOSED_OUTCOMES) ?>;
const CLOSED_STAGES   = <?= json_encode(CLOSED_STAGES) ?>;
window.__spCsrf = <?= json_encode(csrf_token()) ?>;

function spHeaders(json = true) {
  const h = { 'X-CSRF-Token': window.__spCsrf || '' };
  if (json) h['Content-Type'] = 'application/json';
  return h;
}

// ── One place that notices the session is gone ────────────────────────────────
// require_auth_json() answers 401 + {expired:true}. api() handled that, but ~36
// call sites use raw fetch() and only checked `ok`, so _startPolling() would sit
// there re-firing the same 401 every 10s for as long as the tab stayed open —
// which is the console flood of "api/ping.php 401" seen on the live server.
// Patching fetch once fixes every caller instead of 36 separate edits.
// ponytail: one guard at the boundary, not a check per call site.
(() => {
  const realFetch = window.fetch.bind(window);
  let leaving = false;
  window.fetch = async (...args) => {
    const res = await realFetch(...args);
    if (res.status === 401 && !leaving) {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url) || '';
      if (url.includes('api/')) {
        leaving = true;
        window.location.replace('login.php?expired=1');
      }
    }
    return res;
  };
})();

function spApp() {
  return {
    // ── Seeded from PHP ──
    inquiries: <?= json_encode($inquiries, JSON_HEX_TAG) ?>,
    accounts:  <?= json_encode($accounts,  JSON_HEX_TAG) ?>,
    currentUserId: <?= json_encode((int)$user['id']) ?>,
    currentUser:  <?= json_encode($user['name'],  JSON_HEX_TAG) ?>,
    currentRole:  <?= json_encode($user['role'],  JSON_HEX_TAG) ?>,
    currentEmail: <?= json_encode($user['email'], JSON_HEX_TAG) ?>,

    // ── UI state ──
    view: 'dashboard',
    collapsed: false,
    _mob: false,
    // Sidebar accordion groups (Work / Insight / Team / Comms)
    navOpen: { work: true, insight: false, team: false, comms: false },
    navViewGroup: {
      dashboard:'work', inquiries:'work', tasks:'work', calendar:'work', reminders:'work', notes:'work', resources:'work',
      reports:'insight',
      teams:'team', leave:'team', warnings:'team', approvals:'team',
      messages:'comms', announcements:'comms'
    },
    statFilter: '',
    notifsOpen: false,
    toasts: [],
    notifications: [],
    onlineIds: [],
    lastSeen: {},
    contacts: [],
    msgSearch: '',
    activeContact: null,
    thread: [],
    threadLoading: false,
    messageText: '',
    unreadMessages: 0,
    announcements: [],
    announcementText: '',
    _postingAnnouncement: false,
    _sendingAttachment: false,
    annEditId: null,
    annEditText: '',
    _savingAnnouncement: false,
    unreadAnnouncements: 0,
    // ── Resources (shared docs) ──
    resources: [],
    resSearch: '',
    resTitle: '',
    resEditId: null,
    resEditTitle: '',
    _resUploading: false,
    // ── Notes (private) ──
    callNotes: [],
    noteSearch: '',
    noteForm: { title: '', body: '' },
    noteViewFor: null,
    noteEditId: null,
    noteEditTitle: '',
    noteEditBody: '',
    _noteSaving: false,
    leaves: [],
    leaveSummary: { entitlement: 18, taken: 0, pending: 0, remaining: 18, year: new Date().getFullYear() },
    leaveFilter: 'all',
    leaveTypeFilter: '',
    leaveUserFilter: '',
    leaveMonth: new Date().toISOString().slice(0,7),
    leaveTypes: ['Casual Leave','WFH','Sick Leave','Emergency Leave','Half Day'],
    leaveAdvanceTypes: ['Casual Leave','WFH','Sick Leave'],
    leaveShortNoticeTypes: ['Emergency Leave'],
    leaveForm: { leaveType: 'Emergency Leave', startDate: new Date().toLocaleDateString('en-CA'), endDate: new Date().toLocaleDateString('en-CA'), reason: '' },
    leaveErr: '',
    leaveRejectFor: null,
    leaveRejectNote: '',
    pendingLeaves: 0,
    _leaveSaving: false,
    warnings: [],
    myWarnings: <?= json_encode($myWarnings) ?>,
    myDeduction: 0,
    warnStrike: 3,   // overwritten by api/warnings.php that file is the source of truth
    warnAmount: 500,
    warnUserFilter: '',
    warnForm: { accountId: '', reason: '' },
    warnErr: '',
    _warnSaving: false,
    // ── Teams ──
    teams: [],
    teamStatuses: ['Pending','In Progress','Done'], // overwritten by api/teams.php, the source of truth
    teamShowDone: false,   // board only — My tasks uses myWorkFilter chips instead
    myWorkFilter: 'open',  // open | overdue | done
    teamSelected: 0,       // which team's board is on screen; loadTeams() keeps it valid
    teamFormOpen: false,
    teamForm: { id: 0, name: '', description: '' }, // id 0 = creating a new team
    teamErr: '',
    teamMemberDraft: [],   // account ids the team form will save as the roster
    teamMemberSearch: '',
    teamFormLeadId: 0,     // the lead cannot be unticked out of their own team
    teamTaskFor: null,
    teamTaskForm: { assignedTo: '', title: '', requirement: '', due: '', dueTime: '' },
    teamTaskErr: '',
    _teamSaving: false,
    highlightedInq: null,
    highlightedStep: null,
    dueWarnOpen: false,
    dueWarnList: [],
    _dueWarned: {},
    reqPopup: { show: false, text: '', x: 0, y: 0 },

    // ── Filters ──
    search: '',
    employee: '',
    categoryFilter: '',
    typeFilter: '',
    dateFilter: '',
    inqMonth: new Date().toLocaleDateString('en-CA').slice(0,7),  // '' = every month; portal opens on the current one
    _autoMonth: new Date().toLocaleDateString('en-CA').slice(0,7), // last month the portal picked by itself
    _inqPage: 0,

    // ── Inquiry calendar (own view) ──
    calMonth: new Date().toLocaleDateString('en-CA').slice(0,7), // 'YYYY-MM'
    calDate: '',                                                 // '' | 'YYYY-MM-DD'
    calUser: <?= json_encode(is_admin() ? $user['name'] : '', JSON_HEX_TAG) ?>, // admin only: whose calendar is shown
    holidays: [],
    holManageList: [],
    holYearLoaded: 0,
    holManageYear: new Date().getFullYear(),
    holManageOpen: false,
    holFormOpen: false,
    holSaving: false,
    holErr: '',
    holDefaults: { festival: '#F79009', leave: '#7A5AF8', other: '#667085' },
    holForm: { id: 0, holiday_date: '', title: '', holiday_type: 'leave', color: '#7A5AF8' },
    _now: Date.now(),                                            // ticks every minute so Pending → Overdue flips live

    darkMode: localStorage.getItem('sp_theme')==='dark',
    toggleDarkMode(){ this.darkMode=!this.darkMode; localStorage.setItem('sp_theme', this.darkMode?'dark':'light'); },

    // ── Modals ──
    addInquiryOpen: false,
    _stepUploadFor: null,
    _stepUploadFiles: [],
    _stepUploading: false,
    _stageSaving: false,
    _addingInquiry: false,
    _inquirySave: '', // '' | 'saving' | 'saved'
    _taskSaving: false,
    _pollBase: { inquiry:'', pending:0, unread:0 },
    stageUpdateFor: null,
    addTaskFor: null,
    summaryFor: null,
    _reqExpanded: false,
    completeInquiryFor: null,
    adminRemarkFor: null,
    teamRemarkFor: null,
    completeInquiryRemark: '',
    _completeInquiryErr: false,
    _completingInquiry: false,

    // ── Follow-ups ──
    todayFollowUps: <?= json_encode($todayFollowUps, JSON_HEX_TAG) ?>,
    followUps: {},
    fuForm: { date: new Date().toLocaleDateString('en-CA'), time:'', note:'', assignedTo:'' },
    fuOpenFor: null,
    confirmDeleteAccount: null,

    // ── My Tasks view ──
    taskViewFilter: 'to_me', // 'to_me' | 'by_me'
    taskGroupsClosed: [],    // due-groups the user has folded away

    // ── Reminders view ──
    rmFollowUps: <?= json_encode($allFollowUps, JSON_HEX_TAG) ?>,
    rmFilter: 'all',
    // Admins are served the whole team's follow-ups (the calendar's user picker needs them);
    // the Reminders view stays personal.
    get rmMine() { return this.rmFollowUps.filter(f => this._fuBelongsTo(f, this.currentUser)); },
    get rmFiltered() {
      const today = new Date().toLocaleDateString('en-CA');
      return this.rmMine.filter(f => {
        if (this.rmFilter==='today')    return !f.completed && f.follow_up_date===today;
        if (this.rmFilter==='upcoming') return !f.completed && f.follow_up_date>today;
        if (this.rmFilter==='overdue')  return !f.completed && f.follow_up_date<today;
        if (this.rmFilter==='done')     return !!f.completed;
        return !f.completed; // 'all'  never show completed here, those live in History
      });
    },
    rmAddOpen: false,
    rmAddErr: '',
    rmAddForm: { inquiryId:'', note:'', date: new Date().toLocaleDateString('en-CA'), time:'', assignedTo:'' },
    // ── Reports ──
    rptUser: '',
    rptShowOverall: false, // admin secondary: overall team (people list is primary)
    rptSort: 'risk',     // risk | ontime | overdue | close | avgclose | name
    rptPeriod: 'month',
    // Office day for Internal Usage start checks (local clock).
    // First Internal of the day should be created before 11:30; 11:30+ counts as late.
    rptOfficeStartMins: 10 * 60 + 30, // 10:30
    rptLateStartMins:   11 * 60 + 30, // 11:30
    rptFrom: '',
    rptTo: '',
    rptVolumeBucket: 'auto', // auto | day | week | month | quarter | year
    customRoleFor: null,
    rptVolumeChart: null,
    rptPerfChart: null,

    // ── Member profile drawer ──
    profileFor: null,        // the account object being shown, null = closed
    profileDescEdit: false,
    profileDescDraft: '',

    // ── Team directory (Approvals view) ──
    acctSearch: '',
    acctStatusFilter: '',    // '' = everyone, else 'approved' | 'blocked' | 'rejected'
    get acctStats() {
      const s = { total: this.accounts.length, active: 0, pending: 0, blocked: 0 };
      this.accounts.forEach(a => {
        if      (a.status==='approved') s.active++;
        else if (a.status==='pending')  s.pending++;
        else if (a.status==='blocked')  s.blocked++;
      });
      return s;
    },
    // Pending sign-ups are excluded they have their own review card above the directory.
    // Search covers the role description too, so "who runs fieldwork QC" is findable.
    get acctRows() {
      const q = this.acctSearch.trim().toLowerCase();
      return this.accounts.filter(a => {
        if (a.status === 'pending') return false;
        if (this.acctStatusFilter && a.status !== this.acctStatusFilter) return false;
        if (!q) return true;
        return (a.name+' '+a.email+' '+(a.role||'')+' '+(a.role_description||'')).toLowerCase().includes(q);
      });
    },

    // ── Forms ──
    stageForm: { ownership:'Open', stageNum:1, stageStep:'', remark:'', deliveryDate:'', followUpDate:'', proposalValue:'', finalValue:'', finalRemark:'', _finalRemarkErr:false },
    taskForm:  { assignedTo:'', due: new Date().toLocaleDateString('en-CA'), dueTime:'', instruction:'', errors:{} },
    newUser:   { name:'', email:'', password:'', role:'Member', customRole:'', error:'', success:'', showPass:false, saving:false, copied:false },
    addInquiryForm: { inquiryType:'Client Project', date: new Date().toLocaleDateString('en-CA'), projectCategory:'', client:'', designation:'', company:'', country:'India', clientType:'New', requirement:'', assignTo:'', dueDate:'', dueTime:'', proposalValue:'', email:'', emailSecondary:'', phone:'', website:'', emailSubject:'', taskInstruction:'', createdBy:'', errors:{} },
    projectCategories: [],
    _clientAcOpen: false,
    _allClientNames: <?= json_encode($_allClientNames, JSON_HEX_TAG) ?>,

    // ── Init ──
    init() {
      this.inquiries = this.inquiries.map(i => ({
        ...i,
        _open: false,
        _adminRemark: i.admin_remark || '',
        _adminRemarkSaving: false,
        _teamRemark: i.team_remark || '',
        _teamRemarkSaving: false,
        steps: (i.steps||[]).map(s => ({ ...s, _remark: s.remark||'', _saved: false, _remarkOpen: false, _instruction: s.instruction||'', _instrSaved: false }))
      }));
      this.addInquiryForm.createdBy = this.currentUser;
      this.loadProjectCategories();
      this.fetchNotifications();
      // Unlock Web Audio on first user gesture so due alerts can play later.
      const unlockAudio = () => {
        try {
          const Ctx = window.AudioContext || window.webkitAudioContext;
          if (!Ctx) return;
          if (!this._audioCtx || this._audioCtx.state === 'closed') this._audioCtx = new Ctx();
          if (this._audioCtx.state === 'suspended') this._audioCtx.resume();
        } catch (_) {}
        window.removeEventListener('pointerdown', unlockAudio);
        window.removeEventListener('keydown', unlockAudio);
      };
      window.addEventListener('pointerdown', unlockAudio, { once: true });
      window.addEventListener('keydown', unlockAudio, { once: true });
      setInterval(() => this.fetchNotifications(), 30000);
      // Clock + month roll every 30s; due-soon popup every 15s so it appears without a full refresh.
      setInterval(() => {
        this._now = Date.now();
        this._rollMonth(this.todayStr.slice(0,7));
      }, 30000);
      setInterval(() => {
        this._now = Date.now();
        this.checkDueWarnings();
      }, 15000);
      this.$nextTick(() => this.checkDueWarnings());
      // Catch dues that enter the window while the tab was in the background.
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          this._now = Date.now();
          this.checkDueWarnings();
        }
      });
      this.$watch('view', () => {
        this.openNavForView(this.view);
        this.search=''; this._mob=false; this.statFilter=''; this.calDate=''; this.fuOpenFor=null; this._inqPage=0; this.msgSearch=''; this.noteSearch=''; this.noteViewFor=null; this.resSearch=''; this.resEditId=null;
        if (this.view==='resources') this.loadResources(); if (this.view==='messages') this.refreshContacts(); if (this.view==='announcements') this.loadAnnouncements(); if (this.view==='leave') this.loadLeaves(); if (this.view==='warnings') this.loadWarnings(); if (this.view==='teams') this.loadTeams(); if (this.view==='calendar') this.loadHolidays(); if (this.view==='notes') this.loadNotes();
      });
      this.openNavForView(this.view);
      this.$watch('calMonth', () => { if (this.view==='calendar') this.loadHolidays(); });
      if (this.view==='calendar') this.loadHolidays();
      ['search','statFilter','categoryFilter','typeFilter','employee','dateFilter'].forEach(k => this.$watch(k, () => { this._inqPage=0; }));
      this._startPolling();
      this.$watch('addInquiryForm.client', name => {
        const t = (name||'').trim().toLowerCase();
        if (t.length < 2) { this.addInquiryForm._clientExists = false; return; }
        const match = this.inquiries.find(i => i.client.toLowerCase() === t);
        if (match) {
          this.addInquiryForm._clientExists = true;
          if (!this.addInquiryForm.designation.trim())    this.addInquiryForm.designation    = match.designation     || '';
          if (!this.addInquiryForm.company.trim())        this.addInquiryForm.company        = match.company         || '';
          if (!this.addInquiryForm.country)               this.addInquiryForm.country        = match.country         || 'India';
          if (!this.addInquiryForm.phone.trim())          this.addInquiryForm.phone          = match.phone           || '';
          if (!this.addInquiryForm.emailSecondary.trim()) this.addInquiryForm.emailSecondary = match.secondary_email || '';
          if (!this.addInquiryForm.website.trim())        this.addInquiryForm.website        = match.website         || '';
          const prev = match.client_type || 'New';
          this.addInquiryForm.clientType = prev === 'New' ? 'Repeat' : prev;
        } else {
          this.addInquiryForm._clientExists = false;
        }
      });
    },

    toggleMenu() {
      if (window.innerWidth < 768) this._mob = !this._mob;
      else this.collapsed = !this.collapsed;
    },
    toggleNavGroup(key) {
      this.navOpen[key] = !this.navOpen[key];
    },
    openNavForView(viewId) {
      const g = this.navViewGroup[viewId];
      if (g) this.navOpen[g] = true;
    },
    navGroupHasActive(key) {
      return Object.keys(this.navViewGroup).some(v => this.navViewGroup[v] === key && this.view === v);
    },

    async _startPolling() {
      const changed = (a, b) => a !== b;
      const poll = async () => {
        try {
          const d = await fetch('api/ping.php').then(r=>r.json());
          if (d.csrf) window.__spCsrf = d.csrf;
          if (!d.ok) return;
          this.onlineIds = (d.onlineIds || []).map(Number);
          this.lastSeen  = d.lastSeen || {};
          // First run: set baseline silently
          if (!this._pollBase.inquiry) {
            this._pollBase = {
              inquiry: d.latestInquiry, step: d.latestStep, stepUpd: d.latestStepUpd,
              att: d.latestAtt, inqAtt: d.latestInqAtt,
              history: d.latestHistory, fu: d.latestFollowUp, fuCompleted: d.latestFuCompleted,
              pending: d.pendingApprovals, unread: d.unreadNotifications, unreadMsg: d.unreadMessages, unreadAnn: d.unreadAnnouncements, pendingLeaves: d.pendingLeaves || 0
            };
            this.unreadMessages = d.unreadMessages;
            this.unreadAnnouncements = d.unreadAnnouncements;
            this.pendingLeaves = d.pendingLeaves || 0;
            return;
          }
          // Any inquiry-level change: new inquiry, stage update, step add/update, any attachment, follow-up change
          const needsRefresh =
            changed(d.latestInquiry,    this._pollBase.inquiry)  ||
            changed(d.latestStep,       this._pollBase.step)      ||
            changed(d.latestStepUpd,    this._pollBase.stepUpd)   ||
            changed(d.latestAtt,        this._pollBase.att)        ||
            changed(d.latestInqAtt,     this._pollBase.inqAtt)     ||
            changed(d.latestHistory,    this._pollBase.history);
          if (needsRefresh) {
            const isNewInquiry = changed(d.latestInquiry, this._pollBase.inquiry);
            this._pollBase.inquiry = d.latestInquiry;
            this._pollBase.step    = d.latestStep;
            this._pollBase.stepUpd = d.latestStepUpd;
            this._pollBase.att     = d.latestAtt;
            this._pollBase.inqAtt  = d.latestInqAtt;
            this._pollBase.history = d.latestHistory;
            await this.refreshInquiries();
            if (isNewInquiry) this.addToast('New inquiry received', 'info');
          }
          // Follow-up added or completed  invalidate followUps cache so strips refresh on next open
          if (changed(d.latestFollowUp, this._pollBase.fu) || changed(d.latestFuCompleted, this._pollBase.fuCompleted)) {
            this._pollBase.fu          = d.latestFollowUp;
            this._pollBase.fuCompleted = d.latestFuCompleted;
            this.followUps = {}; // clear cache  strips reload lazily on next expand
          }
          // New pending approval request
          if (d.pendingApprovals > this._pollBase.pending) {
            this._pollBase.pending = d.pendingApprovals;
            const r = await fetch('api/accounts.php').then(r=>r.json());
            if (r.ok) this.accounts = r.data;
            this.addToast('New user approval request', 'info');
          } else {
            this._pollBase.pending = d.pendingApprovals;
          }
          // New notifications
          if (d.unreadNotifications > this._pollBase.unread) {
            this._pollBase.unread = d.unreadNotifications;
            const r = await fetch('api/notifications.php').then(r=>r.json());
            if (r.ok) this.notifications = r.data;
          } else {
            this._pollBase.unread = d.unreadNotifications;
          }
          // New messages  live-refresh the contact list and any open thread
          if (d.unreadMessages !== this._pollBase.unreadMsg) {
            this._pollBase.unreadMsg = d.unreadMessages;
            this.unreadMessages = d.unreadMessages;
            if (this.view === 'messages') {
              await this.refreshContacts();
              if (this.activeContact) await this.refreshThread();
            }
          }
          // New announcements  live-refresh the feed if it's open, badge otherwise
          if (d.unreadAnnouncements !== this._pollBase.unreadAnn) {
            this._pollBase.unreadAnn = d.unreadAnnouncements;
            if (this.view === 'announcements') await this.loadAnnouncements();
            else this.unreadAnnouncements = d.unreadAnnouncements;
          }
          const pendingLeaves = d.pendingLeaves || 0;
          if (pendingLeaves !== this._pollBase.pendingLeaves) {
            const was = this._pollBase.pendingLeaves;
            this._pollBase.pendingLeaves = pendingLeaves;
            this.pendingLeaves = pendingLeaves;
            if (this.view === 'leave') await this.loadLeaves();
            else if (pendingLeaves > was) this.addToast('New leave request', 'info');
          }
        } catch(e) { /* silent  offline or server busy */ }
      };
      await poll(); // immediate first ping to set baseline
      // ponytail: self-rescheduling timeout instead of setInterval so the delay can adapt each tick
      const schedule = () => {
        const fast = this.view === 'messages' || this.view === 'announcements';
        setTimeout(async () => { await poll(); schedule(); }, fast ? 3000 : 10000);
      };
      schedule();
    },

    _remap(data) {
      return data.map(i => {
        const old = this.inquiries.find(x => x.id === i.id);
        return {
          ...i,
          _open: old?._open || false,
          _adminRemark: old?._adminRemark ?? i.admin_remark ?? '',
          _adminRemarkSaving: false,
          _teamRemark: old?._teamRemark ?? i.team_remark ?? '',
          _teamRemarkSaving: false,
          history: old?.history || [],
          _historyLoaded: old?._historyLoaded || false,
          steps: (i.steps||[]).map(s => {
            const os = (old?.steps||[]).find(x => x.id === s.id);
            const reassigned = os && os.assigned_to !== s.assigned_to;
            return { ...s, _remark: reassigned ? (s.remark || '') : (os?._remark ?? s.remark ?? ''), _saved: os?._saved || false, _remarkOpen: os?._remarkOpen || false, _instruction: s.instruction||'', _instrSaved: false };
          })
        };
      });
    },

    // ── Computed ──
    get isAdmin()      { return this.currentRole==='Master Admin'||this.currentRole==='Admin'; },
    get isMasterAdmin(){ return this.currentRole==='Master Admin'; },
    get isClient()     { return this.currentRole==='Client'; },
    get pendingCount() { return this.accounts.filter(a=>a.status==='pending').length; },
    get unreadCount()  { return this.notifications.filter(n=>!n.is_read).length; },
    isOnline(id) {
      return this.onlineIds.some(x => Number(x) === Number(id));
    },
    lastSeenLabel(id) {
      const ts = this.lastSeen[id] || this.lastSeen[String(id)];
      if (!ts) return 'Offline';
      const t = new Date(String(ts).replace(' ','T')).getTime();
      if (!t) return 'Offline';
      const mins = Math.round((Date.now() - t) / 60000);
      if (mins < 1)  return 'Last seen just now';
      if (mins < 60) return 'Last seen '+mins+'m ago';
      const hrs = Math.round(mins / 60);
      if (hrs < 24)  return 'Last seen '+hrs+'h ago';
      return 'Last seen '+new Date(t).toLocaleDateString([], {day:'2-digit', month:'short'});
    },
    get messagingContacts() {
      const summaryById = {};
      this.contacts.forEach(c => summaryById[c.account_id] = c);
      return this.accounts
        .filter(a => a.id !== this.currentUserId && a.status === 'approved')
        .filter(a => !this.isClient || a.role !== 'Client')
        .map(a => {
          const s = summaryById[a.id];
          return { ...a, unread: s ? s.unread : 0, last_at: s ? s.last_at : '', last_body: s ? s.last_body : '',
                   _lastTime: s && s.last_at ? this._fmtTime(s.last_at) : '',
                   online: this.isOnline(a.id),
                   _initials: a.name.split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase() };
        })
        .sort((x,y) => (y.online - x.online) || (y.unread - x.unread) || (y.last_at > x.last_at ? 1 : -1) || x.name.localeCompare(y.name));
    },
    get filteredContacts() {
      const q = this.msgSearch.trim().toLowerCase();
      if (!q) return this.messagingContacts;
      return this.messagingContacts.filter(c => c.name.toLowerCase().includes(q) || c.role.toLowerCase().includes(q));
    },

    get viewingEmployee() { return !!this.employee && this.employee !== this.currentUser; },

    get viewTitle() {
      return {dashboard:'Dashboard',tasks:'My Tasks',approvals:'User Management',reminders:'Reminders',reports:'Reports',messages:'Messages',calendar:'Inquiry Calendar',announcements:'Announcements',leave:'Leave',warnings:'Warnings',teams:'Teams',notes:'Notes',resources:'Resources'}[this.view]||'';
    },

    get filteredNotes() {
      const q = (this.noteSearch || '').trim().toLowerCase();
      if (!q) return this.callNotes;
      return this.callNotes.filter(n =>
        (n.title || '').toLowerCase().includes(q) || (n.body || '').toLowerCase().includes(q)
      );
    },

    get clientSuggestions() {
      const q = (this.addInquiryForm.client || '').trim().toLowerCase();
      if (q.length < 1) return [];
      return this._allClientNames.filter(c => c.toLowerCase().includes(q)).slice(0, 6);
    },

    get clientCheck() {
      const name = this.addInquiryForm.client.trim();
      const type = this.addInquiryForm.clientType;
      if (name.length < 2) return null;
      const exists = this.inquiries.some(i => i.client.toLowerCase() === name.toLowerCase());
      if (type === 'New') {
        return exists
          ? { kind:'warning', text:'A client named "'+name+'" already exists in records. Consider changing the type to "Existing" or "Repeat".' }
          : { kind:'success', text:'Client is new no existing records found.' };
      }
      return exists
        ? { kind:'info', text:'Client found in existing records.' }
        : { kind:'warning', text:'No records found for "'+name+'". If this is a new client, change the type to "New".' };
    },

    // Owner, creator, or assigned on any step the one definition of "this user's inquiry"
    _inqBelongsTo(inq, name) {
      return inq.current_owner===name || inq.created_by===name
          || (inq.steps||[]).some(s => s.assigned_to===name || s.assigned_by===name);
    },

    _fuBelongsTo(fu, name) { return fu.assigned_to===name || fu.created_by===name; },

    get visibleInquiries() {
      if (this.isAdmin) return this.inquiries;
      // Server already filters by role; mirror here so client-side stats are consistent
      return this.inquiries.filter(inq => {
        const hasStep = (inq.steps||[]).some(s => s.assigned_to===this.currentUser || s.assigned_by===this.currentUser);
        return inq.created_by===this.currentUser || inq.current_owner===this.currentUser || hasStep;
      });
    },

    // Midnight rollover for a tab left open across a month boundary. Only moves the scope when the
    // user is still sitting on the month the portal picked itself a hand-picked month, or
    // "All months", is never stomped mid-session.
    _rollMonth(nowMonth) {
      if (nowMonth === this._autoMonth) return;
      if (this.inqMonth === this._autoMonth) this.inqMonth = nowMonth;
      // Calendar rolls on the same guard; a day picked in the old month is no longer on the grid
      if (this.calMonth === this._autoMonth) { this.calMonth = nowMonth; this.calDate = ''; }
      this._autoMonth = nowMonth;
    },

    // Rows for the month dropdown: every month that has inquiries, newest first. `count` is the
    // whole month, `open` is what is still unclosed the badge that says a past month needs work.
    get monthTabs() {
      const cur = this.todayStr.slice(0,7);
      const counts = { [cur]: 0 }, open = {}; // current month always listed, even on day one
      this.visibleInquiries.forEach(i => {
        const k = this._inqDateKey(i).slice(0,7);
        if (!k) return;
        counts[k] = (counts[k] || 0) + 1;
        if (!this.isClosed(i)) open[k] = (open[k] || 0) + 1;
      });
      return Object.keys(counts).sort().reverse()
        .map(k => ({ key:k, count:counts[k], open:open[k] || 0, label:this.monthLabel(k) }));
    },

    // Safety net for the strict month boundary: unclosed inquiries parked in months other than
    // the one on screen. Surfaced on the dropdown so August's leftovers can't be forgotten.
    get otherMonthsOpen() {
      if (!this.inqMonth) return 0; // "All months" already shows everything
      return this.monthTabs.reduce((n, m) => n + (m.key === this.inqMonth ? 0 : m.open), 0);
    },

    // Portal shows one month at a time, strictly by creation month September lists only what
    // September created. Nothing is archived or deleted: an older month's inquiries (open ones
    // included) are worked and closed by picking that month in the dropdown.
    get monthInquiries() {
      if (!this.inqMonth) return this.visibleInquiries;
      return this.visibleInquiries.filter(i => this._inqDateKey(i).slice(0,7) === this.inqMonth);
    },

    get filtered() {
      // Split once, not per row. Multiple words are ANDed, so "asha proposal" finds the inquiry
      // Asha owns that is at proposal stage order and field don't matter.
      const terms = this.search ? this.search.toLowerCase().split(/\s+/).filter(Boolean) : null;
      return this.monthInquiries.filter(inq => {
        const isClosed = i => this.isClosed(i);
        if (this.statFilter==='done'       && !isClosed(inq))        return false;
        if (this.statFilter==='received'   && (isClosed(inq) || inq.stage!=='Inquiry')) return false;
        if (this.statFilter==='inProgress' && (isClosed(inq) || inq.stage==='Inquiry')) return false;
        // Drill-down must match the card above it — an inquiry someone else owns is not my overdue.
        if (this.statFilter==='overdue'    && !this.isOverdueFor(inq, this.overdueSubject)) return false;
        if (this.employee && !this._inqBelongsTo(inq, this.employee)) return false;
        if (this.categoryFilter && inq.project_category!==this.categoryFilter) return false;
        if (this.typeFilter && inq.inquiry_type!==this.typeFilter) return false;
        if (this.dateFilter==='today' && !this._isCreatedToday(inq)) return false;
        if (terms) {
          const hay = this._haystack(inq);
          if (!terms.every(t => hay.includes(t))) return false;
        }
        return true;
      });
    },

    get pagedInqs() {
      const s = this._inqPage * 15;
      return this.filtered.slice(s, s + 15);
    },

    // Compact page list: 1 … 4 5 6 … 48 — never dump every page into the footer.
    get inqPageList() {
      const total = Math.ceil(this.filtered.length / 15);
      if (total <= 1) return [];
      if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
      const cur = this._inqPage + 1;
      const set = new Set([1, total, cur, cur - 1, cur + 1, cur - 2, cur + 2]);
      const pages = [...set].filter(p => p >= 1 && p <= total).sort((a, b) => a - b);
      const out = [];
      let prev = 0;
      pages.forEach(p => {
        if (prev && p - prev > 1) out.push('...');
        out.push(p);
        prev = p;
      });
      return out;
    },

    get stats() {
      const v = this.monthInquiries;
      // Closed: Stage 5 steps OR Stage 4 lost path "Inquiry Closed" OR legacy closed
      const isClosed = i => this.isClosed(i);
      // Inquiry Received: Stage 1, not yet actioned
      const isReceived = i => !isClosed(i) && i.stage==='Inquiry';
      // In Progress: Stages 2-4 active (not closed, past initial receipt)
      const isInProgress = i => !isClosed(i) && i.stage!=='Inquiry';
      return {
        total:      v.length,
        received:   v.filter(isReceived).length,
        inProgress: v.filter(isInProgress).length,
        done:       v.filter(isClosed).length,
        overdue:    v.filter(i=>this.isOverdueFor(i, this.overdueSubject)).length,
      };
    },

    get chips() {
      const sfLabel = {received:'Inquiry Received',inProgress:'In Progress',done:'Closed',overdue:'Overdue'};
      return [
        this.statFilter  && { label:'Filter: '+(sfLabel[this.statFilter]||this.statFilter), clear:()=>this.statFilter='' },
        this.employee    && { label:'Employee: '+this.employee,   clear:()=>this.employee='' },
        this.categoryFilter && { label:'Category: '+this.categoryFilter, clear:()=>this.categoryFilter='' },
        this.typeFilter  && { label:'Type: '+this.typeFilter,     clear:()=>this.typeFilter='' },
        this.dateFilter  && { label:'Created: Today',             clear:()=>this.dateFilter='' },
        this.inqMonth !== this.todayStr.slice(0,7) && { label: this.inqMonth ? 'Month: '+this.monthLabel(this.inqMonth) : 'All months', clear:()=>this.inqMonth=this.todayStr.slice(0,7) },
      ].filter(Boolean);
    },

    get customRoles() {
      const std = ['Client','Member','Master Admin'];
      return [...new Set(this.accounts.map(a=>a.role).filter(r=>!std.includes(r)))];
    },

    // Both lists skip closed inquiries: once an inquiry is won, lost or paid, its leftover steps
    // are not work anyone still owes, so they drop off My Tasks instead of nagging forever.
    get tasksAssignedToMe() {
      const out = [];
      this.inquiries.forEach(inq => {
        if (this.isClosed(inq)) return;
        (inq.steps||[]).forEach(step => {
          if (step.assigned_to===this.currentUser && !['Done','Completed','Cancelled'].includes(step.status))
            out.push({ inq, step });
        });
      });
      return out;
    },
    get tasksAssignedByMe() {
      const out = [];
      this.inquiries.forEach(inq => {
        if (this.isClosed(inq)) return;
        (inq.steps||[]).forEach(step => {
          if (step.assigned_by===this.currentUser && step.assigned_to!==this.currentUser && !['Done','Completed','Cancelled'].includes(step.status))
            out.push({ inq, step });
        });
      });
      return out;
    },

    // A step keeps date and time in one string: "01 Sep 2026 · 02:30 PM".
    stepDueDate(step) { return String(step.due || '').split('·')[0].trim(); },

    // Rows written before the due column was widened lost their AM/PM to truncation ("· 02:30 ").
    // The server reads those as end of day, so hide the fragment here rather than show a half-time
    // that nothing honours. Same test as due_ts() in includes/functions.php keep them in step.
    stepDueTime(step) {
      const t = (String(step.due || '').split('·')[1] || '').trim();
      return /^\d{1,2}:\d{2}\s*[AP]M$/i.test(t) ? t.toUpperCase() : '';
    },

    // Due state for a task card. Reuses the server's `overdue` flag as the single source of truth
    // for lateness (it already accounts for status and the parent inquiry) and only adds the
    // "today" distinction, which a plain overdue/not-overdue split cannot express.
    stepDueState(step) {
      if (!step.due || step.due === 'TBD') return 'none';
      if (step.overdue) return 'overdue';
      const d = new Date(this.stepDueDate(step));
      if (isNaN(d)) return 'none';
      return d.toLocaleDateString('en-CA') === this.todayStr ? 'today' : 'upcoming';
    },

    stepDueLabel(step) {
      const raw = this.stepDueDate(step);
      switch (this.stepDueState(step)) {
        case 'none':  return 'No due date';
        case 'today': return 'Due today';
        case 'overdue': {
          const days = Math.round((new Date(this.todayStr) - new Date(raw)) / 86400000);
          return days <= 0 ? 'Overdue' : days + (days === 1 ? ' day overdue' : ' days overdue');
        }
        default: return 'Due ' + raw;
      }
    },
    toggleTaskGroup(key) {
      this.taskGroupsClosed = this.taskGroupsClosed.includes(key)
        ? this.taskGroupsClosed.filter(k => k !== key)
        : [...this.taskGroupsClosed, key];
    },

    // The list as report sections. myTasks is already urgency-sorted, so bucketing keeps that
    // order inside each group and empty groups simply don't render.
    get myTaskGroups() {
      const labels = { overdue:'Overdue', today:'Due today', upcoming:'Upcoming', none:'No due date' };
      return ['overdue','today','upcoming','none']
        .map(k => ({ key:k, label:labels[k], items:this.myTasks.filter(t => this.stepDueState(t.step) === k) }))
        .filter(g => g.items.length);
    },

    // Most urgent first a task list sorted by inquiry id makes you hunt for what's actually late.
    get myTasks() {
      const rank = { overdue:0, today:1, upcoming:2, none:3 };
      const ts   = s => { const d = new Date(this.stepDueDate(s)); return isNaN(d) ? Infinity : d.getTime(); };
      const list = this.taskViewFilter==='by_me' ? this.tasksAssignedByMe : this.tasksAssignedToMe;
      return [...list].sort((a,b) =>
        rank[this.stepDueState(a.step)] - rank[this.stepDueState(b.step)] || ts(a.step) - ts(b.step));
    },

    get teamWorkload() {
      const counts = {};
      this.visibleInquiries.forEach(inq => {
        (inq.steps||[]).forEach(s => {
          if (s.status !== 'Done' && s.status !== 'Cancelled' && s.assigned_to)
            counts[s.assigned_to] = (counts[s.assigned_to]||0) + 1;
        });
      });
      return Object.entries(counts).map(([name,tasks])=>({name,tasks})).sort((a,b)=>b.tasks-a.tasks);
    },


    // ── Helpers ──
    resetFilters() { this.search=''; this.employee=''; this.categoryFilter=''; this.typeFilter=''; this.statFilter=''; this.dateFilter=''; this.inqMonth=this.todayStr.slice(0,7); this._inqPage=0; },

    initials(name) { return (name||'').split(' ').slice(0,2).map(w=>w[0]).join('').toUpperCase(); },

    stageLabel(stage) {
      const n = {'Inquiry':1,'Communication / Proposal':2,'Decision':3,'Project Execution':4,'Closure':5};
      const label = {'Closure':'Closed'};
      return n[stage] ? 'S'+n[stage]+' · '+(label[stage]||stage) : stage;
    },
    stageClass(stage) {
      const m={ 
        'Inquiry':'bg-[#EEF4FF] text-[#175CD3]',
        'Communication / Proposal':'bg-[#F4F3FF] text-[#5925DC]',
        'Decision':'bg-[#FFF6ED] text-[#C4320A]',
        'Project Execution':'bg-[#EFF8FF] text-[#026AA2]',
        'Closure':'bg-[#ECFDF3] text-[#166534]',
        // legacy
        'Inquiry Received':'bg-[#EEF4FF] text-[#175CD3]',
        'Proposal Sent':'bg-[#F4F3FF] text-[#5925DC]',
        'Proposal Won':'bg-[#ECFDF3] text-[#16803C]','Proposal Lost':'bg-[#FEF3F2] text-[#B42318]',
        'Invoice Sent':'bg-[#FFF7ED] text-[#C2410C]',
        'Payment Received':'bg-[#ECFDF3] text-[#166534]','Project Closed':'bg-[#ECFDF3] text-[#166534]',
      };
      return m[stage]||'bg-[#F2F4F7] text-[#667085]';
    },

    outcomeClass(outcome) {
      const m={
        'Inquiry Received':'bg-[#EEF4FF] text-[#175CD3]',
        'Inquiry Created':'bg-[#EEF4FF] text-[#175CD3]',
        'Initial Communication':'bg-[#EFF8FF] text-[#026AA2]',
        'Proposal Submitted':'bg-[#F4F3FF] text-[#5925DC]',
        'Follow-up Sent':'bg-[#F4F3FF] text-[#5925DC]',
        'Awaiting Client Response':'bg-[#FFFAEB] text-[#B54708]',
        'Project Won':'bg-[#ECFDF3] text-[#16803C]',
        'Project Lost':'bg-[#FEF3F2] text-[#B42318]',
        'Questionnaire':'bg-[#EFF8FF] text-[#026AA2]',
        'Link Testing':'bg-[#EFF8FF] text-[#026AA2]',
        'Field Work':'bg-[#EFF8FF] text-[#026AA2]',
        'Project Delivered':'bg-[#F0FDF4] text-[#15803D]',
        'Invoice Sent':'bg-[#FFF7ED] text-[#C2410C]',
        'Payment Received':'bg-[#ECFDF3] text-[#166534]',
        'Inquiry Closed':'bg-[#ECFDF3] text-[#166534]',
        // legacy
        'In Progress':'bg-[#EFF8FF] text-[#026AA2]',
        'Won':'bg-[#ECFDF3] text-[#16803C]',
        'Lost':'bg-[#FEF3F2] text-[#B42318]',
        'No Reply':'bg-[#FFFAEB] text-[#B54708]',
        'On Hold':'bg-[#F2F4F7] text-[#344054]',
        'Cancelled':'bg-[#F2F4F7] text-[#667085]',
      };
      return m[outcome]||'bg-[#F2F4F7] text-[#667085]';
    },

    // Ownership is binary: an inquiry is either still being worked (Open) or it is finished
    // (Closed). Stage Type then offers every outcome on that side picking Open shows the whole
    // working pipeline to choose from, picking Closed shows the three ways an inquiry ends.
    get currentStageSteps() {
      return this.stageForm.ownership === 'Closed'
        ? ALL_OUTCOMES.filter(o =>  CLOSED_OUTCOMES.includes(o))
        : ALL_OUTCOMES.filter(o => !CLOSED_OUTCOMES.includes(o));
    },

    // Which pipeline step an outcome belongs to. Drives the read-only stepper and the `stage`
    // column, so the two can never disagree about where an inquiry sits.
    stageNumOf(outcome) {
      const n = {
        'Inquiry Received':1,'Inquiry Created':1,'In Progress':1,
        'Initial Communication':2,'Proposal Submitted':2,'Follow-up Sent':2,'Awaiting Client Response':2,'No Reply':2,'On Hold':2,
        'Project Won':3,'Project Lost':3,'Won':3,'Lost':3,'Cancelled':3,
        'Questionnaire':4,'Link Testing':4,'Field Work':4,'Project Delivered':4,'Invoice Sent':4,
        'Project Won and Started':4,'Project Running':4,'Project Completed':4,
        'Payment Received':5,'Inquiry Closed':5,'Completed':5,
      };
      return n[outcome] || 1;
    },

    // Flipping Open/Closed invalidates the current Stage Type unless it happens to be on the
    // new side clear it rather than submitting a type that contradicts the ownership.
    onOwnershipChange() {
      if (!this.currentStageSteps.includes(this.stageForm.stageStep)) this.stageForm.stageStep = '';
      this.syncStageNum();
    },
    syncStageNum() {
      this.stageForm.stageNum = this.stageForm.stageStep ? this.stageNumOf(this.stageForm.stageStep)
                                                         : (this.stageForm.ownership === 'Closed' ? 5 : 1);
    },

    stepStatusClass(status) {
      const m={
        'New':'bg-[#EEF4FF] text-[#175CD3]',
        'Pending':'bg-[#FFFAEB] text-[#B54708]',
        'In Progress':'bg-[#EFF8FF] text-[#026AA2]',
        'Completed':'bg-[#ECFDF3] text-[#16803C]',
        'Done':'bg-[#ECFDF3] text-[#16803C]',
        'Other':'bg-[#F4F3FF] text-[#5925DC]',
        'Rejected':'bg-[#FEF3F2] text-[#B42318]',
        'Cancelled':'bg-[#F2F4F7] text-[#667085]',
      };
      return m[status]||'bg-[#F2F4F7] text-[#667085]';
    },

    // P1/P8: Dynamic status options assigners/admins can approve(Done) or reject
    stepStatusOptions(step) {
      return ['In Progress','Pending','Completed','Other'];
    },

    roleBadge(role) {
      if (role==='Master Admin') return '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#5925DC]">⚑ Master Admin</span>';
      if (role==='Admin')        return '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#175CD3]">⚑ Admin</span>';
      if (role==='Client')       return '<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#B54708]">◉ Client</span>';
      if (role==='Member')       return '<span class="text-[10px] font-semibold text-[#344054]">Member</span>';
      const safe = role.replace(/[<>&"]/g, c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
      return '<span class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#667085] bg-[#F2F4F7] px-1.5 py-0.5 rounded-[4px]">◈ '+safe+'</span>';
    },

    statusPill(status) {
      if (status==='approved') return '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#ECFDF3] text-[#16803C]">✓ Active</span>';
      if (status==='rejected') return '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#FEF3F2] text-[#B42318]">✕ Rejected</span>';
      if (status==='blocked')  return '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#F2F4F7] text-[#344054]">⊘ Blocked</span>';
      return '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#FFFAEB] text-[#B54708]">◴ Pending</span>';
    },

    canDeleteStep(step) { return this.isAdmin || step.assigned_by===this.currentUser; },

    // ── Toasts ──
    addToast(msg, type='success') {
      const id = Date.now();
      this.toasts.push({ id, msg, type });
      setTimeout(()=>{ this.toasts=this.toasts.filter(t=>t.id!==id); }, 4000);
    },

    // ── API helpers ──
    async api(url, body) {
      // ponytail: base64-encode HTML strings so ModSecurity WAF doesn't flag <tags> as XSS
      const _b64 = s => btoa(encodeURIComponent(s).replace(/%([0-9A-F]{2})/g, (_, p) => String.fromCharCode('0x'+p)));
      const safe = Object.fromEntries(Object.entries(body).map(([k,v]) => [k, typeof v==='string' && /</.test(v) ? '__b64__'+_b64(v) : v]));
      try {
        const r = await fetch(url, { method:'POST', headers: spHeaders(true), body: JSON.stringify(safe) });
        const text = await r.text();
        try {
          const res = JSON.parse(text.replace(/^﻿/, ''));
          if (res.expired) { window.location.href = 'login.php'; return res; }
          return res;
        } catch {
          console.error('Non-JSON response from '+url+':', text.slice(0,300));
          return { ok:false, message:'Server error. ('+(text.replace(/^﻿/,'').slice(0,120)||'empty response')+')' };
        }
      } catch (err) {
        console.error('Fetch failed:', err);
        return { ok:false, message:'Network error: '+err.message };
      }
    },

    async refreshInquiries() {
      try {
        const text = await fetch('api/inquiries.php').then(r => r.text());
        const d = JSON.parse(text.replace(/^﻿/, ''));
        if (d.ok && Array.isArray(d.data)) {
          this.inquiries = this._remap(d.data);
          this.$nextTick(() => this.checkDueWarnings());
        }
      } catch(e) { console.error('refreshInquiries failed:', e); }
    },

    // ── Stage Update ──
    async openStageUpdate(inq) {
      this.stageUpdateFor = inq;
      if (!inq._historyLoaded) {
        const d = await this.api('api/inquiry.php', { action:'load_history', id:inq.id });
        if (d.ok) { inq.history = d.data; inq._historyLoaded = true; }
      }
      // Map current outcome → stageNum
      const numMap = {
        'Inquiry Received':1,'Inquiry Created':1,
        'Initial Communication':2,'Proposal Submitted':2,'Follow-up Sent':2,'Awaiting Client Response':2,
        'Project Won':3,'Project Lost':3,
        'Questionnaire':4,'Link Testing':4,'Field Work':4,'Project Delivered':4,'Invoice Sent':4,
        'Payment Received':5,'Inquiry Closed':5,
        // legacy
        'In Progress':1,'Won':3,'Project Won and Started':4,'Project Running':4,'Project Completed':4,
        'Lost':3,'No Reply':2,'On Hold':2,'Cancelled':3,
      };
      this.stageForm = {
        // Seeded from where the inquiry actually stands, so opening the modal and saving
        // without touching anything is a no-op rather than a silent reopen.
        ownership: this.isClosed(inq) ? 'Closed' : 'Open',
        stageNum: numMap[inq.outcome] || 1,
        stageStep: inq.outcome || '',
        remark: '',
        deliveryDate: inq.delivery_date || '',
        followUpDate: inq.follow_up_date || '',
        proposalValue: inq.proposal_value || '',
        finalValue: inq.final_value || '',
        finalRemark: '',
        _finalRemarkErr: false,
      };
    },
    async saveStageUpdate() {
      if (!this.stageUpdateFor || this._stageSaving) return;
      if (!this.stageForm.stageStep) { this.addToast('Please select a Stage Type','error'); return; }
      if (!this.stageForm.finalRemark.trim()) { this.stageForm._finalRemarkErr=true; this.addToast('Final Updated Status is required','error'); return; }
      this._stageSaving = true;
      const stageLabels = {1:'Inquiry',2:'Communication / Proposal',3:'Decision',4:'Project Execution',5:'Closure'};
      // The stored `stage` has to agree with the Ownership the user picked is_closed_inquiry()
      // reads stage as well as outcome, so writing an open-looking stage on a Closed inquiry
      // would leave it reading Open on the dashboard.
      const stage = this.stageForm.ownership === 'Closed'
        ? 'Closure'
        : stageLabels[this.stageNumOf(this.stageForm.stageStep)] || 'Inquiry';
      const payload = {
        stage,
        outcome: this.stageForm.stageStep,
        outcome_reason: '',
        remark: this.stageForm.remark,
        deliveryDate: this.stageForm.deliveryDate,
        followUpDate: this.stageForm.followUpDate,
        proposalValue: this.stageForm.proposalValue,
        finalValue: this.stageForm.finalValue,
        finalRemark: this.stageForm.finalRemark,
      };
      const d = await this.api('api/inquiry.php', { action:'stage_update', id:this.stageUpdateFor.id, ...payload });
      this._stageSaving = false;
      if (d.ok) { this.inquiries = this._remap(d.data); this.stageUpdateFor=null; this.addToast('Stage updated'); }
      else this.addToast(d.message,'error');
    },

    // ── Closed check — hides "Complete Inquiry" once the inquiry is closed ──
    // Was a hand-copied list missing the 'Completed' / 'Project Lost' outcomes and the
    // 'Cancelled' stage, so those closures still offered a Complete button. Delegate to
    // isClosed(), which reads the CLOSED_OUTCOMES / CLOSED_STAGES injected from PHP.
    isInquiryClosed(inq) { return this.isClosed(inq); },

    // Tasks still owing work — mirrors SETTLED_STEP_STATUSES in includes/functions.php.
    // The server enforces this; here it only greys the button out before you click it.
    openSteps(inq) {
      return (inq.steps || []).filter(s => !['Done','Completed','Rejected','Cancelled'].includes(s.status));
    },

    // ── Quick complete: small modal asking for the final status, then closes the inquiry ──
    openCompleteInquiry(inq) {
      this.completeInquiryFor = inq;
      this.completeInquiryRemark = '';
      this._completeInquiryErr = false;
    },
    async submitCompleteInquiry() {
      if (!this.completeInquiryFor || this._completingInquiry) return;
      const remark = this.completeInquiryRemark.trim();
      if (!remark) { this._completeInquiryErr = true; return; }
      this._completingInquiry = true;
      const d = await this.api('api/inquiry.php', {
        action: 'stage_update', id: this.completeInquiryFor.id,
        stage: 'Closure', outcome: 'Inquiry Closed', outcomeReason: remark,
        remark, finalRemark: remark,
      });
      this._completingInquiry = false;
      if (d.ok) { this.inquiries = this._remap(d.data); this.completeInquiryFor = null; this.addToast('Inquiry marked as completed'); }
      else this.addToast(d.message||'Failed to complete inquiry','error');
    },

    // ── Add Task ──
    openAddTask(inq) {
      this.addTaskFor=inq;
      const today = new Date().toLocaleDateString('en-CA');
      this.taskForm={ assignedTo:'', due: today, dueTime:'', instruction:'', errors:{} };
      this.$nextTick(() => { if(this.$refs.taskFiles) this.$refs.taskFiles.value=''; });
    },
    async saveTask() {
      if (!this.addTaskFor || this._taskSaving) return;
      this.taskForm.errors = {};
      if (this.addTaskFor.inquiry_type === 'Internal Usage') {
        this.taskForm.due = new Date().toLocaleDateString('en-CA');
      }
      if (!this.taskForm.assignedTo)       this.taskForm.errors.assignedTo   = 'Please select a user to assign to.';
      if (!this.taskForm.due)              this.taskForm.errors.due          = 'Due date is required.';
      if (!this.taskForm.dueTime)          this.taskForm.errors.dueTime      = 'Due time is required.';
      if (!this.stripHtml(this.taskForm.instruction).trim()) this.taskForm.errors.instruction = 'Task instruction is required.';
      if (Object.keys(this.taskForm.errors).length) return;
      this._taskSaving = true;
      try {
        const d = await this.api('api/inquiry.php', { action:'add_step', inquiryId:this.addTaskFor.id, ...this.taskForm });
        if (!d.ok) { this.addToast(d.message,'error'); return; }
        const files = this.$refs.taskFiles?.files;
        if (files && files.length > 0 && d.stepId) {
          const fd = new FormData();
          fd.append('step_id', d.stepId);
          for (const f of files) fd.append('files[]', f);
          const up = await fetch('api/upload.php', { method:'POST', headers:spHeaders(false), body: fd })
            .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^\uFEFF/,''))}catch{return {ok:false}}}).catch(()=>({ok:false}));
          await this.refreshInquiries();
          this.addTaskFor = null;
          if (up.saved?.length) this.addToast('Task added '+up.saved.length+' file(s) uploaded');
          else { this.addToast('Task added'); this.addToast(this._uploadError(up), 'error'); }
        } else {
          this.inquiries = this._remap(d.data);
          this.addTaskFor = null;
          this.addToast('Task added');
        }
        this.$nextTick(() => this.checkDueWarnings());
      } finally {
        this._taskSaving = false;
      }
    },

    // ── Step status ──
    async updateStepStatus(inquiryId, stepId, status) {
      const d = await this.api('api/inquiry.php', { action:'step_status', stepId, status });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Status updated'); }
      else this.addToast(d.message||'Failed to update status','error');
    },

    // ── Step instruction ──
    async saveInstruction(stepId, instruction, step) {
      const d = await this.api('api/inquiry.php', { action:'step_instruction', stepId, instruction });
      if (d.ok) {
        step._instrSaved = true;
        setTimeout(() => { step._instrSaved = false; }, 2000);
        this.addToast('Instruction saved');
      } else {
        this.addToast(d.message||'Failed to save instruction','error');
      }
    },

    async saveRemark(inquiryId, stepId, remark, step) {
      const d = await this.api('api/inquiry.php', { action:'step_remark', stepId, remark });
      if (d.ok) {
        this.inquiries = this._remap(d.data);
        this.addToast('Remark saved');
      } else this.addToast(d.message,'error');
    },

    async autoSaveRemark(inquiryId, stepId, remark, step) {
      if (!(remark||'').trim()) return;
      const d = await this.api('api/inquiry.php', { action:'step_remark', stepId, remark });
      if (d.ok) {
        step._remarkSaved = true;
        setTimeout(() => { step._remarkSaved = false; }, 2000);
      }
    },

    async saveAdminRemark(inq) {
      inq._adminRemarkSaving = true;
      const d = await this.api('api/inquiry.php', { action:'admin_remark', inquiryId:inq.id, remark:inq._adminRemark });
      inq._adminRemarkSaving = false;
      if (d.ok) { this.inquiries = this._remap(d.data); this.adminRemarkFor = null; this.addToast('Admin remark saved'); }
      else this.addToast(d.message||'Failed to save remark','error');
    },

    async saveTeamRemark(inq) {
      inq._teamRemarkSaving = true;
      const d = await this.api('api/inquiry.php', { action:'team_remark', inquiryId:inq.id, remark:inq._teamRemark });
      inq._teamRemarkSaving = false;
      if (d.ok) { this.inquiries = this._remap(d.data); this.teamRemarkFor = null; this.addToast('Remark saved'); }
      else this.addToast(d.message||'Failed to save remark','error');
    },

    async deleteTeamRemark(inq) {
      if (!confirm('Delete this remark?')) return;
      const d = await this.api('api/inquiry.php', { action:'team_remark', inquiryId:inq.id, remark:'' });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Remark deleted'); }
      else this.addToast(d.message||'Failed to delete remark','error');
    },

    // ── Delete step ──
    async deleteStep(inquiryId, stepId) {
      const d = await this.api('api/inquiry.php', { action:'delete_step', stepId });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Step removed','info'); }
      else this.addToast(d.message||'Failed to delete step','error');
    },

    // ── Delete whole inquiry (Master Admin only) ──
    async deleteInquiry(inq) {
      if (!confirm(`Delete inquiry ${inq.id} (${inq.client})?\n\nThis will also delete all steps, history, and notifications. This cannot be undone.`)) return;
      const d = await this.api('api/inquiry.php', { action:'delete_inquiry', id: inq.id });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Inquiry deleted','info'); }
      else this.addToast(d.message||'Failed to delete inquiry','error');
    },

    // ── Inquiry attachments ──
    async uploadInquiryFiles(inq, input) {
      if (!input.files || !input.files.length) return;
      const fd = new FormData();
      fd.append('inquiry_id', inq.id);
      for (const f of input.files) fd.append('files[]', f);
      const d = await fetch('api/upload.php', { method:'POST', headers:spHeaders(false), body: fd })
        .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^﻿/,''))}catch{return {ok:false,message:'Server error'}}}).catch(()=>({ok:false,message:'Upload failed'}));
      input.value = '';
      if (d.ok && d.saved?.length) {
        this.inquiries = this._remap(d.data);
        this.addToast(d.saved.length+' file(s) uploaded');
        if (d.errors?.length) this.addToast(this._uploadError(d), 'error'); // partial success
      } else this.addToast(this._uploadError(d), 'error');
    },
    async deleteInquiryAttachment(inq, att) {
      const d = await this.api('api/inquiry.php', { action:'delete_inquiry_attachment', id: att.id });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Attachment removed','info'); }
      else this.addToast(d.message,'error');
    },

    // ── Convert "28 Jul 2026" → "2026-07-28" for <input type="date"> ──
    toIsoDate(str) {
      if (!str || str === 'TBD') return '';
      const months = {Jan:'01',Feb:'02',Mar:'03',Apr:'04',May:'05',Jun:'06',Jul:'07',Aug:'08',Sep:'09',Oct:'10',Nov:'11',Dec:'12'};
      const m = str.match(/^(\d{1,2})\s+(\w{3})\s+(\d{4})/);
      if (!m) return '';
      return m[3]+'-'+(months[m[2]]||'01')+'-'+m[1].padStart(2,'0');
    },
    // ── Extract "03:30 PM" → "15:30" (H:i) from a due string like "28 Jul 2026 · 03:30 PM" ──
    toIsoTime(str) {
      if (!str) return '';
      const m = str.match(/·\s*(\d{1,2}):(\d{2})\s*([AP]M)/i);
      if (!m) return '';
      let h = parseInt(m[1]); const min = m[2]; const ap = m[3].toUpperCase();
      if (ap === 'PM' && h < 12) h += 12;
      if (ap === 'AM' && h === 12) h = 0;
      return h.toString().padStart(2,'0') + ':' + min;
    },
    stripHtml(html) { const d=document.createElement('div'); d.innerHTML=html||''; return d.textContent||d.innerText||''; },

    // One lowercase blob of everything a person might type looking for an inquiry: ids, names,
    // both date formats, contact details, stage/outcome, money, remarks, and every task on it.
    // Only built while the search box has text, so it costs nothing the rest of the time.
    _haystack(inq) {
      const steps = inq.steps || [];
      return [
        inq.id, inq.date, inq.created_at, inq.inquiry_type, inq.project_category, inq.client, inq.designation,
        inq.company, inq.country, inq.client_type, inq.created_by, inq.current_owner,
        inq.due_date, inq.due_time, inq.delivery_date, inq.follow_up_date,
        inq.stage, inq.outcome, inq.outcome_reason, inq.proposal_value, inq.final_value,
        inq.email, inq.secondary_email, inq.phone, inq.website, inq.email_subject,
        inq.team_remark_by,
        this.stripHtml(inq.requirement || ''),
        this.stripHtml(inq.admin_remark || ''),
        this.stripHtml(inq.team_remark || ''),
        ...steps.flatMap(s => [s.assigned_to, s.assigned_by, s.status, this.stripHtml(s.instruction || '')]),
      ].filter(Boolean).join(' ').toLowerCase();
    },
    showReqPopup(e, html) {
      const r = e.currentTarget.getBoundingClientRect();
      const popupMaxH = 280;
      const x = Math.min(r.left, window.innerWidth - 320);
      let y = r.bottom + 6 + window.scrollY;
      const viewBottom = window.scrollY + window.innerHeight;
      if (y + popupMaxH > viewBottom) {
        // Not enough room below  flip above the row instead
        y = Math.max(window.scrollY + 8, r.top + window.scrollY - popupMaxH - 6);
      }
      this.reqPopup = { show: true, text: this.stripHtml(html), x, y };
    },
    hideReqPopup() { this.reqPopup.show = false; },
    linkify(html) {
      const sanitized = DOMPurify.sanitize(html||'', {ADD_ATTR:['target','rel']});
      const div = document.createElement('div');
      div.innerHTML = sanitized;
      const walk = node => {
        if (node.nodeType===3 && /https?:\/\//.test(node.textContent)) {
          const span = document.createElement('span');
          span.innerHTML = node.textContent.replace(/https?:\/\/[^\s<>"]+/g,
            u=>`<a href="${u}" target="_blank" rel="noopener noreferrer" style="color:#1268F3;text-decoration:underline;word-break:break-all">${u}</a>`);
          node.parentNode.replaceChild(span, node);
        } else if (node.nodeType===1 && node.tagName!=='A') {
          Array.from(node.childNodes).forEach(walk);
        }
      };
      Array.from(div.childNodes).forEach(walk);
      return div.innerHTML;
    },

    // ── Flatpickr helpers ──
    _fpCls: 'w-full text-[13px] border border-[#E4E7EC] rounded-[8px] pl-9 pr-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer caret-transparent',
    _fpClsSm: 'text-[11px] border border-[#E4E7EC] rounded-[6px] pl-6 pr-2 py-1.5 bg-white focus:outline-none focus:border-[#1268F3] text-[#344054] w-full cursor-pointer caret-transparent',
    _fpDate(el, get, set, wk, sm, opts={}) {
      const fp = flatpickr(el, {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y · D', allowInput: false,
        defaultDate: get() || null,
        altInputClass: sm ? this._fpClsSm : this._fpCls,
        onChange(d) { set(d[0] ? d[0].toLocaleDateString('en-CA') : '') },
        ...opts
      });
      this.$watch(wk, v => v ? fp.setDate(v, false) : fp.clear());
      return fp;
    },
    _fpTime(el, get, set, wk) {
      const toD = v => { if (!v) return null; const [h,m]=v.split(':'); const d=new Date(); d.setHours(+h,+m,0,0); return d; };
      const fp = flatpickr(el, {
        enableTime: true, noCalendar: true, dateFormat: 'h:i K', time_24hr: false,
        allowInput: false, defaultDate: toD(get()),
        onChange(d) {
          if (!d[0]) { set(''); return; }
          set(d[0].getHours().toString().padStart(2,'0') + ':' + d[0].getMinutes().toString().padStart(2,'0'));
        }
      });
      this.$watch(wk, v => v ? fp.setDate(toD(v), false) : fp.clear());
      return fp;
    },

    // ── Update step due date (optionally with time) ──
    async updateStepDue(stepId, isoDate, inquiryId, dueTime = '') {
      const d = await this.api('api/inquiry.php', { action:'update_step_due', stepId, due: isoDate, dueTime });
      if (d.ok) {
        this.inquiries = this._remap(d.data);
        this.addToast('Due date updated');
        this.$nextTick(() => this.checkDueWarnings());
      }
      else this.addToast(d.message,'error');
    },

    // ── Reassign step ──
    async updateStepAssignee(stepId, assignedTo, inquiryId) {
      const d = await this.api('api/inquiry.php', { action:'update_step_assignee', stepId, assignedTo, inquiryId });
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Step reassigned'); }
      else this.addToast(d.message,'error');
    },

    // ── Upload files for an existing step ──
    async uploadStepFiles(stepId, files, inquiryId) {
      if (!files || files.length === 0) return;
      const fd = new FormData();
      fd.append('step_id', stepId);
      for (const f of files) fd.append('files[]', f);
      const d = await fetch('api/upload.php', { method:'POST', headers:spHeaders(false), body: fd })
        .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^﻿/,''))}catch{return {ok:false,message:'Server error'}}}).catch(()=>({ok:false,message:'Upload failed'}));
      if (d.ok && d.saved?.length) {
        const inq = this.inquiries.find(i => i.id == inquiryId);
        if (inq) { const step = inq.steps.find(s => s.id == stepId); if (step) step.attachments = [...(step.attachments||[]), ...d.saved]; }
        this.addToast(d.saved.length+' file(s) uploaded');
      } else this.addToast(this._uploadError(d), 'error');
    },

    // The server now says *why* each file was dropped — a bad type, a size limit, an unwritable
    // uploads folder. Showing that beats the old guess of "check allowed types", which sent
    // people hunting the wrong problem when it was really a permission on the server.
    _uploadError(d) {
      if (d?.errors?.length) return d.errors.length === 1 ? d.errors[0]
        : d.errors[0] + ' (+' + (d.errors.length - 1) + ' more)';
      return d?.message || 'Upload failed';
    },

    openStepUpload(stepId, inquiryId) {
      this._stepUploadFor = { stepId, inquiryId };
      this._stepUploadFiles = [];
      this._stepUploading = false;
    },

    async submitStepUpload() {
      if (!this._stepUploadFor || !this._stepUploadFiles.length || this._stepUploading) return;
      this._stepUploading = true;
      try {
        await this.uploadStepFiles(this._stepUploadFor.stepId, this._stepUploadFiles, this._stepUploadFor.inquiryId);
        this._stepUploadFor = null;
        this._stepUploadFiles = [];
      } catch(e) {
        this.addToast('Upload failed','error');
      } finally {
        this._stepUploading = false;
      }
    },

    async deleteStepAttachment(stepId, filename, inquiryId) {
      if (!confirm('Remove this attachment?')) return;
      const d = await this.api('api/inquiry.php', { action:'delete_step_attachment', stepId, filename });
      if (d.ok) {
        const inq = this.inquiries.find(i => i.id == inquiryId);
        if (inq) { const step = inq.steps.find(s => s.id == stepId); if (step) step.attachments = (step.attachments||[]).filter(f => f !== filename); }
        this.addToast('Attachment removed','info');
      } else this.addToast(d.message||'Failed to remove attachment','error');
    },

    get summaryParts() {
      if (!this.summaryFor) return [];
      const inq = this.summaryFor;
      const parts = [];
      const who = inq.created_by || 'the team';
      const client = inq.client || '';
      const company = inq.company || '';
      const country = inq.country || '';
      const type = inq.client_type ? `${inq.client_type} client` : 'client';
      const location = [company, country].filter(Boolean).join(', ');
      const clientLine = client + (location ? ` (${location})` : '');
      parts.push({ type: 'intro', text: `On ${inq.date || ''}, ${who} created an inquiry for ${clientLine} a ${type}.` });
      (inq.steps || []).forEach((s, i) => {
        parts.push({
          type: 'task', num: i + 1,
          from: s.assigned_by || '', to: s.assigned_to || '',
          instr: this.stripHtml(s.instruction || '').trim().slice(0, 150) || 'No specific instruction.',
          status: s.status || 'New',
          remark: (s.remark || '').trim(),
        });
      });
      const stage = this.stageLabel(inq.stage || '');
      parts.push({ type: 'status', text: `Currently at ${stage}${inq.outcome ? ' · ' + inq.outcome : ''}, managed by ${inq.current_owner || ''}.` });
      return parts;
    },

    // ── Summary ──
    openSummary(inq) { this.summaryFor = inq; this._reqExpanded = false; },

    // ── Add Inquiry ──
    setInquiryType(type) {
      this.addInquiryForm.inquiryType = type;
      if (type === 'Internal Usage') {
        this.addInquiryForm.dueDate = new Date().toLocaleDateString('en-CA');
        delete this.addInquiryForm.errors.dueDate;
      }
    },
    todayLabel() {
      return new Date().toLocaleDateString([], { weekday:'short', day:'2-digit', month:'short', year:'numeric' });
    },
    async loadProjectCategories() {
      try {
        const d = await fetch('api/categories.php?action=list', { headers: spHeaders(true) }).then(r => r.json());
        if (d.ok && Array.isArray(d.data)) this.projectCategories = d.data;
      } catch (_) { /* keep empty until retry */ }
    },
    async addProjectCategory() {
      if (!this.isAdmin) { this.addToast('Only Admin can add project categories.', 'error'); return; }
      const raw = prompt('New project category name:');
      if (raw === null) return;
      const name = raw.trim();
      if (!name) { this.addToast('Category name is required.', 'error'); return; }
      const d = await this.api('api/categories.php', { action:'add', name });
      if (!d.ok) { this.addToast(d.message || 'Could not add category.', 'error'); return; }
      const row = d.data;
      if (!this.projectCategories.some(c => String(c.id) === String(row.id))) {
        this.projectCategories = [...this.projectCategories, row]
          .sort((a, b) => (b.is_default - a.is_default) || String(a.name).localeCompare(String(b.name)));
      }
      this.addInquiryForm.projectCategory = row.name;
      delete this.addInquiryForm.errors.projectCategory;
      this.addToast(d.existed ? ('Using existing “'+row.name+'”') : ('Added “'+row.name+'”'));
    },
    async addInquiry() {
      this.addInquiryForm.errors = {};
      const e = this.addInquiryForm.errors;
      const isClientProject = this.addInquiryForm.inquiryType !== 'Internal Usage';
      if (!isClientProject) {
        this.addInquiryForm.dueDate = new Date().toLocaleDateString('en-CA');
      }
      if (isClientProject && !this.addInquiryForm.client.trim())         e.client          = 'Client name is required.';
      if (isClientProject && !this.addInquiryForm.company.trim())        e.company         = 'Company is required.';
      if (!this.stripHtml(this.addInquiryForm.requirement).trim())      e.requirement     = 'Requirement is required.';
      if (!this.addInquiryForm.assignTo)                                e.assignTo        = 'Please select a user.';
      if (!this.stripHtml(this.addInquiryForm.taskInstruction).trim())  e.taskInstruction = 'Task instruction is required.';
      if (isClientProject && !this.addInquiryForm.country.trim())        e.country         = 'Country is required.';
      if (isClientProject && !this.addInquiryForm.website.trim())        e.website         = 'Website is required.';
      if (!this.addInquiryForm.dueDate)                                 e.dueDate         = 'Due date is required.';
      if (!this.addInquiryForm.dueTime)                                 e.dueTime         = 'Due time is required.';
      if (!isClientProject && this.addInquiryForm.dueDate && this.addInquiryForm.dueDate !== new Date().toLocaleDateString('en-CA')) {
        e.dueDate = 'Internal tasks can only be due today.';
      }
      const em = this.addInquiryForm.email.trim();
      if (isClientProject && !em)                                        e.email           = 'Primary email is required.';
      else if (em && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em))            e.email           = 'Enter a valid email address.';
      const em2 = this.addInquiryForm.emailSecondary.trim();
      if (em2 && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em2)) e.emailSecondary = 'Enter a valid email address.';
      if (Object.keys(e).length) return;
      if (this._addingInquiry) return;
      this._addingInquiry = true;
      this._inquirySave = 'saving';
      const fmtDate = iso => { if (!iso) return ''; const [y,m,d]=iso.split('-'); const mon=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][+m-1]; return d+' '+mon+' '+y; };
      const body = {
        inquiryType: this.addInquiryForm.inquiryType,
        projectCategory: this.addInquiryForm.projectCategory,
        date: this.addInquiryForm.date ? fmtDate(this.addInquiryForm.date) : '',
        client: this.addInquiryForm.client.trim(),
        designation: this.addInquiryForm.designation.trim(),
        company: this.addInquiryForm.company.trim(),
        country: this.addInquiryForm.country || 'India',
        clientType: this.addInquiryForm.clientType,
        requirement: this.addInquiryForm.requirement.trim(),
        createdBy: this.addInquiryForm.createdBy || this.currentUser,
        assignTo: this.addInquiryForm.assignTo,
        dueDate: this.addInquiryForm.dueDate ? fmtDate(this.addInquiryForm.dueDate) : 'TBD',
        dueTime: this.addInquiryForm.dueTime,
        proposalValue: this.addInquiryForm.proposalValue.trim(),
        email: this.addInquiryForm.email.trim(),
        emailSecondary: this.addInquiryForm.emailSecondary.trim(),
        phone: this.addInquiryForm.phone.trim(),
        website: this.addInquiryForm.website.trim(),
        emailSubject: this.addInquiryForm.emailSubject.trim(),
        taskInstruction: this.addInquiryForm.taskInstruction.trim(),
      };
      const d = await this.api('api/inquiries.php', body);
      this._addingInquiry = false;
      if (d.ok) {
        this._inquirySave = 'saved';
        const files = this.addInquiryForm._files;
        // Append new inquiry (oldest-first order)
        const ni = d.inquiry;
        const mapped = { ...ni, _open:false, _adminRemark:ni.admin_remark||'', _adminRemarkSaving:false, _teamRemark:ni.team_remark||'', _teamRemarkSaving:false, history:[], _historyLoaded:false,
          steps:(ni.steps||[]).map(s=>({...s,_remark:s.remark||'',_saved:false,_remarkOpen:false,_instruction:s.instruction||'',_instrSaved:false})) };
        this.inquiries = [...this.inquiries, mapped];
        if (files && files.length) {
          const fd = new FormData();
          fd.append('inquiry_id', d.id);
          for (const f of files) fd.append('files[]', f);
          const up = await fetch('api/upload.php', { method:'POST', headers:spHeaders(false), body: fd }).then(r=>r.json()).catch(()=>({ok:false}));
          if (up.ok && up.saved?.length) { mapped.attachments = up.saved.map(f=>({filename:f})); this.addToast(d.id+' created + '+up.saved.length+' file(s) uploaded'); }
          else { this.addToast('Inquiry '+d.id+' created'); this.addToast(this._uploadError(up), 'error'); }
        } else {
          this.addToast('Inquiry '+d.id+' created');
        }
        this.$nextTick(() => this.checkDueWarnings());
        setTimeout(() => {
          this.addInquiryOpen = false;
          this._inquirySave = '';
          this.addInquiryForm = { inquiryType:'Client Project', date: new Date().toLocaleDateString('en-CA'), projectCategory:'', client:'', designation:'', company:'', country:'India', clientType:'New', requirement:'', assignTo:'', dueDate:'', dueTime:'', proposalValue:'', email:'', emailSecondary:'', phone:'', website:'', emailSubject:'', taskInstruction:'', createdBy:this.currentUser, _files:null, errors:{} };
        }, 700);
        const newId = d.id;
        this.highlightedInq = newId;
        setTimeout(() => { this.highlightedInq = null; }, 3000);
        this.$nextTick(() => {
          const el = document.querySelector('[data-inq="'+newId+'"]');
          if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
        });
      } else {
        this.addInquiryForm.errors._api = d.message || 'Failed to create inquiry.';
      }
    },

    // ── Account actions ──
    async accountAction(action, id, name) {
      if (action === 'delete') this.accounts = this.accounts.filter(a => a.id !== id); // optimistic
      const d = await this.api('api/accounts.php', { action, id });
      if (d.ok) {
        const msgs = { approve:name+' approved access granted', reject:name+' rejected', block:name+' has been blocked', unblock:name+' has been unblocked', delete:name+' removed from the system' };
        this.addToast(msgs[action]||'Done', action==='delete'?'info':'success');
        if (d.data) this.accounts = d.data;
        if (action==='approve'||action==='reject') this.fetchNotifications();
      } else {
        this.addToast(d.message,'error');
        if (action === 'delete') { const r = await fetch('api/accounts.php').then(r=>r.json()); if(r.ok) this.accounts=r.data; } // restore on failure
      }
    },

    async changeRole(id, value, name) {
      if (value==='__custom__') { this.customRoleFor={ id, value:'' }; return; }
      const d = await this.api('api/accounts.php', { action:'role_change', id, role:value });
      if (d.ok) { this.addToast(name+' is now '+value); if (d.data) this.accounts=d.data; }
      else this.addToast(d.message,'error');
    },

    async saveCustomRole(id) {
      if (!this.customRoleFor?.value?.trim()) return;
      const role = this.customRoleFor.value.trim();
      const acct = this.accounts.find(a=>a.id===id);
      const d = await this.api('api/accounts.php', { action:'role_change', id, role });
      if (d.ok) { this.customRoleFor=null; this.addToast((acct?.name||'User')+' is now '+role); if (d.data) this.accounts=d.data; }
      else this.addToast(d.message,'error');
    },

    // ── Member profile drawer ──
    openProfile(a) { this.profileFor = a; this.profileDescEdit = false; this.profileDescDraft = a.role_description || ''; },

    // Report rows carry a name, not an account. Silently do nothing for a name with no account
    // (a legacy owner string) rather than opening an empty drawer.
    openProfileByName(name) {
      const a = this.accounts.find(x => x.name === name);
      if (a) this.openProfile(a);
    },

    // Plain text, one responsibility per line blank lines dropped so a stray newline
    // does not render an empty bullet.
    roleDescLines(desc) { return (desc||'').split('\n').map(s => s.trim()).filter(Boolean); },

    // Reports rows carry a name, not an account id.
    memberDesc(name) { return this.accounts.find(a => a.name===name)?.role_description || ''; },

    // Profile header figures. Reads the full `inquiries` list, not the month-scoped one a
    // profile describes the whole person, not just what landed this month.
    memberStats(name) {
      let inqs = 0, open = 0, closed = 0, tasks = 0;
      this.inquiries.forEach(i => {
        if ((i.current_owner || i.created_by) === name) {
          inqs++;
          this.isClosed(i) ? closed++ : open++;
        }
        (i.steps||[]).forEach(s => {
          if (s.assigned_to === name && !['Done','Completed','Cancelled'].includes(s.status)) tasks++;
        });
      });
      return { inqs, open, closed, tasks };
    },

    async saveRoleDescription() {
      const a = this.profileFor; if (!a) return;
      const d = await this.api('api/accounts.php', { action:'role_description', id:a.id, description:this.profileDescDraft });
      if (d.ok) {
        if (d.data) this.accounts = d.data;
        this.profileFor = this.accounts.find(x => x.id===a.id) || a;   // re-point at the refreshed row
        this.profileDescEdit = false;
        this.addToast('Role description updated');
      } else this.addToast(d.message,'error');
    },

    // ── Jump to an inquiry (and optionally a step) from My Tasks / notifs / reminders ──
    goToInquiry(inq, stepId = null) {
      const id = typeof inq === 'string' ? inq : inq?.id;
      const target = this.inquiries.find(i => i.id === id);
      if (!target) { this.addToast('Inquiry not found', 'error'); return; }
      const run = () => this.revealInquiry(target, stepId);
      if (this.view !== 'dashboard' && this.view !== 'inquiries') {
        this.view = 'dashboard';
        this.$nextTick(run);
      } else {
        run();
      }
    },

    revealInquiry(target, stepId = null) {
      // Clear filters that would hide the row, then land on its creation month + page.
      this.search = '';
      this.statFilter = '';
      this.employee = '';
      this.categoryFilter = '';
      this.typeFilter = '';
      this.dateFilter = '';
      this.inqMonth = (this._inqDateKey(target) || '').slice(0, 7);
      this.highlightedInq = target.id;
      this.highlightedStep = stepId != null ? Number(stepId) || stepId : null;
      clearTimeout(this._hlInqTimer);
      clearTimeout(this._hlStepTimer);
      this._hlInqTimer = setTimeout(() => { this.highlightedInq = null; }, 4000);
      this._hlStepTimer = setTimeout(() => { this.highlightedStep = null; }, 6000);

      this.$nextTick(() => {
        const idx = this.filtered.findIndex(i => i.id === target.id);
        this._inqPage = idx >= 0 ? Math.floor(idx / 15) : 0;
        this.inquiries.forEach(i => { i._open = (i.id === target.id); });
        this.loadFollowUps(target);

        this.$nextTick(() => {
          setTimeout(() => {
            const stepEl = stepId != null
              ? document.querySelector('[data-step="'+String(stepId).replace(/"/g,'')+'"]')
              : null;
            const inqEl = document.querySelector('[data-inq="'+String(target.id).replace(/"/g,'')+'"]');
            const el = stepEl || inqEl;
            if (el) el.scrollIntoView({ behavior: 'smooth', block: stepEl ? 'center' : 'start' });
          }, 160);
        });
      });
    },

    openNotifInquiry(n) {
      // Remove from list immediately, delete from server
      this.notifications = this.notifications.filter(x => x.id !== n.id);
      fetch('api/notifications.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'dismiss', id: n.id }) });
      this.notifsOpen = false;
      this.goToInquiry(n.inquiry_id);
    },
    async fetchNotifications() {
      try {
        const text = await fetch('api/notifications.php').then(r=>r.text());
        const d = JSON.parse(text.replace(/^﻿/,''));
        if (d.ok) this.notifications = d.data;
      } catch { /* silent bell icon just stays as-is if server unreachable */ }
    },
    async markNotifsRead() {
      if (this.unreadCount === 0) return;
      this.notifications = this.notifications.map(n => ({...n, is_read: true}));
      await fetch('api/notifications.php', { method:'POST', headers:spHeaders(true), body: JSON.stringify({action:'mark_read'}) });
    },
    async clearAllNotifs() {
      this.notifications = [];
      await fetch('api/notifications.php', { method:'POST', headers:spHeaders(true), body: JSON.stringify({action:'clear_all'}) });
    },

    // ── Messages ──
    async refreshContacts() {
      try {
        const r = await fetch('api/messages.php?action=contacts').then(r=>r.json());
        if (r.ok) this.contacts = r.data;
        else this.addToast(r.message||'Could not load conversations','error');
      } catch { this.addToast('Could not load conversations','error'); }
    },
    _fmtTime(ts) {
      return new Date(ts.replace(' ','T')).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    },
    _scrollThreadToBottom() {
      const el = this.$refs.threadScroll;
      if (el) el.scrollTop = el.scrollHeight;
    },
    async selectContact(c) {
      this.activeContact = c;
      this.threadLoading = true;
      this.thread = [];
      let r;
      try { r = await fetch('api/messages.php?action=thread&withId='+c.id).then(r=>r.json()); }
      catch { this.threadLoading = false; this.addToast('Could not load thread','error'); return; }
      this.threadLoading = false;
      if (r.ok) {
        this.thread = r.data.map(m => ({ ...m, _time: this._fmtTime(m.created_at) }));
        this.$nextTick(() => this._scrollThreadToBottom());
      } else this.addToast(r.message||'Could not load thread','error');
      const entry = this.contacts.find(x => x.account_id === c.id);
      if (entry && entry.unread) {
        this.unreadMessages = Math.max(0, this.unreadMessages - entry.unread);
        entry.unread = 0;
      }
    },
    async refreshThread() {
      if (this.activeContact) await this.selectContact(this.activeContact);
    },
    async sendMessage() {
      const body = this.messageText.trim();
      if (!body || !this.activeContact) return;
      this.messageText = '';
      const r = await fetch('api/messages.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'send', receiverId: this.activeContact.id, body }) }).then(r=>r.json());
      if (r.ok) await this.selectContact(this.activeContact);
      else this.addToast(r.message||'Failed to send message','error');
    },
    async sendChatAttachment(e) {
      const file = e.target.files[0];
      e.target.value = '';
      if (!file || !this.activeContact) return;
      this._sendingAttachment = true;
      const fd = new FormData();
      fd.append('file', file);
      fd.append('receiverId', this.activeContact.id);
      fd.append('body', this.messageText.trim());
      const r = await fetch('api/messages.php?action=sendFile', { method:'POST', headers:spHeaders(false), body: fd }).then(r=>r.json());
      this._sendingAttachment = false;
      if (r.ok) { this.messageText = ''; await this.selectContact(this.activeContact); }
      else this.addToast(r.message||'Failed to send file','error');
    },

    // ── Announcements ──
    _fmtDate(ts) {
      return new Date(ts.replace(' ','T')).toLocaleString([], {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
    },
    async loadAnnouncements() {
      const r = await fetch('api/announcements.php?action=list').then(r=>r.json());
      if (r.ok) {
        this.announcements = r.data.map(a => ({ ...a, _time: this._fmtDate(a.created_at),
          _initials: a.created_by.split(' ').slice(0,2).map(w=>w[0]||'').join('').toUpperCase() }));
      }
      this.unreadAnnouncements = 0;
      await fetch('api/announcements.php', { method:'POST', headers:spHeaders(true), body: JSON.stringify({action:'markSeen'}) });
    },
    async postAnnouncement() {
      const body = this.announcementText.trim();
      if (!body) return;
      this._postingAnnouncement = true;
      const r = await fetch('api/announcements.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'post', body }) }).then(r=>r.json());
      this._postingAnnouncement = false;
      if (r.ok) { this.announcementText = ''; await this.loadAnnouncements(); this.addToast('Announcement posted'); }
      else this.addToast(r.message||'Failed to post announcement','error');
    },
    startEditAnnouncement(a) {
      this.annEditId = a.id;
      this.annEditText = a.body || '';
    },
    async saveAnnouncement() {
      const body = this.annEditText.trim();
      if (!body || !this.annEditId) return;
      this._savingAnnouncement = true;
      const r = await fetch('api/announcements.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'edit', id:this.annEditId, body }) }).then(r=>r.json());
      this._savingAnnouncement = false;
      if (r.ok) {
        this.annEditId = null; this.annEditText = '';
        await this.loadAnnouncements();
        this.addToast('Announcement updated');
      } else this.addToast(r.message||'Failed to update','error');
    },
    async deleteAnnouncement(a) {
      if (!confirm('Delete this announcement?')) return;
      const r = await fetch('api/announcements.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'delete', id:a.id }) }).then(r=>r.json());
      if (r.ok) {
        if (this.annEditId === a.id) { this.annEditId = null; this.annEditText = ''; }
        this.announcements = this.announcements.filter(x => x.id !== a.id);
        this.addToast('Announcement deleted');
      } else this.addToast(r.message||'Failed to delete','error');
    },

    // ── Resources (shared docs — admin uploads, everyone downloads) ──
    get filteredResources() {
      const q = this.resSearch.trim().toLowerCase();
      if (!q) return this.resources;
      return this.resources.filter(r =>
        (r.title+' '+r.filename+' '+r.uploaded_by).toLowerCase().includes(q));
    },
    resSize(bytes) {
      if (!bytes) return '—';
      return bytes < 1024*1024 ? Math.max(1, Math.round(bytes/1024))+' KB'
                               : (bytes/1048576).toFixed(1)+' MB';
    },
    resCanView(r) {
      return ['pdf','jpg','jpeg','png','gif','txt'].includes(r.ext);
    },
    async loadResources() {
      const r = await fetch('api/resources.php?action=list').then(r=>r.json()).catch(()=>({ok:false}));
      if (r.ok) this.resources = r.data.map(x => ({ ...x, _time: this._fmtDate(x.created_at) }));
    },
    async uploadResources() {
      const input = this.$refs.resFiles;
      if (!input || !input.files.length) { this.addToast('Choose a file first','error'); return; }
      const fd = new FormData();
      fd.append('action', 'upload');
      fd.append('title', this.resTitle.trim());
      for (const f of input.files) fd.append('files[]', f);
      this._resUploading = true;
      const d = await fetch('api/resources.php', { method:'POST', headers:spHeaders(false), body: fd })
        .then(r=>r.json()).catch(()=>({ok:false,message:'Upload failed'}));
      this._resUploading = false;
      if (d.ok) {
        input.value = ''; this.resTitle = '';
        await this.loadResources();
        this.addToast(d.saved.length+' resource(s) added');
        if (d.errors?.length) this.addToast(this._uploadError(d), 'error'); // partial success
      } else this.addToast(this._uploadError(d), 'error');
    },
    async saveResourceTitle() {
      const title = this.resEditTitle.trim();
      if (!title || !this.resEditId) return;
      const d = await fetch('api/resources.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'rename', id:this.resEditId, title }) }).then(r=>r.json());
      if (d.ok) {
        const row = this.resources.find(x => x.id === this.resEditId);
        if (row) row.title = title;
        this.resEditId = null; this.resEditTitle = '';
        this.addToast('Resource renamed');
      } else this.addToast(d.message||'Failed to rename','error');
    },
    async deleteResource(r) {
      if (!confirm('Delete "'+r.title+'"?\n\nThe file will be removed for everyone.')) return;
      const d = await fetch('api/resources.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'delete', id:r.id }) }).then(r=>r.json());
      if (d.ok) {
        this.resources = this.resources.filter(x => x.id !== r.id);
        this.addToast('Resource deleted','info');
      } else this.addToast(d.message||'Failed to delete','error');
    },

    // ── Notes (private to signed-in user) ──
    // ponytail: length heuristic, not a DOM measure — matches the 4-line clamp closely
    // enough at card width. Swap for a scrollHeight check if the card size changes a lot.
    noteIsLong(n) {
      const b = n.body || '';
      return b.length > 150 || (b.match(/\n/g) || []).length >= 3;
    },
    _noteTime(n) {
      const ts = n.updated_at || n.created_at;
      if (!ts) return '';
      const label = n.updated_at && n.updated_at !== n.created_at ? 'Updated ' : '';
      return label + this._fmtDate(ts);
    },
    async loadNotes() {
      const r = await fetch('api/notes.php?action=list').then(r=>r.json());
      if (r.ok) {
        this.callNotes = (r.data || []).map(n => ({ ...n, _time: this._noteTime(n) }));
      } else this.addToast(r.message||'Failed to load notes','error');
    },
    focusNoteComposer() {
      this.noteEditId = null;
      this.$nextTick(() => {
        const el = this.$refs.noteComposer;
        if (el) {
          el.scrollIntoView({ behavior:'smooth', block:'nearest' });
          const input = el.querySelector('input');
          if (input) input.focus();
        }
      });
    },
    async saveNote() {
      const title = this.noteForm.title.trim();
      const body  = this.noteForm.body.trim();
      if (!title || !body) return;
      this._noteSaving = true;
      const r = await fetch('api/notes.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'create', title, body }) }).then(r=>r.json());
      this._noteSaving = false;
      if (r.ok) {
        this.noteForm = { title: '', body: '' };
        await this.loadNotes();
        this.addToast('Note saved');
      } else this.addToast(r.message||'Failed to save note','error');
    },
    startEditNote(n) {
      this.noteEditId = n.id;
      this.noteEditTitle = n.title || '';
      this.noteEditBody = n.body || '';
    },
    cancelEditNote() {
      this.noteEditId = null;
      this.noteEditTitle = '';
      this.noteEditBody = '';
    },
    async updateNote() {
      const title = this.noteEditTitle.trim();
      const body  = this.noteEditBody.trim();
      if (!title || !body || !this.noteEditId) return;
      this._noteSaving = true;
      const r = await fetch('api/notes.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'update', id:this.noteEditId, title, body }) }).then(r=>r.json());
      this._noteSaving = false;
      if (r.ok) {
        this.cancelEditNote();
        await this.loadNotes();
        this.addToast('Note updated');
      } else this.addToast(r.message||'Failed to update note','error');
    },
    async deleteNote(n) {
      if (!confirm('Delete this note? This cannot be undone.')) return;
      const r = await fetch('api/notes.php', { method:'POST', headers:spHeaders(true),
        body: JSON.stringify({ action:'delete', id:n.id }) }).then(r=>r.json());
      if (r.ok) {
        if (this.noteEditId === n.id) this.cancelEditNote();
        this.callNotes = this.callNotes.filter(x => x.id !== n.id);
        this.addToast('Note deleted');
      } else this.addToast(r.message||'Failed to delete note','error');
    },


    // ── Leave ──
    // Casual / WFH / Sick need ≥12h before leave start (midnight); otherwise only Emergency.
    leaveHasNotice(startDate) {
      if (!startDate) return false;
      const start = new Date(startDate + 'T00:00:00');
      if (isNaN(start.getTime())) return false;
      return (start.getTime() - Date.now()) >= 12 * 3600 * 1000;
    },
    get leaveRequestTypes() {
      return this.leaveHasNotice(this.leaveForm.startDate)
        ? this.leaveAdvanceTypes
        : this.leaveShortNoticeTypes;
    },
    get leaveNoticeHint() {
      return this.leaveHasNotice(this.leaveForm.startDate)
        ? 'Casual Leave, WFH and Sick Leave need at least 12 hours notice before the leave start.'
        : 'Less than 12 hours notice only Emergency Leave is available. Admin still confirms before it counts.';
    },
    onLeaveStartChange() {
      if (this.leaveForm.endDate && this.leaveForm.endDate < this.leaveForm.startDate) {
        this.leaveForm.endDate = this.leaveForm.startDate;
      }
      const allowed = this.leaveRequestTypes;
      if (!allowed.includes(this.leaveForm.leaveType)) {
        this.leaveForm.leaveType = allowed[0];
      }
    },
    get leaveDays() {
      const s = this.leaveForm.startDate, e = this.leaveForm.endDate;
      if (!s || !e) return 0;
      const a = new Date(s+'T00:00:00'), b = new Date(e+'T00:00:00');
      if (b < a) return 0;
      return Math.round((b - a) / 86400000) + 1;
    },
    get leaveUserOptions() {
      return [...new Set(this.leaves.map(r => r.name).filter(Boolean))].sort();
    },
    get filteredLeaves() {
      return this.leaves.filter(r => {
        if (this.leaveFilter !== 'all' && r.status !== this.leaveFilter) return false;
        if (this.leaveTypeFilter && (r.leave_type || 'Casual Leave') !== this.leaveTypeFilter) return false;
        if (this.leaveUserFilter && r.name !== this.leaveUserFilter) return false;
        if (this.isAdmin && this.leaveMonth) {
          const m = (r.start_date || '').slice(0, 7);
          if (m && m !== this.leaveMonth) return false;
        }
        return true;
      });
    },
    leaveDateLabel(row) {
      if (!row) return '';
      const fmt = d => { const x = new Date(d+'T00:00:00'); return x.toLocaleDateString([], {day:'2-digit', month:'short', year:'numeric'}); };
      if ((row.leave_type || '') === 'Half Day' || row.start_date === row.end_date) return fmt(row.start_date);
      return fmt(row.start_date)+' – '+fmt(row.end_date);
    },
    leaveDaysLabel(days) {
      const n = Number(days);
      if (n === 0.5) return '0.5';
      return Number.isInteger(n) ? String(n) : String(n);
    },
    leaveSubmittedLabel(ts) {
      if (!ts) return '';
      const d = new Date(String(ts).replace(' ','T'));
      return d.toLocaleDateString([], {day:'2-digit', month:'short', year:'numeric'})
           + ', ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    },
    async loadLeaves() {
      try {
        const r = await fetch('api/leave.php?action=list').then(r=>r.json());
        if (!r.ok) { this.addToast(r.message||'Could not load leave','error'); return; }
        this.leaveSummary = r.summary || this.leaveSummary;
        this.leaves = r.data || [];
        this.pendingLeaves = r.pendingCount || 0;
        if (r.types && r.types.length) this.leaveTypes = r.types;
        if (r.advanceTypes && r.advanceTypes.length) this.leaveAdvanceTypes = r.advanceTypes;
        if (r.shortNoticeTypes && r.shortNoticeTypes.length) this.leaveShortNoticeTypes = r.shortNoticeTypes;
        this.onLeaveStartChange();
      } catch { this.addToast('Could not load leave','error'); }
    },
    openLeaveForm() {
      const today = new Date().toLocaleDateString('en-CA');
      this.leaveForm = { leaveType: 'Emergency Leave', startDate: today, endDate: today, reason: '' };
      this.onLeaveStartChange();
      this.leaveErr = '';
      this.$nextTick(() => {
        const el = this.$refs.leaveStart;
        if (el) { el.scrollIntoView({ behavior:'smooth', block:'center' }); el.focus(); }
      });
    },
    async submitLeave() {
      this.leaveErr = '';
      this.onLeaveStartChange();
      if (!this.leaveForm.leaveType) { this.leaveErr = 'Please select a leave type.'; return; }
      if (!this.leaveRequestTypes.includes(this.leaveForm.leaveType)) {
        this.leaveErr = this.leaveNoticeHint;
        return;
      }
      if (!this.leaveForm.startDate || !this.leaveForm.endDate) { this.leaveErr = 'Start and end dates are required.'; return; }
      if (!this.leaveForm.reason.trim()) { this.leaveErr = 'Please add a reason for this leave.'; return; }
      if (this._leaveSaving) return;
      this._leaveSaving = true;
      const r = await this.api('api/leave.php', { action:'request', leaveType:this.leaveForm.leaveType, startDate:this.leaveForm.startDate, endDate:this.leaveForm.endDate, reason:this.leaveForm.reason.trim() });
      this._leaveSaving = false;
      if (r.ok) {
        const today = new Date().toLocaleDateString('en-CA');
        this.leaveForm = { leaveType: 'Emergency Leave', startDate: today, endDate: today, reason: '' };
        this.onLeaveStartChange();
        this.leaveErr = '';
        this.leaveFilter = 'all';
        await this.loadLeaves();
        this.addToast('Leave request submitted waiting for admin confirmation','success');
      } else this.leaveErr = r.message || 'Could not submit request.';
    },
    exportLeaveCSV() {
      const rows = this.filteredLeaves;
      if (!rows.length) { this.addToast('No leave rows to download','error'); return; }
      const hdr = ['Member','Type','From','To','Days','Reason','Status','Reviewed by','Requested'];
      const body = rows.map(r => [
        r.name, r.leave_type || 'Casual Leave', r.start_date, r.end_date, this.leaveDaysLabel(r.days),
        r.reason, r.status, r.reviewed_by || '', r.created_at || ''
      ]);
      const csv = '\uFEFF'+[hdr,...body].map(r=>r.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
      const a = Object.assign(document.createElement('a'), { href:'data:text/csv;charset=utf-8,'+encodeURIComponent(csv), download:'leave-'+(this.leaveMonth||'all')+'.csv' });
      a.click();
    },
    openLeaveReject(row) {
      this.leaveRejectFor = row;
      this.leaveRejectNote = '';
    },
    async decideLeave(row, status) {
      if (!row) return;
      const note = status === 'rejected' ? (this.leaveRejectNote || '').trim() : '';
      const r = await this.api('api/leave.php', { action:'decide', id: row.id, status, note });
      if (r.ok) {
        this.leaveRejectFor = null;
        this.leaveRejectNote = '';
        await this.loadLeaves();
        this.addToast(status==='approved' ? 'Leave approved' : 'Leave declined', status==='approved'?'success':'info');
      } else this.addToast(r.message||'Could not update request','error');
    },
    async cancelLeave(row) {
      if (!confirm('Cancel this leave request?')) return;
      const r = await this.api('api/leave.php', { action:'cancel', id: row.id });
      if (r.ok) { await this.loadLeaves(); this.addToast('Request cancelled'); }
      else this.addToast(r.message||'Could not cancel','error');
    },

    // ── Warnings ──
    // Cycle label inside the strike window: warning 4 with strike 3 → "Warning 1 of 3".
    warnLevelStep(nth) {
      const s = this.warnStrike || 3;
      return ((Number(nth) - 1) % s) + 1;
    },
    warnLevelLabel(nth) {
      return 'Warning ' + this.warnLevelStep(nth) + ' of ' + (this.warnStrike || 3);
    },
    get warnableUsers() {
      return this.accounts.filter(a => a.status==='approved' && a.role!=='Client' && a.id!==this.currentUserId);
    },
    // Rows arrive newest-first, so the first one seen for a member is their highest number.
    // `_nth` is what makes a row read "warning 3 of 3" that is the row that costs ₹500.
    get warningsWithNth() {
      const total = {}, seen = {};
      this.warnings.forEach(r => total[r.name] = (total[r.name]||0)+1);
      return this.warnings.map(r => {
        seen[r.name] = (seen[r.name]||0)+1;
        return { ...r, _nth: total[r.name] - seen[r.name] + 1 };
      });
    },
    get warnSummary() {
      const total = {};
      this.warnings.forEach(r => total[r.name] = (total[r.name]||0)+1);
      return Object.entries(total)
        .map(([name,count]) => ({ name, count, deduction: Math.floor(count/this.warnStrike)*this.warnAmount }))
        .sort((a,b) => b.count - a.count || a.name.localeCompare(b.name));
    },
    get filteredWarnings() {
      const rows = this.warningsWithNth;
      return this.warnUserFilter ? rows.filter(r => r.name===this.warnUserFilter) : rows;
    },
    warnCountFor(name) {
      return this.warnings.filter(r => r.name===name).length;
    },
    async loadWarnings() {
      try {
        const r = await fetch('api/warnings.php?action=list').then(r=>r.json());
        if (!r.ok) { this.addToast(r.message||'Could not load warnings','error'); return; }
        this.warnings    = r.data || [];
        this.myWarnings  = r.myCount || 0;
        this.myDeduction = r.myDeduction || 0;
        this.warnStrike  = r.strike || this.warnStrike;
        this.warnAmount  = r.amount || this.warnAmount;
      } catch { this.addToast('Could not load warnings','error'); }
    },
    async issueWarning() {
      this.warnErr = '';
      if (!this.warnForm.accountId) { this.warnErr = 'Please pick a user to warn.'; return; }
      if (!this.warnForm.reason.trim()) { this.warnErr = 'Please write the reason for this warning.'; return; }
      if (this._warnSaving) return;
      this._warnSaving = true;
      const r = await this.api('api/warnings.php', { action:'issue', accountId:this.warnForm.accountId, reason:this.warnForm.reason.trim() });
      this._warnSaving = false;
      if (r.ok) {
        this.warnForm = { accountId:'', reason:'' };
        await this.loadWarnings();
        this.addToast(r.count % this.warnStrike === 0
          ? this.warnLevelLabel(r.count) + ' issued — ₹' + this.warnAmount + ' deduction applies'
          : this.warnLevelLabel(r.count) + ' issued',
          r.count % this.warnStrike === 0 ? 'error' : 'success');
      } else this.warnErr = r.message || 'Could not issue warning.';
    },
    async revokeWarning(row) {
      if (!confirm('Withdraw this warning? It stops counting towards the salary deduction.')) return;
      const r = await this.api('api/warnings.php', { action:'revoke', id: row.id });
      if (r.ok) { await this.loadWarnings(); this.addToast('Warning withdrawn'); }
      else this.addToast(r.message||'Could not withdraw warning','error');
    },

    // ── Teams ──
    // due_ts comes from PHP's due_ts() in seconds — the same parse the inquiry side uses —
    // while _now is in ms and ticks every minute, so Pending flips to Overdue without a reload.
    teamTaskState(t) {
      if (t.status === 'Done') return 'done';
      if (t.due_ts && t.due_ts * 1000 < this._now) return 'overdue';
      return t.status === 'In Progress' ? 'progress' : 'pending';
    },
    teamTaskPill(t) {
      const state = this.teamTaskState(t);
      if (state === 'done')     return { text: t.is_late ? 'Done · late' : 'Done',
                                         cls: t.is_late ? 'bg-[#FFFAEB] text-[#B54708]' : 'bg-[#ECFDF3] text-[#16803C]' };
      if (state === 'overdue')  return { text: 'Overdue',     cls: 'bg-[#FEF3F2] text-[#B42318]' };
      if (state === 'progress') return { text: 'In Progress', cls: 'bg-[#EEF4FF] text-[#175CD3]' };
      return { text: 'Pending', cls: 'bg-[#F2F4F7] text-[#344054]' };
    },
    // TBD sorts last — a task with no due date is not more urgent than one with a date.
    _teamByDue(a, b) { return (a.due_ts ?? Infinity) - (b.due_ts ?? Infinity); },
    // My tasks: overdue → in progress → pending → done, then soonest due within each bucket.
    _teamMySort(a, b) {
      const rank = { overdue: 0, progress: 1, pending: 2, done: 3 };
      const d = rank[this.teamTaskState(a)] - rank[this.teamTaskState(b)];
      return d || this._teamByDue(a, b);
    },
    // Relative due line for My tasks — calendar days from _now, date bit from API's due text.
    teamDueLabel(t) {
      if (!t.due_ts) return 'No due date';
      const dateBit = String(t.due || '').split(' · ')[0] || '';
      const dueDay = new Date(t.due_ts * 1000); dueDay.setHours(0, 0, 0, 0);
      const nowDay = new Date(this._now); nowDay.setHours(0, 0, 0, 0);
      const days = Math.round((dueDay - nowDay) / 86400000);
      if (t.status === 'Done') return dateBit ? ('Was due · ' + dateBit) : 'Completed';
      if (days < 0) {
        const n = Math.abs(days);
        return n + (n === 1 ? ' day late' : ' days late') + (dateBit ? ' · ' + dateBit : '');
      }
      if (days === 0) return 'Due today';
      if (days === 1) return 'Due tomorrow';
      return dateBit ? ('Due · ' + dateBit) : ('Due in ' + days + 'd');
    },
    teamTasksOf(team) {
      return team.tasks.filter(t => this.teamShowDone || t.status !== 'Done').sort(this._teamByDue);
    },
    get myTeamTasks() {
      const filter = this.myWorkFilter || 'open';
      return this.teams
        .flatMap(t => t.tasks.map(k => ({ ...k, _team: t.name })))
        .filter(k => {
          if (k.assigned_to !== this.currentUserId) return false;
          const state = this.teamTaskState(k);
          if (filter === 'done') return state === 'done';
          if (filter === 'overdue') return state === 'overdue';
          return state !== 'done';
        })
        .sort((a, b) => this._teamMySort(a, b));
    },
    get myOpenTeamTasks() {
      return this.teams.reduce((n, t) =>
        n + t.tasks.filter(k => k.assigned_to === this.currentUserId && k.status !== 'Done').length, 0);
    },
    get myOverdueTeamTasks() {
      return this.teams.reduce((n, t) =>
        n + t.tasks.filter(k => k.assigned_to === this.currentUserId && this.teamTaskState(k) === 'overdue').length, 0);
    },
    // Done count for the chip — independent of which filter is active.
    get myDoneTeamTasks() {
      return this.teams.reduce((n, t) =>
        n + t.tasks.filter(k => k.assigned_to === this.currentUserId && k.status === 'Done').length, 0);
    },
    // Lead first, then everyone else in the order the API sorted them (by name).
    teamRoster(team) {
      return [...team.members].sort((a, b) =>
        (Number(b.account_id) === team.lead_id) - (Number(a.account_id) === team.lead_id));
    },
    // Clients never join a team; a blocked or pending account cannot be given work.
    get teamCandidates() {
      return this.accounts.filter(a => a.status === 'approved' && a.role !== 'Client');
    },
    get teamPickList() {
      const q = this.teamMemberSearch.trim().toLowerCase();
      if (!q) return this.teamCandidates;
      return this.teamCandidates.filter(a =>
        (a.name + ' ' + a.role).toLowerCase().includes(q));
    },
    get selectedTeam() {
      return this.teams.find(t => t.id === this.teamSelected) || null;
    },
    // Open / overdue / done for one team, plus the same three as bar widths.
    // overdue is a SUBSET of open, so the bar splits open into overdue + the rest.
    teamStats(team) {
      const s = team.tasks.reduce((a, t) => {
        const state = this.teamTaskState(t);
        if (state === 'done') a.done++; else a.open++;
        if (state === 'overdue') a.overdue++;
        return a;
      }, { open: 0, overdue: 0, done: 0 });
      const total = team.tasks.length;
      const pc = n => total ? (n / total) * 100 : 0;
      s.total = total;
      s.pct = { done: pc(s.done), overdue: pc(s.overdue), open: pc(s.open - s.overdue) };
      return s;
    },
    // One colour per state, for the left accent on my work rows.
    teamTaskColor(t) {
      return { done: '#16803C', overdue: '#B42318', progress: '#1268F3', pending: '#D1D9E6' }[this.teamTaskState(t)];
    },
    teamOpenFor(team, accountId) {
      if (!team) return 0;
      return team.tasks.filter(t => t.assigned_to === Number(accountId) && t.status !== 'Done').length;
    },
    // toLocaleDateString('en-CA') is the one-liner that yields a local (not UTC) YYYY-MM-DD,
    // which is what <input type="date"> wants and what due_text() parses server-side.
    get teamDuePresets() {
      const day = n => { const d = new Date(); d.setDate(d.getDate() + n); return d.toLocaleDateString('en-CA'); };
      return [{ label: 'Today', date: day(0) }, { label: 'Tomorrow', date: day(1) }, { label: 'In a week', date: day(7) }];
    },

    async loadTeams() {
      try {
        const r = await fetch('api/teams.php?action=list').then(r => r.json());
        if (!r.ok) { this.addToast(r.message || 'Could not load teams', 'error'); return; }
        this.teams = r.teams || [];
        this.teamStatuses = r.statuses || this.teamStatuses;
        // Keep the board pointed at something real — the selected team may have just
        // been deleted, or this may be the first load.
        if (!this.teams.some(t => t.id === this.teamSelected))
          this.teamSelected = this.teams.length ? this.teams[0].id : 0;
      } catch { this.addToast('Could not load teams', 'error'); }
    },

    openTeamForm(team = null) {
      this.teamErr = '';
      this.teamMemberSearch = '';
      this.teamForm = team
        ? { id: team.id, name: team.name, description: team.description || '' }
        : { id: 0, name: '', description: '' };
      // A new team's lead is whoever is creating it — the server records that on insert.
      this.teamFormLeadId  = team ? Number(team.lead_id) : this.currentUserId;
      this.teamMemberDraft = team ? team.members.map(m => Number(m.account_id)) : [this.currentUserId];
      this.teamFormOpen = true;
    },
    // Two calls, because the API keeps the team row and its roster separate — and the
    // roster half can legitimately refuse (a dropped member with open tasks), so the
    // box stays open on that message with the name change already saved.
    async saveTeam() {
      if (this._teamSaving) return;
      this.teamErr = '';
      if (!this.teamForm.name.trim()) { this.teamErr = 'Please give the team a name.'; return; }
      this._teamSaving = true;
      const r = await this.api('api/teams.php', { action:'save_team', ...this.teamForm, name: this.teamForm.name.trim() });
      if (!r.ok) { this._teamSaving = false; this.teamErr = r.message || 'Could not save the team.'; return; }

      const creating = !this.teamForm.id;
      const m = await this.api('api/teams.php', { action:'set_members', id: r.id, members: this.teamMemberDraft });
      this._teamSaving = false;
      if (!m.ok) { this.teamForm.id = r.id; this.teamErr = m.message || 'Could not save the members.'; await this.loadTeams(); return; }

      this.teamFormOpen = false;
      this.teamSelected = r.id;   // a team you just made or edited is the one you want on screen
      await this.loadTeams();
      this.addToast(creating ? 'Team created — you are its lead' : 'Team updated');
    },
    async deleteTeam(team) {
      if (!confirm('Delete "'+team.name+'"? Its members and tasks go with it.')) return;
      const r = await this.api('api/teams.php', { action:'delete_team', id: team.id });
      if (r.ok) { await this.loadTeams(); this.addToast('Team deleted'); }
      else this.addToast(r.message || 'Could not delete the team', 'error');
    },

    openTeamTask(team) {
      this.teamTaskErr = '';
      this.teamTaskForm = { assignedTo:'', title:'', requirement:'', due: new Date().toLocaleDateString('en-CA'), dueTime:'' };
      this.teamTaskFor = team;
    },
    async assignTeamTask() {
      if (this._teamSaving) return;
      this.teamTaskErr = '';
      if (!this.teamTaskForm.assignedTo)     { this.teamTaskErr = 'Pick who this task is for.'; return; }
      if (!this.teamTaskForm.title.trim())   { this.teamTaskErr = 'Please write what the task is.'; return; }
      this._teamSaving = true;
      const r = await this.api('api/teams.php', { action:'assign_task', teamId: this.teamTaskFor.id,
        ...this.teamTaskForm, title: this.teamTaskForm.title.trim() });
      this._teamSaving = false;
      if (!r.ok) { this.teamTaskErr = r.message || 'Could not assign the task.'; return; }
      this.teamTaskFor = null;
      await this.loadTeams();
      this.addToast('Task assigned');
    },
    async setTeamTaskStatus(task, status) {
      const r = await this.api('api/teams.php', { action:'task_status', id: task.id, status });
      if (r.ok) { await this.loadTeams(); this.addToast(status === 'Done' ? 'Task completed' : 'Status updated'); }
      else { await this.loadTeams(); this.addToast(r.message || 'Could not update the task', 'error'); }
    },
    async setTeamTaskRemark(task, remark) {
      if ((task.remark || '') === remark) return;
      const r = await this.api('api/teams.php', { action:'task_status', id: task.id, status: task.status, remark });
      if (r.ok) { await this.loadTeams(); this.addToast('Remark saved'); }
      else this.addToast(r.message || 'Could not save the remark', 'error');
    },
    async deleteTeamTask(task) {
      if (!confirm('Remove "'+task.title+'" from this team?')) return;
      const r = await this.api('api/teams.php', { action:'delete_task', id: task.id });
      if (r.ok) { await this.loadTeams(); this.addToast('Task removed'); }
      else this.addToast(r.message || 'Could not remove the task', 'error');
    },

    // ── Follow-ups ──
    toggleInquiry(inq) {
      const opening = !inq._open;
      if (opening) this.inquiries.forEach(i => { if (i !== inq) i._open = false; });
      inq._open = opening;
      if (inq._open) this.loadFollowUps(inq);
    },

    inqRowStyle(inq, i) {
      const d = this.darkMode;
      let bg = i % 2 === 1 ? (d ? '#1B222B' : '#F8FAFC') : (d ? '#151B23' : '#ffffff');
      if (this.isOverdue(inq) && !inq._open) bg = d ? '#241A1B' : '#FFEAEA';
      if (inq._open) bg = d ? '#16223A' : '#EFF6FF';
      if (inq._open) {
        return 'border:2px solid #1268F3;margin:2px 0;background:' + bg + ';box-shadow:0 4px 16px rgba(18,104,243,0.18)';
      }
      let style = 'border-bottom:1px solid #E4E7EC;background:' + bg;
      if (this.highlightedInq === inq.id) style += ';box-shadow:inset 3px 0 0 #1268F3';
      return style;
    },

    async loadFollowUps(inq) {
      if (this.followUps[inq.id] !== undefined) return;
      this.followUps = { ...this.followUps, [inq.id]: null };
      const r = await fetch('api/followup.php', {method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'load',inquiryId:inq.id})}).then(r=>r.json()).catch(()=>({ok:false,data:[]}));
      this.followUps = { ...this.followUps, [inq.id]: r.ok ? r.data : [] };
    },

    openFuForm(inq) {
      this.fuOpenFor = inq;
      this.fuForm = { date: new Date().toLocaleDateString('en-CA'), time:'', note:'', assignedTo:'' };
    },

    async addFollowUp(inq) {
      if (!inq) return;
      if (!this.fuForm.note.trim() || !this.fuForm.date) { this.addToast('Note and date are required','error'); return; }
      const assignedTo = this.fuForm.assignedTo || this.currentUser;
      const r = await this.api('api/followup.php',{action:'add',inquiryId:inq.id,note:this.fuForm.note,date:this.fuForm.date,time:this.fuForm.time||null,assignedTo});
      if (r.ok) {
        this.fuOpenFor = null;
        const fu = {id:r.id,inquiry_id:inq.id,note:this.fuForm.note,follow_up_date:this.fuForm.date,follow_up_time:this.fuForm.time||null,assigned_to:assignedTo,client:inq.client||'',company:inq.company||'',completed:0,created_by:this.currentUser};
        this._pushDashboardFu(fu);
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
        this.addToast('Follow-up scheduled','success');
      } else this.addToast(r.message||'Error','error');
    },

    _pushDashboardFu(fu) {
      this.rmFollowUps = [...this.rmFollowUps, fu];
      const today = new Date().toLocaleDateString('en-CA');
      if (!fu.completed && fu.assigned_to === this.currentUser && fu.follow_up_date <= today) {
        this.todayFollowUps = [fu, ...this.todayFollowUps];
      }
    },

    async completeFollowUp(fu, inq) {
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        this.rmFollowUps = this.rmFollowUps.map(f=>f.id===fu.id?{...f,completed:1}:f);
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
        this.addToast('Follow-up completed','success');
      }
    },

    async deleteFollowUp(fu, inq) {
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'delete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
      }
    },

    async completeFuDashboard(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        this.rmFollowUps = this.rmFollowUps.map(f=>f.id===fu.id?{...f,completed:1}:f);
        if (Array.isArray(this.followUps[fu.inquiry_id])) {
          const f = this.followUps[fu.inquiry_id].find(f=>f.id===fu.id);
          if (f) f.completed = 1;
        }
        this.addToast('Follow-up completed','success');
      }
    },

    openFuInquiry(fu) {
      this.goToInquiry(fu.inquiry_id);
    },

    fuTimeLabel(t) {
      if (!t) return '';
      const [h,m] = t.split(':').map(Number);
      return ((h%12)||12)+':'+(m<10?'0':'')+m+' '+(h>=12?'PM':'AM');
    },

    get todayStr() { return new Date().toLocaleDateString('en-CA'); },

    // 'YYYY-MM-DD' the inquiry was created on created_at is authoritative, inq.date is the fallback
    _inqDateKey(inq) {
      if (inq.created_at) return String(inq.created_at).slice(0,10);
      const d = new Date(inq.date);
      return isNaN(d) ? '' : d.toLocaleDateString('en-CA');
    },

    _isCreatedToday(inq) { return this._inqDateKey(inq) === this.todayStr; },

    // ── Inquiry calendar ──

    // { 'YYYY-MM-DD': count } across every inquiry this user can see recomputes
    // automatically when one is added, so no separate calendar fetch is needed.
    // Both calendar sources honour the admin-only user picker; calUser stays '' for everyone else.
    get calSrcInquiries() {
      return this.calUser ? this.visibleInquiries.filter(i => this._inqBelongsTo(i, this.calUser)) : this.visibleInquiries;
    },
    get calSrcFollowUps() {
      return this.calUser ? this.rmFollowUps.filter(f => this._fuBelongsTo(f, this.calUser)) : this.rmFollowUps;
    },

    get calCounts() {
      const m = {};
      this.calSrcInquiries.forEach(i => { const k = this._inqDateKey(i); if (k) m[k] = (m[k]||0) + 1; });
      return m;
    },

    // { 'YYYY-MM-DD': count } of open follow-ups same rmFollowUps the Reminders view uses
    get calFuCounts() {
      const m = {};
      this.calSrcFollowUps.forEach(f => { if (!f.completed && f.follow_up_date) m[f.follow_up_date] = (m[f.follow_up_date]||0) + 1; });
      return m;
    },

    // Month grid: leading nulls pad to the first weekday, then one cell per day
    get calCells() {
      const [y, mo] = this.calMonth.split('-').map(Number);
      const counts  = this.calCounts;
      const fu      = this.calFuCounts;
      const byDate  = {};
      (this.holidays || []).forEach(h => { if (h.holiday_date) byDate[h.holiday_date] = h; });
      const cells   = Array.from({ length: new Date(y, mo-1, 1).getDay() }, () => null);
      for (let d = 1; d <= new Date(y, mo, 0).getDate(); d++) {
        const key = this.calMonth + '-' + String(d).padStart(2,'0');
        const dow = new Date(y, mo-1, d).getDay(); // 0=Sun … 6=Sat
        cells.push({
          key, day: d, count: counts[key] || 0, fu: fu[key] || 0,
          isSun: dow === 0, isSat: dow === 6,
          holiday: byDate[key] || null,
        });
      }
      return cells;
    },

    calCellTitle(c) {
      if (!c) return '';
      const parts = [c.key];
      if (c.isSun) parts.push('Sunday holiday');
      if (c.holiday) parts.push(c.holiday.title + ' (' + c.holiday.holiday_type + ')');
      parts.push(c.count + (c.count === 1 ? ' inquiry' : ' inquiries') + ' created');
      parts.push(c.fu + (c.fu === 1 ? ' follow-up' : ' follow-ups') + ' due');
      return parts.join(' — ');
    },

    async loadHolidays() {
      const year = parseInt(String(this.calMonth || '').slice(0, 4), 10) || new Date().getFullYear();
      try {
        const r = await fetch('api/holidays.php?action=list&year=' + year).then(r => r.json());
        if (r.ok) {
          this.holidays = r.data || [];
          this.holYearLoaded = year;
          if (r.defaults) this.holDefaults = r.defaults;
          // Keep manage list in sync when viewing the same year
          if (this.holManageYear === year) this.holManageList = r.data || [];
        }
      } catch (_) {}
    },

    async loadHolManageList() {
      const year = parseInt(this.holManageYear, 10) || new Date().getFullYear();
      try {
        const r = await fetch('api/holidays.php?action=list&year=' + year).then(r => r.json());
        if (r.ok) {
          this.holManageList = r.data || [];
          if (r.defaults) this.holDefaults = r.defaults;
          // Keep calendar colors in sync when managing the displayed year
          const calY = parseInt(String(this.calMonth || '').slice(0, 4), 10);
          if (calY === year) {
            this.holidays = r.data || [];
            this.holYearLoaded = year;
          }
        }
      } catch (_) {}
    },

    holToggleManage() {
      if (this.holManageOpen) {
        this.holManageOpen = false;
        this.holFormOpen = false;
        this.holErr = '';
        return;
      }
      const calY = parseInt(String(this.calMonth || '').slice(0, 4), 10) || new Date().getFullYear();
      this.holManageYear = calY;
      this.holManageOpen = true;
      this.holFormOpen = false;
      this.holErr = '';
      this.loadHolManageList();
    },

    holShiftYear(n) {
      this.holSetYear((parseInt(this.holManageYear, 10) || new Date().getFullYear()) + n);
    },

    holSetYear(y) {
      const year = Math.max(2000, Math.min(2100, parseInt(y, 10) || new Date().getFullYear()));
      this.holManageYear = year;
      this.holFormOpen = false;
      this.holErr = '';
      this.loadHolManageList();
    },

    holResetForm() {
      if (!this.holManageOpen) {
        this.holToggleManage();
        return;
      }
      const y = String(this.holManageYear || new Date().getFullYear());
      let date = this.calDate && String(this.calDate).startsWith(y) ? this.calDate : (y + '-01-01');
      this.holForm = {
        id: 0,
        holiday_date: date,
        title: '',
        holiday_type: 'leave',
        color: this.holDefaults.leave || '#7A5AF8',
      };
      this.holErr = '';
      this.holFormOpen = true;
    },

    holEdit(h) {
      this.holForm = {
        id: h.id,
        holiday_date: h.holiday_date,
        title: h.title,
        holiday_type: h.holiday_type || 'leave',
        color: h.color || this.holDefaults[h.holiday_type] || '#7A5AF8',
      };
      this.holErr = '';
      this.holFormOpen = true;
      this.holManageOpen = true;
    },

    async holSave() {
      if (this.holSaving) return;
      this.holErr = '';
      if (!this.holForm.holiday_date || !String(this.holForm.title || '').trim()) {
        this.holErr = 'Date and title are required';
        return;
      }
      // Keep the saved date inside the year being managed
      const y = String(this.holManageYear);
      if (!String(this.holForm.holiday_date).startsWith(y)) {
        this.holErr = 'Pick a date in ' + y;
        return;
      }
      this.holSaving = true;
      const wasUpdate = !!this.holForm.id;
      try {
        const r = await fetch('api/holidays.php', {
          method: 'POST', headers: spHeaders(true),
          body: JSON.stringify({
            action: 'save',
            id: this.holForm.id || 0,
            holiday_date: this.holForm.holiday_date,
            title: String(this.holForm.title).trim(),
            holiday_type: this.holForm.holiday_type,
            color: this.holForm.color,
          }),
        }).then(r => r.json());
        if (!r.ok) { this.holErr = r.error || 'Save failed'; return; }
        this.holFormOpen = false;
        await this.loadHolManageList();
        await this.loadHolidays();
        this.addToast(wasUpdate ? 'Holiday updated' : 'Holiday added');
      } catch (_) {
        this.holErr = 'Network error';
      } finally {
        this.holSaving = false;
      }
    },

    async holDelete(h) {
      if (!h?.id || !confirm('Delete "' + h.title + '" on ' + h.holiday_date + '?')) return;
      const r = await fetch('api/holidays.php', {
        method: 'POST', headers: spHeaders(true),
        body: JSON.stringify({ action: 'delete', id: h.id }),
      }).then(r => r.json()).catch(() => ({ ok: false }));
      if (r.ok) {
        await this.loadHolManageList();
        await this.loadHolidays();
        this.addToast('Holiday removed');
      } else {
        this.addToast(r.error || 'Delete failed', 'error');
      }
    },

    monthLabel(m) {
      const [y, mo] = m.split('-').map(Number);
      return new Date(y, mo-1, 1).toLocaleDateString([], { month:'long', year:'numeric' });
    },

    get calMonthLabel() {
      const [y, mo] = this.calMonth.split('-').map(Number);
      return new Date(y, mo-1, 1).toLocaleDateString([], { month:'long', year:'numeric' });
    },

    get calDateLabel() {
      if (!this.calDate) return '';
      const [y, m, d] = this.calDate.split('-').map(Number);
      return new Date(y, m-1, d).toLocaleDateString([], { weekday:'short', day:'2-digit', month:'short', year:'numeric' });
    },

    // When the inquiry was created — date+time for month view, time-only when a day is selected.
    calCreatedLabel(inq) {
      const raw = String(inq.created_at || '');
      const dateKey = this._inqDateKey(inq);
      const timePart = raw.length >= 16 ? raw.slice(11, 16) : '';
      const timeLabel = timePart ? this.fuTimeLabel(timePart) : '';
      if (this.calDate) return timeLabel || '—';
      if (!dateKey) return timeLabel || '—';
      const [y, m, d] = dateKey.split('-').map(Number);
      const day = new Date(y, m-1, d).toLocaleDateString([], { day:'2-digit', month:'short' });
      return timeLabel ? (day + ' · ' + timeLabel) : day;
    },

    // Selected day, or the whole displayed month when no day is picked
    get calInquiries() {
      const prefix = this.calDate || this.calMonth;
      // newest first; no created_at (date-only fallback) sorts to the end of its day
      const key = i => String(i.created_at || this._inqDateKey(i)); // 'Y-m-d H:i:s' > 'Y-m-d' → no-time sorts last in its day
      return this.calSrcInquiries.filter(i => this._inqDateKey(i).startsWith(prefix))
                 .sort((a,b) => key(b).localeCompare(key(a)));
    },

    // Follow-ups due on the selected day, or across the displayed month. Earliest first —
    // this is a to-do list, unlike the inquiry list above it.
    get calFollowUps() {
      const prefix = this.calDate || this.calMonth;
      const key = f => f.follow_up_date + ' ' + (f.follow_up_time || '23:59');
      return this.calSrcFollowUps.filter(f => String(f.follow_up_date||'').startsWith(prefix))
                 .sort((a,b) => (a.completed - b.completed) || key(a).localeCompare(key(b)));
    },

    get calMonthTotal() {
      return this.calSrcInquiries.filter(i => this._inqDateKey(i).startsWith(this.calMonth)).length;
    },

    calShift(n) {
      const [y, mo] = this.calMonth.split('-').map(Number);
      const d = new Date(y, mo-1+n, 1);
      this.calMonth = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
    },

    // ── Due date / time ──

    // What the team owes on this inquiry, rolled up from its tasks. Overdue beats Pending because
    // a missed deadline is the thing you need to see first; Completed only once every task is in.
    // Cancelled/Rejected tasks are dropped they are not outstanding work and must not block
    // an inquiry from reading Completed.
    teamStatus(inq) {
      // Closing an inquiry closes its tasks with it load_inquiries() already suppresses their
      // `overdue` for the same reason. Leftover steps on a won/lost inquiry are not work anyone
      // still owes, so it must not read Pending next to a Closed ownership pill.
      if (this.isClosed(inq)) return 'Completed';
      const steps = (inq.steps || []).filter(s => !['Cancelled','Rejected'].includes(s.status));
      if (!steps.length) return 'No tasks';
      if (steps.some(s => s.overdue)) return 'Overdue';
      if (steps.every(s => ['Done','Completed'].includes(s.status))) return 'Completed';
      return 'Pending';
    },
    teamStatusClass(t) {
      return t === 'Completed' ? 'bg-[#ECFDF3] text-[#16803C]'
           : t === 'Overdue'   ? 'bg-[#FEF3F2] text-[#B42318]'
           : t === 'Pending'   ? 'bg-[#FFFAEB] text-[#B54708]'
                               : 'bg-[#F2F4F7] text-[#667085]';
    },

    // Closed = no longer actionable. Single source for stats, filters and the due pill.
    isClosed(i) {
      return CLOSED_OUTCOMES.includes((i||{}).outcome) || CLOSED_STAGES.includes((i||{}).stage);
    },

    // Due date + optional 'HH:MM' → ms. No time means end of that day. null when TBD.
    dueTs(inq) {
      if (!inq.due_date || inq.due_date === 'TBD') return null;
      const t = new Date(inq.due_date + ' ' + (inq.due_time || '23:59')).getTime();
      return isNaN(t) ? null : t;
    },

    stepDueTs(step) {
      if (!step?.due || step.due === 'TBD') return null;
      const datePart = this.stepDueDate(step);
      const timePart = this.stepDueTime(step);
      const t = new Date(datePart + (timePart ? ' ' + timePart : '')).getTime();
      return isNaN(t) ? null : t;
    },

    // Popup + soft chime when a task/inquiry due for you is within 5 minutes.
    // Runs on a timer (no page refresh needed) and after inquiry data reloads.
    checkDueWarnings() {
      const now = Date.now();
      this._now = now;
      const windowMs = 5 * 60 * 1000;
      const fresh = [];
      const stepWarnInqIds = new Set();
      const push = (key, item) => {
        if (this._dueWarned[key]) return;
        this._dueWarned[key] = true;
        fresh.push(item);
      };

      // Prefer step warnings; inquiry-level is skipped when a matching open step is also due soon.
      (this.inquiries || []).forEach(inq => {
        if (this.isClosed(inq)) return;
        (inq.steps || []).forEach(step => {
          if (step.assigned_to !== this.currentUser) return;
          if (['Done','Completed','Cancelled','Rejected'].includes(step.status)) return;
          const sts = this.stepDueTs(step);
          if (sts == null) return;
          const left = sts - now;
          if (left > 0 && left <= windowMs) {
            stepWarnInqIds.add(inq.id);
            push('step:'+step.id, {
              key: 'step:'+step.id,
              id: inq.id,
              label: (inq.client || inq.id) + ' · step',
              mins: Math.max(1, Math.ceil(left / 60000)),
              kind: 'task',
              stepId: step.id,
              dueAt: sts
            });
          }
        });
      });

      (this.inquiries || []).forEach(inq => {
        if (this.isClosed(inq)) return;
        if (stepWarnInqIds.has(inq.id)) return;
        const involved = inq.current_owner === this.currentUser
          || inq.created_by === this.currentUser
          || (inq.steps || []).some(s => s.assigned_to === this.currentUser && !['Done','Completed','Cancelled','Rejected'].includes(s.status));
        if (!involved) return;

        const its = this.dueTs(inq);
        if (its == null) return;
        const left = its - now;
        if (left > 0 && left <= windowMs) {
          push('inq:'+inq.id, {
            key: 'inq:'+inq.id,
            id: inq.id,
            label: inq.client || inq.id,
            mins: Math.max(1, Math.ceil(left / 60000)),
            kind: 'inquiry',
            dueAt: its
          });
        }
      });

      // Keep countdown fresh on already-open rows.
      if (this.dueWarnOpen && (this.dueWarnList || []).length) {
        this.dueWarnList = this.dueWarnList
          .map(item => {
            const left = (item.dueAt || 0) - now;
            if (left <= 0) return null;
            return { ...item, mins: Math.max(1, Math.ceil(left / 60000)) };
          })
          .filter(Boolean);
        if (!this.dueWarnList.length && !fresh.length) {
          this.dueWarnOpen = false;
          return;
        }
      }

      if (!fresh.length) return;
      this.dueWarnList = [...fresh, ...(this.dueWarnList || [])].slice(0, 8);
      this.dueWarnOpen = true;
      this.playDueAlertSound();
    },

    playDueAlertSound() {
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        if (!this._audioCtx || this._audioCtx.state === 'closed') this._audioCtx = new Ctx();
        const ctx = this._audioCtx;
        const run = () => {
          const beep = (freq, start, dur, vol=0.28) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'triangle';
            o.frequency.setValueAtTime(freq, start);
            g.gain.setValueAtTime(0.0001, start);
            g.gain.exponentialRampToValueAtTime(vol, start + 0.03);
            g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
            o.connect(g); g.connect(ctx.destination);
            o.start(start); o.stop(start + dur + 0.05);
          };
          const t0 = ctx.currentTime + 0.02;
          beep(740, t0, 0.22, 0.32);
          beep(990, t0 + 0.28, 0.26, 0.36);
          beep(1180, t0 + 0.58, 0.32, 0.4);
        };
        if (ctx.state === 'suspended') ctx.resume().then(run).catch(() => {});
        else run();
      } catch (_) { /* browsers may block audio until a click */ }
    },

    dismissDueWarn() {
      this.dueWarnOpen = false;
      this.dueWarnList = [];
      // Keep _dueWarned so the same items do not pop again until reload / new due window.
    },

    openDueWarnItem(item) {
      const id = item && item.id;
      const stepId = item && item.stepId ? item.stepId : null;
      this.dismissDueWarn();
      if (id) this.goToInquiry(id, stepId);
    },

    // 'completed' | 'overdue' | 'pending' | 'none'
    dueStatus(inq) {
      if (this.isClosed(inq)) return 'completed';
      const ts = this.dueTs(inq);
      if (ts === null) return 'none';
      return ts < this._now ? 'overdue' : 'pending';
    },

    isOverdue(inq) { return this.dueStatus(inq) === 'overdue'; },

    // ── Whose miss is an overdue inquiry? ──
    // isOverdue() is a fact about the inquiry; it says nothing about blame. The inquiry's own
    // due date is the owner's to meet — the creator, or whoever it is currently parked with.
    // A step assignee who delivered on time does not own the inquiry's miss, so it must never
    // land in their overdue card, list, report or chart. Same key rptTeamStats uses for its
    // inquiry columns, so every surface agrees on who owes what.
    inqOwner(inq) { return inq.current_owner || inq.created_by || ''; },
    // `who` empty = nobody in particular (admin looking at the whole team) → count everything.
    isOverdueFor(inq, who) {
      if (!this.isOverdue(inq)) return false;
      return !who || this.inqOwner(inq) === who;
    },
    // Whose numbers the dashboard cards are showing: an admin sees the team, a member themselves.
    get overdueSubject() { return this.isAdmin ? '' : this.currentUser; },
    // Same, for the Reports view — an admin narrowed to one person sees only that person's misses.
    get rptSubject() { return this.isAdmin ? (this.rptUser || '') : this.currentUser; },

    dueLabel(inq) {
      if (!inq.due_date || inq.due_date === 'TBD') return 'TBD';
      return inq.due_date + (inq.due_time ? ' · ' + this.fuTimeLabel(inq.due_time) : '');
    },
    dueDateOnly(inq) {
      if (!inq.due_date || inq.due_date === 'TBD') return 'TBD';
      return inq.due_date;
    },
    dueTimeOnly(inq) {
      if (!inq.due_date || inq.due_date === 'TBD' || !inq.due_time) return '';
      return this.fuTimeLabel(inq.due_time);
    },
    // When the inquiry was generated (created_at), fallback to stored date string.
    inqCreatedDate(inq) {
      if (inq.created_at) {
        const raw = String(inq.created_at).replace(' ', 'T');
        const d = new Date(raw);
        if (!isNaN(d)) return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
      }
      return inq.date || '—';
    },
    inqCreatedTime(inq) {
      if (!inq.created_at) return '';
      const hhmm = String(inq.created_at).slice(11, 16);
      return /^\d{2}:\d{2}$/.test(hhmm) ? this.fuTimeLabel(hhmm) : '';
    },

    // Pill text. Before the due date it stays a plain "Pending" a "6h left" countdown was tried
    // and dropped as noise. Once late it says how late, off _now, which ticks every minute.
    dueCountdown(inq) {
      const st = this.dueStatus(inq);
      if (st === 'completed') return 'Completed';
      if (st === 'none')      return '';
      if (st !== 'overdue')   return 'Pending';
      const mins = Math.floor((this._now - this.dueTs(inq)) / 60000);
      const days = Math.floor(mins / 1440);
      const amt  = mins < 60   ? Math.max(mins, 1) + 'm'
                 : mins < 1440 ? Math.floor(mins / 60) + 'h'
                 : days + (days === 1 ? ' day' : ' days');
      return amt + ' overdue';
    },


    fuStatus(fu) {
      const t = new Date().toLocaleDateString('en-CA');
      if (fu.completed) return 'done';
      if (fu.follow_up_date < t)  return 'overdue';
      if (fu.follow_up_date === t) return 'today';
      return 'upcoming';
    },

    async rmComplete(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        const f = this.rmFollowUps.find(f=>f.id===fu.id);
        if (f) f.completed = 1; // keep in list  shows under History filter
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        if (Array.isArray(this.followUps[fu.inquiry_id])) {
          const fi = this.followUps[fu.inquiry_id].find(f=>f.id===fu.id);
          if (fi) fi.completed = 1;
        }
        this.rmFilter = 'done'; // jump to History so user sees the completed row
        this.addToast('Moved to History','success');
      }
    },

    async rmDel(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify({action:'delete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.rmFollowUps = this.rmFollowUps.filter(f=>f.id!==fu.id);
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
      }
    },

    async rmAdd() {
      this.rmAddErr = '';
      if (!this.rmAddForm.inquiryId) { this.rmAddErr='Select an inquiry.'; return; }
      if (!this.rmAddForm.note.trim()) { this.rmAddErr='Note is required.'; return; }
      if (!this.rmAddForm.date) { this.rmAddErr='Date is required.'; return; }
      const assignedTo = this.rmAddForm.assignedTo || this.currentUser;
      const body = { action:'add', inquiryId:this.rmAddForm.inquiryId, note:this.rmAddForm.note, date:this.rmAddForm.date, time:this.rmAddForm.time||null, assignedTo };
      const r = await fetch('api/followup.php',{method:'POST',headers:spHeaders(true),body:JSON.stringify(body)}).then(r=>r.json());
      if (r.ok) {
        const inq = this.inquiries.find(i=>i.id===this.rmAddForm.inquiryId)||{};
        this._pushDashboardFu({id:r.id,inquiry_id:this.rmAddForm.inquiryId,note:this.rmAddForm.note,follow_up_date:this.rmAddForm.date,follow_up_time:this.rmAddForm.time||null,assigned_to:assignedTo,client:inq.client||'',company:inq.company||'',completed:0,created_by:this.currentUser});
        delete this.followUps[this.rmAddForm.inquiryId];
        this.rmAddOpen = false;
        this.rmAddForm = {inquiryId:'',note:'',date:new Date().toLocaleDateString('en-CA'),time:'',assignedTo:''};
        this.addToast('Follow-up scheduled','success');
      } else this.rmAddErr = r.message||'Error';
    },
    

    // ── Reports ──────────────────────────────────────────────────────────────────
    // `back` = how many periods to step back: 0 is the current window, 1 the one immediately
    // before it. "This month" and "last month" come from this one path so the KPI deltas cannot
    // drift from the figures they are compared against.
    // toLocaleDateString('en-CA') not toISOString() the latter converts to UTC and returns
    // yesterday at 2 AM IST.
    rptRange(back = 0) {
      const t = new Date(); t.setHours(0,0,0,0);
      const fmt = d => d.toLocaleDateString('en-CA');
      const p = this.rptPeriod;
      if (p==='today')   { const d=new Date(t); d.setDate(t.getDate()-back); return [fmt(d),fmt(d)]; }
      if (p==='week')    { const s=new Date(t); s.setDate(t.getDate()-t.getDay()-back*7);
                           const e=new Date(s); e.setDate(s.getDate()+6);
                           return [fmt(s), fmt(back ? e : t)]; }
      if (p==='month')   { const s=new Date(t.getFullYear(), t.getMonth()-back, 1);
                           const e=new Date(t.getFullYear(), t.getMonth()-back+1, 0);
                           return [fmt(s), fmt(back ? e : t)]; }
      if (p==='quarter') { const q=Math.floor(t.getMonth()/3);
                           const s=new Date(t.getFullYear(), (q-back)*3, 1);
                           const e=new Date(t.getFullYear(), (q-back)*3+3, 0);
                           return [fmt(s), fmt(back ? e : t)]; }
      if (p==='year')    { const s=new Date(t.getFullYear()-back, 0, 1);
                           const e=new Date(t.getFullYear()-back, 11, 31);
                           return [fmt(s), fmt(back ? e : t)]; }
      if (p==='custom')  {
        if (!back) return [this.rptFrom, this.rptTo];
        // Shift the custom window back by its own length so the comparison is like-for-like
        if (!this.rptFrom || !this.rptTo) return ['',''];
        const a=new Date(this.rptFrom), b=new Date(this.rptTo);
        const days=Math.round((b-a)/86400000)+1;
        // back=1 is the window immediately before; back=n steps n whole windows back, which the
        // on-time trend needs. Stepping only one window regardless of `back` would have drawn the
        // same point six times.
        const pe=new Date(a); pe.setDate(a.getDate()-1-(back-1)*days);
        const ps=new Date(pe); ps.setDate(pe.getDate()-days+1);
        return [fmt(ps), fmt(pe)];
      }
      return ['',''];   // 'all' no window, so no previous window either
    },
    get rptDateRange() { return this.rptRange(0); },
    get rptPeriodLabel() {
      return {today:'Today',week:'This Week',month:'This Month',quarter:'This Quarter',year:'This Year',all:'All Time',custom:'Custom Range'}[this.rptPeriod]||'';
    },
    // Plain-language scope line under the page title — "My inquiries · This Month" alone
    // was too cryptic for both members and admins.
    get rptScopeTitle() {
      if (this.isAdmin && !this.rptUser && !this.rptShowOverall) return 'People · ' + this.rptPeriodLabel;
      const who = this.rptUser ? this.rptUser
                : (this.isAdmin ? 'Overall team' : 'My inquiries');
      return who + ' · ' + this.rptPeriodLabel;
    },
    get rptScopeHint() {
      if (this.rptUser) return 'Overdue, pending, late closes, and time to close for this person.';
      if (this.isAdmin && this.rptShowOverall) return 'Team-wide totals. Pick a person for the full user report.';
      if (this.isAdmin) return 'Choose a person to open their report. Overall team is secondary.';
      return 'Your overdue, pending, late closes, and time to close.';
    },
    openRptUser(name) {
      this.rptUser = name || '';
      this.rptShowOverall = false;
      this._scheduleRptCharts();
    },
    openRptOverall() {
      this.rptUser = '';
      this.rptShowOverall = true;
      this._scheduleRptCharts();
    },
    openRptPeople() {
      this.rptUser = '';
      this.rptShowOverall = false;
      try { if (this.rptVolumeChart) { this.rptVolumeChart.destroy(); this.rptVolumeChart = null; } } catch (e) {}
      try { if (this.rptPerfChart) { this.rptPerfChart.destroy(); this.rptPerfChart = null; } } catch (e) {}
    },
    // Alpine x-if mounts canvases one tick after rptUser flips — retry until they are in the DOM.
    _scheduleRptCharts() {
      clearTimeout(this._rptChartTimer);
      this._rptChartTimer = setTimeout(() => this.initRptCharts(0), 60);
    },
    get rptRangeText() {
      if (this.rptPeriod === 'all') return 'All dates';
      const [from, to] = this.rptDateRange;
      if (!from && !to) return '';
      const fmt = iso => {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        if (isNaN(d)) return iso;
        return d.toLocaleDateString([], { day:'numeric', month:'short', year:'numeric' });
      };
      if (from === to) return fmt(from);
      return fmt(from) + ' – ' + fmt(to);
    },
    get rptSummary() {
      // Inquiry numbers come from what the subject owns; task numbers from every inquiry they
      // touch (their own steps on someone else's inquiry still count as their work).
      const rows = this.rptOwnedInquiries;
      let open = 0, closed = 0, overdue = 0, lateClosed = 0, pending = 0;
      let tasks = 0, taskMiss = 0;
      let closeSumMs = 0, closeN = 0;
      let overdueHrs = 0, pendingHrs = 0;
      let clientN = 0, internalN = 0, internalLateStart = 0, internalOnTimeStart = 0, internalEarlyStart = 0;
      let lateStartMinsSum = 0;
      const who = this.rptSubject;
      const now = this._now || Date.now();
      const office = this.rptOfficeStartMins;
      const lateCut = this.rptLateStartMins;
      rows.forEach(i => {
        const isInternal = (i.inquiry_type || '') === 'Internal Usage';
        if (isInternal) {
          internalN++;
          const mins = this._rptCreatedClockMins(i);
          if (mins != null) {
            if (mins >= lateCut) {
              internalLateStart++;
              lateStartMinsSum += (mins - lateCut);
            } else if (mins < office) {
              internalEarlyStart++;
            } else {
              internalOnTimeStart++;
            }
          }
        } else {
          clientN++;
        }
        if (this.isClosed(i)) {
          closed++;
          if (i.late) lateClosed++;
          const c0 = this._rptCreatedTs(i), c1 = this._rptClosedTs(i);
          if (c0 != null && c1 != null && c1 >= c0) { closeSumMs += (c1 - c0); closeN++; }
        } else {
          open++;
          const dts = this.dueTs(i);
          if (this.isOverdue(i)) {
            overdue++;
            if (dts != null) overdueHrs += Math.max(0, (now - dts) / 3600000);
          } else {
            pending++;
            if (dts != null && dts > now) pendingHrs += (dts - now) / 3600000;
          }
        }
      });

      // Tasks are counted off the involvement scope: the subject's steps live on other
      // people's inquiries too, and those are still the subject's own delivery record.
      this.rptInquiries.forEach(i => {
        (i.steps || []).forEach(s => {
          if (!s.assigned_to) return;
          if (who && s.assigned_to !== who) return;
          tasks++;
          if (s.overdue || s.late) taskMiss++;
        });
      });
      // Previous period must be scoped the same way as `rows`, or the delta compares the
      // subject's own inquiries against every inquiry they touched.
      const prev = this._rptScope(this.rptPeriod === 'all' ? null : this.rptRange(1));
      const prevTotal = (who ? prev.filter(i => this.inqOwner(i) === who) : prev).length;
      const deltaPct = prevTotal === 0
        ? (rows.length > 0 ? 100 : null)
        : Math.round(((rows.length - prevTotal) / prevTotal) * 100);
      // Time usage ≈ time spent closing work + hours already late (open overdue).
      const usageMs = closeSumMs + (overdueHrs * 3600000);
      return {
        total: rows.length,
        open, closed, overdue, pending, lateClosed,
        onTrack: pending,
        tasks,
        onTimePct: tasks ? Math.round((tasks - taskMiss) / tasks * 100) : null,
        deltaPct,
        closedPct: rows.length ? Math.round((closed / rows.length) * 100) : 0,
        overduePct: rows.length ? Math.round((overdue / rows.length) * 100) : 0,
        avgCloseMs: closeN ? Math.round(closeSumMs / closeN) : null,
        avgCloseLabel: closeN ? this._rptFmtDuration(closeSumMs / closeN) : '—',
        totalCloseLabel: closeN ? this._rptFmtDuration(closeSumMs) : '—',
        usageLabel: (closeN || overdueHrs) ? this._rptFmtDuration(usageMs) : '—',
        overdueHrs: Math.round(overdueHrs),
        pendingHrs: Math.round(pendingHrs),
        overdueHrsLabel: this._rptFmtHours(overdueHrs),
        pendingHrsLabel: this._rptFmtHours(pendingHrs),
        closeSample: closeN,
        clientN, internalN, internalLateStart, internalOnTimeStart, internalEarlyStart,
        avgLateStartLabel: internalLateStart
          ? this._rptFmtDuration((lateStartMinsSum / internalLateStart) * 60000)
          : '—',
        closedOnTime: Math.max(0, closed - lateClosed),
      };
    },

    // Doughnut slices for the focused user (or overall): status mix of inquiries in scope.
    get rptPerfPie() {
      const s = this.rptSummary;
      const slices = [
        { key: 'ontime',  label: 'Closed on time', value: s.closedOnTime || 0, color: '#16803C' },
        { key: 'late',    label: 'Late closed',    value: s.lateClosed || 0,   color: '#D97706' },
        { key: 'pending', label: 'Pending',        value: s.pending || 0,      color: '#1268F3' },
        { key: 'overdue', label: 'Overdue',        value: s.overdue || 0,      color: '#B42318' },
      ].filter(x => x.value > 0);
      const total = slices.reduce((n, x) => n + x.value, 0);
      return {
        total,
        slices: slices.map(x => ({
          ...x,
          pct: total ? Math.round((x.value / total) * 100) : 0,
        })),
      };
    },

    _rptCreatedTs(inq) {
      if (inq?.created_at) {
        const t = new Date(String(inq.created_at).replace(' ', 'T')).getTime();
        if (!isNaN(t)) return t;
      }
      const day = this._rptParseDay(inq?.date || '');
      if (!day) return null;
      const t = new Date(day + 'T12:00:00').getTime();
      return isNaN(t) ? null : t;
    },
    // Minutes from local midnight for create time. Needs a real clock (created_at);
    // date-only rows cannot be scored for Internal late-start.
    _rptCreatedClockMins(inq) {
      if (!inq?.created_at) return null;
      const d = new Date(String(inq.created_at).replace(' ', 'T'));
      if (isNaN(d.getTime())) return null;
      return d.getHours() * 60 + d.getMinutes();
    },
    // Internal Usage must be created before 11:30; 11:30 and later = late start.
    _rptIsInternalLateStart(inq) {
      if ((inq?.inquiry_type || '') !== 'Internal Usage') return false;
      const mins = this._rptCreatedClockMins(inq);
      return mins != null && mins >= this.rptLateStartMins;
    },
    _rptFmtClock(mins) {
      if (mins == null || !isFinite(mins)) return '—';
      const h = Math.floor(mins / 60), m = mins % 60;
      const ampm = h >= 12 ? 'PM' : 'AM';
      const h12 = ((h + 11) % 12) + 1;
      return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
    },
    _rptClosedTs(inq) {
      if (!inq?.closed_at) return null;
      const t = new Date(String(inq.closed_at).replace(' ', 'T')).getTime();
      return isNaN(t) ? null : t;
    },
    _rptFmtDuration(ms) {
      if (ms == null || !isFinite(ms) || ms < 0) return '—';
      const mins = Math.round(ms / 60000);
      if (mins < 60) return mins + 'm';
      const hrs = Math.round(ms / 3600000);
      if (hrs < 48) return hrs + 'h';
      const d = Math.floor(hrs / 24), h = hrs % 24;
      return h ? (d + 'd ' + h + 'h') : (d + 'd');
    },
    _rptFmtHours(hrs) {
      if (hrs == null || !isFinite(hrs) || hrs <= 0) return '—';
      const h = Math.round(hrs);
      if (h < 48) return h + 'h';
      const d = Math.floor(h / 24), rh = h % 24;
      return rh ? (d + 'd ' + rh + 'h') : (d + 'd');
    },

    // Overdue inquiries first, then closed-late — actionable list for admin & member.
    get rptAttention() {
      const rows = this.rptOwnedInquiries;
      const overdue = rows.filter(i => !this.isClosed(i) && this.isOverdue(i))
        .sort((a, b) => (this.dueTs(a) || 0) - (this.dueTs(b) || 0))
        .map(i => ({ i, kind: 'overdue' }));
      const late = rows.filter(i => this.isClosed(i) && i.late)
        .map(i => ({ i, kind: 'late' }));
      return [...overdue, ...late].slice(0, 25);
    },
    // Same definition as isClosed(). It used to carry its own copy that also counted 'No Reply'
    // as closed a client going quiet is not a closure, and the report disagreed with the
    // dashboard on the same inquiry.
    _rptClosed(i) { return this.isClosed(i); },
    get rptInquiries() {
      return this._rptScope(this.rptPeriod==='all' ? null : this.rptDateRange);
    },
    // Every inquiry the subject is involved in — created, owns, or has a task on. Right for
    // workload questions ("what am I touching?"), wrong for performance ones.
    //
    // An inquiry's own deadline is the owner's to meet, so closed / late-closed / overdue /
    // pending / hours / avg-close must all be counted from THIS list, not the one above. B
    // finishing their task on time then appearing in A's late closure was exactly that
    // mistake: B stays in scope through the completed task, so every unguarded counter
    // picked them up. Scoping once here beats guarding each counter and missing one.
    get rptOwnedInquiries() {
      const who = this.rptSubject;
      if (!who) return this.rptInquiries;            // admin, whole team — count everything once
      return this.rptInquiries.filter(i => this.inqOwner(i) === who);
    },
    // Inquiry columns key off the owner; task columns off each step's assignee, so a missed
    // deadline lands on whoever actually owed the work. `overdue` = still open past due,
    // `late` = submitted after due a late submit clears overdue, so both must be counted.
    // The inquiries the report covers, for an arbitrary window. rptInquiries is this with the
    // current window; the KPI row reuses it for the previous one.
    _rptScope(range) {
      let v = this.isAdmin ? this.inquiries : this.visibleInquiries;
      if (this.isAdmin && this.rptUser)
        v = v.filter(i => i.created_by===this.rptUser || i.current_owner===this.rptUser
                       || (i.steps||[]).some(s=>s.assigned_to===this.rptUser));
      if (!range) return v;
      const [from,to] = range;
      if (!from && !to) return v;
      return v.filter(i => {
        const d = (i.created_at||i.date||'').slice(0,10);
        return d && (!from||d>=from) && (!to||d<=to);
      });
    },

    get rptTeamStats() {
      const map = {}, row = k => map[k] || (map[k] = {
        name: k, inqs: 0, active: 0, pending: 0, closed: 0, lateClosed: 0,
        tasksOD: 0, inqLate: 0, tasks: 0, taskOD: 0, taskLate: 0,
        closeSumMs: 0, closeN: 0, overdueHrs: 0, pendingHrs: 0,
        internalN: 0, internalLateStart: 0,
      });
      const now = this._now || Date.now();
      // People directory needs every approved member for the period — use unscoped-by-user rows.
      const scope = this.isAdmin && !this.rptUser
        ? this._rptScope(this.rptPeriod === 'all' ? null : this.rptDateRange)
        : this.rptInquiries;
      scope.forEach(i => {
        const key = i.current_owner || i.created_by;
        if (key) {
          const m = row(key);
          m.inqs++;
          if ((i.inquiry_type || '') === 'Internal Usage') {
            m.internalN++;
            if (this._rptIsInternalLateStart(i)) m.internalLateStart++;
          }
          if (this._rptClosed(i)) {
            m.closed++;
            if (i.late) { m.lateClosed++; m.inqLate++; }
            const c0 = this._rptCreatedTs(i), c1 = this._rptClosedTs(i);
            if (c0 != null && c1 != null && c1 >= c0) { m.closeSumMs += (c1 - c0); m.closeN++; }
          } else {
            m.active++;
            const dts = this.dueTs(i);
            if (this.isOverdue(i)) {
              m.tasksOD++;
              if (dts != null) m.overdueHrs += Math.max(0, (now - dts) / 3600000);
            } else {
              m.pending++;
              if (dts != null && dts > now) m.pendingHrs += (dts - now) / 3600000;
            }
          }
        }
        (i.steps || []).forEach(s => {
          if (!s.assigned_to) return;
          const m = row(s.assigned_to);
          m.tasks++;
          if (s.overdue) m.taskOD++;
          else if (s.late) m.taskLate++;
        });
      });
      const rows = Object.values(map).map(m => ({
        ...m,
        avgCloseMs: m.closeN ? Math.round(m.closeSumMs / m.closeN) : null,
        avgCloseLabel: m.closeN ? this._rptFmtDuration(m.closeSumMs / m.closeN) : '—',
        overdueHrsLabel: this._rptFmtHours(m.overdueHrs),
        pendingHrsLabel: this._rptFmtHours(m.pendingHrs),
        onTimePct: m.tasks ? Math.round((m.tasks - m.taskOD - m.taskLate) / m.tasks * 100) : null,
        closePct: m.inqs ? Math.round(m.closed / m.inqs * 100) : 0,
      }));
      const sorters = {
        risk:    (a,b) => (b.taskOD + b.taskLate + b.tasksOD + b.inqLate + b.internalLateStart) - (a.taskOD + a.taskLate + a.tasksOD + a.inqLate + a.internalLateStart) || b.inqs - a.inqs,
        ontime:  (a,b) => this._onTimePct(b) - this._onTimePct(a),
        overdue: (a,b) => b.tasksOD - a.tasksOD || b.taskOD - a.taskOD,
        close:   (a,b) => (b.inqs ? b.closed / b.inqs : 0) - (a.inqs ? a.closed / a.inqs : 0),
        avgclose:(a,b) => (b.avgCloseMs || 0) - (a.avgCloseMs || 0),
        name:    (a,b) => a.name.localeCompare(b.name),
      };
      return rows.sort(sorters[this.rptSort] || sorters.risk);
    },
    // Admin people directory: every approved account + period snapshot (zeros if idle).
    get rptPeople() {
      const byName = {};
      this.rptTeamStats.forEach(r => { byName[r.name] = r; });
      const empty = name => ({
        name, inqs: 0, pending: 0, tasksOD: 0, closed: 0, lateClosed: 0,
        internalN: 0, internalLateStart: 0, avgCloseLabel: '—',
        overdueHrsLabel: '—', pendingHrsLabel: '—', onTimePct: null,
      });
      const people = (this.accounts || [])
        .filter(a => a.status === 'approved' && a.role !== 'Client')
        .map(a => byName[a.name] || empty(a.name));
      // Include orphan owners who appear in data but not in accounts.
      Object.keys(byName).forEach(n => {
        if (!people.some(p => p.name === n)) people.push(byName[n]);
      });
      return people.sort((a, b) =>
        (b.tasksOD + b.lateClosed + b.internalLateStart) - (a.tasksOD + a.lateClosed + a.internalLateStart)
        || b.inqs - a.inqs
        || a.name.localeCompare(b.name));
    },
    // Internal Usage created at/after 11:30 — actionable list on the user report.
    get rptInternalLate() {
      return this.rptOwnedInquiries
        .filter(i => this._rptIsInternalLateStart(i))
        .sort((a, b) => (this._rptCreatedTs(b) || 0) - (this._rptCreatedTs(a) || 0))
        .slice(0, 25)
        .map(i => {
          const mins = this._rptCreatedClockMins(i);
          return { i, createdLabel: this._rptFmtClock(mins), minsPast: mins != null ? mins - this.rptLateStartMins : 0 };
        });
    },
    get rptLateClosed() {
      return this.rptOwnedInquiries
        .filter(i => this.isClosed(i) && i.late)
        .sort((a, b) => (this._rptClosedTs(b) || 0) - (this._rptClosedTs(a) || 0))
        .slice(0, 25);
    },
    // Share of a member's tasks delivered on or before the due date. -1 for "no tasks" so those
    // rows sort last rather than tying with a genuine 0%.
    _onTimePct(m) { return m.tasks ? Math.round((m.tasks-m.taskOD-m.taskLate)/m.tasks*100) : -1; },

    // Inquiry volume series for the grouped bar chart.
    // Bucket: user pick (Daily/Weekly/Monthly/Quarterly/Yearly) or Auto from the window.
    get rptVolumeSeries() {
      const [from, to] = this.rptDateRange;
      // Owner-scoped: the bars read as "this person's work over time", and its overdue bar
      // has to agree with the Overdue card above it.
      const inqs = this.rptOwnedInquiries;
      if (!inqs.length) return [];
      const dates = inqs.map(i => (i.created_at || i.date || '').slice(0, 10)).filter(Boolean).sort();
      const start = from || dates[0], end = to || dates[dates.length - 1];
      if (!start || !end) return [];
      const d0 = new Date(start + 'T00:00:00'), last = new Date(end + 'T00:00:00');
      if (isNaN(d0) || isNaN(last) || last < d0) return [];
      const span = Math.round((last - d0) / 86400000) + 1;
      const pick = this.rptVolumeBucket || 'auto';
      const mode = pick !== 'auto' ? pick
        : (span <= 14 ? 'day' : (span <= 92 ? 'week' : (span <= 370 ? 'month' : 'quarter')));
      const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const fmtDay = d => d.toLocaleDateString('en-CA');
      const buckets = [];
      const cur = new Date(d0);

      // Align the cursor to the start of the chosen period (still clipped to the window).
      if (mode === 'week') {
        // Keep starting at d0 — week buckets roll forward 7 days from range start.
      } else if (mode === 'month') {
        cur.setDate(1);
        if (cur < d0) cur.setTime(d0.getTime());
      } else if (mode === 'quarter') {
        cur.setMonth(Math.floor(cur.getMonth() / 3) * 3, 1);
        if (cur < d0) cur.setTime(d0.getTime());
      } else if (mode === 'year') {
        cur.setMonth(0, 1);
        if (cur < d0) cur.setTime(d0.getTime());
      }

      let guard = 0;
      while (cur <= last && guard++ < 800) {
        let lo, hi, label, key;
        if (mode === 'day') {
          lo = hi = fmtDay(cur);
          key = lo;
          label = cur.getDate() + ' ' + monthNames[cur.getMonth()];
          cur.setDate(cur.getDate() + 1);
        } else if (mode === 'week') {
          lo = fmtDay(cur);
          const endW = new Date(cur); endW.setDate(cur.getDate() + 6);
          if (endW > last) endW.setTime(last.getTime());
          hi = fmtDay(endW);
          key = lo;
          label = cur.getDate() + ' ' + monthNames[cur.getMonth()];
          cur.setDate(cur.getDate() + 7);
        } else if (mode === 'month') {
          const mStart = new Date(cur.getFullYear(), cur.getMonth(), 1);
          const mEnd = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
          lo = fmtDay(mStart < d0 ? d0 : mStart);
          hi = fmtDay(mEnd > last ? last : mEnd);
          key = cur.getFullYear() + '-' + String(cur.getMonth() + 1).padStart(2, '0');
          label = monthNames[cur.getMonth()] + " '" + String(cur.getFullYear()).slice(2);
          cur.setMonth(cur.getMonth() + 1, 1);
        } else if (mode === 'quarter') {
          const q = Math.floor(cur.getMonth() / 3);
          const qStart = new Date(cur.getFullYear(), q * 3, 1);
          const qEnd = new Date(cur.getFullYear(), q * 3 + 3, 0);
          lo = fmtDay(qStart < d0 ? d0 : qStart);
          hi = fmtDay(qEnd > last ? last : qEnd);
          key = cur.getFullYear() + '-Q' + (q + 1);
          label = 'Q' + (q + 1) + ' ' + cur.getFullYear();
          cur.setMonth(q * 3 + 3, 1);
        } else { // year
          const yStart = new Date(cur.getFullYear(), 0, 1);
          const yEnd = new Date(cur.getFullYear(), 11, 31);
          lo = fmtDay(yStart < d0 ? d0 : yStart);
          hi = fmtDay(yEnd > last ? last : yEnd);
          key = String(cur.getFullYear());
          label = String(cur.getFullYear());
          cur.setFullYear(cur.getFullYear() + 1, 0, 1);
        }
        if (lo > hi) continue;
        const created = inqs.filter(i => {
          const k = (i.created_at || i.date || '').slice(0, 10);
          return k && k >= lo && k <= hi;
        }).length;
        const closed = inqs.filter(i => {
          if (!this.isClosed(i)) return false;
          const k = (i.closed_at || i.created_at || i.date || '').slice(0, 10);
          return k && k >= lo && k <= hi;
        }).length;
        const overdue = inqs.filter(i => {
          if (this.isClosed(i) || !this.isOverdueFor(i, this.rptSubject)) return false;
          const k = (i.created_at || i.date || '').slice(0, 10);
          return k && k >= lo && k <= hi;
        }).length;
        buckets.push({ key, label, mode, created, closed, overdue });
      }
      return buckets;
    },
    get rptVolumeMax() {
      const s = this.rptVolumeSeries;
      if (!s.length) return 1;
      return Math.max(1, ...s.map(d => Math.max(d.created, d.closed, d.overdue)));
    },
    get rptVolumeBucketHint() {
      const pick = this.rptVolumeBucket || 'auto';
      if (pick !== 'auto') {
        return { day:'Daily', week:'Weekly', month:'Monthly', quarter:'Quarterly', year:'Yearly' }[pick] || '';
      }
      const s = this.rptVolumeSeries;
      if (!s.length) return '';
      return { day:'Daily', week:'Weekly', month:'Monthly', quarter:'Quarterly', year:'Yearly' }[s[0].mode] || '';
    },

    // Stage mix for the donut — cumulative % so conic-gradient segments meet exactly.
    // Owner-scoped so the donut's slice count matches the summary cards beside it.
    get rptStageDist() {
      const rows = this.rptOwnedInquiries;
      const counts = {};
      rows.forEach(i => {
        const s = i.stage || 'Other';
        counts[s] = (counts[s] || 0) + 1;
      });
      const total = rows.length;
      if (!total) return [];
      const palette = ['#175CD3','#5925DC','#C4320A','#026AA2','#16803C','#B54708','#344054','#7A5AF8','#DD2590'];
      const known = (typeof ALL_STAGES !== 'undefined' ? ALL_STAGES : []).filter(s => counts[s]);
      const rest = Object.keys(counts).filter(s => !known.includes(s)).sort();
      let acc = 0, idx = 0;
      return [...known, ...rest].map(name => {
        const count = counts[name];
        const start = Math.round((acc / total) * 100);
        acc += count;
        const end = Math.round((acc / total) * 100);
        return {
          name, count,
          pct: Math.round((count / total) * 100),
          start, end,
          color: palette[idx++ % palette.length],
        };
      });
    },
    get rptStageConic() {
      const parts = this.rptStageDist;
      if (!parts.length) return '#F2F4F7 0% 100%';
      return parts.map(p => p.color + ' ' + p.start + '% ' + p.end + '%').join(', ');
    },

    // Parse a step/team due string into YYYY-MM-DD (local). Accepts ISO prefix or "01 Sep 2026…".
    _rptParseDay(raw) {
      if (!raw) return '';
      const s = String(raw).trim();
      if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
      const m = s.match(/(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(\d{4})/i);
      if (!m) return '';
      const months = {jan:0,feb:1,mar:2,apr:3,may:4,jun:5,jul:6,aug:7,sep:8,oct:9,nov:10,dec:11};
      const d = new Date(Number(m[3]), months[m[2].slice(0,3).toLowerCase()], Number(m[1]));
      return isNaN(d) ? '' : d.toLocaleDateString('en-CA');
    },

    initRptCharts(attempt = 0) {
      if (typeof Chart === 'undefined' || this.view !== 'reports') return;
      if (this.isAdmin && !this.rptUser && !this.rptShowOverall) return; // people list — no charts

      const needPie = this.rptPerfPie.total > 0;
      const needVol = this.rptVolumeSeries.length > 0;
      const pieEl = document.getElementById('rpt-perf-chart');
      const volEl = document.getElementById('rpt-volume-chart');
      const pieReady = !needPie || (pieEl && pieEl.isConnected && pieEl.clientWidth > 0);
      const volReady = !needVol || (volEl && volEl.isConnected && volEl.clientWidth > 0);

      if ((!pieReady || !volReady) && attempt < 20) {
        clearTimeout(this._rptChartTimer);
        this._rptChartTimer = setTimeout(() => this.initRptCharts(attempt + 1), 50);
        return;
      }

      try { if (this.rptVolumeChart) { this.rptVolumeChart.destroy(); this.rptVolumeChart = null; } } catch (e) {}
      try { if (this.rptPerfChart) { this.rptPerfChart.destroy(); this.rptPerfChart = null; } } catch (e) {}

      const pie = this.rptPerfPie;
      if (pieEl && pie.total > 0) {
        this.rptPerfChart = new Chart(pieEl, {
          type: 'doughnut',
          data: {
            labels: pie.slices.map(s => s.label),
            datasets: [{
              data: pie.slices.map(s => s.value),
              backgroundColor: pie.slices.map(s => s.color),
              hoverBackgroundColor: pie.slices.map(s => s.color),
              borderWidth: 2,
              borderColor: '#ffffff',
              hoverOffset: 4,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            animation: { duration: 700, easing: 'easeOutQuart' },
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: '#fff',
                titleColor: '#344054',
                titleFont: { weight: '700', size: 12 },
                bodyColor: '#172B3A',
                bodyFont: { weight: '600', size: 11 },
                borderColor: '#E4E7EC',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 8,
                displayColors: true,
                boxPadding: 4,
                callbacks: {
                  label: (ctx) => {
                    const v = ctx.raw || 0;
                    const pct = pie.total ? Math.round((v / pie.total) * 100) : 0;
                    return ' ' + v + ' · ' + pct + '%';
                  },
                },
              },
            },
          },
        });
        try { this.rptPerfChart.resize(); } catch (e) {}
      }

      if (!volEl || !needVol) return;
      const series = this.rptVolumeSeries;
      const tick = { color: '#98A2B3', font: { size: 10, family: 'Inter, system-ui, sans-serif', weight: '600' } };
      const barRadius = { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 };
      this.rptVolumeChart = new Chart(volEl, {
        type: 'bar',
        data: {
          labels: series.map(d => d.label),
          datasets: [
            {
              label: 'Created',
              data: series.map(d => d.created),
              backgroundColor: '#1268F3',
              hoverBackgroundColor: '#0f55d6',
              borderRadius: barRadius,
              borderSkipped: false,
              maxBarThickness: 28,
            },
            {
              label: 'Closed',
              data: series.map(d => d.closed),
              backgroundColor: '#16803C',
              hoverBackgroundColor: '#126b32',
              borderRadius: barRadius,
              borderSkipped: false,
              maxBarThickness: 28,
            },
            {
              label: 'Overdue',
              data: series.map(d => d.overdue),
              backgroundColor: '#B42318',
              hoverBackgroundColor: '#912018',
              borderRadius: barRadius,
              borderSkipped: false,
              maxBarThickness: 28,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          animation: { duration: 750, easing: 'easeOutQuart' },
          datasets: {
            bar: {
              categoryPercentage: 0.72,
              barPercentage: 0.85,
            },
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: '#fff',
              titleColor: '#344054',
              titleFont: { weight: '700', size: 12 },
              bodyColor: '#172B3A',
              bodyFont: { weight: '600', size: 11 },
              borderColor: '#E4E7EC',
              borderWidth: 1,
              padding: 10,
              cornerRadius: 8,
              displayColors: true,
              boxPadding: 4,
              callbacks: {
                title: (items) => {
                  const i = items[0]?.dataIndex;
                  if (i == null || !series[i]) return '';
                  const b = series[i];
                  return b.mode === 'month' ? b.label : (b.label + ' · ' + (b.mode === 'week' ? 'week' : 'day'));
                },
              },
            },
          },
          scales: {
            x: {
              stacked: false,
              grid: { display: false },
              ticks: {
                ...tick,
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: series.length > 16 ? 10 : 16,
              },
              border: { display: false },
            },
            y: {
              stacked: false,
              beginAtZero: true,
              grace: '8%',
              grid: { color: '#F2F4F7', drawBorder: false },
              ticks: { ...tick, precision: 0 },
              border: { display: false },
            },
          },
        },
      });
      try { this.rptVolumeChart.resize(); } catch (e) {}
    },

    rptOpenInquiry(i) {
      this.view = 'dashboard';
      this.$nextTick(() => {
        this.resetFilters();
        this.inqMonth = '';
        this.search = i.client || String(i.id);
      });
    },

    exportRptCSV() {
      // Owner-scoped, so the exported rows add up to the summary line above them.
      const inqs = this.rptOwnedInquiries;
      const s = this.rptSummary;
      if (!inqs.length && !(this.isAdmin && this.rptShowOverall && !this.rptUser && this.rptTeamStats.length)) {
        this.addToast('No data to export', 'error'); return;
      }
      const today = new Date().toLocaleDateString('en-CA');
      const esc = c => '"' + String(c ?? '').replace(/"/g, '""') + '"';
      const line = r => r.map(esc).join(',');
      const out = [];

      out.push(line(['Scope', this.rptScopeTitle || '']));
      out.push(line(['Period', this.rptRangeText || this.rptPeriod || '']));
      out.push(line(['Created', s.total, 'Pending', s.pending, 'Overdue', s.overdue, 'Late closed', s.lateClosed, 'Closed', s.closed]));
      out.push(line(['Avg close', s.avgCloseLabel, 'Time usage', s.usageLabel, 'Overdue hrs', s.overdueHrsLabel, 'Pending hrs left', s.pendingHrsLabel]));
      out.push(line(['Client', s.clientN, 'Internal', s.internalN, 'Internal late start (>=11:30)', s.internalLateStart, 'Avg late by', s.avgLateStartLabel]));
      out.push('');

      if (this.isAdmin && this.rptShowOverall && !this.rptUser) {
        out.push(line(['Team performance']));
        out.push(line(['Member','Created','Pending','Overdue','Late closed','Avg close','Overdue hrs','Pending hrs left']));
        this.rptTeamStats.forEach(emp => {
          out.push(line([
            emp.name, emp.inqs, emp.pending, emp.tasksOD, emp.lateClosed,
            emp.avgCloseLabel, emp.overdueHrsLabel, emp.pendingHrsLabel,
          ]));
        });
        out.push('');
      }

      if (inqs.length) {
        out.push(line(['Inquiries']));
        out.push(line(['ID','Client','Company','Stage','Outcome','Owner','Created','Due Date','Status','Tasks Overdue','Tasks Late','Late By']));
        inqs.forEach(i => {
          out.push(line([
            i.id, i.client, i.company, i.stage, i.outcome || '', i.current_owner || i.created_by,
            (i.date || i.created_at || '').slice(0, 10), i.due_date || '',
            this.isClosed(i) ? (i.late ? 'Closed (Late)' : 'Closed') : (this.isOverdue(i) ? 'Overdue' : 'Pending'),
            (i.steps || []).filter(s => s.overdue).map(s => s.assigned_to).join('; '),
            (i.steps || []).filter(s => !s.overdue && s.late).map(s => s.assigned_to).join('; '),
            i.late ? (i.closed_at || '').slice(0, 16) : '',
          ]));
        });
      }

      const a = Object.assign(document.createElement('a'), {
        href: 'data:text/csv;charset=utf-8,' + encodeURIComponent(out.join('\n')),
        download: 'sp-report-' + today + '.csv',
      });
      document.body.appendChild(a); a.click(); a.remove();
      this.addToast('Report exported as CSV', 'success');
    },

    get newUserRole() {
      return this.newUser.role==='__custom__' ? this.newUser.customRole.trim() : this.newUser.role;
    },

    // Per-field messages. An untouched field returns nothing don't shout before they type.
    // The server still re-validates; this only shortens the round trip.
    get newUserErr() {
      const u = this.newUser, e = {};
      const email = u.email.trim().toLowerCase();
      if (u.name && u.name.trim().length < 2) e.name = 'Enter their full name.';
      if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) e.email = 'That does not look like an email address.';
      else if (email && this.accounts.some(a => (a.email||'').toLowerCase() === email)) e.email = 'An account with this email already exists.';
      if (u.password && u.password.length < 6) e.pass = 'Too short minimum 6 characters.';
      return e;
    },
    get newUserReady() {
      const u = this.newUser;
      return !!u.name.trim() && !!u.email.trim() && u.password.length >= 6
          && !!this.newUserRole && Object.keys(this.newUserErr).length === 0;
    },

    // crypto.getRandomValues, not Math.random this ends up being someone real account password.
    // Alphabet drops look-alikes (I l 1 O 0) because this gets read aloud or retyped by hand.
    genPassword() {
      const all = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?";
      const buf = new Uint32Array(14);
      crypto.getRandomValues(buf);
      this.newUser.password = Array.from(buf, n => all[n % all.length]).join('');
      this.newUser.showPass = true;
      this.newUser.copied   = false;
    },
    async copyNewPassword() {
      if (!this.newUser.password) return;
      try {
        await navigator.clipboard.writeText(this.newUser.password);
        this.newUser.copied = true;
        setTimeout(() => { this.newUser.copied = false; }, 2000);
      } catch { this.addToast('Could not copy select the field and copy manually','error'); }
    },

    async directAddUser() {
      if (this.newUser.saving) return;
      this.newUser.error=''; this.newUser.success='';
      if (!this.newUser.name.trim()) return this.newUser.error='Full name is required.';
      if (!this.newUser.email.includes('@')) return this.newUser.error='Enter a valid email.';
      if (this.newUser.password.length<6) return this.newUser.error='Password must be at least 6 characters.';
      const role = this.newUserRole;
      if (!role) return this.newUser.error='Enter a custom role name.';
      this.newUser.saving = true;
      try {
        const d = await this.api('api/accounts.php', { action:'direct_add', name:this.newUser.name, email:this.newUser.email, password:this.newUser.password, role });
        if (d.ok) {
          this.newUser.success=d.message; this.newUser.name=''; this.newUser.email=''; this.newUser.password=''; this.newUser.role='Member'; this.newUser.customRole=''; this.newUser.showPass=false; this.newUser.copied=false;
          this.addToast(d.message);
          if (d.data) this.accounts=d.data;
        } else this.newUser.error=d.message;
      } finally { this.newUser.saving = false; }
    },
  }
}
</script>
</body>
</html>
