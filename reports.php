<?php
/*
 * ══════════════════════════════════════════════════════════════════════════════
 *  REPORTS — cut from index.php, work in progress
 *
 *  To restore:
 *   1. HEAD        → add Chart.js CDN script (section A)
 *   2. SVG SPRITE  → add icon-download + icon-eye symbols (section B)
 *   3. NAV         → uncomment Reports nav item (section C)
 *   4. viewTitle   → add 'reports' key (section D)
 *   5. ALPINE DATA → add state vars (section E)
 *   6. init()      → add $watch lines (section F)
 *   7. HTML VIEW   → paste the reports div (section G)
 *   8. ALPINE JS   → paste Reports computed + methods block (section H)
 * ══════════════════════════════════════════════════════════════════════════════
 */
?>

<!-- ══ SECTION A — HEAD: Chart.js CDN (add after Alpine script tag) ═════════ -->
<!--
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
-->


<!-- ══ SECTION B — SVG SPRITE: add before closing </svg> of sprite ══════════ -->
<!--
  <symbol id="icon-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
  <symbol id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
-->


<!-- ══ SECTION C — NAV: Reports nav item (inside $navItems array) ════════════ -->
<!--
      ['id'=>'reports', 'label'=>'Reports', 'icon'=>'bar-chart-2'],
-->


<!-- ══ SECTION D — viewTitle: add 'reports' key ═════════════════════════════ -->
<!--
      return {dashboard:'Dashboard',tasks:'My Tasks',approvals:'User Management',reports:'Reports'}[this.view]||'';
-->


<!-- ══ SECTION E — ALPINE DATA: state vars (inside spApp return object) ═════ -->
<!--
    reportPeriod: '7',
    reportEmployee: '',
    rptTrendChart: null,
    rptDonutChart: null,
-->


<!-- ══ SECTION F — init(): $watch lines (inside init()) ═════════════════════ -->
<!--
      this.$watch('view', (v) => { this.search=''; this._mob=false; this.statFilter=''; if(v==='reports') this.$nextTick(()=>this.initReportCharts()); });
      this.$watch('reportPeriod', () => { if(this.view==='reports') this.$nextTick(()=>this.initReportCharts()); });
-->


