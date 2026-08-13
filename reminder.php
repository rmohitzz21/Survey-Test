<?php
ob_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
sp_session_start();
require_auth('login.php');

$user     = current_user();
$accounts = load_accounts($pdo);

// Load all follow-ups (admin = all, member = own only)
$stmt = $pdo->prepare("SELECT f.*,i.client,i.company FROM follow_ups f JOIN inquiries i ON i.id=f.inquiry_id WHERE f.assigned_to=? OR f.created_by=? ORDER BY f.completed ASC,f.follow_up_date ASC,f.follow_up_time ASC");
$stmt->execute([$user['name'], $user['name']]);
$allFollowUps = $stmt->fetchAll();
foreach ($allFollowUps as &$_fu) { $_fu['completed'] = (int)$_fu['completed']; }
unset($_fu);

// Inquiry list for "add" dropdown (id + client only)
$inqList = $pdo->query("SELECT id,client,company FROM inquiries ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reminders — Survey Pacific</title>
<link rel="icon" type="image/png" href="image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="assets/app.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
<style>
  html,body{height:100%;overflow:hidden;}
  *{font-family:'Inter',system-ui,sans-serif;box-sizing:border-box;}
  [x-cloak]{display:none!important;}
  @keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
  @keyframes slideInRight{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
  @keyframes spin{to{transform:rotate(360deg)}}
  .anim-fade{animation:fadeIn 0.2s ease}
  .toast-enter{animation:slideInRight 0.25s ease}
  .spin{animation:spin 1s linear infinite}
  ::-webkit-scrollbar{width:6px;height:6px}
  ::-webkit-scrollbar-track{background:transparent}
  ::-webkit-scrollbar-thumb{background:#D1D9E6;border-radius:3px}
  select{-webkit-appearance:none;appearance:none}
</style>
</head>
<body class="h-full flex" style="background:#F4F6F8;font-family:'Inter',system-ui,sans-serif"
      x-data="reminderApp()" x-init="init()">

<!-- Toast -->
<div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
  <template x-for="t in toasts" :key="t.id">
    <div class="pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-[10px] text-[12px] font-semibold shadow-lg border min-w-[220px] max-w-[320px] toast-enter"
         :class="t.type==='success'?'bg-[#ECFDF3] text-[#16803C] border-[#BBF7D0]':t.type==='error'?'bg-[#FEF3F2] text-[#B42318] border-[#FECDCA]':'bg-white text-[#172B3A] border-[#E4E7EC]'">
      <span x-text="t.msg" class="flex-1"></span>
      <button @click="toasts=toasts.filter(x=>x.id!==t.id)" class="opacity-50 hover:opacity-100">✕</button>
    </div>
  </template>
</div>

<!-- Mobile backdrop -->
<div x-show="_mob" @click="_mob=false" class="fixed inset-0 z-30 md:hidden" style="background:rgba(0,0,0,0.45)"></div>

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="h-full flex flex-col bg-white border-r border-[#E4E7EC] shrink-0 fixed md:relative inset-y-0 left-0 z-40 w-[220px]"
       :class="_mob ? 'translate-x-0 shadow-xl md:shadow-none' : '-translate-x-full md:translate-x-0'" style="transition:transform 0.2s">

  <div class="border-b border-[#E4E7EC] px-4 py-4">
    <svg width="160" height="34" viewBox="0 0 300 52" fill="none"><circle cx="26" cy="26" r="22" stroke="#1D3461" stroke-width="3.2"/><circle cx="26" cy="26" r="9" fill="#1D3461"/><text x="58" y="31" font-family="'Inter',system-ui" font-size="28" font-weight="700" fill="#1D3461" letter-spacing="-0.3">Survey Pacific</text><text x="59" y="46" font-family="'Inter',system-ui" font-size="10.5" font-weight="400" fill="#1D3461" letter-spacing="1.4">Global Research Execution Partner</text></svg>
  </div>

  <nav class="flex-1 py-2">
    <a href="index.php" class="w-full flex items-center gap-3 px-4 py-2.5 text-[12px] font-semibold text-[#344054] hover:bg-[#F9FAFB] transition-colors">
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="index.php" class="w-full flex items-center gap-3 px-4 py-2.5 text-[12px] font-semibold text-[#344054] hover:bg-[#F9FAFB] transition-colors">
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      My Tasks
    </a>
    <!-- Reminders (active) -->
    <span class="w-full flex items-center gap-3 px-4 py-2.5 text-[12px] font-semibold bg-[#FEF3F2] text-[#B42318] relative">
      <span class="absolute left-0 top-1 bottom-1 w-[3px] bg-[#B42318] rounded-r-full"></span>
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Reminders
    </span>
    <?php if (is_admin()): ?>
    <a href="index.php" class="w-full flex items-center gap-3 px-4 py-2.5 text-[12px] font-semibold text-[#344054] hover:bg-[#F9FAFB] transition-colors">
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
      Approvals
    </a>
    <?php endif; ?>
  </nav>

  <div class="border-t border-[#E4E7EC] p-3">
    <div class="flex items-center gap-2.5 mb-2.5">
      <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center shrink-0">
        <span class="text-[10px] font-extrabold text-[#175CD3]"><?= strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $user['name']), 0, 2)))) ?></span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-[12px] font-semibold text-[#172B3A] truncate"><?= htmlspecialchars($user['name']) ?></div>
        <div class="text-[10px] text-[#667085]"><?= htmlspecialchars($user['role']) ?></div>
      </div>
    </div>
    <a href="api/logout.php" class="w-full flex items-center gap-2 text-[11px] font-semibold text-[#667085] hover:text-[#B42318] transition-colors rounded-[6px] px-2 py-1.5 hover:bg-[#FEF3F2]">
      <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ═══ MAIN ═══════════════════════════════════════════════════════════════════ -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

  <!-- Topbar -->
  <div class="bg-white border-b border-[#E4E7EC] flex items-center justify-between px-3 sm:px-6 shrink-0" style="height:56px">
    <div class="flex items-center gap-3">
      <button @click="_mob=!_mob" class="md:hidden text-[#667085]">
        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-[#B42318]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h1 class="text-[18px] font-bold text-[#071D2B]">Reminders</h1>
      </div>
    </div>
    <button @click="addOpen=true" class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
      <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Follow-up
    </button>
  </div>

  <!-- Content -->
  <div class="flex-1 overflow-y-auto p-3 sm:p-6" style="-webkit-overflow-scrolling:touch">

    <!-- Filter tabs -->
    <div class="flex items-center gap-1 mb-4 bg-white border border-[#E4E7EC] rounded-[10px] p-1 w-fit" style="box-shadow:0 1px 3px rgba(7,29,43,0.04)">
      <button @click="tableFilter='pending'"
              :class="tableFilter==='pending'?'bg-[#172B3A] text-white':'text-[#667085] hover:text-[#344054]'"
              class="text-[11px] font-bold px-3.5 py-1.5 rounded-[7px] transition-colors">
        Pending (<span x-text="followUps.filter(f=>!f.completed).length"></span>)
      </button>
      <button @click="tableFilter='completed'"
              :class="tableFilter==='completed'?'bg-[#172B3A] text-white':'text-[#667085] hover:text-[#344054]'"
              class="text-[11px] font-bold px-3.5 py-1.5 rounded-[7px] transition-colors">
        Completed (<span x-text="completed.length"></span>)
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-auto" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
      <table class="w-full min-w-[640px] text-[12px] border-collapse">
        <thead>
          <tr style="background:#F9FAFB;border-bottom:1px solid #E4E7EC">
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085] whitespace-nowrap">Status</th>
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085] whitespace-nowrap">Date / Time</th>
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085] whitespace-nowrap">Inquiry</th>
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085]">Client</th>
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085]">Note</th>
            <th class="px-4 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-[0.06em] text-[#667085] whitespace-nowrap">Assigned To</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </thead>
        <tbody>
          <template x-for="fu in tableRows" :key="fu.id">
            <tr style="border-bottom:1px solid #F2F4F7"
                :style="fuStatus(fu)==='overdue'?'background:#FFF5F5':fuStatus(fu)==='today'?'background:#EFF6FF':''">
              <td class="px-4 py-2.5 whitespace-nowrap">
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                      :class="fuStatus(fu)==='overdue'?'bg-[#FEF3F2] text-[#B42318]':fuStatus(fu)==='today'?'bg-[#EEF4FF] text-[#175CD3]':fuStatus(fu)==='upcoming'?'bg-[#F2F4F7] text-[#667085]':'bg-[#ECFDF3] text-[#16803C]'"
                      x-text="fuStatus(fu)==='overdue'?'Overdue':fuStatus(fu)==='today'?'Today':fuStatus(fu)==='upcoming'?'Upcoming':'Done'"></span>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="text-[12px] font-semibold text-[#344054]" x-text="fu.follow_up_date"></div>
                <div x-show="fu.follow_up_time" class="text-[10px] text-[#667085]" x-text="fuTimeLabel(fu.follow_up_time)"></div>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <a :href="'index.php?open='+fu.inquiry_id" class="text-[12px] font-extrabold text-[#1268F3] hover:underline" x-text="fu.inquiry_id"></a>
              </td>
              <td class="px-4 py-2.5">
                <div class="text-[12px] font-semibold text-[#172B3A]" x-text="fu.client||'—'"></div>
                <div class="text-[10px] text-[#667085]" x-text="fu.company"></div>
              </td>
              <td class="px-4 py-2.5 max-w-[240px]">
                <div class="text-[12px] text-[#344054] leading-snug" :class="fu.completed?'line-through text-[#B8CCED]':''" x-text="fu.note"></div>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="text-[12px] text-[#344054]" x-text="fu.assigned_to"></div>
              </td>
              <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="flex items-center gap-1">
                  <button x-show="!fu.completed" @click="complete(fu)"
                          class="text-[10px] font-bold px-2.5 py-1 rounded-[6px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">✓</button>
                  <span x-show="fu.completed" class="text-[#16803C] text-[13px] px-1">✔</span>
                  <button @click="del(fu)"
                          class="text-[10px] font-bold px-2 py-1 rounded-[6px] text-[#98A2B3] hover:text-[#B42318] hover:bg-[#FEF3F2] transition-colors">✕</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <div x-show="tableRows.length===0" class="py-12 text-center">
        <div class="text-[13px] font-semibold text-[#344054] mb-1">No follow-ups found</div>
        <div class="text-[12px] text-[#667085]">Try a different filter or add a follow-up.</div>
      </div>
    </div>

  </div>
