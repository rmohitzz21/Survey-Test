const assert = require('assert');

// Mirrors teamTaskState / teamTaskPill / teamTasksOf / myTeamTasks in index.php.
// due_ts arrives from PHP in SECONDS (includes/functions.php due_ts()); _now is in MS.
// Getting that unit mismatch wrong is what makes every task read "Overdue" forever.
const app = {
  currentUserId: 7,
  teamShowDone: false,
  myWorkFilter: 'open',
  _now: Date.UTC(2026, 8, 22, 12, 0, 0),   // 22 Sep 2026, noon
  teams: [],
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
  _teamByDue(a, b) { return (a.due_ts ?? Infinity) - (b.due_ts ?? Infinity); },
  _teamMySort(a, b) {
    const rank = { overdue: 0, progress: 1, pending: 2, done: 3 };
    const d = rank[this.teamTaskState(a)] - rank[this.teamTaskState(b)];
    return d || this._teamByDue(a, b);
  },
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
  get myDoneTeamTasks() {
    return this.teams.reduce((n, t) =>
      n + t.tasks.filter(k => k.assigned_to === this.currentUserId && k.status === 'Done').length, 0);
  },
  teamRoster(team) {
    return [...team.members].sort((a, b) =>
      (Number(b.account_id) === team.lead_id) - (Number(a.account_id) === team.lead_id));
  },
  accounts: [],
  teamMemberSearch: '',
  get teamCandidates() {
    return this.accounts.filter(a => a.status === 'approved' && a.role !== 'Client');
  },
  get teamPickList() {
    const q = this.teamMemberSearch.trim().toLowerCase();
    if (!q) return this.teamCandidates;
    return this.teamCandidates.filter(a => (a.name + ' ' + a.role).toLowerCase().includes(q));
  },
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
  teamTaskColor(t) {
    return { done: '#16803C', overdue: '#B42318', progress: '#1268F3', pending: '#D1D9E6' }[this.teamTaskState(t)];
  },
  teamSelected: 0,
  get selectedTeam() {
    return this.teams.find(t => t.id === this.teamSelected) || null;
  },
  teamOpenFor(team, accountId) {
    if (!team) return 0;
    return team.tasks.filter(t => t.assigned_to === Number(accountId) && t.status !== 'Done').length;
  },
};

const sec = (...a) => Date.UTC(...a) / 1000;

app.teams = [
  { id: 1, name: 'Fieldwork QC', tasks: [
    { id: 11, assigned_to: 7, status: 'Pending',     due_ts: sec(2026, 8, 25, 12), due: '25 Sep 2026', is_late: false }, // future
    { id: 12, assigned_to: 7, status: 'Pending',     due_ts: sec(2026, 8, 20, 12), due: '20 Sep 2026', is_late: false }, // past → overdue
    { id: 13, assigned_to: 9, status: 'In Progress', due_ts: sec(2026, 8, 23, 12), due: '23 Sep 2026', is_late: false }, // someone else
    { id: 14, assigned_to: 7, status: 'Done',        due_ts: sec(2026, 8, 19, 12), due: '19 Sep 2026', is_late: true  },
  ]},
  { id: 2, name: 'Scripting', tasks: [
    { id: 21, assigned_to: 7, status: 'In Progress', due_ts: null,                 due: '',           is_late: false }, // TBD
    { id: 22, assigned_to: 7, status: 'Done',        due_ts: sec(2026, 8, 21, 12), due: '21 Sep 2026', is_late: false },
  ]},
];

// ── State: a past due is overdue, a future one is not, Done never is ──
const state = id => app.teamTaskState([].concat(...app.teams.map(t => t.tasks)).find(t => t.id === id));
assert.strictEqual(state(11), 'pending');
assert.strictEqual(state(12), 'overdue');
assert.strictEqual(state(13), 'progress');
assert.strictEqual(state(14), 'done');   // late AND past due, but finished — never "Overdue"
assert.strictEqual(state(21), 'progress'); // TBD has no due moment, so it cannot be overdue

// ── Pills: late completion is visibly different from on-time ──
assert.strictEqual(app.teamTaskPill({ status:'Done', is_late:true  }).text, 'Done · late');
assert.strictEqual(app.teamTaskPill({ status:'Done', is_late:false }).text, 'Done');

// ── My tasks (open): overdue first, then in progress, then pending by due ──
assert.deepStrictEqual(app.myTeamTasks.map(t => t.id), [12, 21, 11]);
assert.strictEqual(app.myTeamTasks[0]._team, 'Fieldwork QC');

// ── Chip counts stay stable while the list follows the active filter ──
assert.strictEqual(app.myOpenTeamTasks, 3);
assert.strictEqual(app.myOverdueTeamTasks, 1);
assert.strictEqual(app.myDoneTeamTasks, 2);
assert.strictEqual(app.myOpenTeamTasks, app.myTeamTasks.length); // open filter lists every open task

app.myWorkFilter = 'overdue';
assert.deepStrictEqual(app.myTeamTasks.map(t => t.id), [12]);
app.myWorkFilter = 'done';
assert.deepStrictEqual(app.myTeamTasks.map(t => t.id), [14, 22]); // soonest was-due first
app.myWorkFilter = 'open';

