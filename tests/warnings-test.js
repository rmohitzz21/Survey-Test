const assert = require('assert');

// Mirrors warningsWithNth / warnSummary in index.php and the $deduction closure in
// api/warnings.php — the "every 3rd warning costs ₹500" rule lives in all three.
const app = {
  warnStrike: 3,
  warnAmount: 500,
  warnUserFilter: '',
  warnings: [],
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
};

// Newest first, exactly as api/warnings.php returns them.
app.warnings = [
  { id:7, name:'Riya',  reason:'d' },
  { id:6, name:'Arjun', reason:'c' },
  { id:5, name:'Arjun', reason:'b' },
  { id:4, name:'Riya',  reason:'c' },
  { id:3, name:'Arjun', reason:'a' },
  { id:2, name:'Riya',  reason:'b' },
  { id:1, name:'Riya',  reason:'a' },
];

// ── Ordinals count from the oldest warning, per member ──
const nth = Object.fromEntries(app.warningsWithNth.map(r => [r.id, r._nth]));
assert.deepStrictEqual(nth, { 1:1, 2:2, 4:3, 7:4, 3:1, 5:2, 6:3 });

// ── The 3rd warning is the one that carries the deduction, and only it ──
const striking = app.warningsWithNth.filter(r => r._nth % app.warnStrike === 0).map(r => r.id).sort();
assert.deepStrictEqual(striking, [4, 6]); // Riya's 3rd, Arjun's 3rd — Riya's 4th does NOT re-charge

// ── Summary: 4 warnings still cost ₹500, 3 cost ₹500, sorted by count ──
assert.deepStrictEqual(app.warnSummary, [
  { name:'Riya',  count:4, deduction:500 },
  { name:'Arjun', count:3, deduction:500 },
]);

// ── 6 warnings cost ₹1000; 2 cost nothing ──
app.warnings = Array.from({length:6}, (_,i) => ({ id:i+1, name:'Riya' }));
assert.strictEqual(app.warnSummary[0].deduction, 1000);
app.warnings = [{ id:1, name:'Riya' }, { id:2, name:'Riya' }];
assert.strictEqual(app.warnSummary[0].deduction, 0);

// ── Filter narrows to one member and keeps their ordinals ──
app.warnings = [
  { id:3, name:'Arjun' }, { id:2, name:'Riya' }, { id:1, name:'Riya' },
];
app.warnUserFilter = 'Riya';
assert.deepStrictEqual(app.filteredWarnings.map(r => [r.id, r._nth]), [[2,2],[1,1]]);

// ── Level title cycles inside the strike window (4th warning → "Warning 1 of 3") ──
function levelLabel(nth, strike = 3) {
  const step = ((nth - 1) % strike) + 1;
  return 'Warning ' + step + ' of ' + strike;
}
assert.strictEqual(levelLabel(1), 'Warning 1 of 3');
assert.strictEqual(levelLabel(2), 'Warning 2 of 3');
assert.strictEqual(levelLabel(3), 'Warning 3 of 3');
assert.strictEqual(levelLabel(4), 'Warning 1 of 3');
assert.strictEqual(levelLabel(6), 'Warning 3 of 3');

console.log('warnings-test: all assertions passed');
