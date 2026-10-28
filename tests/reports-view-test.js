const assert = require('assert');

// Mirrors the arithmetic still behind the Reports view: which inquiries reach the Needs-attention
// list and in what order, and the bar chart's scale. Both live in Alpine getters, so this file is
// the only place they can be exercised without a browser.
//
// The KPI tile row was removed from the page, so its tests went with it — _rptKpis, rptKpiRow,
// rptDeltaClass and rptDeltaText no longer exist.

// Must match CLOSED_OUTCOMES / CLOSED_STAGES in includes/functions.php — index.php injects the
// real constants into JS, so only hand-copied mirrors like this one can drift. 'Completed',
// 'Project Lost' and the 'Cancelled' stage were missing here, the same drift that made
// admin/index.php badge a closed inquiry "Overdue".
const CLOSED_OUTCOMES = ['Payment Received', 'Inquiry Closed', 'Completed', 'Project Lost', 'Lost', 'Cancelled'];
const CLOSED_STAGES   = ['Payment Received', 'Project Closed', 'Proposal Lost', 'Closure', 'Cancelled'];
const NOW = Date.parse('2026-09-15 12:00');

const isClosed = i => CLOSED_OUTCOMES.includes(i.outcome) || CLOSED_STAGES.includes(i.stage);
const dueTs = i => {
  if (!i.due_date || i.due_date === 'TBD') return null;
  const t = Date.parse(i.due_date + ' ' + (i.due_time || '23:59'));
  return isNaN(t) ? null : t;
};
const isOverdue = i => !isClosed(i) && dueTs(i) !== null && dueTs(i) < NOW;

// An overdue inquiry is charged to whoever holds it, never to a task assignee who
// delivered on time. Empty `who` = whole team, so count everything.
const inqOwner    = i => i.current_owner || i.created_by || '';
const isOverdueFor = (i, who) => isOverdue(i) && (!who || inqOwner(i) === who);

const rows = [
  { id:1, stage:'Inquiry',          outcome:'', due_date:'2026-09-30' },                   // open, on track
  { id:2, stage:'Quotation',        outcome:'', due_date:'2026-09-10' },                   // open, overdue
  { id:3, stage:'Quotation',        outcome:'', due_date:'2026-09-15', due_time:'09:00' }, // overdue by time of day
  { id:4, stage:'Quotation',        outcome:'', due_date:'2026-09-15', due_time:'18:00' }, // still pending today
  { id:5, stage:'Payment Received', outcome:'', due_date:'2026-09-01', late:1 },           // closed late
  { id:6, stage:'Proposal', outcome:'Payment Received', due_date:'2026-09-20' },           // closed on time
  { id:7, stage:'Inquiry',          outcome:'', due_date:'TBD' },                          // no due date
  { id:8, stage:'Inquiry',          outcome:'', due_date:'' },
];

// ── Needs attention ──
// Worst-first ordering is load-bearing: this list replaced a separate aging chart, so it is the
// only thing now telling you which overdue inquiry is furthest gone.
const overdue = rows.filter(i => !isClosed(i) && isOverdue(i)).sort((a, b) => (dueTs(a) || 0) - (dueTs(b) || 0));
const late    = rows.filter(i => isClosed(i) && i.late);
const attention = [...overdue.map(i => ({ i, kind:'overdue' })), ...late.map(i => ({ i, kind:'late' }))];

assert.deepStrictEqual(overdue.map(i => i.id), [2, 3], 'overdue rows, most overdue first');
assert.ok(dueTs(overdue[0]) < dueTs(overdue[1]), 'strictly ordered by how long it has been due');
assert.strictEqual(overdue.length, 2, 'the due time of day counts — 09:00 today is already past');
assert.deepStrictEqual(late.map(i => i.id), [5], 'a late closure stays on the list');
assert.deepStrictEqual(attention.map(a => a.i.id), [2, 3, 5], 'overdue first, then closed-late');

// Closing late clears the live overdue flag, so a row must never appear under both labels.
assert.strictEqual(
  new Set(attention.map(a => a.i.id)).size, attention.length,
  'no inquiry is listed twice');

