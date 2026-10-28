const assert = require('assert');

// Mirrors _haystack + the search branch of the `filtered` getter in index.php.
// stripHtml uses the DOM in the browser; here a tag strip is equivalent for plain markup.
const app = {
  search: '',
  stripHtml(html) { return String(html || '').replace(/<[^>]*>/g, ' '); },
  _haystack(inq) {
    const steps = inq.steps || [];
    return [
      inq.id, inq.date, inq.created_at, inq.inquiry_type, inq.client, inq.designation,
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
  get hits() {
    const terms = this.search ? this.search.toLowerCase().split(/\s+/).filter(Boolean) : null;
    return this.inquiries.filter(inq => {
      if (!terms) return true;
      const hay = this._haystack(inq);
      return terms.every(t => hay.includes(t));
    }).map(i => i.id);
  },
};

app.inquiries = [
  {
    id:'SP-2026-001', date:'05 Aug 2026', created_at:'2026-08-05 09:10:00',
    client:'Rahul Mehta', company:'Acme Ltd', country:'India', designation:'Head of Research',
    created_by:'Asha', current_owner:'Asha', stage:'Proposal Making', outcome:'In Progress',
    requirement:'<p>Need a <b>CATI</b> study for rural markets</p>',
    email:'rahul@acme.example', phone:'9876543210', website:'acme.example',
    proposal_value:'45000', due_date:'20 Aug 2026',
    steps:[{ assigned_to:'Ravi', assigned_by:'Asha', status:'In Progress', instruction:'<p>Draft the questionnaire</p>' }],
  },
  {
    id:'SP-2026-002', date:'12 Aug 2026', created_at:'2026-08-12 14:00:00',
    client:'Priya Nair', company:'Novus Research', country:'Singapore',
    created_by:'Ravi', current_owner:'Meera', stage:'Proposal Sent', outcome:'In Progress',
    requirement:'Online panel, 500 completes', email:'priya@novus.example',
    admin_remark:'Client wants a discount', team_remark:'Sent revised pricing', team_remark_by:'Meera',
    steps:[],
  },
  {
    id:'SP-2026-003', date:'02 Sep 2026', created_at:'2026-09-02 10:00:00',
    client:'Internal', company:'Internal', inquiry_type:'Internal Usage',
    created_by:'Meera', current_owner:'Meera', stage:'Inquiry', outcome:'In Progress',
    requirement:'Refresh the sample frame', steps:[],
  },
];

const find = q => { app.search = q; return app.hits; };

// Fields that already worked keep working
assert.deepStrictEqual(find('SP-2026-002'), ['SP-2026-002'], 'by id');
assert.deepStrictEqual(find('acme'), ['SP-2026-001'], 'by company');
assert.deepStrictEqual(find('priya'), ['SP-2026-002'], 'by client');

// Newly searchable
assert.deepStrictEqual(find('cati'), ['SP-2026-001'], 'by requirement, HTML tags stripped');
assert.deepStrictEqual(find('asha'), ['SP-2026-001'], 'by user name (created_by / owner)');
assert.deepStrictEqual(find('ravi'), ['SP-2026-001','SP-2026-002'], 'name matches step assignee too');
assert.deepStrictEqual(find('questionnaire'), ['SP-2026-001'], 'by task instruction');
assert.deepStrictEqual(find('aug 2026'), ['SP-2026-001','SP-2026-002'], "by the displayed 'd M Y' date");
assert.deepStrictEqual(find('2026-09'), ['SP-2026-003'], 'by created_at, the machine date format');
assert.deepStrictEqual(find('9876543210'), ['SP-2026-001'], 'by phone');
assert.deepStrictEqual(find('rahul@acme.example'), ['SP-2026-001'], 'by email');
assert.deepStrictEqual(find('singapore'), ['SP-2026-002'], 'by country');
assert.deepStrictEqual(find('proposal sent'), ['SP-2026-002'], 'by stage');
assert.deepStrictEqual(find('45000'), ['SP-2026-001'], 'by proposal value');
assert.deepStrictEqual(find('discount'), ['SP-2026-002'], 'by admin remark');
assert.deepStrictEqual(find('head of research'), ['SP-2026-001'], 'by designation');
assert.deepStrictEqual(find('internal usage'), ['SP-2026-003'], 'by inquiry type');

// Multi-word is AND across the whole record, not a phrase match
assert.deepStrictEqual(find('asha proposal'), ['SP-2026-001'], 'terms may come from different fields');
assert.deepStrictEqual(find('proposal asha'), ['SP-2026-001'], 'order does not matter');
assert.deepStrictEqual(find('asha singapore'), [], 'all terms must match, not any');

// Housekeeping
assert.deepStrictEqual(find(''), ['SP-2026-001','SP-2026-002','SP-2026-003'], 'empty search = everything');
assert.deepStrictEqual(find('   '), ['SP-2026-001','SP-2026-002','SP-2026-003'], 'whitespace only = everything');
assert.deepStrictEqual(find('ACME'), ['SP-2026-001'], 'case insensitive');
assert.deepStrictEqual(find('zzz'), [], 'no match');
// Missing fields must not crash or leak "undefined" into the haystack
assert.ok(!app._haystack(app.inquiries[2]).includes('undefined'), 'absent fields are dropped, not stringified');
assert.deepStrictEqual(find('undefined'), [], 'searching "undefined" matches nothing');

console.log('search-test: all passed');
