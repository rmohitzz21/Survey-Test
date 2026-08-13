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

// Today's and overdue follow-ups for dashboard widget — only assigned to current user
$_fuToday = date('Y-m-d');
$_fuStmt  = $pdo->prepare("SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id WHERE f.completed=0 AND f.follow_up_date<=? AND f.assigned_to=? ORDER BY f.follow_up_date ASC,f.follow_up_time ASC");
$_fuStmt->execute([$_fuToday, $user['name']]);
$todayFollowUps = $_fuStmt->fetchAll();

// All follow-ups for Reminders view
$_rmStmt = $pdo->prepare("SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id WHERE f.assigned_to=? OR f.created_by=? ORDER BY f.completed ASC,f.follow_up_date ASC,f.follow_up_time ASC");
$_rmStmt->execute([$user['name'], $user['name']]);
$allFollowUps = $_rmStmt->fetchAll();
foreach ($allFollowUps as &$_rfu) { $_rfu['completed'] = (int)$_rfu['completed']; } unset($_rfu);

// All unique client names from every inquiry — for global autocomplete (not role-filtered)
$_allClientNames = $pdo->query("SELECT DISTINCT client FROM inquiries WHERE client!='' ORDER BY client")->fetchAll(PDO::FETCH_COLUMN);

$allStages = ['Inquiry','Communication / Proposal','Decision','Project Execution','Closure'];
$allOutcomes = ['Inquiry Received','Inquiry Created','Initial Communication','Proposal Submitted','Follow-up Sent','Awaiting Client Response','Project Won','Project Lost','Questionnaire','Link Testing','Field Work','Project Delivered','Invoice Sent','Payment Received','Inquiry Closed'];
$stepStatuses = ['New','Pending','In Progress','Client/Team Se Reply Pending','Checking Me','On Hold','Blocked','Submitted for Review','Done','Rejected','Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Survey Pacific Work Journey</title>
<link rel="icon" type="image/png" href="image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.3/dist/purify.min.js" defer></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
<style>
  html,body{height:100%;overflow:hidden;}
  *,[x-cloak]{font-family:'Inter',system-ui,sans-serif;box-sizing:border-box;}
  [x-cloak]{display:none!important;}
  @keyframes slideInRight{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
  @keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .toast-enter{animation:slideInRight 0.25s ease}
  .anim-fade{animation:fadeIn 0.2s ease}
  .spin{animation:spin 1s linear infinite}
  ::-webkit-scrollbar{width:6px;height:6px}
  ::-webkit-scrollbar-track{background:transparent}
  ::-webkit-scrollbar-thumb{background:#D1D9E6;border-radius:3px}
  select{-webkit-appearance:none;appearance:none}
  /* ── Flatpickr calendar skin ── */
  .flatpickr-calendar{border-radius:12px!important;box-shadow:0 8px 32px rgba(18,104,243,.15),0 2px 8px rgba(0,0,0,.07)!important;border:1px solid #E4E7EC!important;font-family:'Inter',system-ui,sans-serif!important;font-size:13px!important}
  .flatpickr-months .flatpickr-month{background:#1268F3!important;border-radius:10px 10px 0 0!important;height:44px!important}
  .flatpickr-current-month{padding-top:9px!important}
  .flatpickr-current-month .flatpickr-monthDropdown-months,.flatpickr-current-month input.cur-year{color:#fff!important;font-weight:700!important;font-size:14px!important}
  .flatpickr-monthDropdown-months{-webkit-appearance:auto!important;appearance:auto!important;background:transparent!important;cursor:pointer!important}
  .flatpickr-monthDropdown-months option{color:#344054!important;background:#fff!important;font-weight:500!important}
  .flatpickr-months .flatpickr-prev-month,.flatpickr-months .flatpickr-next-month{color:#fff!important;fill:#fff!important;padding-top:11px!important}
  .flatpickr-months .flatpickr-prev-month:hover svg,.flatpickr-months .flatpickr-next-month:hover svg{fill:rgba(255,255,255,.6)!important}
  .flatpickr-weekdays,span.flatpickr-weekday{background:#EEF4FF!important}
  .flatpickr-weekday{color:#1268F3!important;font-weight:700!important;font-size:10px!important}
  .flatpickr-days{border:none!important}
  .flatpickr-day{border-radius:8px!important;font-size:12px!important;height:34px!important;line-height:34px!important;margin:1px!important;border:none!important}
  .flatpickr-day.selected,.flatpickr-day.selected:hover{background:#1268F3!important;border-color:#1268F3!important;color:#fff!important;box-shadow:none!important}
  .flatpickr-day.today:not(.selected){border:2px solid #1268F3!important;color:#1268F3!important;font-weight:700!important}
  .flatpickr-day:hover:not(.selected){background:#EEF4FF!important}
  .flatpickr-day.prevMonthDay,.flatpickr-day.nextMonthDay{color:#D1D9E6!important}
  .flatpickr-time{border-top:1px solid #E4E7EC!important;padding:6px 8px!important}
  .flatpickr-time input.flatpickr-hour,.flatpickr-time input.flatpickr-minute{font-size:20px!important;font-weight:700!important;color:#344054!important}
  .flatpickr-time .flatpickr-am-pm{color:#1268F3!important;font-weight:700!important;font-size:14px!important}
  .flatpickr-time .flatpickr-time-separator{color:#98A2B3!important;font-size:20px!important;font-weight:700!important}
  .numInputWrapper:hover{background:#EEF4FF!important;border-radius:4px!important}
  .flatpickr-time input:focus,.flatpickr-time input:hover{background:#EEF4FF!important;outline:none!important}
  input.flatpickr-input::placeholder{color:#98A2B3!important}
  /* ── Rich text editor ── */
  .rich-editor:empty::before{content:attr(data-placeholder);color:#98A2B3;pointer-events:none;display:block}
  .rich-editor b,.rich-editor strong{font-weight:700}
  .rich-editor i,.rich-editor em{font-style:italic}
  .rich-editor u{text-decoration:underline}
  .rich-editor ul{list-style:disc!important;padding-left:1.4em!important;margin:0.2em 0}
  .rich-editor ol{list-style:decimal!important;padding-left:1.4em!important;margin:0.2em 0}
  .rich-editor li{display:list-item!important;margin:0.1em 0}
  .rich-editor a{color:#1268F3;text-decoration:underline}
  /* ── Inquiry Excel grid ── */
  .inq-col-header { display:none }
  .inq-card-label { display:block }
  .inq-row { flex-wrap:wrap; align-items:stretch }
  .inq-cell { display:flex; flex-direction:column; justify-content:center; padding:10px 12px; flex-shrink:0; overflow:hidden; border-right:1px solid #E4E7EC }
  @media (min-width:1280px) {
    .inq-row { flex-wrap:nowrap }
    .inq-col-header { display:flex; align-items:stretch }
    .inq-card-label { display:none }
  }
  @media (max-width:1279px) {
    .inq-cell { border-right:none; padding:3px 0; flex-direction:row; align-items:center; flex-wrap:wrap; gap:4px }
  }
  @media print {
    body > *:not(#summary-print-area) { display:none!important; }
    #summary-print-area { display:block!important; position:static!important; background:#fff!important; padding:0!important; }
    #summary-print-area > div { box-shadow:none!important; border-radius:0!important; max-height:none!important; width:100%!important; max-width:100%!important; }
    #summary-print-area .print-hide { display:none!important; }
    /* Dark banner → white bg with dark text */
    #summary-print-area [style*="linear-gradient"] { background:#fff!important; border:1px solid #ccc!important; color:#000!important; }
    #summary-print-area [style*="linear-gradient"] * { color:#000!important; }
    /* All text → dark */
    #summary-print-area * { color:#000!important; background:transparent!important; box-shadow:none!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    #summary-print-area table th, #summary-print-area table td { border:1px solid #ccc!important; color:#000!important; }
    #summary-print-area table thead tr { background:#f0f0f0!important; }
  }
</style>
</head>
<body class="h-full flex" style="background:#F4F6F8;font-family:'Inter',system-ui,sans-serif"
      x-data="spApp()" x-init="init()">

<!-- ═══ TOAST NOTIFICATIONS ══════════════════════════════════════════════════ -->
<div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
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

<!-- Mobile sidebar backdrop -->
<div x-show="_mob" @click="_mob=false" class="fixed inset-0 z-30 md:hidden" style="background:rgba(0,0,0,0.45)"></div>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="h-full flex flex-col bg-white border-r border-[#E4E7EC] transition-all duration-200 shrink-0 fixed md:relative inset-y-0 left-0 z-40"
       :class="[collapsed ? 'md:w-14' : 'md:w-[220px]', 'w-[220px]', _mob ? 'translate-x-0 shadow-xl md:shadow-none' : '-translate-x-full md:translate-x-0']">

  <!-- Logo -->
  <div class="border-b border-[#E4E7EC]" :class="collapsed ? 'px-3 py-4' : 'px-4 py-4'">
    <template x-if="collapsed">
      <svg width="28" height="28" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="17" stroke="#1D3461" stroke-width="3"/><circle cx="20" cy="20" r="7" fill="#1D3461"/></svg>
    </template>
    <template x-if="!collapsed">
      <svg width="160" height="34" viewBox="0 0 300 52" fill="none"><circle cx="26" cy="26" r="22" stroke="#1D3461" stroke-width="3.2"/><circle cx="26" cy="26" r="9" fill="#1D3461"/><text x="58" y="31" font-family="'Inter',system-ui" font-size="28" font-weight="700" fill="#1D3461" letter-spacing="-0.3">Survey Pacific</text><text x="59" y="46" font-family="'Inter',system-ui" font-size="10.5" font-weight="400" fill="#1D3461" letter-spacing="1.4">Global Research Execution Partner</text></svg>
    </template>
  </div>

  <!-- Nav items -->
  <nav class="flex-1 py-2">
    <?php
    $navItems = [
      ['id'=>'dashboard',  'label'=>'Dashboard',     'icon'=>'layout-dashboard'],
      ['id'=>'tasks',      'label'=>'My Tasks',       'icon'=>'check-square'],
      ['id'=>'reports',    'label'=>'Reports',        'icon'=>'bar-chart-2'],
      // ['id'=>'inquiries',  'label'=>'All Inquiries',  'icon'=>'list'],
    ];
    foreach ($navItems as $item): ?>
    <button @click="view='<?= $item['id'] ?>'" :title="collapsed ? '<?= $item['label'] ?>' : undefined"
            class="w-full flex items-center gap-3 py-2.5 text-[12px] font-semibold transition-colors relative"
            :class="[view==='<?= $item['id'] ?>' ? 'bg-[#EEF4FF] text-[#175CD3]' : 'text-[#344054] hover:bg-[#F9FAFB]', collapsed ? 'justify-center px-0' : 'px-4']">
      <span x-show="view==='<?= $item['id'] ?>' && !collapsed" class="absolute left-0 top-1 bottom-1 w-[3px] bg-[#1268F3] rounded-r-full"></span>
      <svg class="w-4 h-4 shrink-0"><use href="#icon-<?= $item['icon'] ?>"/></svg>
      <span x-show="!collapsed" class="flex-1 text-left"><?= $item['label'] ?></span>
    </button>
    <?php endforeach; ?>

    <!-- Reminders -->
    <button @click="view='reminders'" :title="collapsed ? 'Reminders' : undefined"
            class="w-full flex items-center gap-3 py-2.5 text-[12px] font-semibold transition-colors relative"
            :class="[view==='reminders' ? 'bg-[#EEF4FF] text-[#175CD3]' : 'text-[#344054] hover:bg-[#F9FAFB]', collapsed ? 'justify-center px-0' : 'px-4']">
      <span x-show="view==='reminders' && !collapsed" class="absolute left-0 top-1 bottom-1 w-[3px] bg-[#1268F3] rounded-r-full"></span>
      <span class="relative shrink-0">
        <svg class="w-4 h-4"><use href="#icon-clock"/></svg>
        <?php if (!empty($todayFollowUps)): ?>
        <span class="absolute -top-1.5 -right-1.5 text-[8px] font-extrabold w-3.5 h-3.5 flex items-center justify-center rounded-full bg-[#B42318] text-white"><?= count($todayFollowUps) ?></span>
        <?php endif; ?>
      </span>
      <span x-show="!collapsed" class="flex-1 text-left">Reminders</span>
      <?php if (!empty($todayFollowUps)): ?>
      <span x-show="!collapsed" class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-[#FEF3F2] text-[#B42318]"><?= count($todayFollowUps) ?></span>
      <?php endif; ?>
    </button>

    <!-- Approvals (admin only) -->
    <?php if (is_admin()): ?>
    <button @click="view='approvals'" :title="collapsed ? 'Approvals' : undefined"
            class="w-full flex items-center gap-3 py-2.5 text-[12px] font-semibold transition-colors relative"
            :class="[view==='approvals' ? 'bg-[#EEF4FF] text-[#175CD3]' : 'text-[#344054] hover:bg-[#F9FAFB]', collapsed ? 'justify-center px-0' : 'px-4']">
      <span x-show="view==='approvals' && !collapsed" class="absolute left-0 top-1 bottom-1 w-[3px] bg-[#1268F3] rounded-r-full"></span>
      <span class="relative shrink-0">
        <svg class="w-4 h-4"><use href="#icon-shield-check"/></svg>
        <span x-show="pendingCount>0" class="absolute -top-1.5 -right-1.5 text-[8px] font-extrabold w-3.5 h-3.5 flex items-center justify-center rounded-full bg-[#B54708] text-white" x-text="pendingCount"></span>
      </span>
      <span x-show="!collapsed" class="flex-1 text-left">Approvals</span>
      <span x-show="!collapsed && pendingCount>0" class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-[#FFFAEB] text-[#B54708]" x-text="pendingCount"></span>
    </button>
    <?php endif; ?>
  </nav>

  <!-- User footer -->
  <div class="border-t border-[#E4E7EC] p-3">
    <div x-show="!collapsed" class="flex items-center gap-2.5 mb-2.5">
      <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
        <span class="text-[10px] font-extrabold text-[#175CD3]"><?= strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $user['name']), 0, 2)))) ?></span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-[12px] font-semibold text-[#172B3A] truncate"><?= htmlspecialchars($user['name']) ?></div>
        <div class="text-[10px] text-[#667085]"><?= htmlspecialchars($user['role']) ?></div>
      </div>
    </div>
    <a href="api/logout.php" class="w-full flex items-center gap-2 text-[11px] font-semibold text-[#667085] hover:text-[#B42318] transition-colors rounded-[6px] px-2 py-1.5 hover:bg-[#FEF3F2]"
       :class="collapsed ? 'justify-center' : ''" :title="collapsed ? 'Sign Out' : undefined">
      <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-log-out"/></svg>
      <span x-show="!collapsed">Sign Out</span>
    </a>
  </div>
</aside>

<!-- ═══ MAIN CONTENT AREA ═══════════════════════════════════════════════════ -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

  <!-- Topbar -->
  <div class="bg-white border-b border-[#E4E7EC] flex items-center justify-between px-3 sm:px-6 shrink-0" style="height:56px">
    <div class="flex items-center gap-3">
      <button @click="toggleMenu()" class="text-[#667085] hover:text-[#172B3A] transition-colors">
        <svg class="w-[18px] h-[18px]"><use href="#icon-menu"/></svg>
      </button>
      <h1 class="text-[18px] font-bold text-[#071D2B]" x-text="viewTitle"></h1>
    </div>
    <div class="flex items-center gap-2">
      <button x-show="view==='reminders'" @click="rmAddOpen=true" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3"><use href="#icon-plus"/></svg> Add Follow-up
      </button>
      <button x-show="view!=='reminders'" @click="addInquiryOpen=true" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
        <svg class="w-3 h-3"><use href="#icon-plus"/></svg> Add Inquiry
      </button>
      <button @click="notifsOpen=!notifsOpen; if(notifsOpen) markNotifsRead()" class="relative text-[#667085] hover:text-[#172B3A] transition-colors p-1.5">
        <svg class="w-4 h-4"><use href="#icon-bell"/></svg>
        <span x-show="unreadCount>0" class="absolute top-0 right-0 text-[8px] font-extrabold w-3.5 h-3.5 flex items-center justify-center rounded-full bg-[#1268F3] text-white" x-text="unreadCount"></span>
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
  <div class="flex-1 overflow-y-auto overflow-x-hidden p-3 sm:p-6" style="-webkit-overflow-scrolling:touch">

    <!-- ── DASHBOARD / INQUIRIES VIEW ─────────────────────────────────────── -->
    <div x-show="view==='dashboard' || view==='inquiries'">

      <!-- Restricted banner -->
      <?php if (!is_admin()): ?>
      <div class="flex items-center gap-2 text-[11px] text-[#026AA2] bg-[#EFF8FF] border border-[#BAE6FD] rounded-[8px] px-3 py-2 mb-3">
        <svg class="w-3 h-3 shrink-0"><use href="#icon-user"/></svg>
        Showing inquiries you created or are assigned to. Admins see all inquiries.
      </div>
      <?php endif; ?>

      <!-- Today's Follow-ups widget -->
      <template x-if="todayFollowUps.length > 0">
        <div class="bg-white rounded-[10px] border border-[#E4E7EC] mb-4 overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
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
                  <div class="text-[11px] font-extrabold" :class="fu.follow_up_date < new Date().toISOString().slice(0,10) ? 'text-[#B42318]' : 'text-[#1268F3]'" x-text="fu.follow_up_time ? fuTimeLabel(fu.follow_up_time) : 'Today'"></div>
                  <div x-show="fu.follow_up_date < new Date().toISOString().slice(0,10)" class="text-[9px] font-extrabold text-[#B42318] bg-[#FEF3F2] px-1.5 py-0.5 rounded-full mt-0.5 inline-block">Overdue</div>
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
                  <button @click="completeFuDashboard(fu)" class="text-[10px] font-bold px-2.5 py-1.5 rounded-[7px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">✓ Done</button>
                  <button @click="openFuInquiry(fu)" class="text-[10px] font-bold px-2.5 py-1.5 rounded-[7px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">Open</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>

      <!-- Stats bar (6 tiles clickable filters) -->
      <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-4">
        <button type="button" @click="statFilter=''" class="bg-white rounded-[10px] border border-[#E4E7EC] px-4 py-3 text-left w-full cursor-pointer"
          :style="statFilter==='' ? 'box-shadow:0 0 0 2px #667085' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5 mb-1"><div class="w-1.5 h-1.5 rounded-full bg-[#667085]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Total Inquiries</div></div>
          <div class="text-[28px] font-bold leading-none text-[#172B3A]" x-text="stats.total"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='inProgress' ? '' : 'inProgress'" class="bg-white rounded-[10px] border border-[#E4E7EC] px-4 py-3 text-left w-full cursor-pointer"
          :style="statFilter==='inProgress' ? 'box-shadow:0 0 0 2px #1268F3' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5 mb-1"><div class="w-1.5 h-1.5 rounded-full bg-[#1268F3]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">In Progress</div></div>
          <div class="text-[28px] font-bold leading-none text-[#172B3A]" x-text="stats.inProgress"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='done' ? '' : 'done'" class="bg-white rounded-[10px] border border-[#E4E7EC] px-4 py-3 text-left w-full cursor-pointer"
          :style="statFilter==='done' ? 'box-shadow:0 0 0 2px #16803C' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)'">
          <div class="flex items-center gap-1.5 mb-1"><div class="w-1.5 h-1.5 rounded-full bg-[#16803C]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Closed</div></div>
          <div class="text-[28px] font-bold leading-none text-[#172B3A]" x-text="stats.done"></div>
        </button>
        <button type="button" @click="statFilter = statFilter==='overdue' ? '' : 'overdue'" class="bg-white rounded-[10px] border border-[#E4E7EC] px-4 py-3 text-left w-full cursor-pointer"
          :style="statFilter==='overdue' ? 'box-shadow:0 0 0 2px #B42318' : (stats.overdue>0 ? 'box-shadow:0 1px 4px rgba(7,29,43,0.04);border-color:#FECDCA' : 'box-shadow:0 1px 4px rgba(7,29,43,0.04)')">
          <div class="flex items-center gap-1.5 mb-1"><div class="w-1.5 h-1.5 rounded-full bg-[#B42318]"></div><div class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Overdue</div></div>
          <div class="text-[28px] font-bold leading-none" :style="stats.overdue>0?'color:#B42318':'color:#172B3A'" x-text="stats.overdue"></div>
        </button>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-[10px] border border-[#E4E7EC] px-4 py-3 mb-4 flex flex-wrap items-center gap-2" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <!-- Search -->
        <div class="flex items-center gap-2 border border-[#E4E7EC] rounded-[8px] px-2.5 flex-1 min-w-[180px]" style="height:38px">
          <svg class="w-3 h-3 text-[#667085] shrink-0"><use href="#icon-search"/></svg>
          <input x-model="search" placeholder="Search by ID, client, company…" class="flex-1 text-[12px] text-[#172B3A] placeholder:text-[#667085] bg-transparent focus:outline-none" />
          <button x-show="search" @click="search=''" class="text-[#667085]">✕</button>
        </div>
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
        <!-- Stage filter -->
        <div class="relative">
          <select x-model="stageFilter" class="text-[12px] font-semibold text-[#344054] border border-[#E4E7EC] rounded-[8px] pl-3 pr-7 bg-white focus:outline-none cursor-pointer" style="height:38px;min-width:150px">
            <option value="">All Stages</option>
            <?php foreach ($allStages as $s): if($s==='Other') continue; ?><option value="<?= $s ?>"><?= $s==='Closure' ? 'Closed' : $s ?></option><?php endforeach; ?>
          </select>
          <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
        <?php if (is_admin()): ?>
        <div class="w-px h-5 bg-[#E4E7EC]"></div>
        <button @click="myOnly=false" class="text-[11px] font-bold px-3 rounded-[8px] transition-colors" :class="!myOnly?'bg-[#1268F3] text-white':'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'" style="height:34px">View All</button>
        <button @click="myOnly=true"  class="text-[11px] font-bold px-3 rounded-[8px] transition-colors" :class="myOnly ?'bg-[#1268F3] text-white':'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'" style="height:34px">My Inquiries</button>
        <?php endif; ?>
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
      <div x-show="viewingEmployee" class="flex items-center gap-2.5 mb-3 px-3 py-2.5 rounded-[10px] bg-[#FFFAEB] border border-[#FEF0C7] text-[#B54708]">
        <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-alert-circle"/></svg>
        <span class="text-[11px] font-semibold">Viewing <span class="font-bold" x-text="employee"></span>'s inquiries read only. Select "All Users" to take actions.</span>
      </div>

      <!-- Inquiry list -->
      <div x-show="filtered.length===0" class="flex flex-col items-center justify-center bg-white border border-dashed border-[#E4E7EC] rounded-[12px] text-center" style="padding:60px 40px">
        <svg class="w-10 h-10 text-[#D1D9E6] mb-3"><use href="#icon-file-text"/></svg>
        <div class="text-[15px] font-bold text-[#344054] mb-1">No inquiries found</div>
        <div class="text-[13px] text-[#667085] mb-4">Try adjusting your filters or search query.</div>
        <button @click="resetFilters()" class="text-[11px] font-bold px-4 py-2 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">Clear Filters</button>
      </div>

      <template x-if="filtered.length>0">
        <div>
          <!-- Excel grid table -->
          <div style="border:1px solid #D1D9E6;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(7,29,43,0.07)">
            <!-- Frozen header row -->
            <div class="inq-col-header bg-[#F2F4F7]" style="border-bottom:2px solid #C8D2DF">
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#98A2B3]" style="width:40px;align-items:center;justify-content:center;flex-direction:row">#</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:100px">Inquiry ID</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:82px">Date</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:108px">Client</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:110px">Company</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="flex:1;min-width:80px">Requirement</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:92px">Created By</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:108px">Currently With</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:80px">Due</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:130px">Status</div>
              <div class="inq-cell text-[10px] font-bold uppercase tracking-[0.06em] text-[#667085]" style="width:140px;border-right:none">Actions</div>
            </div>
          <template x-for="(inq, i) in pagedInqs" :key="inq.id">
            <div :data-inq="inq.id"
                 class="transition-colors"
                 :style="'border-bottom:'+(inq._open?'2px solid #1268F3':'1px solid #E4E7EC')+';'+(i%2===1?'background:#F8FAFC':'background:#ffffff')+(inq._open?';background:#EFF6FF':'')+(inq._open?';box-shadow:inset 4px 0 0 #1268F3':(inq.overdue?';box-shadow:inset 4px 0 0 #B42318':highlightedInq===inq.id?';box-shadow:inset 3px 0 0 #1268F3':''))">

                <!-- Horizontal row -->
                <div class="inq-row flex cursor-pointer" @click="toggleInquiry(inq)">
                  <!-- # -->
                  <div class="inq-cell" style="width:40px;align-items:center;justify-content:center;flex-direction:row">
                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-extrabold"
                         :class="inq.overdue ? 'bg-[#EEF4FF] text-[#B42318]' : 'bg-[#EEF4FF] text-[#175CD3]'"
                         x-text="_inqPage*10+i+1"></div>
                  </div>
                  <!-- Inquiry ID -->
                  <div class="inq-cell" style="width:100px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Inquiry ID</div>
                    <div class="text-[11px] font-bold text-[#071D2B]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.id"></div>
                  </div>
                  <!-- Date -->
                  <div class="inq-cell" style="width:82px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Date</div>
                    <div class="text-[11px] text-[#344054]" x-text="inq.date"></div>
                  </div>
                  <!-- Client -->
                  <div class="inq-cell" style="width:108px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Client</div>
                    <div class="text-[11px] font-bold text-[#172B3A]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.client"></div>
                    <span x-show="inq.is_new" class="inline-flex mt-0.5 text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-[#FFFAEB] text-[#B54708]">New</span>
                  </div>
                  <!-- Company -->
                  <div class="inq-cell" style="width:110px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Company</div>
                    <div class="text-[11px] text-[#344054]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:3px">
                      <svg class="w-2.5 h-2.5 shrink-0 text-[#98A2B3]"><use href="#icon-building2"/></svg>
                      <span x-text="inq.company"></span>
                    </div>
                  </div>
                  <!-- Requirement (popup on hover) -->
                  <div class="inq-cell" style="flex:1;min-width:80px"
                       @mouseenter="showReqPopup($event, inq.requirement)"
                       @mouseleave="hideReqPopup()">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Requirement</div>
                    <div class="text-[11px] text-[#667085]" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="stripHtml(inq.requirement)"></div>
                  </div>
                  <!-- Created By -->
                  <div class="inq-cell" style="width:92px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Created By</div>
                    <div class="text-[11px] font-bold text-[#172B3A]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.created_by"></div>
                  </div>
                  <!-- Currently With -->
                  <div class="inq-cell" style="width:108px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Currently With</div>
                    <div class="text-[11px] font-bold text-[#172B3A]" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="inq.current_owner"></div>
                  </div>
                  <!-- Due -->
                  <div class="inq-cell" style="width:80px">
                    <div class="inq-card-label text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-0.5">Due</div>
                    <div class="text-[11px]" :class="inq.overdue?'text-[#B42318] font-bold':'text-[#344054]'" x-text="inq.due_date"></div>
                  </div>
                  <!-- Status -->
                  <div class="inq-cell" style="width:130px;gap:4px">
                    <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-[3px] text-[10px]" :class="stageClass(inq.stage)" x-text="stageLabel(inq.stage)"></span>
                    <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2 py-0.5 text-[9px]" :class="outcomeClass(inq.outcome)" x-text="inq.outcome"></span>
                  </div>
                  <!-- Actions -->
                  <div class="inq-cell" style="width:140px;border-right:none;flex-direction:row;align-items:center;gap:5px" @click.stop>
                    <button x-show="!isClient" @click="toggleInquiry(inq)" class="text-[11px] font-bold px-2.5 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors flex items-center gap-1">
                      <template x-if="!inq._open">
                        <span class="flex items-center gap-1">Steps <svg class="w-3 h-3"><use href="#icon-chevron-down"/></svg></span>
                      </template>
                      <template x-if="inq._open">
                        <span class="flex items-center gap-1">Hide <svg class="w-3 h-3"><use href="#icon-chevron-up"/></svg></span>
                      </template>
                    </button>
                    <span x-show="isClient" class="text-[10px] font-bold px-2.5 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#667085]" x-text="stageLabel(inq.stage)"></span>
                    <button @click="openSummary(inq)" class="text-[11px] font-bold px-2.5 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Summary</button>
                  </div>
                </div>

                <!-- Expanded Journey hidden for Clients -->
                <div x-show="inq._open && !isClient" x-cloak class="px-4 pt-3 pb-4 anim-fade" style="border-top:2px solid #1268F3;background:#EFF6FF">

                  <!-- ── Inquiry Info panel: attachments, admin remark, follow-ups — grouped so it reads as one zone ── -->
                  <div class="bg-white border border-[#E4E7EC] rounded-[10px] p-3 mb-4">

                  <!-- Action bar: attachments left, buttons right -->
                  <div class="flex items-start justify-between gap-3 mb-3">
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
                    <div class="flex items-center gap-2 shrink-0">
                      <button x-show="!viewingEmployee && (isAdmin || inq.created_by===currentUser) && !isInquiryClosed(inq)"
                        @click="openCompleteInquiry(inq)" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors flex items-center gap-1">
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

                  <!-- ── Admin Remark ── -->
                  <div class="mb-3">
                    <!-- Admin: editable -->
                    <template x-if="isAdmin">
                      <div class="bg-[#FFFAEB] border border-[#FDE68A] rounded-[10px] px-3.5 py-3">
                        <div class="flex items-center gap-2 mb-2">
                          <svg class="w-3.5 h-3.5 text-[#B45309] shrink-0"><use href="#icon-shield-check"/></svg>
                          <span class="text-[10px] font-extrabold uppercase tracking-[0.08em] text-[#B45309]">Admin Remark</span>
                          <span class="text-[9px] text-[#B45309] opacity-60"> visible to all members of this inquiry</span>
                        </div>
                        <textarea x-model="inq._adminRemark"
                                  rows="2"
                                  placeholder="Add a remark visible to everyone involved in this inquiry…"
                                  class="w-full text-[12px] text-[#172B3A] bg-white border border-[#FDE68A] rounded-[7px] px-3 py-2 resize-none focus:outline-none focus:border-[#F59E0B] placeholder:text-[#D1D9E6]"
                                  @input="autoSaveAdminRemark(inq)"
                                  @keydown.ctrl.enter="saveAdminRemark(inq)"></textarea>
                        <div x-show="inq._adminRemarkSaving" class="flex items-center gap-1.5 mt-1.5 text-[10px] text-[#B45309]">
                          <svg class="w-3 h-3 spin"><use href="#icon-refresh-cw"/></svg> Saving…
                        </div>
                      </div>
                    </template>
                    <!-- Non-admin: read-only, shown only if a remark exists -->
                    <template x-if="!isAdmin && inq.admin_remark">
                      <div class="bg-[#FFFAEB] border border-[#FDE68A] rounded-[10px] px-3.5 py-3">
                        <div class="flex items-center gap-2 mb-1.5">
                          <svg class="w-3.5 h-3.5 text-[#B45309] shrink-0"><use href="#icon-shield-check"/></svg>
                          <span class="text-[10px] font-extrabold uppercase tracking-[0.08em] text-[#B45309]">Admin Remark</span>
                        </div>
                        <p class="text-[12px] text-[#172B3A] leading-relaxed whitespace-pre-line" x-text="inq.admin_remark"></p>
                      </div>
                    </template>
                  </div>

                  <!-- ── Follow-up Status Strip ─────────────────────────────── -->
                  <div x-show="!viewingEmployee" class="mb-3"
                       x-data="{
                         get fus()     { return Array.isArray(followUps[inq.id]) ? followUps[inq.id] : []; },
                         get pending() { return this.fus.filter(f=>!f.completed).length; },
                         get overdue() { const t=new Date().toISOString().slice(0,10); return this.fus.filter(f=>!f.completed&&f.follow_up_date<t).length; },
                         get loaded()  { return Array.isArray(followUps[inq.id]); }
                       }">
                    <div class="rounded-[10px] border overflow-hidden transition-colors"
                         :class="loaded && overdue ? 'bg-[#FFF8F8] border-[#FECDCA]' : loaded && pending ? 'bg-[#F5F9FF] border-[#C7D7FA]' : 'bg-[#F9FAFB] border-[#E4E7EC]'">

                      <!-- Status bar -->
                      <div class="flex items-center gap-2.5 px-3 py-2.5">
                        <span x-show="loaded && pending > 0"
                              class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2 py-[2px] rounded-full"
                              :class="overdue ? 'bg-[#FEF3F2] text-[#B42318]' : 'bg-[#EEF4FF] text-[#175CD3]'">
                          <svg class="w-3 h-3"><use href="#icon-bell"/></svg>
                          <span x-text="pending+' pending'"></span>
                        </span>
                        <div class="flex-1 min-w-0"></div>
                        <button @click.stop="openFuForm(inq)"
                                class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1 shrink-0">
                          <svg class="w-3.5 h-3.5"><use href="#icon-bell"/></svg> Follow Up
                        </button>
                      </div>

                      <!-- Pending follow-ups list -->
                      <template x-if="loaded && pending > 0">
                        <div class="border-t border-[#E4E7EC] divide-y divide-[#F2F4F7]">
                          <template x-for="fu in fus.filter(f=>!f.completed)" :key="fu.id">
                            <div class="flex items-center gap-2.5 px-3 py-2 bg-white">
                              <div class="flex-1 min-w-0">
                                <div class="text-[11px] font-semibold text-[#344054] truncate" x-text="fu.note"></div>
                                <div class="text-[10px] text-[#98A2B3] mt-0.5"
                                     x-text="fu.follow_up_date+(fu.follow_up_time?' · '+fu.follow_up_time:'')+' · '+fu.assigned_to"></div>
                              </div>
                              <button @click.stop="completeFollowUp(fu, inq)"
                                      class="shrink-0 text-[10px] font-bold px-2.5 py-1 rounded-[6px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">
                                ✓ Done
                              </button>
                            </div>
                          </template>
                        </div>
                      </template>

                    </div>
                  </div><!-- /follow-up strip -->

                  </div><!-- /Inquiry Info panel -->

                  <!-- Follow-up popup modal -->
                  <div x-show="fuOpenFor===inq.id" x-cloak
                         @click.self="fuOpenFor=null"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
                      <div class="bg-white rounded-[14px] w-full max-w-[700px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">

                        <!-- Header -->
                        <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
                          <div>
                            <h2 class="text-[16px] font-bold text-white leading-tight">Schedule Follow-up</h2>
                            <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)" x-text="inq.id + ' · ' + inq.client"></p>
                          </div>
                          <button @click="fuOpenFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
                        </div>

                        <!-- Fields -->
                        <div class="overflow-y-auto flex-1 p-5 space-y-4">

                          <!-- Note -->
                          <div>
                            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Note <span class="text-[#B42318]">*</span></label>
                            <input x-model="fuForm.note" type="text"
                                   placeholder="e.g. Call client for proposal feedback"
                                   class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white">
                          </div>

                          <!-- Date + Time -->
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

                          <!-- Assigned To -->
                          <div>
                            <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assigned To</label>
                            <div class="relative">
                              <select x-model="fuForm.assignedTo"
                                      class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
                                <option value="">Me (<?= htmlspecialchars($user['name']) ?>)</option>
                                <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
                                  <option :value="a.name" x-text="a.name"></option>
                                </template>
                              </select>
                              <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
                            </div>
                          </div>

                        </div>

                        <!-- Footer -->
                        <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
                          <button @click="fuOpenFor=null" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
                          <button @click="addFollowUp(inq)" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
                            Schedule <svg class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
                          </button>
                        </div>

                      </div>
                  </div><!-- /follow-up modal -->

                  <!-- ── Workflow Steps panel — separate zone from the info panel above ── -->
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
                  <div x-show="inq.steps.length>0" class="overflow-x-auto">

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
                           :class="step.overdue?'border-[#FECDCA] bg-[#FFF8F8]':(['Done','Completed'].includes(step.status)?'border-[#D1FAE5] bg-[#F0FDF4]':'border-[#E4E7EC] bg-white')"
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
                            <span x-show="viewingEmployee || step.assigned_to!==currentUser || ['Done','Completed'].includes(step.status)"
                                  class="text-[8px] text-[#B8CCED]" x-text="'by ' + step.assigned_to"></span>
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

                          <!-- Assignee: change own status min 20-word remark required -->
                          <select x-show="step.assigned_to===currentUser && !['Done','Completed','Rejected'].includes(step.status)"
                            :disabled="(step._remark||'').trim().length < 20"
                            :title="(step._remark||'').trim().length < 20 ? 'Add at least 20 words in your update first' : ''"
                            @change="if($event.target.value){ updateStepStatus(inq.id,step.id,$event.target.value); $event.target.value=''; }"
                            :class="(step._remark||'').trim().length < 20 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
                            class="text-[11px] border border-[#E4E7EC] rounded-[6px] px-2 py-1.5 bg-white focus:outline-none focus:border-[#1268F3] text-[#344054] w-full">
                            <option value="" disabled selected x-text="step.status"></option>
                            <option value="In Progress">In Progress</option>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Other">Other</option>
                          </select>

                          <p x-show="step.assigned_to===currentUser && !['Done','Completed','Rejected'].includes(step.status)"
                             class="text-[8px] font-semibold mt-0.5"
                             :class="(step._remark||'').trim().length < 20 ? 'text-[#B54708]' : 'text-[#16803C]'"
                             x-text="(()=>{ const w=(step._remark||'').trim().length; return w>=20 ? '✓ Ready' : w+' / 20 Keywords'; })()"></p>

                          <!-- Non-assignee: read-only status badge -->
                          <span x-show="step.assigned_to!==currentUser && !['Done','Completed','Rejected'].includes(step.status)"
                            class="inline-flex text-[10px] font-bold px-2 py-1 rounded-[6px] w-fit"
                            :class="stepStatusClass(step.status)"
                            x-text="step.status"></span>

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
                              <span class="text-[11px] font-semibold text-[#344054]" x-text="(step.due||'').split(' · ')[0]"></span>
                              <span x-show="(step.due||'').includes(' · ')" class="text-[10px] text-[#667085]" x-text="(step.due||'').split(' · ')[1]"></span>
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

          <!-- Pagination bar -->
          <div x-show="filtered.length > 10"
               class="flex items-center justify-between gap-3 mt-4 px-1">
            <div class="text-[11px] text-[#667085]">
              Showing <span class="font-semibold text-[#344054]" x-text="(_inqPage*10+1)+' – '+Math.min(_inqPage*10+10, filtered.length)"></span>
              of <span class="font-semibold text-[#344054]" x-text="filtered.length"></span> inquiries
            </div>
            <div class="flex items-center gap-1.5">
              <button @click="_inqPage--" :disabled="_inqPage===0"
                      class="flex items-center gap-1 text-[11px] font-semibold px-3 py-1.5 rounded-[8px] border transition-all"
                      :class="_inqPage===0 ? 'border-[#E4E7EC] text-[#C4CAD4] cursor-not-allowed' : 'border-[#E4E7EC] text-[#344054] hover:border-[#1268F3] hover:text-[#1268F3]'">
                <svg class="w-3.5 h-3.5"><use href="#icon-chevron-left"/></svg>
                Prev
              </button>
              <template x-for="p in Math.ceil(filtered.length/10)" :key="p">
                <button @click="_inqPage=p-1"
                        class="w-7 h-7 flex items-center justify-center rounded-[6px] text-[11px] font-bold transition-all"
                        :class="p-1===_inqPage ? 'bg-[#1268F3] text-white' : 'text-[#667085] hover:bg-[#EEF4FF] hover:text-[#175CD3]'"
                        x-text="p"></button>
              </template>
              <button @click="_inqPage++" :disabled="_inqPage>=Math.ceil(filtered.length/10)-1"
                      class="flex items-center gap-1 text-[11px] font-semibold px-3 py-1.5 rounded-[8px] border transition-all"
                      :class="_inqPage>=Math.ceil(filtered.length/10)-1 ? 'border-[#E4E7EC] text-[#C4CAD4] cursor-not-allowed' : 'border-[#E4E7EC] text-[#344054] hover:border-[#1268F3] hover:text-[#1268F3]'">
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
      <template x-if="myTasks.length===0">
        <div class="flex flex-col items-center justify-center bg-white border border-dashed border-[#E4E7EC] rounded-[12px] text-center" style="padding:60px 40px">
          <svg class="w-10 h-10 text-[#D1D9E6] mb-3"><use href="#icon-check-circle"/></svg>
          <div class="text-[15px] font-bold text-[#344054] mb-1">No open tasks</div>
          <div class="text-[13px] text-[#667085]">All steps are completed or none assigned yet.</div>
        </div>
      </template>
      <template x-if="myTasks.length>0">
        <div class="space-y-5">
          <template x-for="(t,ti) in myTasks" :key="t.inq.id+'-'+t.step.id">
            <div class="bg-white rounded-[12px] border border-[#E4E7EC] border-l-[5px] overflow-hidden cursor-pointer hover:shadow-md transition-shadow"
                 :class="t.step.overdue        ? 'border-l-[#B42318]' :
                         t.step.status==='Completed'  ? 'border-l-[#16803C]' :
                         t.step.status==='In Progress'? 'border-l-[#1268F3]' :
                         t.step.status==='Pending'    ? 'border-l-[#B54708]' :
                         t.step.status==='Other'      ? 'border-l-[#5925DC]' :
                                                        'border-l-[#D1D9E6]'"
                 style="box-shadow:0 1px 4px rgba(7,29,43,0.05)"
                 @click="goToInquiry(t.inq)">

              <!-- Header: serial + IDs + status -->
              <div class="px-4 py-3 flex items-center justify-between gap-3 border-b border-[#F2F4F7]"
                   :class="t.step.overdue ? 'bg-[#FFF5F5]' : 'bg-[#F8FAFC]'">
                <div class="flex items-center gap-3 min-w-0">
                  <span class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-extrabold"
                        :class="t.step.overdue ? 'bg-[#FEF3F2] text-[#B42318]' :
                                t.step.status==='Completed'   ? 'bg-[#ECFDF3] text-[#16803C]' :
                                t.step.status==='In Progress' ? 'bg-[#EFF8FF] text-[#1268F3]' :
                                t.step.status==='Pending'     ? 'bg-[#FFFAEB] text-[#B54708]' :
                                t.step.status==='Other'       ? 'bg-[#F4F3FF] text-[#5925DC]' :
                                                                'bg-[#F2F4F7] text-[#667085]'"
                        x-text="ti+1"></span>
                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-[13px] font-extrabold text-[#1268F3]" x-text="t.inq.id"></span>
                      <span class="text-[#D1D9E6]">·</span>
                      <span class="text-[13px] font-bold text-[#172B3A] truncate" x-text="t.inq.client"></span>
                    </div>
                    <div x-show="t.inq.company" class="text-[11px] text-[#667085] mt-0.5 truncate" x-text="t.inq.company"></div>
                  </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <span x-show="t.step.overdue" class="text-[11px] font-bold px-2 py-0.5 rounded-[5px] bg-[#FEF3F2] text-[#B42318]">⚠ Overdue</span>
                  <span class="inline-flex items-center rounded-[6px] font-bold whitespace-nowrap px-2.5 py-1 text-[11px]"
                        :class="stepStatusClass(t.step.status)" x-text="t.step.status"></span>
                </div>
              </div>

              <!-- Task instruction -->
              <div class="px-4 py-3">
                <div class="text-[13px] text-[#344054] leading-[1.6] line-clamp-2" x-text="stripHtml(t.step.instruction)"></div>
              </div>

            </div>
          </template>
        </div>
      </template>
    </div>

    <!-- ── APPROVALS VIEW ─────────────────────────────────────────────────── -->
    <?php if (is_admin()): ?>
    <div x-show="view==='approvals'" x-cloak class="space-y-6 max-w-[900px]">

      <!-- Pending Requests -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC] flex items-center justify-between">
          <div>
            <div class="text-[14px] font-bold text-[#172B3A]">Pending Requests</div>
            <div class="text-[11px] text-[#667085] mt-0.5" x-text="pendingCount===0?'No requests awaiting review':pendingCount+' request'+(pendingCount>1?'s':'')+' awaiting review'"></div>
          </div>
          <span x-show="pendingCount>0" class="text-[10px] font-extrabold px-2 py-1 rounded-full bg-[#FFFAEB] text-[#B54708]" x-text="pendingCount+' pending'"></span>
        </div>
        <div class="p-5">
          <template x-if="pendingCount===0">
            <div class="flex flex-col items-center py-8 text-center">
              <svg class="w-8 h-8 text-[#D1D9E6] mb-2"><use href="#icon-shield-check"/></svg>
              <div class="text-[12px] font-semibold text-[#344054]">No pending requests</div>
              <div class="text-[11px] text-[#667085]">New sign-up requests will appear here for approval.</div>
            </div>
          </template>
          <template x-if="pendingCount>0">
            <div class="space-y-2">
              <template x-for="a in accounts.filter(a=>a.status==='pending')" :key="a.id">
                <div class="flex items-center gap-3 px-3.5 py-3 rounded-[10px] border border-[#DBEAFE] bg-[#F5F8FF]">
                  <div class="w-8 h-8 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
                    <span class="text-[10px] font-extrabold text-[#175CD3]" x-text="initials(a.name)"></span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-[12px] font-bold text-[#172B3A]" x-text="a.name"></div>
                    <div class="text-[11px] text-[#667085] flex items-center gap-1 truncate">
                      <svg class="w-2.5 h-2.5"><use href="#icon-mail"/></svg><span x-text="a.email"></span>
                    </div>
                  </div>
                  <div class="text-[10px] text-[#667085] shrink-0 hidden sm:block" x-text="a.requested_at"></div>
                  <div class="flex items-center gap-2 shrink-0">
                    <button @click="accountAction('approve',a.id,a.name)" class="flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#16803C] text-white hover:bg-[#136832] transition-colors">✓ Approve</button>
                    <button @click="accountAction('reject',a.id,a.name)"  class="flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#B42318] hover:text-white transition-colors">✕ Reject</button>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>

      <!-- Manage Users -->
      <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-5 py-4 border-b border-[#E4E7EC]">
          <div class="text-[14px] font-bold text-[#172B3A]">Manage Users</div>
          <div class="text-[11px] text-[#667085] mt-0.5" x-text="accounts.length+' total users'"></div>
        </div>
        <div class="p-5">
          <?php if (!is_master_admin()): ?>
          <div class="flex items-center gap-2 text-[11px] text-[#175CD3] bg-[#EEF4FF] border border-[#DBEAFE] rounded-[8px] px-3 py-2 mb-4">
            <svg class="w-3 h-3 shrink-0"><use href="#icon-shield-check"/></svg>
            As an Admin you can approve, reject, block and unblock users. Only a Master Admin can change roles or delete accounts.
          </div>
          <?php endif; ?>
          <div class="rounded-[10px] border border-[#E4E7EC] overflow-hidden">
            <table class="w-full text-[11px]">
              <thead><tr class="bg-[#F8FAFC] border-b border-[#E4E7EC]">
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">User</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Role</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Status</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Actions</th>
              </tr></thead>
              <tbody>
                <template x-for="(a,i) in accounts.filter(a=>a.status!=='pending')" :key="a.id">
                  <tr class="border-b border-[#E4E7EC] last:border-0" :class="i%2===0?'bg-white':'bg-[#FAFBFC]'">
                    <td class="px-4 py-3">
                      <div class="font-semibold text-[#172B3A] flex items-center gap-1.5">
                        <span x-text="a.name"></span>
                        <span x-show="a.email==='<?= $user['email'] ?>'" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#EEF4FF] text-[#175CD3]">You</span>
                      </div>
                      <div class="text-[10px] text-[#667085]" x-text="a.email"></div>
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
                      <template x-if="a.email==='<?= $user['email'] ?>'">
                        <span class="text-[10px] text-[#B8CCED]"></span>
                      </template>
                      <div class="flex items-center gap-1.5 flex-wrap">
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
        <div class="px-5 py-4 border-b border-[#E4E7EC]">
          <div class="text-[14px] font-bold text-[#172B3A]">Add New User</div>
          <div class="text-[11px] text-[#667085] mt-0.5">Create an account directly bypasses the sign-up queue</div>
        </div>
        <form class="p-5 space-y-4" @submit.prevent="directAddUser()" autocomplete="off">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-[#344054] mb-1">Full Name</label>
              <input x-model="newUser.name" placeholder="e.g. Riya Patel" autocomplete="off" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#175CD3] bg-white" />
            </div>
            <div>
              <label class="block text-[10px] font-bold text-[#344054] mb-1">Email</label>
              <input type="email" x-model="newUser.email" placeholder="e.g. riya@client.com" autocomplete="off" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#175CD3] bg-white" />
            </div>
            <div>
              <label class="block text-[10px] font-bold text-[#344054] mb-1">Password</label>
              <div class="relative">
                <input :type="newUser.showPass ? 'text' : 'password'" x-model="newUser.password" placeholder="Min. 6 characters" autocomplete="new-password" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 pr-9 focus:outline-none focus:border-[#175CD3] bg-white" />
                <button type="button" @click="newUser.showPass=!newUser.showPass" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] hover:text-[#344054]" tabindex="-1">
                  <svg x-show="!newUser.showPass" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg x-show="newUser.showPass" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.05-3.293M6.939 6.939A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.398 2.567M6.939 6.939L3 3m3.939 3.939l10.122 10.122M3 3l18 18"/></svg>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-[#344054] mb-1">Role</label>
              <select x-model="newUser.role" @change="if($event.target.value!=='__custom__')newUser.customRole=''" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#175CD3] bg-white cursor-pointer">
                <option value="Client">Client</option>
                <option value="Member">Member</option>
                <option value="Master Admin">Master Admin</option>
                <template x-for="cr in customRoles" :key="cr"><option :value="cr" x-text="cr"></option></template>
                <option value="__custom__">Custom role…</option>
              </select>
              <input x-show="newUser.role==='__custom__'" x-model="newUser.customRole" placeholder="Type role name…" class="w-full mt-1.5 text-[12px] border border-[#175CD3] rounded-[8px] px-3 py-2 focus:outline-none bg-white" />
            </div>
          </div>
          <div x-show="newUser.error" class="text-[11px] text-[#B42318] font-semibold" x-text="newUser.error"></div>
          <div x-show="newUser.success" class="text-[11px] text-[#16803C] font-semibold" x-text="newUser.success"></div>
          <div class="flex justify-end">
            <button type="submit" class="flex items-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#175CD3] text-white hover:bg-[#1349a8] transition-colors">+ Add User</button>
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
                <span class="text-[11px] text-[#98A2B3] tabular-nums" x-text="rmFilter==='done'?rmFollowUps.filter(f=>!f.completed).length:rmFiltered.length"></span>
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
                          x-text="key==='all'?rmFollowUps.filter(f=>!f.completed).length:key==='today'?rmFollowUps.filter(f=>!f.completed&&f.follow_up_date===new Date().toISOString().slice(0,10)).length:key==='upcoming'?rmFollowUps.filter(f=>!f.completed&&f.follow_up_date>new Date().toISOString().slice(0,10)).length:rmFollowUps.filter(f=>!f.completed&&f.follow_up_date<new Date().toISOString().slice(0,10)).length"></span>
                    <svg x-show="rmFilter===key||(rmFilter==='done'&&key==='all')" class="w-3 h-3 text-[#1268F3] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                </template>
              </div>
            </div>

            <!-- History — standalone toggle CTA -->
            <button @click="rmFilter = rmFilter==='done' ? 'all' : 'done'"
                    class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] text-[12px] font-semibold border transition-all"
                    :style="rmFilter==='done' ? 'background:#344054;border-color:#344054;color:#fff' : 'background:#fff;border-color:#E4E7EC;color:#667085'">
              <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              History
              <span class="text-[10px] font-extrabold tabular-nums px-1.5 py-0.5 rounded-full"
                    :style="rmFilter==='done' ? 'background:rgba(255,255,255,0.2);color:#fff' : 'background:#F2F4F7;color:#98A2B3'"
                    x-text="rmFollowUps.filter(f=>f.completed).length"></span>
            </button>

          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto" x-show="rmFiltered.length>0">
          <table class="w-full min-w-[700px] border-collapse">
            <thead>
              <tr style="background:#F8FAFC;border-bottom:2px solid #E4E7EC">
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
                    :style="fuStatus(fu)==='overdue'?'background:#FFFAFA':fuStatus(fu)==='today'?'background:#F5F8FF':''">

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
                    <div class="text-[12px] font-semibold text-[#172B3A] truncate max-w-[140px]" x-text="fu.client||'—'"></div>
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

                  <!-- Actions — only for the assignee -->
                  <td class="px-5 py-3.5 whitespace-nowrap">
                    <template x-if="!fu.completed && fu.assigned_to===currentUser">
                      <button @click="rmComplete(fu)"
                              class="text-[11px] font-bold px-3 py-1.5 rounded-[6px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors opacity-0 group-hover:opacity-100">
                        ✓ Done
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

    <?php include __DIR__.'/reports-view.php'; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ═══ MODALS ════════════════════════════════════════════════════════════════ -->

<!-- ── Step File Upload Modal ──────────────────────────────────────────────── -->
<div x-show="_stepUploadFor" x-cloak
     @click.self="_stepUploadFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[380px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18)">

    <!-- Header — matches Schedule Follow-up exactly -->
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

    <!-- Footer — matches Schedule Follow-up exactly -->
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

<!-- Reminders — Add Follow-up Modal -->
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
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[12px] font-extrabold transition-colors cursor-pointer"
                  :class="stageForm.stageNum > s ? 'bg-[#16803C] text-white' : stageForm.stageNum === s ? 'bg-[#1268F3] text-white' : 'bg-[#E4E7EC] text-[#667085]'"
                  @click="stageForm.stageNum=s; stageForm.stageStep=''"
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
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Stage *</label>
          <select x-model.number="stageForm.stageNum" @change="stageForm.stageStep=''"
            class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
            <option :value="1">Stage 1 Inquiry</option>
            <option :value="2">Stage 2 Communication / Proposal</option>
            <option :value="3">Stage 3 Decision (Won / Lost)</option>
            <option :value="4">Stage 4 Project Execution</option>
            <option :value="5">Stage 5 Closed</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Stage Type *</label>
          <select x-model="stageForm.stageStep"
            class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white cursor-pointer">
            <option value="">Select type </option>
            <template x-for="opt in currentStageSteps" :key="opt">
              <option :value="opt" x-text="opt"></option>
            </template>
          </select>
          <!-- Lost path note -->
          <p x-show="stageForm.stageNum===4 && stageForm.wonPath===false" class="text-[10px] text-[#B42318] font-semibold mt-0.5">Lost path only Inquiry Closed available</p>
          <p x-show="stageForm.stageNum===5 && stageForm.wonPath===false" class="text-[10px] text-[#B42318] font-semibold mt-0.5">Stage 5 not available on lost path</p>
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
              <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px] shrink-0" :class="stageClass(h.stage)" x-text="stageLabel(h.stage)"></span>
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
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <input type="text" placeholder="Select date" class="hidden" x-init="_fpDate($el,()=>taskForm.due,v=>taskForm.due=v,'taskForm.due')" />
          </div>
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

<!-- Complete Inquiry Modal -->
<div x-show="completeInquiryFor" x-cloak @click.self="completeInquiryFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[16px] w-full max-w-[360px] p-5 text-center relative" style="box-shadow:0 20px 60px rgba(7,29,43,0.18)">
    <button @click="completeInquiryFor=null" class="absolute top-3 right-3 text-[#98A2B3] hover:text-[#344054] transition-colors text-lg leading-none">✕</button>
    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background:#ECFDF3">
      <svg class="w-6 h-6 text-[#16803C]"><use href="#icon-check-circle"/></svg>
    </div>
    <h2 class="text-[15px] font-bold text-[#172B3A]">Complete Inquiry</h2>
    <p class="text-[11px] text-[#667085] mt-0.5 mb-4" x-text="completeInquiryFor?(completeInquiryFor.id+' · '+completeInquiryFor.client):''"></p>
    <textarea x-model="completeInquiryRemark" rows="3" placeholder="Final status…"
      @input="_completeInquiryErr=false"
      :class="_completeInquiryErr ? 'border-[#B42318]' : 'border-[#E4E7EC]'"
      class="w-full text-[13px] text-left border rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] resize-none"></textarea>
    <p x-show="_completeInquiryErr" class="text-[10px] font-semibold text-[#B42318] mt-1 text-left">Final status is required.</p>
    <div class="flex gap-2 mt-4">
      <button @click="completeInquiryFor=null" :disabled="_completingInquiry" class="flex-1 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="submitCompleteInquiry()" :disabled="_completingInquiry" class="flex-1 flex items-center justify-center gap-1.5 text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#16803C] text-white hover:bg-[#106430] transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
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
          <button type="button" @click="addInquiryForm.inquiryType='Client Project'"
            :class="addInquiryForm.inquiryType==='Client Project' ? 'bg-white shadow-sm text-[#175CD3]' : 'text-[#667085]'"
            class="px-3 py-1.5 rounded-[7px] text-[12px] font-semibold transition-colors">Client Project</button>
          <button type="button" @click="addInquiryForm.inquiryType='Internal Usage'"
            :class="addInquiryForm.inquiryType==='Internal Usage' ? 'bg-white shadow-sm text-[#175CD3]' : 'text-[#667085]'"
            class="px-3 py-1.5 rounded-[7px] text-[12px] font-semibold transition-colors">Internal Usage</button>
        </div>
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
              <option>New</option><option>Existing Contact / Prospect</option><option>Repeat</option>
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
      <!-- Email Subject -->
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Email Subject</label>
        <input type="text" x-model="addInquiryForm.emailSubject" placeholder="e.g. Re: Survey Requirement for Q3 Project"
          class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3] bg-white" />
      </div>
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
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-[#98A2B3]"><svg xmlns="http://www.w3.org/2000/svg" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
              <input type="text" placeholder="Select date" class="hidden"
                x-init="_fpDate($el,()=>addInquiryForm.dueDate,v=>{addInquiryForm.dueDate=v;if(v)delete addInquiryForm.errors.dueDate},'addInquiryForm.dueDate',false,{minDate:'today'})" />
            </div>
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
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px]" :class="summaryFor?stageClass(summaryFor.stage):''" x-text="summaryFor?stageLabel(summaryFor.stage):''"></span>
          <span class="inline-flex items-center rounded-full font-extrabold whitespace-nowrap px-2.5 py-[3px] text-[10px]" :class="summaryFor?outcomeClass(summaryFor.outcome):''" x-text="summaryFor?.outcome||''"></span>
          <span class="text-[11px] text-[#667085]" x-text="'Owner: '+(summaryFor?.current_owner||'')"></span>
          <span class="text-[11px] text-[#667085]" x-text="'Due: '+(summaryFor?.due_date||'TBD')"></span>
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
               style="overflow-wrap:anywhere;word-break:break-word"
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
     :style="`position:fixed;left:${reqPopup.x}px;top:${reqPopup.y}px;z-index:9999;max-width:320px;background:#fff;border:1px solid #D1D9E6;border-radius:10px;padding:12px 14px;box-shadow:0 8px 28px rgba(7,29,43,0.14);pointer-events:none`">
  <div class="text-[10px] font-bold uppercase tracking-[0.06em] text-[#98A2B3] mb-1.5">Requirement</div>
  <div class="text-[12px] text-[#344054] leading-[1.7]" x-text="reqPopup.text"></div>
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
  <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>
  <symbol id="icon-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></symbol>
  <symbol id="icon-chevron-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></symbol>
  <symbol id="icon-chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></symbol>
  <symbol id="icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
  <symbol id="icon-file-text" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></symbol>
  <symbol id="icon-trash-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></symbol>
  <symbol id="icon-paperclip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></symbol>
  <symbol id="icon-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></symbol>
  <symbol id="icon-building2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="18"/><path d="M16 8h4l3 3v10H16V8z"/><line x1="5" y1="7" x2="9" y2="7"/><line x1="5" y1="11" x2="9" y2="11"/><line x1="5" y1="15" x2="9" y2="15"/></symbol>
  <symbol id="icon-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></symbol>
  <symbol id="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></symbol>
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

function spApp() {
  return {
    // ── Seeded from PHP ──
    inquiries: <?= json_encode($inquiries, JSON_HEX_TAG) ?>,
    accounts:  <?= json_encode($accounts,  JSON_HEX_TAG) ?>,
    currentUser:  <?= json_encode($user['name'],  JSON_HEX_TAG) ?>,
    currentRole:  <?= json_encode($user['role'],  JSON_HEX_TAG) ?>,
    currentEmail: <?= json_encode($user['email'], JSON_HEX_TAG) ?>,

    // ── UI state ──
    view: 'dashboard',
    collapsed: false,
    _mob: false,
    statFilter: '',
    notifsOpen: false,
    toasts: [],
    notifications: [],
    highlightedInq: null,
    reqPopup: { show: false, text: '', x: 0, y: 0 },

    // ── Filters ──
    search: '',
    employee: '',
    stageFilter: '',
    myOnly: false,
    _inqPage: 0,

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
    completeInquiryRemark: '',
    _completeInquiryErr: false,
    _completingInquiry: false,

    // ── Follow-ups ──
    todayFollowUps: <?= json_encode($todayFollowUps, JSON_HEX_TAG) ?>,
    followUps: {},
    fuForm: { date: new Date().toLocaleDateString('en-CA'), time:'', note:'', assignedTo:'' },
    fuOpenFor: null,
    confirmDeleteAccount: null,

    // ── Reminders view ──
    rmFollowUps: <?= json_encode($allFollowUps, JSON_HEX_TAG) ?>,
    rmFilter: 'all',
    get rmFiltered() {
      const today = new Date().toISOString().slice(0,10);
      return this.rmFollowUps.filter(f => {
        if (this.rmFilter==='today')    return !f.completed && f.follow_up_date===today;
        if (this.rmFilter==='upcoming') return !f.completed && f.follow_up_date>today;
        if (this.rmFilter==='overdue')  return !f.completed && f.follow_up_date<today;
        if (this.rmFilter==='done')     return !!f.completed;
        return !f.completed; // 'all' — never show completed here, those live in History
      });
    },
    rmAddOpen: false,
    rmAddErr: '',
    rmAddForm: { inquiryId:'', note:'', date: new Date().toLocaleDateString('en-CA'), time:'', assignedTo:'' },
    // ── Reports ──
    rptUser: '',
    rptPeriod: 'month',
    rptFrom: '',
    rptTo: '',
    _rptCharts: {},
    customRoleFor: null,

    // ── Forms ──
    stageForm: { stageNum:1, stageStep:'', wonPath:null, remark:'', deliveryDate:'', followUpDate:'', proposalValue:'', finalValue:'', finalRemark:'', _finalRemarkErr:false },
    taskForm:  { assignedTo:'', due: new Date().toLocaleDateString('en-CA'), dueTime:'', instruction:'', errors:{} },
    newUser:   { name:'', email:'', password:'', role:'Member', customRole:'', error:'', success:'', showPass:false },
    addInquiryForm: { inquiryType:'Client Project', date: new Date().toLocaleDateString('en-CA'), client:'', designation:'', company:'', country:'India', clientType:'New', requirement:'', assignTo:'', dueDate:'', dueTime:'', proposalValue:'', email:'', emailSecondary:'', phone:'', website:'', emailSubject:'', taskInstruction:'', createdBy:'', errors:{} },
    _clientAcOpen: false,
    _allClientNames: <?= json_encode($_allClientNames, JSON_HEX_TAG) ?>,

    // ── Init ──
    init() {
      this.inquiries = this.inquiries.map(i => ({
        ...i,
        _open: false,
        _adminRemark: i.admin_remark || '',
        _adminRemarkSaving: false,
        steps: (i.steps||[]).map(s => ({ ...s, _remark: s.remark||'', _saved: false, _remarkOpen: false, _instruction: s.instruction||'', _instrSaved: false }))
      }));
      this.addInquiryForm.createdBy = this.currentUser;
      this.fetchNotifications();
      setInterval(() => this.fetchNotifications(), 30000);
      this.$watch('view', () => { this.search=''; this._mob=false; this.statFilter=''; this.fuOpenFor=null; this._inqPage=0; });
      ['search','statFilter','stageFilter','employee','myOnly'].forEach(k => this.$watch(k, () => { this._inqPage=0; }));
      setTimeout(() => this._startPolling(), 8000); // first ping after 8s, then every 30s
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

    async _startPolling() {
      const changed = (a, b) => a !== b;
      const poll = async () => {
        try {
          const d = await fetch('api/ping.php').then(r=>r.json());
          if (!d.ok) return;
          // First run: set baseline silently
          if (!this._pollBase.inquiry) {
            this._pollBase = {
              inquiry: d.latestInquiry, step: d.latestStep, stepUpd: d.latestStepUpd,
              att: d.latestAtt, inqAtt: d.latestInqAtt,
              history: d.latestHistory, fu: d.latestFollowUp, fuCompleted: d.latestFuCompleted,
              pending: d.pendingApprovals, unread: d.unreadNotifications
            };
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
          // Follow-up added or completed — invalidate followUps cache so strips refresh on next open
          if (changed(d.latestFollowUp, this._pollBase.fu) || changed(d.latestFuCompleted, this._pollBase.fuCompleted)) {
            this._pollBase.fu          = d.latestFollowUp;
            this._pollBase.fuCompleted = d.latestFuCompleted;
            this.followUps = {}; // clear cache — strips reload lazily on next expand
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
        } catch(e) { /* silent — offline or server busy */ }
      };
      await poll(); // immediate first ping to set baseline
      setInterval(poll, 10000); // every 10 seconds
    },

    _remap(data) {
      return data.map(i => {
        const old = this.inquiries.find(x => x.id === i.id);
        return {
          ...i,
          _open: old?._open || false,
          _adminRemark: old?._adminRemark ?? i.admin_remark ?? '',
          _adminRemarkSaving: false,
          history: old?.history || [],
          _historyLoaded: old?._historyLoaded || false,
          steps: (i.steps||[]).map(s => {
            const os = (old?.steps||[]).find(x => x.id === s.id);
            return { ...s, _remark: os?._remark ?? s.remark ?? '', _saved: os?._saved || false, _remarkOpen: os?._remarkOpen || false, _instruction: s.instruction||'', _instrSaved: false };
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

    get viewingEmployee() { return !!this.employee && this.employee !== this.currentUser; },

    get viewTitle() {
      return {dashboard:'Dashboard',tasks:'My Tasks',approvals:'User Management',reminders:'Reminders',reports:'Reports'}[this.view]||'';
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
          ? { kind:'warning', text:'A client named "'+name+'" already exists in records. Consider changing the type to "Existing Contact / Prospect" or "Repeat".' }
          : { kind:'success', text:'Client is new no existing records found.' };
      }
      return exists
        ? { kind:'info', text:'Client found in existing records.' }
        : { kind:'warning', text:'No records found for "'+name+'". If this is a new client, change the type to "New".' };
    },

    get visibleInquiries() {
      if (this.isAdmin) return this.inquiries;
      // Server already filters by role; mirror here so client-side stats are consistent
      return this.inquiries.filter(inq => {
        const hasStep = (inq.steps||[]).some(s => s.assigned_to===this.currentUser || s.assigned_by===this.currentUser);
        return inq.created_by===this.currentUser || inq.current_owner===this.currentUser || hasStep;
      });
    },

    get filtered() {
      const isDone = i => i.outcome==='Inquiry Closed' || ['Payment Received','Lost','Cancelled'].includes(i.outcome) || ['Payment Received','Project Closed','Proposal Lost','Closure'].includes(i.stage);
      const isIP   = i => !isDone(i) && (['Questionnaire','Link Testing','Field Work','Project Delivered','Invoice Sent','Won','Project Won and Started','Project Running','Project Completed'].includes(i.outcome) || ['Project Execution'].includes(i.stage));
      const isPend = i => !isDone(i) && !isIP(i) && (['Project Won','Project Lost','Proposal Submitted','Follow-up Sent','Awaiting Client Response','No Reply','On Hold'].includes(i.outcome) || ['Communication / Proposal','Decision'].includes(i.stage));
      return this.visibleInquiries.filter(inq => {
        const isClosed = i => i.outcome==='Inquiry Closed' || ['Payment Received','Inquiry Closed'].includes(i.outcome) || i.stage==='Closure' || ['Lost','Cancelled'].includes(i.outcome) || ['Payment Received','Project Closed','Proposal Lost'].includes(i.stage);
        if (this.statFilter==='done'       && !isClosed(inq))        return false;
        if (this.statFilter==='inProgress' && isClosed(inq))         return false;
        if (this.statFilter==='overdue'    && (!inq.overdue || isClosed(inq))) return false;
        if (this.myOnly && inq.created_by!==this.currentUser && inq.current_owner!==this.currentUser) return false;
        if (this.employee && inq.current_owner!==this.employee && inq.created_by!==this.employee && !(inq.steps||[]).some(s=>s.assigned_to===this.employee||s.assigned_by===this.employee)) return false;
        if (this.stageFilter && inq.stage!==this.stageFilter) return false;
        if (this.search) {
          const q = this.search.toLowerCase();
          return inq.id.toLowerCase().includes(q)||inq.client.toLowerCase().includes(q)||inq.company.toLowerCase().includes(q)||this.stripHtml(inq.requirement||'').toLowerCase().includes(q);
        }
        return true;
      });
    },

    get pagedInqs() {
      const s = this._inqPage * 10;
      return this.filtered.slice(s, s + 10);
    },

    get stats() {
      const v = this.visibleInquiries;
      // Closed: Stage 5 steps OR Stage 4 lost path "Inquiry Closed" OR legacy closed
      const isClosed = i => i.outcome==='Inquiry Closed'
        || ['Payment Received','Inquiry Closed'].includes(i.outcome)
        || i.stage==='Closure'
        || ['Lost','Cancelled'].includes(i.outcome)
        || ['Payment Received','Project Closed','Proposal Lost'].includes(i.stage);
      // In Progress: Stages 1-4 active (not closed)
      const isInProgress = i => !isClosed(i);
      return {
        total:      v.length,
        inProgress: v.filter(isInProgress).length,
        done:       v.filter(isClosed).length,
        overdue:    v.filter(i=>i.overdue && !isClosed(i)).length,
      };
    },

    get chips() {
      const sfLabel = {inProgress:'In Progress',done:'Closed',overdue:'Overdue'};
      return [
        this.statFilter  && { label:'Filter: '+(sfLabel[this.statFilter]||this.statFilter), clear:()=>this.statFilter='' },
        this.employee    && { label:'Employee: '+this.employee,   clear:()=>this.employee='' },
        this.stageFilter && { label:'Stage: '+this.stageFilter,   clear:()=>this.stageFilter='' },
        this.myOnly      && { label:'My Inquiries',               clear:()=>this.myOnly=false },
      ].filter(Boolean);
    },

    get customRoles() {
      const std = ['Client','Member','Master Admin'];
      return [...new Set(this.accounts.map(a=>a.role).filter(r=>!std.includes(r)))];
    },

    get myTasks() {
      const out = [];
      this.inquiries.forEach(inq => {
        (inq.steps||[]).forEach(step => {
          if (step.assigned_to===this.currentUser && !['Done','Completed','Cancelled'].includes(step.status))
            out.push({ inq, step });
        });
      });
      return out;
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
    resetFilters() { this.search=''; this.employee=''; this.stageFilter=''; this.myOnly=false; this.statFilter=''; this._inqPage=0; },

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

    get currentStageSteps() {
      const wp = this.stageForm.stageStep === 'Project Won' ? true
               : this.stageForm.stageStep === 'Project Lost' ? false
               : this.stageForm.wonPath;
      const steps = {
        1: ['Inquiry Received','Inquiry Created'],
        2: ['Initial Communication','Proposal Submitted','Follow-up Sent','Awaiting Client Response'],
        3: ['Project Won','Project Lost'],
        4: wp === false ? ['Inquiry Closed'] : ['Questionnaire','Link Testing','Field Work','Project Delivered','Invoice Sent'],
        5: wp === false ? [] : ['Payment Received','Inquiry Closed'],
      };
      return steps[this.stageForm.stageNum] || [];
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
        const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(safe) });
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
        if (d.ok && Array.isArray(d.data)) this.inquiries = this._remap(d.data);
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
      const wonSignals = ['Project Won','Questionnaire','Link Testing','Field Work','Project Delivered','Invoice Sent','Payment Received','Won','Project Won and Started','Project Running','Project Completed'];
      const lostSignals = ['Project Lost','Lost','Cancelled'];
      let wonPath = null;
      const hist = inq.history || [];
      for (const h of [...hist].reverse()) {
        if (wonSignals.includes(h.outcome)) { wonPath = true; break; }
        if (lostSignals.includes(h.outcome)) { wonPath = false; break; }
      }
      if (wonPath === null) {
        if (wonSignals.includes(inq.outcome)) wonPath = true;
        else if (lostSignals.includes(inq.outcome)) wonPath = false;
      }
      this.stageForm = {
        stageNum: numMap[inq.outcome] || 1,
        stageStep: inq.outcome || '',
        wonPath,
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
      const payload = {
        stage: stageLabels[this.stageForm.stageNum],
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

    // ── Closed check (mirrors stats getter) — used to hide Complete Inquiry once closed ──
    isInquiryClosed(inq) {
      return inq.outcome==='Inquiry Closed'
        || ['Payment Received','Inquiry Closed'].includes(inq.outcome)
        || inq.stage==='Closure'
        || ['Lost','Cancelled'].includes(inq.outcome)
        || ['Payment Received','Project Closed','Proposal Lost'].includes(inq.stage);
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
      this.taskForm={ assignedTo:'', due: new Date().toLocaleDateString('en-CA'), dueTime:'', instruction:'', errors:{} };
      this.$nextTick(() => { if(this.$refs.taskFiles) this.$refs.taskFiles.value=''; });
    },
    async saveTask() {
      if (!this.addTaskFor || this._taskSaving) return;
      this.taskForm.errors = {};
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
          const up = await fetch('api/upload.php', { method:'POST', body: fd })
            .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^﻿/,''))}catch{return {ok:false}}}).catch(()=>({ok:false}));
          await this.refreshInquiries();
          this.addTaskFor = null;
          if (up.saved?.length) this.addToast('Task added '+up.saved.length+' file(s) uploaded');
          else { this.addToast('Task added'); if (files.length) this.addToast('File upload failed check server uploads folder','error'); }
        } else {
          this.inquiries = this._remap(d.data);
          this.addTaskFor = null;
          this.addToast('Task added');
        }
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

    autoSaveAdminRemark(inq) {
      clearTimeout(inq._adminRemarkTimer);
      inq._adminRemarkTimer = setTimeout(() => this.saveAdminRemark(inq), 1000);
    },

    async saveAdminRemark(inq) {
      inq._adminRemarkSaving = true;
      const d = await this.api('api/inquiry.php', { action:'admin_remark', inquiryId:inq.id, remark:inq._adminRemark });
      inq._adminRemarkSaving = false;
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Admin remark saved'); }
      else this.addToast(d.message||'Failed to save remark','error');
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
      const d = await fetch('api/upload.php', { method:'POST', body: fd })
        .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^﻿/,''))}catch{return {ok:false,message:'Server error'}}}).catch(()=>({ok:false,message:'Upload failed'}));
      input.value = '';
      if (d.ok && d.saved.length) { this.inquiries = this._remap(d.data); this.addToast(d.saved.length+' file(s) uploaded'); }
      else if (d.ok && !d.saved.length) this.addToast('No valid files check allowed types (PDF, Word, Excel, images, ZIP)', 'error');
      else this.addToast(d.message||'Upload failed', 'error');
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
    showReqPopup(e, html) {
      const r = e.currentTarget.getBoundingClientRect();
      const x = Math.min(r.left, window.innerWidth - 320);
      const y = r.bottom + 6 + window.scrollY;
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
      if (d.ok) { this.inquiries = this._remap(d.data); this.addToast('Due date updated'); }
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
      const d = await fetch('api/upload.php', { method:'POST', body: fd })
        .then(r=>r.text()).then(t=>{try{return JSON.parse(t.replace(/^﻿/,''))}catch{return {ok:false,message:'Server error'}}}).catch(()=>({ok:false,message:'Upload failed'}));
      if (d.ok && d.saved?.length) {
        const inq = this.inquiries.find(i => i.id == inquiryId);
        if (inq) { const step = inq.steps.find(s => s.id == stepId); if (step) step.attachments = [...(step.attachments||[]), ...d.saved]; }
        this.addToast(d.saved.length+' file(s) uploaded');
      } else if (d.ok && !d.saved?.length) this.addToast('File upload failed — check allowed types or server uploads folder','error');
      else this.addToast(d.message||'Upload failed','error');
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
    async addInquiry() {
      this.addInquiryForm.errors = {};
      const e = this.addInquiryForm.errors;
      const isClientProject = this.addInquiryForm.inquiryType !== 'Internal Usage';
      if (isClientProject && !this.addInquiryForm.client.trim())         e.client          = 'Client name is required.';
      if (isClientProject && !this.addInquiryForm.company.trim())        e.company         = 'Company is required.';
      if (!this.stripHtml(this.addInquiryForm.requirement).trim())      e.requirement     = 'Requirement is required.';
      if (!this.addInquiryForm.assignTo)                                e.assignTo        = 'Please select a user.';
      if (!this.stripHtml(this.addInquiryForm.taskInstruction).trim())  e.taskInstruction = 'Task instruction is required.';
      if (isClientProject && !this.addInquiryForm.country.trim())        e.country         = 'Country is required.';
      if (isClientProject && !this.addInquiryForm.website.trim())        e.website         = 'Website is required.';
      if (!this.addInquiryForm.dueDate)                                 e.dueDate         = 'Due date is required.';
      if (!this.addInquiryForm.dueTime)                                 e.dueTime         = 'Due time is required.';
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
        const mapped = { ...ni, _open:false, _adminRemark:ni.admin_remark||'', _adminRemarkSaving:false, history:[], _historyLoaded:false,
          steps:(ni.steps||[]).map(s=>({...s,_remark:s.remark||'',_saved:false,_remarkOpen:false,_instruction:s.instruction||'',_instrSaved:false})) };
        this.inquiries = [...this.inquiries, mapped];
        if (files && files.length) {
          const fd = new FormData();
          fd.append('inquiry_id', d.id);
          for (const f of files) fd.append('files[]', f);
          const up = await fetch('api/upload.php', { method:'POST', body:fd }).then(r=>r.json()).catch(()=>({ok:false}));
          if (up.ok && up.saved?.length) { mapped.attachments = up.saved.map(f=>({filename:f})); this.addToast(d.id+' created + '+up.saved.length+' file(s) uploaded'); }
          else this.addToast('Inquiry '+d.id+' created');
        } else {
          this.addToast('Inquiry '+d.id+' created');
        }
        setTimeout(() => {
          this.addInquiryOpen = false;
          this._inquirySave = '';
          this.addInquiryForm = { inquiryType:'Client Project', date: new Date().toLocaleDateString('en-CA'), client:'', designation:'', company:'', country:'India', clientType:'New', requirement:'', assignTo:'', dueDate:'', dueTime:'', proposalValue:'', email:'', emailSecondary:'', phone:'', website:'', emailSubject:'', taskInstruction:'', createdBy:this.currentUser, _files:null, errors:{} };
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

    // ── Notifications ──
    goToInquiry(inq) {
      this.view = 'dashboard';
      this.$nextTick(() => {
        inq._open = true;
        this.highlightedInq = inq.id;
        setTimeout(() => { this.highlightedInq = null; }, 3000);
        this.$nextTick(() => {
          const el = document.querySelector('[data-inq="'+inq.id+'"]');
          if (el) el.scrollIntoView({ behavior:'smooth', block:'center' });
        });
      });
    },

    openNotifInquiry(n) {
      // Remove from list immediately, delete from server
      this.notifications = this.notifications.filter(x => x.id !== n.id);
      fetch('api/notifications.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'dismiss', id: n.id }) });
      this.notifsOpen = false;
      this.view = 'dashboard';
      this.$nextTick(() => {
        const inq = this.inquiries.find(i => i.id === n.inquiry_id);
        if (!inq) return;
        inq._open = true;
        this.highlightedInq = n.inquiry_id;
        setTimeout(() => { this.highlightedInq = null; }, 3000);
        this.$nextTick(() => {
          const el = document.querySelector('[data-inq="'+n.inquiry_id+'"]');
          if (el) el.scrollIntoView({ behavior:'smooth', block:'center' });
        });
      });
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
      await fetch('api/notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'mark_read'}) });
    },
    async clearAllNotifs() {
      this.notifications = [];
      await fetch('api/notifications.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'clear_all'}) });
    },

    // ── Follow-ups ──
    toggleInquiry(inq) {
      inq._open = !inq._open;
      if (inq._open) this.loadFollowUps(inq);
    },

    async loadFollowUps(inq) {
      if (this.followUps[inq.id] !== undefined) return;
      this.followUps = { ...this.followUps, [inq.id]: null };
      const r = await fetch('api/followup.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'load',inquiryId:inq.id})}).then(r=>r.json()).catch(()=>({ok:false,data:[]}));
      this.followUps = { ...this.followUps, [inq.id]: r.ok ? r.data : [] };
    },

    openFuForm(inq) {
      this.fuOpenFor = this.fuOpenFor===inq.id ? null : inq.id;
      this.fuForm = { date: new Date().toLocaleDateString('en-CA'), time:'', note:'', assignedTo:'' };
    },

    async addFollowUp(inq) {
      if (!this.fuForm.note.trim() || !this.fuForm.date) { this.addToast('Note and date are required','error'); return; }
      const r = await this.api('api/followup.php',{action:'add',inquiryId:inq.id,note:this.fuForm.note,date:this.fuForm.date,time:this.fuForm.time||null,assignedTo:this.fuForm.assignedTo||this.currentUser});
      if (r.ok) {
        this.fuOpenFor = null;
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
        this.addToast('Follow-up scheduled','success');
      } else this.addToast(r.message||'Error','error');
    },

    async completeFollowUp(fu, inq) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        this.rmFollowUps = this.rmFollowUps.map(f=>f.id===fu.id?{...f,completed:1}:f);
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
        this.addToast('Follow-up completed','success');
      }
    },

    async deleteFollowUp(fu, inq) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        this.todayFollowUps = this.todayFollowUps.filter(f=>f.id!==fu.id);
        delete this.followUps[inq.id];
        await this.loadFollowUps(inq);
      }
    },

    async completeFuDashboard(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
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
      this.view = 'dashboard';
      this.$nextTick(() => {
        const inq = this.inquiries.find(i=>i.id===fu.inquiry_id);
        if (!inq) return;
        inq._open = true;
        this.loadFollowUps(inq);
        this.highlightedInq = inq.id;
        setTimeout(() => { this.highlightedInq=null; }, 3000);
        this.$nextTick(() => {
          const el = document.querySelector('[data-inq="'+inq.id+'"]');
          if (el) el.scrollIntoView({behavior:'smooth',block:'center'});
        });
      });
    },

    fuTimeLabel(t) {
      if (!t) return '';
      const [h,m] = t.split(':').map(Number);
      return ((h%12)||12)+':'+(m<10?'0':'')+m+' '+(h>=12?'PM':'AM');
    },

    get todayStr() { return new Date().toISOString().slice(0,10); },


    fuStatus(fu) {
      const t = new Date().toISOString().slice(0,10);
      if (fu.completed) return 'done';
      if (fu.follow_up_date < t)  return 'overdue';
      if (fu.follow_up_date === t) return 'today';
      return 'upcoming';
    },

    async rmComplete(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) {
        const f = this.rmFollowUps.find(f=>f.id===fu.id);
        if (f) f.completed = 1; // keep in list — shows under History filter
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
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:fu.id})}).then(r=>r.json());
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
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());
      if (r.ok) {
        const inq = this.inquiries.find(i=>i.id===this.rmAddForm.inquiryId)||{};
        this.rmFollowUps.push({id:r.id,inquiry_id:this.rmAddForm.inquiryId,note:this.rmAddForm.note,follow_up_date:this.rmAddForm.date,follow_up_time:this.rmAddForm.time||null,assigned_to:assignedTo,client:inq.client||'',company:inq.company||'',completed:0,created_by:this.currentUser});
        delete this.followUps[this.rmAddForm.inquiryId];
        this.rmAddOpen = false;
        this.rmAddForm = {inquiryId:'',note:'',date:new Date().toLocaleDateString('en-CA'),time:'',assignedTo:''};
        this.addToast('Follow-up scheduled','success');
      } else this.rmAddErr = r.message||'Error';
    },

    // ── Reports ──────────────────────────────────────────────────────────────────
    get rptDateRange() {
      const t = new Date(); t.setHours(0,0,0,0);
      const fmt = d => d.toISOString().slice(0,10);
      if (this.rptPeriod==='today')   return [fmt(t), fmt(t)];
      if (this.rptPeriod==='week')    { const s=new Date(t); s.setDate(t.getDate()-t.getDay()); return [fmt(s),fmt(t)]; }
      if (this.rptPeriod==='month')   return [fmt(new Date(t.getFullYear(),t.getMonth(),1)), fmt(t)];
      if (this.rptPeriod==='quarter') { const q=Math.floor(t.getMonth()/3); return [fmt(new Date(t.getFullYear(),q*3,1)), fmt(t)]; }
      if (this.rptPeriod==='year')    return [fmt(new Date(t.getFullYear(),0,1)), fmt(t)];
      if (this.rptPeriod==='custom')  return [this.rptFrom, this.rptTo];
      return ['',''];
    },
    get rptPeriodLabel() {
      return {today:'Today',week:'This Week',month:'This Month',quarter:'This Quarter',year:'This Year',all:'All Time',custom:'Custom Range'}[this.rptPeriod]||'';
    },
    _rptInRange(dateStr) {
      const [from,to] = this.rptDateRange;
      if (!from && !to) return true;
      if (!dateStr) return false;
      const d = dateStr.slice(0,10);
      return (!from||d>=from) && (!to||d<=to);
    },
    _rptClosed(i) {
      return ['Payment Received','Inquiry Closed','Completed'].includes(i.outcome)
          || ['Lost','Cancelled','No Reply'].includes(i.outcome)
          || ['Payment Received','Project Closed','Proposal Lost','Closure'].includes(i.stage);
    },
    get rptInquiries() {
      let v = this.isAdmin ? this.inquiries : this.visibleInquiries;
      if (this.isAdmin && this.rptUser)
        v = v.filter(i => i.created_by===this.rptUser || i.current_owner===this.rptUser
                       || (i.steps||[]).some(s=>s.assigned_to===this.rptUser));
      if (this.rptPeriod!=='all')
        v = v.filter(i => this._rptInRange(i.created_at||i.date));
      return v;
    },
    get rptTeamStats() {
      const map={};
      this.rptInquiries.forEach(i=>{
        const key=i.current_owner||i.created_by; if(!key) return;
        if(!map[key]) map[key]={name:key,inqs:0,active:0,closed:0,tasksOD:0};
        map[key].inqs++;
        if(this._rptClosed(i)) map[key].closed++; else map[key].active++;
        if(!this._rptClosed(i) && i.overdue) map[key].tasksOD++;
      });
      return Object.values(map).sort((a,b)=>b.inqs-a.inqs);
    },

    initRptCharts() {},

    exportRptCSV() {
      const inqs=this.rptInquiries;
      if(!inqs.length){ this.addToast('No data to export','error'); return; }
      const today=new Date().toISOString().slice(0,10);
      const hdr=['ID','Client','Company','Stage','Outcome','Owner','Created','Due Date','Status'];
      const rows=inqs.map(i=>[i.id,i.client,i.company,i.stage,i.outcome||'',i.current_owner||i.created_by,
        (i.date||i.created_at||'').slice(0,10),i.due_date||'',
        this._rptClosed(i)?'Closed':(i.overdue?'Overdue':'Active')]);
      const csv=[hdr,...rows].map(r=>r.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
      const a=Object.assign(document.createElement('a'),{href:'data:text/csv;charset=utf-8,'+encodeURIComponent(csv),download:'sp-report-'+today+'.csv'});
      document.body.appendChild(a);a.click();a.remove();
      this.addToast('Report exported as CSV','success');
    },

    async directAddUser() {
      this.newUser.error=''; this.newUser.success='';
      if (!this.newUser.name.trim()) return this.newUser.error='Full name is required.';
      if (!this.newUser.email.includes('@')) return this.newUser.error='Enter a valid email.';
      if (this.newUser.password.length<6) return this.newUser.error='Password must be at least 6 characters.';
      const role = this.newUser.role==='__custom__' ? this.newUser.customRole.trim() : this.newUser.role;
      if (!role) return this.newUser.error='Enter a custom role name.';
      const d = await this.api('api/accounts.php', { action:'direct_add', name:this.newUser.name, email:this.newUser.email, password:this.newUser.password, role });
      if (d.ok) {
        this.newUser.success=d.message; this.newUser.name=''; this.newUser.email=''; this.newUser.password=''; this.newUser.role='Member'; this.newUser.customRole=''; this.newUser.showPass=false;
        this.addToast(d.message);
        if (d.data) this.accounts=d.data;
      } else this.newUser.error=d.message;
    },
  }
}
</script>
</body>
</html>