// Rows that must never reach the list at all.
assert.ok(!attention.some(a => a.i.id === 1), 'an inquiry inside its due date is not chased');
assert.ok(!attention.some(a => a.i.id === 4), 'a deadline later today is not yet missed');
assert.ok(!attention.some(a => a.i.id === 7), 'TBD is not a missed deadline, however old');
assert.ok(!attention.some(a => a.i.id === 8), 'neither is a blank due date');
assert.ok(!attention.some(a => a.i.id === 6), 'an inquiry closed on time is not chased');

// Empty period: the card swaps to the "nothing is past due" state rather than rendering nothing.
assert.strictEqual([].filter(i => isOverdue(i)).length, 0, 'an empty period yields an empty list, not an error');

// ── Snapshot summary (rptSummary) — strip above the charts ──
// Mirrors rptOwnedInquiries: inquiry performance numbers come from what the subject OWNS.
// `who` empty = team-wide (admin with nobody selected), so nothing is filtered out.
const ownedBy = (list, who) => who ? list.filter(i => inqOwner(i) === who) : list;

// `who` empty = team-wide; otherwise only the inquiries that person owns.
function summarise(list, now = NOW, who = '') {
  let open = 0, closed = 0, overdue = 0, lateClosed = 0, pending = 0;
  let overdueHrs = 0, pendingHrs = 0;
  ownedBy(list, who).forEach(i => {
    if (isClosed(i)) { closed++; if (i.late) lateClosed++; }
    else {
      open++;
      const dts = dueTs(i);
      if (isOverdue(i)) {
        overdue++;
        if (dts != null) overdueHrs += Math.max(0, (now - dts) / 3600000);
      } else {
        pending++;
        if (dts != null && dts > now) pendingHrs += (dts - now) / 3600000;
      }
    }
  });
  return {
    total: ownedBy(list, who).length, open, closed, overdue, pending, lateClosed,
    onTrack: pending,
    overdueHrs: Math.round(overdueHrs),
    pendingHrs: Math.round(pendingHrs),
  };
}
function fmtDuration(ms) {
  if (ms == null || !isFinite(ms) || ms < 0) return '—';
  const mins = Math.round(ms / 60000);
  if (mins < 60) return mins + 'm';
  const hrs = Math.round(ms / 3600000);
  if (hrs < 48) return hrs + 'h';
  const d = Math.floor(hrs / 24), h = hrs % 24;
  return h ? (d + 'd ' + h + 'h') : (d + 'd');
}
function avgCloseLabel(list) {
  let sum = 0, n = 0;
  list.forEach(i => {
    if (!isClosed(i) || !i.created_at || !i.closed_at) return;
    const a = Date.parse(String(i.created_at).replace(' ', 'T'));
    const b = Date.parse(String(i.closed_at).replace(' ', 'T'));
    if (!isNaN(a) && !isNaN(b) && b >= a) { sum += (b - a); n++; }
  });
  return n ? fmtDuration(sum / n) : '—';
}
const snap = summarise(rows);
assert.strictEqual(snap.total, 8, 'summary counts every inquiry in scope');
assert.strictEqual(snap.closed, 2, 'Payment Received stage + Payment Received outcome');
assert.strictEqual(snap.lateClosed, 1, 'only the late closure');
assert.strictEqual(snap.overdue, 2, 'ids 2 and 3 are still open past due');
assert.strictEqual(snap.pending, snap.open - snap.overdue, 'pending = open minus overdue');
assert.strictEqual(snap.onTrack, snap.pending, 'on track mirrors pending');
assert.ok(snap.overdueHrs > 0, 'overdue hours accumulate for open past-due rows');
assert.ok(snap.pendingHrs > 0, 'pending hours left accumulate for open not-yet-due rows');
assert.strictEqual(summarise([]).total, 0, 'empty period summary is zeros, not NaN');
assert.strictEqual(
  avgCloseLabel([
    { stage:'Payment Received', outcome:'', created_at:'2026-09-01 10:00:00', closed_at:'2026-09-03 10:00:00' },
    { stage:'Payment Received', outcome:'', created_at:'2026-09-01 10:00:00', closed_at:'2026-09-05 10:00:00' },
  ]),
  '3d',
  'avg close is mean created→closed duration');