<!-- ══ SECTION G — HTML VIEW: paste inside the content scroll div ════════════ -->

    <!-- ── REPORTS VIEW ───────────────────────────────────────────────────── -->
    <div x-show="view==='reports'" x-cloak class="space-y-4 anim-fade min-w-0">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0">
          <h2 class="text-[18px] sm:text-[20px] font-bold text-[#172B3A] truncate" x-text="isAdmin ? 'Reports Overview' : 'My Reports'"></h2>
          <p class="text-[11px] sm:text-[12px] text-[#667085] mt-0.5" x-text="isAdmin ? 'Organisation-wide inquiry analytics and team performance' : 'Your personal inquiry activity and performance metrics'"></p>
        </div>
        <div class="flex items-center gap-2 flex-wrap shrink-0">
          <select x-model="reportPeriod" class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
            <option value="3">Last 3 months</option>
            <option value="7">Last 7 months</option>
            <option value="12">Last 12 months</option>
            <option value="0">All time</option>
          </select>
          <template x-if="isAdmin">
            <select x-model="reportEmployee" @change="$nextTick(()=>initReportCharts())" class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer max-w-[140px]">
              <option value="">All Employees</option>
              <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
                <option :value="a.name" x-text="a.name"></option>
              </template>
            </select>
          </template>
          <button @click="exportReportCSV()" class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap">
            <svg class="w-3.5 h-3.5"><use href="#icon-download"/></svg> Export
          </button>
        </div>
      </div>

      <!-- Non-admin info banner -->
      <template x-if="!isAdmin">
        <div class="flex items-center gap-2 text-[11px] text-[#026AA2] bg-[#EFF8FF] border border-[#BAE6FD] rounded-[8px] px-3 py-2">
          <svg class="w-3.5 h-3.5 shrink-0"><use href="#icon-eye"/></svg>
          Showing only your assigned and created inquiries. Admins can view organisation-wide data.
        </div>
      </template>

      <!-- 4 Stat Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-3 sm:py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-[10px] bg-[#EEF4FF] flex items-center justify-center">
              <svg class="w-4 h-4 text-[#1268F3]"><use href="#icon-file-text"/></svg>
            </div>
            <span class="text-[10px] sm:text-[11px] font-bold" :class="reportStats.totalTrend>=0?'text-[#16803C]':'text-[#B42318]'" x-text="(reportStats.totalTrend>=0?'↑ ':'↓ ')+Math.abs(reportStats.totalTrend)+'%'"></span>
          </div>
          <div class="text-[22px] sm:text-[28px] font-bold text-[#172B3A] leading-none" x-text="reportStats.total"></div>
          <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mt-1.5" x-text="isAdmin ? 'TOTAL INQUIRIES' : 'MY INQUIRIES'"></div>
          <div class="text-[9px] sm:text-[10px] text-[#98A2B3] mt-0.5" x-text="'vs '+reportStats.prevTotal+' last period'"></div>
        </div>
        <div class="bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-3 sm:py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-[10px] bg-[#ECFDF3] flex items-center justify-center">
              <svg class="w-4 h-4 text-[#16803C]"><use href="#icon-check-circle"/></svg>
            </div>
            <span class="text-[10px] sm:text-[11px] font-bold" :class="reportStats.closedTrend>=0?'text-[#16803C]':'text-[#B42318]'" x-text="(reportStats.closedTrend>=0?'↑ ':'↓ ')+Math.abs(reportStats.closedTrend)+'%'"></span>
          </div>
          <div class="text-[22px] sm:text-[28px] font-bold text-[#172B3A] leading-none" x-text="reportStats.closed"></div>
          <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mt-1.5">CLOSED</div>
          <div class="text-[9px] sm:text-[10px] text-[#98A2B3] mt-0.5" x-text="reportStats.total>0?(Math.round(reportStats.closed/reportStats.total*100)+'% resolution rate'):'—'"></div>
        </div>
        <div class="bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-3 sm:py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-[10px] bg-[#EFF8FF] flex items-center justify-center">
              <svg class="w-4 h-4 text-[#026AA2]"><use href="#icon-clock"/></svg>
            </div>
            <span class="text-[10px] sm:text-[11px] font-bold" :class="reportStats.ipTrend<=0?'text-[#16803C]':'text-[#B42318]'" x-text="(reportStats.ipTrend<=0?'↓ ':'↑ ')+Math.abs(reportStats.ipTrend)+'%'"></span>
          </div>
          <div class="text-[22px] sm:text-[28px] font-bold text-[#172B3A] leading-none" x-text="reportStats.inProgress"></div>
          <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mt-1.5">IN PROGRESS</div>
          <div class="text-[9px] sm:text-[10px] text-[#98A2B3] mt-0.5" x-text="'Avg. '+reportStats.avgDays+' days open'"></div>
        </div>
        <div class="bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-3 sm:py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex items-center justify-between mb-2 sm:mb-3">
            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-[10px] bg-[#FEF3F2] flex items-center justify-center">
              <svg class="w-4 h-4 text-[#B42318]"><use href="#icon-alert-circle"/></svg>
            </div>
            <span class="text-[10px] sm:text-[11px] font-bold" :class="reportStats.overdueTrend>0?'text-[#B42318]':'text-[#16803C]'" x-text="(reportStats.overdueTrend>0?'↑ ':'↓ ')+Math.abs(reportStats.overdueTrend)+'%'"></span>
          </div>
          <div class="text-[22px] sm:text-[28px] font-bold leading-none" :class="reportStats.overdue>0?'text-[#B42318]':'text-[#172B3A]'" x-text="reportStats.overdue"></div>
          <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mt-1.5">OVERDUE</div>
          <div class="text-[9px] sm:text-[10px] text-[#98A2B3] mt-0.5" x-text="reportStats.total>0?(Math.round(reportStats.overdue/reportStats.total*100)+'% of total'):'—'"></div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Trend chart -->
        <div class="md:col-span-2 bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex items-center justify-between mb-1">
            <div class="text-[13px] font-bold text-[#172B3A]" x-text="isAdmin?'Inquiry Trends':'My Activity Trend'"></div>
            <span class="text-[11px] font-bold text-[#16803C] hidden sm:inline" x-show="reportStats.totalTrend>0" x-text="'↑ '+reportStats.totalTrend+'% vs last period'"></span>
          </div>
          <div class="text-[11px] text-[#667085] mb-3">Monthly volume by status</div>
          <div class="overflow-hidden" style="height:180px;position:relative"><canvas id="rpt-trend-chart"></canvas></div>
          <div class="flex items-center gap-4 mt-3 justify-center">
            <span class="flex items-center gap-1.5 text-[10px] text-[#667085]"><span class="inline-block w-3 h-0.5 rounded bg-[#1268F3]"></span>Total</span>
            <span class="flex items-center gap-1.5 text-[10px] text-[#667085]"><span class="inline-block w-3 h-0.5 rounded bg-[#16803C]"></span>Closed</span>
            <span class="flex items-center gap-1.5 text-[10px] text-[#667085]"><span class="inline-block w-3 h-0.5 rounded bg-[#B42318]"></span>Overdue</span>
          </div>
        </div>
        <!-- Right panel: Admin = donut, Employee = performance -->
        <div class="bg-white border border-[#E4E7EC] rounded-[12px] px-3 sm:px-5 py-4" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <template x-if="isAdmin">
            <div>
              <div class="text-[13px] font-bold text-[#172B3A]">Status Breakdown</div>
              <div class="text-[11px] text-[#667085] mb-3">Current period distribution</div>
              <div class="overflow-hidden" style="height:150px;position:relative"><canvas id="rpt-donut-chart"></canvas></div>
              <div class="space-y-2 mt-4">
                <div class="flex items-center justify-between text-[11px]">
                  <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#16803C] inline-block"></span>Closed</span>
                  <span class="font-bold text-[#172B3A]" x-text="reportStats.closed"></span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                  <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#1268F3] inline-block"></span>In Progress</span>
                  <span class="font-bold text-[#172B3A]" x-text="Math.max(0,reportStats.inProgress-reportStats.overdue)"></span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                  <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#B42318] inline-block"></span>Overdue</span>
                  <span class="font-bold text-[#172B3A]" x-text="reportStats.overdue"></span>
                </div>
              </div>
            </div>
          </template>
          <template x-if="!isAdmin">
            <div>
              <div class="text-[13px] font-bold text-[#172B3A]">Performance Summary</div>
              <div class="text-[11px] text-[#667085] mb-4">Current period snapshot</div>
              <div class="space-y-3.5">
                <div>
                  <div class="flex justify-between text-[11px] mb-1.5"><span class="text-[#344054]">Resolution Rate</span><span class="font-bold text-[#172B3A]" x-text="reportStats.resolutionRate+'%'"></span></div>
                  <div class="h-1.5 bg-[#F2F4F7] rounded-full"><div class="h-1.5 bg-[#1268F3] rounded-full transition-all" :style="'width:'+reportStats.resolutionRate+'%'"></div></div>
                </div>
                <div>
                  <div class="flex justify-between text-[11px] mb-1.5"><span class="text-[#344054]">Tasks Completed</span><span class="font-bold text-[#172B3A]" x-text="reportStats.taskCompletionRate+'%'"></span></div>
                  <div class="h-1.5 bg-[#F2F4F7] rounded-full"><div class="h-1.5 bg-[#16803C] rounded-full transition-all" :style="'width:'+reportStats.taskCompletionRate+'%'"></div></div>
                </div>
              </div>
              <div class="mt-5 pt-4 border-t border-[#E4E7EC]">
                <div class="text-[11px] font-bold text-[#172B3A] mb-3">Task Summary</div>
                <div class="grid grid-cols-3 gap-2 text-center">
                  <div class="bg-[#F8FAFC] rounded-[8px] px-2 py-2.5">
                    <div class="text-[18px] font-bold text-[#172B3A]" x-text="reportStats.tasksTotal"></div>
                    <div class="text-[9px] text-[#667085] mt-0.5">Assigned</div>
                  </div>
                  <div class="bg-[#ECFDF3] rounded-[8px] px-2 py-2.5">
                    <div class="text-[18px] font-bold text-[#16803C]" x-text="reportStats.tasksDone"></div>
                    <div class="text-[9px] text-[#16803C] mt-0.5">Completed</div>
                  </div>
                  <div class="bg-[#FFFAEB] rounded-[8px] px-2 py-2.5">
                    <div class="text-[18px] font-bold text-[#B54708]" x-text="reportStats.tasksPending"></div>
                    <div class="text-[9px] text-[#B54708] mt-0.5">Pending</div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Completed Inquiries History -->
      <div class="bg-white border border-[#E4E7EC] rounded-[12px]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="px-4 sm:px-5 py-3.5 border-b border-[#E4E7EC] flex items-center justify-between rounded-t-[12px]">
          <div>
            <div class="text-[13px] font-bold text-[#172B3A]">Completed Inquiry History</div>
            <div class="text-[11px] text-[#667085] mt-0.5" x-text="reportCompletedInquiries.length+' closed inquiries in this period'"></div>
          </div>
        </div>
        <div class="overflow-x-auto rounded-b-[12px]" style="-webkit-overflow-scrolling:touch">
          <table class="w-full text-[11px]" style="min-width:640px">
            <thead>
              <tr class="bg-[#F8FAFC] border-b border-[#E4E7EC]">
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">#</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">ID</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Date</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Client</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Company</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Stage</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Outcome</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Closed By</th>
                <th class="text-left px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Steps</th>
              </tr>
            </thead>
            <tbody>
              <template x-if="reportCompletedInquiries.length===0">
                <tr><td colspan="9" class="px-4 py-10 text-center text-[12px] text-[#667085]">No closed inquiries in this period</td></tr>
              </template>
              <template x-for="(inq,i) in reportCompletedInquiries" :key="inq.id">
                <tr class="border-b border-[#E4E7EC] last:border-0" :class="i%2===0?'bg-white':'bg-[#FAFBFC]'">
                  <td class="px-4 py-2.5 text-[#B8CCED] font-bold" x-text="i+1"></td>
                  <td class="px-4 py-2.5 font-bold text-[#071D2B] whitespace-nowrap" x-text="inq.id"></td>
                  <td class="px-4 py-2.5 text-[#667085] whitespace-nowrap" x-text="inq.date"></td>
                  <td class="px-4 py-2.5 font-semibold text-[#172B3A]" x-text="inq.client"></td>
                  <td class="px-4 py-2.5 text-[#667085] max-w-[120px]"><div class="truncate" x-text="inq.company"></div></td>
                  <td class="px-4 py-2.5 whitespace-nowrap"><span class="inline-flex items-center rounded-full font-extrabold px-2.5 py-[3px] text-[10px]" :class="stageClass(inq.stage)" x-text="inq.stage"></span></td>
                  <td class="px-4 py-2.5 whitespace-nowrap"><span class="inline-flex items-center rounded-full font-extrabold px-2 py-0.5 text-[9px]" :class="outcomeClass(inq.outcome)" x-text="inq.outcome"></span></td>
                  <td class="px-4 py-2.5 text-[#344054] whitespace-nowrap" x-text="inq.current_owner"></td>
                  <td class="px-4 py-2.5 whitespace-nowrap">
                    <span class="font-bold text-[#16803C]" x-text="(inq.steps||[]).filter(s=>s.status==='Done').length"></span>
                    <span class="text-[#B8CCED]" x-text="'/'+(inq.steps||[]).length"></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- ── END REPORTS VIEW ────────────────────────────────────────────────── -->


<?php /* ══ SECTION H — ALPINE JS: Reports computed + methods block ══════════

    // ── Reports ──
    _isClosed(i) {
      return ['Payment Received','Inquiry Closed','Completed'].includes(i.outcome)
          || ['Lost','Cancelled','No Reply'].includes(i.outcome)
          || ['Payment Received','Project Closed','Proposal Lost'].includes(i.stage);
    },
    _reportPeriodFilter(inqs) {
      const n = parseInt(this.reportPeriod);
      if (!n) return inqs;
      const cutoff = new Date(); cutoff.setMonth(cutoff.getMonth()-n);
      return inqs.filter(i => { const d=new Date(i.date||i.created_at); return !isNaN(d)&&d>=cutoff; });
    },
    _reportPrevPeriod(inqs) {
      const n = parseInt(this.reportPeriod);
      if (!n) return [];
      const end = new Date(); end.setMonth(end.getMonth()-n);
      const start = new Date(end); start.setMonth(start.getMonth()-n);
      return inqs.filter(i => { const d=new Date(i.date||i.created_at); return !isNaN(d)&&d>=start&&d<end; });
    },
    get reportInquiries() {
      let v = this.isAdmin ? this.inquiries : this.visibleInquiries;
      if (this.isAdmin && this.reportEmployee)
        v = v.filter(i => i.created_by===this.reportEmployee || i.current_owner===this.reportEmployee || (i.steps||[]).some(s=>s.assigned_to===this.reportEmployee));
      return v;
    },
    get reportCompletedInquiries() {
      return this._reportPeriodFilter(this.reportInquiries).filter(i=>this._isClosed(i));
    },
    get reportStats() {
      const all = this.reportInquiries;
      const period = this._reportPeriodFilter(all);
      const prev   = this._reportPrevPeriod(all);
      const closed    = period.filter(i=>this._isClosed(i)).length;
      const total     = period.length;
      const inProgress = period.filter(i=>!this._isClosed(i)).length;
      const overdue   = period.filter(i=>i.overdue&&!this._isClosed(i)).length;
      const prevClosed = prev.filter(i=>this._isClosed(i)).length;
      const prevTotal  = prev.length;
      const prevIp     = prev.filter(i=>!this._isClosed(i)).length;
      const prevOd     = prev.filter(i=>i.overdue&&!this._isClosed(i)).length;
      const trendPct   = (cur,prv) => prv===0 ? (cur>0?100:0) : Math.round((cur-prv)/prv*100);
      const openDays = period.filter(i=>!this._isClosed(i)).map(i=>{const d=new Date(i.date||i.created_at);return isNaN(d)?0:Math.round((Date.now()-d)/86400000);});
      const avgDays  = openDays.length ? Math.round(openDays.reduce((a,b)=>a+b,0)/openDays.length) : 0;
      const target = this.isAdmin && this.reportEmployee ? this.reportEmployee : (!this.isAdmin ? this.currentUser : null);
      const allSteps = [];
      this.reportInquiries.forEach(i=>(i.steps||[]).forEach(s=>{ if(!target||s.assigned_to===target) allSteps.push(s); }));
      const tasksDone    = allSteps.filter(s=>s.status==='Done').length;
      const tasksPending = allSteps.filter(s=>!['Done','Cancelled'].includes(s.status)).length;
      return {
        total, closed, inProgress, overdue, prevTotal,
        totalTrend:  trendPct(total,prevTotal),
        closedTrend: trendPct(closed,prevClosed),
        ipTrend:     trendPct(inProgress,prevIp),
        overdueTrend:trendPct(overdue,prevOd),
        resolutionRate: total>0?Math.round(closed/total*100):0,
        avgDays,
        tasksTotal:    allSteps.length,
        tasksDone,
        tasksPending,
        taskCompletionRate: allSteps.length>0?Math.round(tasksDone/allSteps.length*100):0,
      };
    },
    reportMonthlyData() {
      const n = parseInt(this.reportPeriod)||24;
      const now = new Date(); const months = [];
      for(let i=n-1;i>=0;i--){const d=new Date(now.getFullYear(),now.getMonth()-i,1);months.push({label:d.toLocaleString('en',{month:'short'}),year:d.getFullYear(),month:d.getMonth()});}
      return months.map(m=>{
        const inMonth=this.reportInquiries.filter(i=>{const d=new Date(i.date||i.created_at);return !isNaN(d)&&d.getFullYear()===m.year&&d.getMonth()===m.month;});
        return {label:m.label,total:inMonth.length,closed:inMonth.filter(i=>this._isClosed(i)).length,overdue:inMonth.filter(i=>i.overdue).length};
      });
    },
    initReportCharts() {
      if(typeof Chart==='undefined') return;
      const data   = this.reportMonthlyData();
      const labels = data.map(d=>d.label);
      const tc = document.getElementById('rpt-trend-chart');
      if(tc){
        if(this.rptTrendChart){this.rptTrendChart.destroy();this.rptTrendChart=null;}
        this.rptTrendChart = new Chart(tc,{
          type: this.isAdmin?'line':'bar',
          data:{labels,datasets:[
            {label:'Total',  data:data.map(d=>d.total),  borderColor:'#1268F3',backgroundColor:this.isAdmin?'rgba(18,104,243,0.08)':'rgba(18,104,243,0.55)',fill:this.isAdmin,tension:0.4,borderWidth:2,pointRadius:3},
            {label:'Closed', data:data.map(d=>d.closed), borderColor:'#16803C',backgroundColor:this.isAdmin?'rgba(22,128,60,0.06)':'rgba(22,128,60,0.55)', fill:this.isAdmin,tension:0.4,borderWidth:2,pointRadius:3},
            {label:'Overdue',data:data.map(d=>d.overdue),borderColor:'#B42318',backgroundColor:this.isAdmin?'rgba(180,35,24,0.06)':'rgba(180,35,24,0.55)',fill:this.isAdmin,tension:0.4,borderWidth:2,pointRadius:3},
          ]},
          options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{size:10}}},y:{grid:{color:'#F2F4F7'},ticks:{font:{size:10},precision:0},beginAtZero:true}}}
        });
      }
      const dc = document.getElementById('rpt-donut-chart');
      if(dc&&this.isAdmin){
        if(this.rptDonutChart){this.rptDonutChart.destroy();this.rptDonutChart=null;}
        const s=this.reportStats;
        this.rptDonutChart = new Chart(dc,{
          type:'doughnut',
          data:{labels:['Closed','In Progress','Overdue'],datasets:[{data:[s.closed,Math.max(0,s.inProgress-s.overdue),s.overdue],backgroundColor:['#16803C','#1268F3','#B42318'],borderWidth:0,hoverOffset:4}]},
          options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false}}}
        });
      }
    },
    exportReportCSV() {
      const rows = this.reportCompletedInquiries;
      if(!rows.length){this.addToast('No closed inquiries to export','error');return;}
      const hdr = ['ID','Date','Client','Company','Stage','Outcome','Closed By','Steps Done/Total'];
      const csv = [hdr,...rows.map(i=>[i.id,i.date,i.client,i.company,i.stage,i.outcome,i.current_owner,`${(i.steps||[]).filter(s=>s.status==='Done').length}/${(i.steps||[]).length}`])].map(r=>r.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
      const a=Object.assign(document.createElement('a'),{href:'data:text/csv;charset=utf-8,'+encodeURIComponent(csv),download:'survey-pacific-report.csv'});
      document.body.appendChild(a);a.click();a.remove();
    },

*/ ?>
