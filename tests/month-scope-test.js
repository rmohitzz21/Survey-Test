const assert = require('assert');

// Mirrors the monthInquiries getter in index.php — the month scoping of the portal.
const app = {
  todayStr: '2026-09-01',
  inqMonth: '2026-09',
  visibleInquiries: [],
  _inqDateKey(i) { return String(i.created_at).slice(0,10); },
  isClosed(i) { return ['Payment Received','Inquiry Closed','Lost','Cancelled'].includes(i.outcome)
                    || ['Payment Received','Project Closed','Proposal Lost','Closure'].includes(i.stage); },
  get monthInquiries() {
    if (!this.inqMonth) return this.visibleInquiries;
    return this.visibleInquiries.filter(i => this._inqDateKey(i).slice(0,7) === this.inqMonth);
  },
  _autoMonth: '2026-09',
  _rollMonth(nowMonth) {
    if (nowMonth === this._autoMonth) return;
    if (this.inqMonth === this._autoMonth) this.inqMonth = nowMonth;
    this._autoMonth = nowMonth;
  },
  get monthTabs() {
    const cur = this.todayStr.slice(0,7);
    const counts = { [cur]: 0 }, open = {};
    this.visibleInquiries.forEach(i => {
      const k = this._inqDateKey(i).slice(0,7);
      if (!k) return;
      counts[k] = (counts[k] || 0) + 1;
      if (!this.isClosed(i)) open[k] = (open[k] || 0) + 1;
    });
    return Object.keys(counts).sort().reverse().map(k => ({
      key: k,
      count: counts[k],
      open: open[k] || 0,
      label: new Date(+k.slice(0,4), +k.slice(5) - 1, 1).toLocaleDateString([], { month:'long', year:'numeric' }), // mirrors monthLabel()
    }));
  },
  get otherMonthsOpen() {
    if (!this.inqMonth) return 0;
    return this.monthTabs.reduce((n, m) => n + (m.key === this.inqMonth ? 0 : m.open), 0);
  },
};

app.visibleInquiries = [
  { id:'SP-1', created_at:'2026-09-01 09:00', stage:'Inquiry',        outcome:'' },
  { id:'SP-2', created_at:'2026-08-20 09:00', stage:'Quotation',      outcome:'' }, // last month, still open
  { id:'SP-3', created_at:'2026-08-05 09:00', stage:'Payment Received', outcome:'' }, // last month, closed
  { id:'SP-4', created_at:'2026-07-11 09:00', stage:'Inquiry',        outcome:'' },
];

const ids = () => app.monthInquiries.map(i => i.id);

assert.deepStrictEqual(ids(), ['SP-1'], 'new month starts clean — only what this month created');

app.inqMonth = '2026-08';
assert.deepStrictEqual(ids(), ['SP-2','SP-3'], 'past month = exactly that month, open ones included so they can be closed there');

app.inqMonth = '2026-07';
assert.deepStrictEqual(ids(), ['SP-4'], 'older month still reachable');

app.inqMonth = '';
assert.deepStrictEqual(ids(), ['SP-1','SP-2','SP-3','SP-4'], 'All months = everything');

// ── Month strip chips ──
app.inqMonth = '2026-09';
assert.deepStrictEqual(app.monthTabs.map(t => t.key), ['2026-09','2026-08','2026-07'], 'newest month first');

// Every chip's count must equal what clicking that chip actually shows.
for (const tab of app.monthTabs) {
  app.inqMonth = tab.key;
  assert.strictEqual(tab.count, app.monthInquiries.length, 'chip count matches list for ' + tab.key);
}

// A month with no rows at all still gets its row, so a fresh month is never an unreachable screen.
const empty = Object.create(Object.getPrototypeOf(app), Object.getOwnPropertyDescriptors(app));
empty.visibleInquiries = [];
assert.deepStrictEqual(empty.monthTabs.map(t => [t.key, t.count]), [['2026-09', 0]], 'current month lists even when empty');

// ── Leftover open work in other months must stay findable ──
app.inqMonth = '2026-09';
assert.deepStrictEqual(
  app.monthTabs.map(t => [t.key, t.count, t.open]),
  [['2026-09',1,1], ['2026-08',2,1], ['2026-07',1,1]],
  'per-month open counts (SP-3 is closed, so August shows 2 total / 1 open)');
assert.strictEqual(app.otherMonthsOpen, 2, 'September flags the 2 open inquiries parked in Aug + Jul');

app.inqMonth = '2026-08';
assert.strictEqual(app.otherMonthsOpen, 2, 'viewing August flags the Sep + Jul open ones');

// Closing August's leftover drops it out of the badge — the count is real, not decorative.
app.visibleInquiries.find(i => i.id === 'SP-2').outcome = 'Payment Received';
app.inqMonth = '2026-09';
assert.strictEqual(app.otherMonthsOpen, 1, 'closing SP-2 in August clears it from the flag');
assert.deepStrictEqual(app.monthInquiries.map(i => i.id), ['SP-1'], 'September is unaffected by August closures');
app.visibleInquiries.find(i => i.id === 'SP-2').outcome = '';

app.inqMonth = '';
assert.strictEqual(app.otherMonthsOpen, 0, 'All months shows everything, so nothing is "elsewhere"');

// ── Midnight rollover, month after month, including across a year boundary ──
const roller = Object.create(Object.getPrototypeOf(app), Object.getOwnPropertyDescriptors(app));
roller.inqMonth = '2026-09'; roller._autoMonth = '2026-09';
for (const m of ['2026-10','2026-11','2026-12','2027-01','2027-02']) {
  roller.todayStr = m + '-01';
  roller._rollMonth(m);
  assert.strictEqual(roller.inqMonth, m, 'portal follows the calendar into ' + m);
}

// A hand-picked month survives the rollover — the admin stays where they parked.
roller.inqMonth = '2026-08';
roller.todayStr = '2027-03-01'; roller._rollMonth('2027-03');
assert.strictEqual(roller.inqMonth, '2026-08', 'manual month is not stomped at midnight');
roller.todayStr = '2027-04-01'; roller._rollMonth('2027-04');
assert.strictEqual(roller.inqMonth, '2026-08', 'and stays put on later rollovers too');

// "All months" likewise survives.
roller.inqMonth = '';
roller.todayStr = '2027-05-01'; roller._rollMonth('2027-05');
assert.strictEqual(roller.inqMonth, '', 'All months is not stomped at midnight');

console.log('month-scope-test: all passed');