assert.strictEqual(avgCloseLabel([{ stage:'Inquiry', outcome:'' }]), '—', 'no closures → dash');

// ── Overdue attribution: A creates, B delivers on time, A never closes ──
// The miss belongs to the inquiry's owner. B's own task was delivered inside its due
// date, so B's overdue count, overdue hours and needs-attention list must all stay empty
// even though B still sees the inquiry (they are a step party, so the server returns it).
const abInq = {
  id: 'SP-1', stage: 'Quotation', outcome: '',
  created_by: 'A', current_owner: 'A',          // handed back to A once B finished
  due_date: '2026-09-10',                        // owner's deadline, now blown
  steps: [{ assigned_to: 'B', assigned_by: 'A', overdue: false, late: false,
            status: 'Done', due: '09 Sep 2026 · 05:00 PM' }],
};
assert.ok(isOverdue(abInq), 'the inquiry itself is past due — that fact does not change');
assert.strictEqual(inqOwner(abInq), 'A', 'ownership returned to the creator when the last task closed');

assert.ok(isOverdueFor(abInq, 'A'), "it counts against A, who owes the closure");
assert.ok(!isOverdueFor(abInq, 'B'), "it does NOT count against B, who delivered on time");
assert.ok(isOverdueFor(abInq, ''), 'team-wide view still counts it once');

const aSnap = summarise([abInq], NOW, 'A');
const bSnap = summarise([abInq], NOW, 'B');
assert.strictEqual(aSnap.overdue, 1, "A's report shows the overdue inquiry");
assert.ok(aSnap.overdueHrs > 0, "A's overdue hours accumulate");
assert.strictEqual(aSnap.open, 1, "it is open work on A's plate");
assert.strictEqual(bSnap.overdue, 0, "B's report shows no overdue inquiry");
assert.strictEqual(bSnap.overdueHrs, 0, 'and charges B no overdue hours');
assert.strictEqual(bSnap.pending, 0, 'nor is it re-labelled "pending" for B');
assert.strictEqual(bSnap.open, 0, "it is not open work on B's plate — B owes nothing on it");
assert.strictEqual(bSnap.total, 0, "and it is not counted among B's own inquiries");
// Scoping by owner keeps the buckets adding up, for everyone.
for (const s of [aSnap, bSnap, summarise([abInq], NOW, '')]) {
  assert.strictEqual(s.open, s.overdue + s.pending, 'open === overdue + pending');
  assert.strictEqual(s.total, s.open + s.closed, 'total === open + closed');
}

// ── A closes it an hour late: the miss is A's "late closed", and B's stays empty ──
// This was the second half of the same bug — `lateClosed` had no owner check, so B's report
// showed A's late closure as B's. It must also never show up as overdue: closing ends the
// overdue state, late is the permanent record.
const abClosedLate = {
  ...abInq,
  stage: 'Closure', outcome: 'Inquiry Closed',
  late: 1, closed_at: '2026-09-10 18:00:00',   // due 2026-09-10 23:59 → closed next, an hour over
};
assert.ok(isClosed(abClosedLate), 'it is closed');
assert.ok(!isOverdue(abClosedLate), 'closing ends the overdue state — never both');

const aLate = summarise([abClosedLate], NOW, 'A');
const bLate = summarise([abClosedLate], NOW, 'B');
assert.strictEqual(aLate.lateClosed, 1, "A carries the late closure");
assert.strictEqual(aLate.overdue, 0, "and it is NOT also counted as overdue for A");
assert.strictEqual(bLate.lateClosed, 0, "B does not carry A's late closure");
assert.strictEqual(bLate.closed, 0, "nor A's closure at all");
assert.strictEqual(bLate.overdue, 0, 'and nothing overdue for B');
assert.strictEqual(summarise([abClosedLate], NOW, '').lateClosed, 1, 'admin sees it once, team-wide');

