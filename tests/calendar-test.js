const assert = require('assert');

const app = {
  _now: new Date('2026-08-28 12:00').getTime(),
  calMonth: '2026-08',
  calDate: '',
  visibleInquiries: [],
  inquiries: [],
  rmFollowUps: [],
  calUser: '',
  todayStr: '2026-08-28',

  _inqBelongsTo(inq, name) {
    return inq.current_owner===name || inq.created_by===name
        || (inq.steps||[]).some(s => s.assigned_to===name || s.assigned_by===name);
  },
  _fuBelongsTo(fu, name) { return fu.assigned_to===name || fu.created_by===name; },
  get calSrcInquiries() {
    return this.calUser ? this.visibleInquiries.filter(i => this._inqBelongsTo(i, this.calUser)) : this.visibleInquiries;
  },
  get calSrcFollowUps() {
    return this.calUser ? this.rmFollowUps.filter(f => this._fuBelongsTo(f, this.calUser)) : this.rmFollowUps;
  },

  _inqDateKey(inq) {
    if (inq.created_at) return String(inq.created_at).slice(0,10);
    const d = new Date(inq.date);
    return isNaN(d) ? '' : d.toLocaleDateString('en-CA');
  },
  get calCounts() {
    const m = {};
    this.calSrcInquiries.forEach(i => { const k = this._inqDateKey(i); if (k) m[k] = (m[k]||0) + 1; });
    return m;
  },
  get calFuCounts() {
    const m = {};
    this.calSrcFollowUps.forEach(f => { if (!f.completed && f.follow_up_date) m[f.follow_up_date] = (m[f.follow_up_date]||0) + 1; });
    return m;
  },
  get calCells() {
    const [y, mo] = this.calMonth.split('-').map(Number);
    const counts  = this.calCounts;
    const fu      = this.calFuCounts;
    const cells   = Array.from({ length: new Date(y, mo-1, 1).getDay() }, () => null);
    for (let d = 1; d <= new Date(y, mo, 0).getDate(); d++) {
      const key = this.calMonth + '-' + String(d).padStart(2,'0');
      cells.push({ key, day: d, count: counts[key] || 0, fu: fu[key] || 0 });
    }
    return cells;
  },
  get calFollowUps() {
    const prefix = this.calDate || this.calMonth;
    const key = f => f.follow_up_date + ' ' + (f.follow_up_time || '23:59');
    return this.calSrcFollowUps.filter(f => String(f.follow_up_date||'').startsWith(prefix))
               .sort((a,b) => (a.completed - b.completed) || key(a).localeCompare(key(b)));
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
  get calInquiries() {
    const prefix = this.calDate || this.calMonth;
    const key = i => String(i.created_at || this._inqDateKey(i));
    return this.calSrcInquiries.filter(i => this._inqDateKey(i).startsWith(prefix))
               .sort((a,b) => key(b).localeCompare(key(a)));
  },
  get calMonthTotal() {
    return this.calSrcInquiries.filter(i => this._inqDateKey(i).startsWith(this.calMonth)).length;
  },
  calShift(n) {
    const [y, mo] = this.calMonth.split('-').map(Number);
    const d = new Date(y, mo-1+n, 1);
    this.calMonth = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
  },
  inqMonth: '2026-08',
  _autoMonth: '2026-08',
  _rollMonth(nowMonth) {
    if (nowMonth === this._autoMonth) return;
    if (this.inqMonth === this._autoMonth) this.inqMonth = nowMonth;
    if (this.calMonth === this._autoMonth) { this.calMonth = nowMonth; this.calDate = ''; }
    this._autoMonth = nowMonth;
  },
  // mirrors the :class binding on .cal-cell — a day that created inquiries goes green
  calGreen(c) { return c.count > 0; },
  isClosed(i) {
    return ['Payment Received','Inquiry Closed','Lost','Cancelled'].includes(i.outcome)
        || ['Payment Received','Project Closed','Proposal Lost','Closure'].includes(i.stage);
  },
  dueTs(inq) {
    if (!inq.due_date || inq.due_date === 'TBD') return null;
    const t = new Date(inq.due_date + ' ' + (inq.due_time || '23:59')).getTime();
    return isNaN(t) ? null : t;
  },
  dueStatus(inq) {
    if (this.isClosed(inq)) return 'completed';
    const ts = this.dueTs(inq);
    if (ts === null) return 'none';
    return ts < this._now ? 'overdue' : 'pending';
  },
  isOverdue(inq) { return this.dueStatus(inq) === 'overdue'; },
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
  fuTimeLabel(t) { const [h,m] = t.split(':').map(Number); return ((h%12)||12)+':'+(m<10?'0':'')+m+' '+(h>=12?'PM':'AM'); },
  dueLabel(inq) {
    if (!inq.due_date || inq.due_date === 'TBD') return 'TBD';
    return inq.due_date + (inq.due_time ? ' · ' + this.fuTimeLabel(inq.due_time) : '');
  },
};

const inq = o => ({ outcome:'In Progress', stage:'Inquiry', ...o });

// ── Calendar ──
app.visibleInquiries = [
  inq({ created_at:'2026-08-05 09:10:00' }),
  inq({ created_at:'2026-08-05 17:40:00' }),   // multiple on same date
  inq({ created_at:'2026-08-31 23:59:00' }),   // last day of month
  inq({ created_at:'2026-07-14 08:00:00' }),   // other month
  inq({ date:'03 Aug 2026' }),                 // no created_at → falls back to date
];
const cells = app.calCells;
assert.strictEqual(cells.length, 6 + 31, '1 Aug 2026 is a Saturday → 6 leading blanks');
assert.strictEqual(cells.filter(c=>c===null).length, 6);
const by = Object.fromEntries(cells.filter(Boolean).map(c => [c.key, c.count]));
assert.strictEqual(by['2026-08-05'], 2, 'two inquiries on 5 Aug → green');
assert.strictEqual(by['2026-08-03'], 1, 'date fallback works → green');
assert.strictEqual(by['2026-08-31'], 0 + 1, 'last day of month counted');
assert.strictEqual(by['2026-08-06'], 0, 'empty date → plain box, no marker');
assert.strictEqual(by['2026-07-14'], undefined, 'other month not in this grid');
assert.strictEqual(cells.filter(Boolean).length, 31, 'August has 31 cells');

// The day list under the calendar: selected day, else the whole displayed month
assert.strictEqual(app.calMonthTotal, 4, 'Aug 2026 total (5 inquiries, one is July)');
assert.strictEqual(app.calInquiries.length, 4, 'no day picked → whole month');
assert.deepStrictEqual(
  app.calInquiries.map(i => i.created_at || i.date),
  ['2026-08-31 23:59:00','2026-08-05 17:40:00','2026-08-05 09:10:00','03 Aug 2026'],
  'newest created first; date-only fallback sorts last in its day');
app.calDate = '2026-08-05';
assert.strictEqual(app.calInquiries.length, 2, 'picked day → only that day');
assert.strictEqual(app.calInquiries[0].created_at, '2026-08-05 17:40:00', 'same day → later time first');
assert.strictEqual(app.calMonthTotal, 4, 'month total ignores the day pick');
// Locale decides the ordering (same as the app's other date labels) — assert the parts
assert.ok(['Wed','05','Aug','2026'].every(p => app.calDateLabel.includes(p)), 'day label: '+app.calDateLabel);
app.calDate = '2026-08-06';
assert.strictEqual(app.calInquiries.length, 0, 'empty day → empty list');
app.calDate = '';
assert.strictEqual(app.calDateLabel, '');

// ── Follow-ups on the calendar ──
app.rmFollowUps = [
  { id:1, follow_up_date:'2026-08-05', follow_up_time:'15:00', completed:0 },
  { id:2, follow_up_date:'2026-08-05', follow_up_time:'09:30', completed:0 },
  { id:3, follow_up_date:'2026-08-05', follow_up_time:'08:00', completed:1 }, // done → not counted, sorts last
  { id:4, follow_up_date:'2026-08-20', follow_up_time:'',      completed:0 }, // no time → end of day
  { id:5, follow_up_date:'2026-09-02', follow_up_time:'10:00', completed:0 }, // other month
];
const fuCells = Object.fromEntries(app.calCells.filter(Boolean).map(c => [c.key, c.fu]));
assert.strictEqual(fuCells['2026-08-05'], 2, 'completed follow-ups do not mark the day');
assert.strictEqual(fuCells['2026-08-20'], 1, 'timeless follow-up still marks the day');
assert.strictEqual(fuCells['2026-08-06'], 0);
assert.deepStrictEqual(app.calFollowUps.map(f=>f.id), [2,1,4,3], 'month: open first by date+time, done last');
app.calDate = '2026-08-05';
assert.deepStrictEqual(app.calFollowUps.map(f=>f.id), [2,1,3], 'picked day only');
app.calDate = '2026-08-06';
assert.deepStrictEqual(app.calFollowUps, [], 'day with none');
app.calDate = '';

// ── Admin "whose calendar" picker ──
app.visibleInquiries = [
  inq({ created_at:'2026-08-10 09:00:00', created_by:'Asha',  current_owner:'Asha' }),
  inq({ created_at:'2026-08-10 11:00:00', created_by:'Ravi',  current_owner:'Ravi' }),
  inq({ created_at:'2026-08-11 09:00:00', created_by:'Ravi',  current_owner:'Asha' }), // owner counts too
  inq({ created_at:'2026-08-12 09:00:00', created_by:'Ravi',  current_owner:'Ravi',
        steps:[{ assigned_to:'Asha', assigned_by:'Ravi' }] }),                          // step assignee counts
];
app.rmFollowUps = [
  { id:11, follow_up_date:'2026-08-10', follow_up_time:'10:00', completed:0, assigned_to:'Asha', created_by:'Ravi' },
  { id:12, follow_up_date:'2026-08-10', follow_up_time:'12:00', completed:0, assigned_to:'Ravi', created_by:'Ravi' },
];
assert.strictEqual(app.calMonthTotal, 4, 'no picker → whole team');
assert.strictEqual(app.calFollowUps.length, 2, 'no picker → every follow-up');
app.calUser = 'Asha';
assert.strictEqual(app.calMonthTotal, 3, 'Asha: creator, owner, and step assignee');
assert.deepStrictEqual(app.calFollowUps.map(f=>f.id), [11], 'Asha is the assignee on 11 only');
assert.strictEqual(Object.fromEntries(app.calCells.filter(Boolean).map(c=>[c.key,c.count]))['2026-08-10'], 1,
  'day cells honour the picker');
assert.strictEqual(Object.fromEntries(app.calCells.filter(Boolean).map(c=>[c.key,c.fu]))['2026-08-10'], 1);
app.calUser = 'Ravi';
assert.strictEqual(app.calMonthTotal, 3, 'Ravi created three of them');
assert.deepStrictEqual(app.calFollowUps.map(f=>f.id), [11,12], 'Ravi created both follow-ups');
app.calUser = '';

// Month navigation, including year rollover
assert.strictEqual(app.calMonthLabel, 'August 2026');
app.calShift(1);  assert.strictEqual(app.calMonth, '2026-09');
assert.strictEqual(app.calCells.filter(Boolean).length, 30, 'September has 30 days');
assert.ok(app.calCells.filter(Boolean).every(c => c.count === 0), 'empty month → no green boxes');
app.calShift(-1); assert.strictEqual(app.calMonth, '2026-08');
app.calMonth = '2026-01'; app.calShift(-1); assert.strictEqual(app.calMonth, '2025-12');
app.calShift(1);  assert.strictEqual(app.calMonth, '2026-01');
app.calMonth = '2026-02';
assert.strictEqual(app.calCells.filter(Boolean).length, 28, 'Feb 2026 = 28 days');

// ── Due status ──
assert.strictEqual(app.dueStatus(inq({ due_date:'30 Aug 2026', due_time:'09:00' })), 'pending');
assert.strictEqual(app.dueStatus(inq({ due_date:'28 Aug 2026', due_time:'18:00' })), 'pending', 'later today');
assert.strictEqual(app.dueStatus(inq({ due_date:'28 Aug 2026', due_time:'09:00' })), 'overdue', 'earlier today');
assert.strictEqual(app.dueStatus(inq({ due_date:'28 Aug 2026' })), 'pending', 'no time = end of day');
assert.strictEqual(app.dueStatus(inq({ due_date:'27 Aug 2026' })), 'overdue');
assert.strictEqual(app.dueStatus(inq({ due_date:'01 Aug 2026', outcome:'Payment Received' })), 'completed');
assert.strictEqual(app.dueStatus(inq({ due_date:'01 Aug 2026', stage:'Closure' })), 'completed');
assert.strictEqual(app.dueStatus(inq({ due_date:'TBD' })), 'none');
assert.strictEqual(app.dueStatus(inq({})), 'none');

// dueCountdown — _now is 28 Aug 2026 12:00
assert.strictEqual(app.dueCountdown(inq({ due_date:'28 Aug 2026', due_time:'18:00' })), 'Pending', 'not late yet = no countdown');
assert.strictEqual(app.dueCountdown(inq({ due_date:'31 Aug 2026', due_time:'12:00' })), 'Pending');
assert.strictEqual(app.dueCountdown(inq({ due_date:'28 Aug 2026', due_time:'11:15' })), '45m overdue');
assert.strictEqual(app.dueCountdown(inq({ due_date:'28 Aug 2026', due_time:'09:00' })), '3h overdue');
assert.strictEqual(app.dueCountdown(inq({ due_date:'27 Aug 2026', due_time:'12:00' })), '1 day overdue');
assert.strictEqual(app.dueCountdown(inq({ due_date:'26 Aug 2026', due_time:'12:00' })), '2 days overdue');
assert.strictEqual(app.dueCountdown(inq({ due_date:'28 Aug 2026', due_time:'12:00' })), 'Pending', 'due exactly now is not late yet');
assert.strictEqual(app.dueCountdown(inq({ due_date:'01 Aug 2026', stage:'Closure' })), 'Completed');
assert.strictEqual(app.dueCountdown(inq({ due_date:'TBD' })), '');

// Workflow step due — one string, time shown only when its AM/PM survived
const stepDueDate = step => String(step.due || '').split('·')[0].trim();
const stepDueTime = step => {
  const t = (String(step.due || '').split('·')[1] || '').trim();
  return /^\d{1,2}:\d{2}\s*[AP]M$/i.test(t) ? t.toUpperCase() : '';
};
assert.strictEqual(stepDueDate({ due:'01 Sep 2026 · 02:30 PM' }), '01 Sep 2026');
assert.strictEqual(stepDueTime({ due:'01 Sep 2026 · 02:30 PM' }), '02:30 PM');
assert.strictEqual(stepDueTime({ due:'01 Sep 2026 · 09:05 am' }), '09:05 AM');
assert.strictEqual(stepDueTime({ due:'01 Sep 2026 · 02:30 ' }), '', 'truncated meridiem hidden');
assert.strictEqual(stepDueTime({ due:'01 Sep 2026' }), '');
assert.strictEqual(stepDueTime({ due:'TBD' }), '');

// My Tasks report grouping — buckets follow stepDueState, order is fixed, empty groups vanish
const stepDueState = step => {
  if (!step.due || step.due === 'TBD') return 'none';
  if (step.overdue) return 'overdue';
  const d = new Date(stepDueDate(step));
  if (isNaN(d)) return 'none';
  return d.toLocaleDateString('en-CA') === app.todayStr ? 'today' : 'upcoming';
};
const myTaskGroups = tasks => ['overdue','today','upcoming','none']
  .map(k => ({ key:k, items:tasks.filter(t => stepDueState(t.step) === k) }))
  .filter(g => g.items.length);

const tasks = [
  { step:{ due:'27 Aug 2026 · 02:00 PM', overdue:true } },
  { step:{ due:'28 Aug 2026 · 05:00 PM' } },
  { step:{ due:'30 Aug 2026' } },
  { step:{ due:'TBD' } },
  { step:{ due:'28 Aug 2026 · 09:00 AM' } },
];
assert.deepStrictEqual(myTaskGroups(tasks).map(g => [g.key, g.items.length]),
  [['overdue',1],['today',2],['upcoming',1],['none',1]], 'urgent group first, count per heading');
assert.deepStrictEqual(myTaskGroups([{ step:{ due:'30 Aug 2026' } }]).map(g => g.key), ['upcoming'],
  'empty groups render no heading');
assert.deepStrictEqual(myTaskGroups([]).length, 0, 'no tasks, no headings');
assert.strictEqual(app.isOverdue(inq({ due_date:'01 Aug 2026', outcome:'Lost' })), false, 'closed never overdue');

assert.strictEqual(app.dueLabel(inq({ due_date:'28 Aug 2026', due_time:'14:30' })), '28 Aug 2026 · 2:30 PM');
assert.strictEqual(app.dueLabel(inq({ due_date:'28 Aug 2026', due_time:'00:05' })), '28 Aug 2026 · 12:05 AM');
assert.strictEqual(app.dueLabel(inq({ due_date:'28 Aug 2026' })), '28 Aug 2026');
assert.strictEqual(app.dueLabel(inq({ due_date:'TBD' })), 'TBD');

// ── Green day boxes: a day with any inquiry created goes green, everything else does not ──
app.calMonth = '2026-08'; app.calDate = ''; app.calUser = '';
app.visibleInquiries = [
  inq({ created_at:'2026-08-05 09:10:00' }),
  inq({ created_at:'2026-08-05 17:40:00' }),  // 2 on one day → still one green box
  inq({ date:'03 Aug 2026' }),                // no created_at → date fallback still greens the day
];
app.rmFollowUps = [{ id:99, follow_up_date:'2026-08-07', follow_up_time:'10:00', completed:0 }];
const green = app.calCells.filter(Boolean).filter(c => app.calGreen(c)).map(c => c.key);
assert.deepStrictEqual(green, ['2026-08-03','2026-08-05'], 'only days that created inquiries are green');
assert.strictEqual(app.calGreen(app.calCells.filter(Boolean).find(c => c.key==='2026-08-07')), false,
  'a follow-up alone does not green the day — green means "inquiries created"');

// ── Calendar follows the midnight rollover, same guard as the inquiry list ──
const cal = Object.create(Object.getPrototypeOf(app), Object.getOwnPropertyDescriptors(app));
cal.calMonth = '2026-08'; cal.inqMonth = '2026-08'; cal._autoMonth = '2026-08'; cal.calDate = '2026-08-28';
cal._rollMonth('2026-09');
assert.strictEqual(cal.calMonth, '2026-09', 'calendar rolls into the new month');
assert.strictEqual(cal.calDate, '', 'a day picked in the old month is cleared, not left off-grid');
assert.strictEqual(cal.inqMonth, '2026-09', 'inquiry list rolls with it');

// Browsing back through history is not stomped at midnight.
cal.calMonth = '2026-05';
cal._rollMonth('2026-10');
assert.strictEqual(cal.calMonth, '2026-05', 'a hand-picked calendar month stays put');
assert.strictEqual(cal.inqMonth, '2026-10', 'the list still rolls independently');

// ── My Tasks ──
// Mirrors tasksAssignedToMe / stepDueState / myTasks in index.php.
// defineProperties, not Object.assign — assign *reads* getters instead of copying them.
Object.defineProperties(app, Object.getOwnPropertyDescriptors({
  currentUser: 'Asha',
  get tasksAssignedToMe() {
    const out = [];
    this.inquiries.forEach(inq => {
      if (this.isClosed(inq)) return;
      (inq.steps || []).forEach(step => {
        if (step.assigned_to === this.currentUser && !['Done','Completed','Cancelled'].includes(step.status))
          out.push({ inq, step });
      });
    });
    return out;
  },
  stepDueState(step) {
    if (!step.due || step.due === 'TBD') return 'none';
    if (step.overdue) return 'overdue';
    const d = new Date(String(step.due).split(' · ')[0]);
    if (isNaN(d)) return 'none';
    return d.toLocaleDateString('en-CA') === this.todayStr ? 'today' : 'upcoming';
  },
  get myTasks() {
    const rank = { overdue:0, today:1, upcoming:2, none:3 };
    const ts = s => { const d = new Date(String(s.due||'').split(' · ')[0]); return isNaN(d) ? Infinity : d.getTime(); };
    return [...this.tasksAssignedToMe].sort((a,b) =>
      rank[this.stepDueState(a.step)] - rank[this.stepDueState(b.step)] || ts(a.step) - ts(b.step));
  },
}));

// today is 2026-08-28 in this fixture
const step = o => ({ assigned_to:'Asha', status:'In Progress', ...o });
app.inquiries = [
  { id:'OPEN-late',  outcome:'In Progress', stage:'Inquiry',
    steps:[step({ id:1, due:'20 Aug 2026', overdue:true })] },
  { id:'OPEN-today', outcome:'In Progress', stage:'Inquiry',
    steps:[step({ id:2, due:'28 Aug 2026', overdue:false })] },
  { id:'OPEN-later', outcome:'In Progress', stage:'Inquiry',
    steps:[step({ id:3, due:'05 Sep 2026', overdue:false })] },
  { id:'OPEN-tbd',   outcome:'In Progress', stage:'Inquiry',
    steps:[step({ id:4, due:'TBD', overdue:false })] },
  // The bug: a paid-off inquiry whose step was never marked Done
  { id:'CLOSED-paid', outcome:'Payment Received', stage:'Payment Received',
    steps:[step({ id:5, due:'10 Aug 2026', overdue:true })] },
  { id:'CLOSED-lost', outcome:'Lost', stage:'Proposal Lost',
    steps:[step({ id:6, due:'12 Aug 2026', overdue:true })] },
];

assert.deepStrictEqual(app.myTasks.map(t => t.inq.id),
  ['OPEN-late','OPEN-today','OPEN-later','OPEN-tbd'],
  'closed inquiries contribute no tasks, and the rest sort overdue → today → upcoming → no due');
assert.ok(!app.myTasks.some(t => ['CLOSED-paid','CLOSED-lost'].includes(t.inq.id)),
  'a completed inquiry never leaves an overdue task behind');

assert.strictEqual(app.stepDueState(app.inquiries[0].steps[0]), 'overdue');
assert.strictEqual(app.stepDueState(app.inquiries[1].steps[0]), 'today', 'due today is its own state, not "upcoming"');
assert.strictEqual(app.stepDueState(app.inquiries[2].steps[0]), 'upcoming');
assert.strictEqual(app.stepDueState(app.inquiries[3].steps[0]), 'none', 'TBD has no due state');

// Two overdue tasks: the one late the longest comes first.
app.inquiries = [
  { id:'A', outcome:'In Progress', stage:'Inquiry', steps:[step({ id:7, due:'25 Aug 2026', overdue:true })] },
  { id:'B', outcome:'In Progress', stage:'Inquiry', steps:[step({ id:8, due:'10 Aug 2026', overdue:true })] },
];
assert.deepStrictEqual(app.myTasks.map(t => t.inq.id), ['B','A'], 'oldest overdue first');

console.log('ok');