</div>

<!-- ═══ ADD FOLLOW-UP MODAL ═══════════════════════════════════════════════════ -->
<div x-show="addOpen" x-cloak @click.self="addOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[460px]" style="box-shadow:0 20px 60px rgba(7,29,43,0.18)">
    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px]" style="background:#071D2B">
      <h2 class="text-[16px] font-bold text-white">Schedule Follow-up</h2>
      <button @click="addOpen=false" class="text-xl hover:text-white transition-colors" style="color:rgba(255,255,255,0.45)">✕</button>
    </div>
    <div class="p-5 space-y-3">
      <div>
        <label class="text-[11px] font-bold uppercase tracking-[0.06em] text-[#667085] block mb-1">Inquiry *</label>
        <div class="relative">
          <select x-model="addForm.inquiryId" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 pr-8 py-2 bg-white focus:outline-none focus:border-[#1268F3]" style="-webkit-appearance:none">
            <option value="">Select inquiry…</option>
            <?php foreach ($inqList as $iq): ?>
            <option value="<?= htmlspecialchars($iq['id']) ?>"><?= htmlspecialchars($iq['id'].' · '.$iq['client'].' · '.$iq['company']) ?></option>
            <?php endforeach; ?>
          </select>
          <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div>
        <label class="text-[11px] font-bold uppercase tracking-[0.06em] text-[#667085] block mb-1">Note *</label>
        <input x-model="addForm.note" type="text" placeholder="e.g. Call client for proposal feedback"
               class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3]">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[11px] font-bold uppercase tracking-[0.06em] text-[#667085] block mb-1">Date *</label>
          <input x-model="addForm.date" type="date" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3]">
        </div>
        <div>
          <label class="text-[11px] font-bold uppercase tracking-[0.06em] text-[#667085] block mb-1">Time</label>
          <input x-model="addForm.time" type="time" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 focus:outline-none focus:border-[#1268F3]">
        </div>
      </div>
      <div>
        <label class="text-[11px] font-bold uppercase tracking-[0.06em] text-[#667085] block mb-1">Assigned To</label>
        <div class="relative">
          <select x-model="addForm.assignedTo" class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 pr-8 py-2 bg-white focus:outline-none focus:border-[#1268F3]" style="-webkit-appearance:none">
            <option value="">Me (<?= htmlspecialchars($user['name']) ?>)</option>
            <?php foreach ($accounts as $a): if ($a['status'] !== 'approved') continue; ?>
            <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <svg class="w-3 h-3 absolute right-2.5 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
      </div>
      <div x-show="addErr" class="text-[11px] text-[#B42318] font-semibold" x-text="addErr"></div>
    </div>
    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 rounded-b-[14px]">
      <button @click="addOpen=false" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Cancel</button>
      <button @click="add()" class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors">Schedule</button>
    </div>
  </div>
</div>

<script>
function reminderApp() {
  return {
    followUps: <?= json_encode($allFollowUps, JSON_HEX_TAG) ?>,
    currentUser: <?= json_encode($user['name'], JSON_HEX_TAG) ?>,
    isAdmin: <?= is_admin() ? 'true' : 'false' ?>,
    toasts: [],
    _mob: false,
    addOpen: false,
    tableFilter: 'pending',
    addErr: '',
    addForm: { inquiryId:'', note:'', date: new Date().toLocaleDateString('en-CA'), time:'', assignedTo:'' },

    init() {},

    get todayStr()   { return new Date().toISOString().slice(0,10); },
    get overdue()    { return this.followUps.filter(f=>!f.completed && f.follow_up_date <  this.todayStr); },
    get todayFu()    { return this.followUps.filter(f=>!f.completed && f.follow_up_date === this.todayStr); },
    get upcoming()   { return this.followUps.filter(f=>!f.completed && f.follow_up_date >  this.todayStr); },
    get completed()  { return this.followUps.filter(f=> f.completed); },
    get tableRows()  {
      if (this.tableFilter==='pending')   return this.followUps.filter(f=>!f.completed);
      if (this.tableFilter==='completed') return this.completed;
      return this.followUps;
    },

    fuStatus(fu) {
      if (fu.completed) return 'done';
      if (fu.follow_up_date < this.todayStr)  return 'overdue';
      if (fu.follow_up_date === this.todayStr) return 'today';
      return 'upcoming';
    },

    async complete(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'complete',id:fu.id})}).then(r=>r.json());
      if (r.ok) { fu.completed=1; fu.completed_by=this.currentUser; this.toast('Done!','success'); }
    },

    async del(fu) {
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:fu.id})}).then(r=>r.json());
      if (r.ok) { this.followUps=this.followUps.filter(f=>f.id!==fu.id); }
    },

    async add() {
      this.addErr = '';
      if (!this.addForm.inquiryId) { this.addErr='Select an inquiry.'; return; }
      if (!this.addForm.note.trim()) { this.addErr='Note is required.'; return; }
      if (!this.addForm.date) { this.addErr='Date is required.'; return; }
      const body = { action:'add', inquiryId:this.addForm.inquiryId, note:this.addForm.note, date:this.addForm.date, time:this.addForm.time||null, assignedTo:this.addForm.assignedTo||this.currentUser };
      const r = await fetch('api/followup.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());
      if (r.ok) {
        this.followUps.push({id:r.id,inquiry_id:this.addForm.inquiryId,note:this.addForm.note,follow_up_date:this.addForm.date,follow_up_time:this.addForm.time||null,assigned_to:this.addForm.assignedTo||this.currentUser,client:'',company:'',completed:0,created_by:this.currentUser});
        this.addOpen=false;
        this.addForm={inquiryId:'',note:'',date:new Date().toLocaleDateString('en-CA'),time:'',assignedTo:''};
        this.toast('Follow-up scheduled','success');
      } else this.addErr=r.message||'Error';
    },

    fuTimeLabel(t) {
      if (!t) return '';
      const [h,m]=t.split(':').map(Number);
      return ((h%12)||12)+':'+(m<10?'0':'')+m+' '+(h>=12?'PM':'AM');
    },

    toast(msg,type='info') {
      const id=Date.now();
      this.toasts.push({id,msg,type});
      setTimeout(()=>{ this.toasts=this.toasts.filter(t=>t.id!==id); },3500);
    },
  };
}
</script>
</body>
</html>