// The late-closed and needs-attention lists obey the same scoping.
const lateListFor = who => ownedBy([abClosedLate], who).filter(i => isClosed(i) && i.late).map(i => i.id);
assert.deepStrictEqual(lateListFor('A'), ['SP-1'], "it is on A's late-closed list");
assert.deepStrictEqual(lateListFor('B'), [], "and not on B's");

// B's task record stays clean: delivered before its own due moment.
assert.deepStrictEqual(taskOnTimeAB(abInq, 'B'), { tasks: 1, onTimePct: 100 }, "B is 100% on time");
function taskOnTimeAB(inq, who) {
  let tasks = 0, miss = 0;
  (inq.steps || []).forEach(s => {
    if (!s.assigned_to || (who && s.assigned_to !== who)) return;
    tasks++;
    if (s.overdue || s.late) miss++;
  });
  return { tasks, onTimePct: tasks ? Math.round((tasks - miss) / tasks * 100) : null };
}

// Needs-attention list and the dashboard card obey the same rule.
const attnFor = who => ownedBy([abInq], who).filter(i => !isClosed(i) && isOverdue(i)).map(i => i.id);
assert.deepStrictEqual(attnFor('A'), ['SP-1'], 'A is chased for it');
assert.deepStrictEqual(attnFor('B'), [], 'B is not chased for it');
assert.deepStrictEqual(attnFor(''), ['SP-1'], 'admin still sees it team-wide');

// Still unclosed and still nobody else's: reassigning a fresh task to B must not move the miss.
const abReassigned = { ...abInq, current_owner: 'B' };
assert.ok(isOverdueFor(abReassigned, 'B'),
  'if the inquiry is parked back with B, the open deadline is genuinely B\'s again');

// ── Performance pie slices (user report doughnut) ──
function perfPie(snap) {
  const closedOnTime = Math.max(0, (snap.closed || 0) - (snap.lateClosed || 0));
  const slices = [
    { key: 'ontime',  value: closedOnTime },
    { key: 'late',    value: snap.lateClosed || 0 },
    { key: 'pending', value: snap.pending || 0 },
    { key: 'overdue', value: snap.overdue || 0 },
  ].filter(x => x.value > 0);
  const total = slices.reduce((n, x) => n + x.value, 0);
  return { total, slices };
}
const pie = perfPie(snap);
assert.strictEqual(pie.total, snap.pending + snap.overdue + snap.closed, 'pie covers every inquiry once');
assert.ok(pie.slices.some(s => s.key === 'overdue' && s.value === 2), 'overdue slice present');
assert.ok(pie.slices.some(s => s.key === 'pending' && s.value === snap.pending), 'pending slice present');
assert.deepStrictEqual(perfPie({ closed:0, lateClosed:0, pending:0, overdue:0 }), { total:0, slices:[] }, 'empty period → empty pie');

// ── Volume bar scale (Chart.js uses this max implicitly; keep clamp math covered) ──
const chart = {
  series: [],
  get rptVolumeMax() {
    if (!this.series.length) return 1;
    return Math.max(1, ...this.series.map(d => Math.max(d.created, d.closed, d.overdue)));
  },
  get vmax() { return Math.max(4, Math.ceil(this.rptVolumeMax / 4) * 4); },
  h(n) { return Math.min(100, Math.max(0, n / this.vmax * 100)); },
  get axisLabels() { return [0,1,2,3,4].map(g => Math.round(this.vmax * (4 - g) / 4)); },
};

for (const max of [1, 2, 3, 5, 7, 9, 40, 137]) {
  chart.series = [{ created: max, closed: 0, overdue: 0 }];
  const labels = chart.axisLabels;
  assert.strictEqual(new Set(labels).size, 5, 'five distinct gridline labels at max=' + max);
  assert.ok(labels.every(Number.isInteger), 'gridline labels are whole inquiries at max=' + max);
  assert.deepStrictEqual(labels, [...labels].sort((a, b) => b - a), 'labels descend at max=' + max);
  assert.strictEqual(labels[4], 0, 'axis is zero-based at max=' + max);
  assert.ok(labels[0] >= max, 'top gridline clears the tallest bar at max=' + max);
}