// ── Relative due labels ──
assert.strictEqual(app.teamDueLabel(app.teams[0].tasks[1]), '2 days late · 20 Sep 2026'); // id 12
assert.strictEqual(app.teamDueLabel(app.teams[0].tasks[0]), 'Due · 25 Sep 2026');         // id 11
assert.strictEqual(app.teamDueLabel(app.teams[1].tasks[0]), 'No due date');               // id 21
assert.strictEqual(app.teamDueLabel(app.teams[0].tasks[3]), 'Was due · 19 Sep 2026');     // id 14 Done

// ── A team board shows everyone's tasks, not just mine ──
assert.deepStrictEqual(app.teamTasksOf(app.teams[0]).map(t => t.id), [12, 13, 11]);

// ── Card counts: overdue is a subset of open, Done is never open ──
const counts = s => ({ open: s.open, overdue: s.overdue, done: s.done, total: s.total });
assert.deepStrictEqual(counts(app.teamStats(app.teams[0])), { open: 3, overdue: 1, done: 1, total: 4 });
assert.deepStrictEqual(counts(app.teamStats(app.teams[1])), { open: 1, overdue: 0, done: 1, total: 2 });
assert.deepStrictEqual(counts(app.teamStats({ tasks: [] })), { open: 0, overdue: 0, done: 0, total: 0 });

// ── The card's progress bar: three segments that fill the track exactly once ──
const bar = app.teamStats(app.teams[0]).pct;
assert.deepStrictEqual(bar, { done: 25, overdue: 25, open: 50 });
assert.strictEqual(bar.done + bar.overdue + bar.open, 100);
assert.deepStrictEqual(app.teamStats({ tasks: [] }).pct, { done: 0, overdue: 0, open: 0 });

// ── Row accent colour tracks the same state the pill shows ──
const byId = id => [].concat(...app.teams.map(t => t.tasks)).find(t => t.id === id);
assert.strictEqual(app.teamTaskColor(byId(12)), '#B42318'); // overdue
assert.strictEqual(app.teamTaskColor(byId(14)), '#16803C'); // done
assert.strictEqual(app.teamTaskColor(byId(13)), '#1268F3'); // in progress
assert.strictEqual(app.teamTaskColor(byId(11)), '#D1D9E6'); // pending

// ── The board follows the selected card, and survives a team disappearing ──
assert.strictEqual(app.selectedTeam, null);
app.teamSelected = 2;
assert.strictEqual(app.selectedTeam.name, 'Scripting');
app.teamSelected = 999;
assert.strictEqual(app.selectedTeam, null);
app.teamSelected = 1;

// ── Per-member load ──
assert.strictEqual(app.teamOpenFor(app.teams[0], 7), 2);
assert.strictEqual(app.teamOpenFor(app.teams[0], '9'), 1);
assert.strictEqual(app.teamOpenFor(app.teams[0], 99), 0);
assert.strictEqual(app.teamOpenFor(null, 7), 0);

// ── Member picker ──
app.accounts = [
  { id: 7, name: 'Mohit',  role: 'Admin',      status: 'approved' },
  { id: 9, name: 'Asha',   role: 'Researcher', status: 'approved' },
  { id: 3, name: 'Pending Pete', role: 'Staff', status: 'pending' },
  { id: 4, name: 'Acme Ltd', role: 'Client',   status: 'approved' },
];
assert.deepStrictEqual(app.teamPickList.map(a => a.id), [7, 9]);
app.teamMemberSearch = ' ASH ';
assert.deepStrictEqual(app.teamPickList.map(a => a.id), [9]);
app.teamMemberSearch = 'researcher';
assert.deepStrictEqual(app.teamPickList.map(a => a.id), [9]);
app.teamMemberSearch = 'zzz';
assert.deepStrictEqual(app.teamPickList, []);

// ── Board empty state when every task is Done ──
const allDone = { id: 3, name: 'Wrapped', lead_id: 7, tasks: [
  { id: 31, assigned_to: 7, status: 'Done', due_ts: sec(2026, 8, 18, 12), is_late: false },
]};
app.teamShowDone = false;
assert.strictEqual(allDone.tasks.length > 0, true);
assert.strictEqual(app.teamTasksOf(allDone).length, 0);
assert.deepStrictEqual(counts(app.teamStats(allDone)), { open: 0, overdue: 0, done: 1, total: 1 });
assert.deepStrictEqual(app.teamStats(allDone).pct, { done: 100, overdue: 0, open: 0 });
app.teamShowDone = true;
assert.strictEqual(app.teamTasksOf(allDone).length, 1);
app.teamShowDone = false;

// ── Chip counts vs empty filter ──
assert.strictEqual(app.myDoneTeamTasks, 2);
assert.strictEqual(app.myOpenTeamTasks, 3);
assert.strictEqual(Object.create(app, { teams: { value: [] } }).myDoneTeamTasks, 0);

// ── Roster puts the lead first ──
const team = { lead_id: 9, members: [
  { account_id: '7', name: 'Mohit' }, { account_id: '9', name: 'Asha' }, { account_id: '4', name: 'Ben' },
]};
assert.deepStrictEqual(app.teamRoster(team).map(m => m.name), ['Asha', 'Mohit', 'Ben']);
assert.deepStrictEqual(team.members.map(m => m.name), ['Mohit', 'Asha', 'Ben']);

console.log('teams-test: all assertions passed');