chart.series = [{ created: 9, closed: 4, overdue: 1 }];
assert.strictEqual(chart.rptVolumeMax, 9, 'volume max uses the tallest of created/closed/overdue');
assert.strictEqual(chart.h(0), 0, 'an empty bucket draws no bar');
assert.ok(chart.h(9) <= 100, 'the tallest bar fits inside the plot');
assert.ok(chart.h(4) < chart.h(9), 'closed is drawn shorter than created when it is fewer');

chart.series = [{ created: 1, closed: 12, overdue: 0 }];
assert.strictEqual(chart.rptVolumeMax, 12, 'closed taller than created still sets the scale');
assert.strictEqual(chart.h(12), 100, 'a closed bar at the scale top fills the plot');

// Bucket mode: Auto follows the window; manual pick overrides.
function volumeMode(spanDays, pick) {
  if (pick && pick !== 'auto') return pick;
  return spanDays <= 14 ? 'day' : (spanDays <= 92 ? 'week' : (spanDays <= 370 ? 'month' : 'quarter'));
}
assert.strictEqual(volumeMode(7, 'auto'), 'day');
assert.strictEqual(volumeMode(30, 'auto'), 'week');
assert.strictEqual(volumeMode(120, 'auto'), 'month');
assert.strictEqual(volumeMode(400, 'auto'), 'quarter');
assert.strictEqual(volumeMode(7, 'month'), 'month', 'manual monthly overrides a short window');
assert.strictEqual(volumeMode(400, 'day'), 'day', 'manual daily overrides a long window');
assert.strictEqual(volumeMode(90, 'quarter'), 'quarter');
assert.strictEqual(volumeMode(90, 'year'), 'year');

// ── Stage donut: cumulative % segments must cover 0–100 without gaps ──
function stageDist(list) {
  const counts = {};
  list.forEach(i => { const s = i.stage || 'Other'; counts[s] = (counts[s] || 0) + 1; });
  const total = list.length;
  if (!total) return [];
  let acc = 0;
  return Object.keys(counts).sort().map(name => {
    const count = counts[name];
    const start = Math.round((acc / total) * 100);
    acc += count;
    const end = Math.round((acc / total) * 100);
    return { name, count, start, end, pct: Math.round((count / total) * 100) };
  });
}
const stages = stageDist(rows);
assert.strictEqual(stages[0].start, 0, 'donut starts at 0%');
assert.strictEqual(stages[stages.length - 1].end, 100, 'donut ends at 100%');
assert.strictEqual(stages.reduce((n, s) => n + s.count, 0), rows.length, 'every inquiry lands in a segment');
assert.deepStrictEqual(stageDist([]), [], 'empty period yields no donut segments');

// ── Due-day parse (weekly task chart) ──
function parseDay(raw) {
  if (!raw) return '';
  const s = String(raw).trim();
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
  const m = s.match(/(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(\d{4})/i);
  if (!m) return '';
  const months = {jan:0,feb:1,mar:2,apr:3,may:4,jun:5,jul:6,aug:7,sep:8,oct:9,nov:10,dec:11};
  const d = new Date(Number(m[3]), months[m[2].slice(0,3).toLowerCase()], Number(m[1]));
  return isNaN(d) ? '' : d.toLocaleDateString('en-CA');
}
assert.strictEqual(parseDay('2026-09-20 14:00:00'), '2026-09-20');
assert.strictEqual(parseDay('20 Sep 2026 · 02:30 PM'), '2026-09-20');
assert.strictEqual(parseDay(''), '');
assert.strictEqual(parseDay('TBD'), '');

// ── Task on-time % in the KPI strip ──
function taskOnTime(list, who) {
  let tasks = 0, miss = 0;
  list.forEach(i => (i.steps || []).forEach(s => {
    if (!s.assigned_to) return;
    if (who && s.assigned_to !== who) return;
    tasks++;
    if (s.overdue || s.late) miss++;
  }));
  return { tasks, onTimePct: tasks ? Math.round((tasks - miss) / tasks * 100) : null };
}
const withSteps = [
  { stage:'Inquiry', outcome:'', steps:[
    { assigned_to:'Asha', overdue:false, late:false },
    { assigned_to:'Asha', overdue:true,  late:false },
    { assigned_to:'Mohit', overdue:false, late:true },
  ]},
];
assert.deepStrictEqual(taskOnTime(withSteps), { tasks: 3, onTimePct: 33 });
assert.deepStrictEqual(taskOnTime(withSteps, 'Asha'), { tasks: 2, onTimePct: 50 });
assert.deepStrictEqual(taskOnTime([{ stage:'Inquiry', outcome:'', steps:[] }]), { tasks: 0, onTimePct: null });

// ── Member delivery stacked bars sum to 100 when there is work ──
function memberDelivery(m) {
  const onTime = Math.max(0, m.tasks - m.taskOD - m.taskLate);
  const total = onTime + m.taskLate + m.taskOD;
  const pct = n => total ? Math.round((n / total) * 100) : 0;
  return { onPct: pct(onTime), latePct: pct(m.taskLate), odPct: pct(m.taskOD), total };
}
const delivery = memberDelivery({ tasks: 10, taskOD: 2, taskLate: 1 });
assert.strictEqual(delivery.onPct + delivery.latePct + delivery.odPct, 100, 'delivery bar fills exactly once');
assert.strictEqual(memberDelivery({ tasks: 0, taskOD: 0, taskLate: 0 }).total, 0);

// ── Internal Usage late-start: must create before 11:30; 11:30+ is late ──
const OFFICE = 10 * 60 + 30; // 10:30
const LATE_CUT = 11 * 60 + 30; // 11:30
function clockMins(createdAt) {
  if (!createdAt) return null;
  const d = new Date(String(createdAt).replace(' ', 'T'));
  if (isNaN(d.getTime())) return null;
  return d.getHours() * 60 + d.getMinutes();
}
function isInternalLateStart(inq) {
  if ((inq.inquiry_type || '') !== 'Internal Usage') return false;
  const mins = clockMins(inq.created_at);
  return mins != null && mins >= LATE_CUT;
}
function bucketInternal(list) {
  let late = 0, onTime = 0, early = 0;
  list.forEach(i => {
    if ((i.inquiry_type || '') !== 'Internal Usage') return;
    const mins = clockMins(i.created_at);
    if (mins == null) return;
    if (mins >= LATE_CUT) late++;
    else if (mins < OFFICE) early++;
    else onTime++;
  });
  return { late, onTime, early };
}

const internalSamples = [
  { id:'a', inquiry_type:'Internal Usage', created_at:'2026-09-25 10:29:00' }, // early
  { id:'b', inquiry_type:'Internal Usage', created_at:'2026-09-25 10:30:00' }, // on time
  { id:'c', inquiry_type:'Internal Usage', created_at:'2026-09-25 11:29:00' }, // on time (before 11:30)
  { id:'d', inquiry_type:'Internal Usage', created_at:'2026-09-25 11:30:00' }, // late (exactly 11:30)
  { id:'e', inquiry_type:'Internal Usage', created_at:'2026-09-25 11:42:00' }, // late
  { id:'f', inquiry_type:'Internal Usage', created_at:'2026-09-25 17:51:00' }, // late
  { id:'g', inquiry_type:'Client Project', created_at:'2026-09-25 15:00:00' }, // ignored
];
assert.deepStrictEqual(bucketInternal(internalSamples), { late: 3, onTime: 2, early: 1 });
assert.ok(!isInternalLateStart(internalSamples[2]), '11:29 is still on time');
assert.ok(isInternalLateStart(internalSamples[3]), '11:30 counts as late');
assert.ok(isInternalLateStart(internalSamples[5]), 'afternoon Internal is late');
assert.ok(!isInternalLateStart(internalSamples[6]), 'Client Project never late-start');

console.log('reports-view-test: all passed');
