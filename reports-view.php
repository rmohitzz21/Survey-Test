<?php /* Reports — user-first (pick person → detail); overall team is secondary */ ?>

<div x-show="view==='reports'" x-cloak class="space-y-4 anim-fade min-w-0"
     x-init="
       $watch('view',     v => { if (v==='reports') _scheduleRptCharts(); });
       $watch('rptPeriod', () => { if (view==='reports') _scheduleRptCharts(); });
       $watch('rptUser',   () => { if (view==='reports') _scheduleRptCharts(); });
       $watch('rptShowOverall', () => { if (view==='reports') _scheduleRptCharts(); });
       $watch('rptFrom',   () => { if (view==='reports') _scheduleRptCharts(); });
       $watch('rptTo',     () => { if (view==='reports') _scheduleRptCharts(); });
       $watch('rptVolumeBucket', () => { if (view==='reports') _scheduleRptCharts(); });
     ">


  <!-- Header -->
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <div class="text-[17px] font-bold text-[#172B3A]">Reports</div>
        <template x-if="isAdmin && (rptUser || rptShowOverall)">
          <button type="button" @click="openRptPeople()"
                  class="text-[11px] font-bold text-[#667085] hover:text-[#1268F3] underline">← People</button>
        </template>
      </div>
      <div class="text-[12px] text-[#344054] font-semibold mt-0.5" x-text="rptScopeTitle"></div>
      <div class="text-[11px] text-[#667085] mt-0.5" x-text="rptScopeHint"></div>
      <div class="text-[11px] text-[#98A2B3] mt-0.5 tabular-nums" x-show="rptRangeText" x-text="rptRangeText"></div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <select x-model="rptPeriod"
              class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
        <option value="today">Today</option>
        <option value="week">This Week</option>
        <option value="month">This Month</option>
        <option value="quarter">This Quarter</option>
        <option value="year">This Year</option>
        <option value="all">All Time</option>
        <option value="custom">Custom Range</option>
      </select>
      <template x-if="rptPeriod==='custom'">
        <div class="flex items-center gap-1">
          <input type="date" x-model="rptFrom" class="text-[11px] border border-[#E4E7EC] rounded-[8px] px-2 py-1.5 bg-white text-[#344054]" />
          <span class="text-[#98A2B3]">–</span>
          <input type="date" x-model="rptTo" class="text-[11px] border border-[#E4E7EC] rounded-[8px] px-2 py-1.5 bg-white text-[#344054]" />
        </div>
      </template>
      <template x-if="isAdmin && (rptUser || rptShowOverall)">
        <button type="button" @click="exportRptCSV()"
                class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap">
          <svg class="w-3.5 h-3.5"><use href="#icon-download"/></svg> Export CSV
        </button>
      </template>
      <template x-if="!isAdmin">
        <button type="button" @click="exportRptCSV()"
                class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap">
          <svg class="w-3.5 h-3.5"><use href="#icon-download"/></svg> Export CSV
        </button>
      </template>
    </div>
  </div>

  <!-- ═══════════ ADMIN: PEOPLE DIRECTORY (primary) ═══════════ -->
  <template x-if="isAdmin && !rptUser && !rptShowOverall">
    <div class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-[13px] font-bold text-[#172B3A]">Team members</div>
        <button type="button" @click="openRptOverall()"
                class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] border border-[#E4E7EC] bg-white text-[#344054] hover:border-[#1268F3] hover:text-[#1268F3] transition-colors">
          Overall team report →
        </button>
      </div>

      <div class="bg-white border border-[#E4E7EC] rounded-[12px] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <template x-if="rptPeople.length===0">
          <div class="px-5 py-10 text-center text-[12px] text-[#667085]">No approved team members yet</div>
        </template>
        <template x-for="emp in rptPeople" :key="'rp-'+emp.name">
          <div class="px-4 sm:px-5 py-3.5 flex items-center gap-3 border-b border-[#F2F4F7] last:border-0 hover:bg-[#F8FAFC] transition-colors">
            <div class="w-9 h-9 rounded-full bg-[#1268F3] flex items-center justify-center text-white text-[12px] font-bold shrink-0"
                 x-text="emp.name.charAt(0).toUpperCase()"></div>
            <div class="min-w-0 flex-1 text-[13px] font-bold text-[#172B3A] truncate" x-text="emp.name"></div>
            <button type="button" @click="openRptUser(emp.name)"
                    class="text-[11px] font-bold px-3.5 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap shrink-0">
              View report
            </button>
          </div>
        </template>
      </div>
    </div>
  </template>

  <!-- ═══════════ USER DETAIL / MEMBER SELF / OVERALL ═══════════ -->
  <template x-if="!isAdmin || rptUser || rptShowOverall">
    <div class="space-y-4">

      <!-- Focus metrics — with plain-language meaning for each status -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="rounded-[12px] border border-[#FECDCA] bg-[#FEF3F2] px-4 py-4 min-w-0"
             title="Still open — due date/time already passed">
          <div class="text-[11px] font-bold text-[#B42318]">Overdue</div>
          <div class="text-[28px] font-bold text-[#B42318] tabular-nums leading-none mt-2" x-text="rptSummary.overdue"></div>
          <div class="text-[11px] text-[#912018] mt-2 leading-snug"
               x-text="rptSummary.overdueHrs ? ('Still open · ' + rptSummary.overdueHrsLabel + ' past due') : 'Still open · past due'"></div>
        </div>
        <div class="rounded-[12px] border border-[#B2DDFF] bg-[#EFF8FF] px-4 py-4 min-w-0"
             title="Still open — due date has not passed yet">
          <div class="text-[11px] font-bold text-[#175CD3]">Pending</div>
          <div class="text-[28px] font-bold text-[#026AA2] tabular-nums leading-none mt-2" x-text="rptSummary.pending"></div>
          <div class="text-[11px] text-[#175CD3] mt-2 leading-snug">Still open · not past due yet</div>
        </div>
        <div class="rounded-[12px] border border-[#FEDF89] bg-[#FFFAEB] px-4 py-4 min-w-0"
             title="Already closed — but closed after the due date">
          <div class="text-[11px] font-bold text-[#B54708]">Late closed</div>
          <div class="text-[28px] font-bold text-[#B54708] tabular-nums leading-none mt-2" x-text="rptSummary.lateClosed"></div>
          <div class="text-[11px] text-[#B54708] mt-2 leading-snug">Already closed · was past due</div>
        </div>
        <div class="rounded-[12px] border border-[#E4E7EC] bg-white px-4 py-4 min-w-0" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)"
             title="Internal Usage created at 11:30 AM or later">
          <div class="text-[11px] font-bold text-[#667085]">Internal late</div>
          <div class="text-[28px] font-bold tabular-nums leading-none mt-2"
               :class="rptSummary.internalLateStart?'text-[#B54708]':'text-[#172B3A]'"
               x-text="rptSummary.internalLateStart"></div>
          <div class="text-[11px] text-[#667085] mt-2 leading-snug">Internal created at/after 11:30 AM</div>
        </div>
        <div class="rounded-[12px] border border-[#E4E7EC] bg-white px-4 py-4 min-w-0" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)"
             title="Total inquiries created in this period">
          <div class="text-[11px] font-bold text-[#667085]">Created</div>
          <div class="text-[28px] font-bold text-[#172B3A] tabular-nums leading-none mt-2" x-text="rptSummary.total"></div>
          <div class="text-[11px] text-[#667085] mt-2 leading-snug"
               x-text="rptSummary.closeSample ? ('Avg close ' + rptSummary.avgCloseLabel) : (rptSummary.closed + ' closed in period')"></div>
        </div>
      </div>

      <!-- Status guide so everyone report is readable at a glance -->
      <div class="rounded-[12px] border border-[#E4E7EC] bg-white px-4 py-3.5 sm:px-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
        <div class="text-[12px] font-bold text-[#172B3A] mb-2.5">What each number means</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-[11px] leading-snug">
          <div class="flex gap-2 min-w-0">
            <span class="w-1.5 h-1.5 rounded-full bg-[#B42318] mt-1.5 shrink-0"></span>
            <div><span class="font-bold text-[#B42318]">Overdue</span>
              <span class="text-[#667085]"> — work is <span class="font-semibold text-[#344054]">still open</span> and the due date/time has already passed. Hours shown are how long it has been late.</span></div>
          </div>
          <div class="flex gap-2 min-w-0">
            <span class="w-1.5 h-1.5 rounded-full bg-[#026AA2] mt-1.5 shrink-0"></span>
            <div><span class="font-bold text-[#026AA2]">Pending</span>
              <span class="text-[#667085]"> — work is <span class="font-semibold text-[#344054]">still open</span> and the due date has not passed yet (on track).</span></div>
          </div>
          <div class="flex gap-2 min-w-0">
            <span class="w-1.5 h-1.5 rounded-full bg-[#B54708] mt-1.5 shrink-0"></span>
            <div><span class="font-bold text-[#B54708]">Late closed</span>
              <span class="text-[#667085]"> — work is <span class="font-semibold text-[#344054]">already closed</span>, but it was finished after the due date (miss stays on record).</span></div>
          </div>
          <div class="flex gap-2 min-w-0">
            <span class="w-1.5 h-1.5 rounded-full bg-[#667085] mt-1.5 shrink-0"></span>
            <div><span class="font-bold text-[#344054]">Internal late</span>
              <span class="text-[#667085]"> — Internal Usage <span class="font-semibold text-[#344054]">created</span> at 11:30 AM or later (office 10:30 · should start before 11:30). Not about due date.</span></div>
          </div>
        </div>
      </div>

      <!-- Internal late list — only when there are late Internal creates -->
      <template x-if="rptInternalLate.length">
        <div class="bg-white border border-[#FEDF89] rounded-[12px] overflow-hidden" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="px-5 py-3 border-b border-[#FEF0C7] bg-[#FFFAEB] flex flex-wrap items-center justify-between gap-2">
            <div>
              <div class="text-[13px] font-bold text-[#172B3A]">Internal Usage started after 11:30</div>
              <div class="text-[11px] text-[#667085] mt-0.5">Office starts 10:30 AM · first create should be before 11:30 AM</div>
            </div>
            <span class="text-[11px] font-bold tabular-nums text-[#B54708]"
                  x-text="rptInternalLate.length"></span>
          </div>
          <template x-for="r in rptInternalLate" :key="'il-'+r.i.id">
            <div class="px-5 py-2.5 flex items-center gap-3 border-b border-[#F2F4F7] last:border-0">
              <button type="button" @click="rptOpenInquiry(r.i)"
                      class="text-[12px] font-semibold text-[#172B3A] hover:text-[#1268F3] hover:underline truncate text-left flex-1 min-w-0"
                      x-text="(r.i.client||('Inquiry #'+r.i.id))+(r.i.company?' · '+r.i.company:'')"></button>
              <div class="text-[11px] font-bold text-[#B54708] tabular-nums shrink-0" x-text="r.createdLabel"></div>
            </div>
          </template>
        </div>
      </template>

      <!-- Performance pie + volume -->
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2 bg-white border border-[#E4E7EC] rounded-[12px] p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="mb-3">
            <div class="text-[13px] font-bold text-[#172B3A]">Performance mix</div>
            <div class="text-[11px] text-[#667085] mt-0.5">Share of inquiries in this period</div>
          </div>
          <template x-if="rptPerfPie.total===0">
            <div class="h-[220px] flex items-center justify-center text-[12px] text-[#667085]">No inquiries in this period</div>
          </template>
          <div x-show="rptPerfPie.total>0" x-cloak class="flex flex-col sm:flex-row items-center gap-4">
            <div class="relative shrink-0" style="width:180px;height:180px">
              <canvas id="rpt-perf-chart" width="180" height="180"></canvas>
              <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <div class="text-[22px] font-bold text-[#172B3A] tabular-nums leading-none" x-text="rptPerfPie.total"></div>
                <div class="text-[10px] font-semibold text-[#98A2B3] mt-1">total</div>
              </div>
            </div>
            <div class="flex-1 w-full space-y-2 min-w-0">
              <template x-for="slice in rptPerfPie.slices" :key="'pie-'+slice.key">
                <div class="flex items-center gap-2.5">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:'+slice.color"></span>
                  <span class="text-[12px] font-semibold text-[#344054] flex-1 truncate" x-text="slice.label"></span>
                  <span class="text-[12px] font-bold text-[#172B3A] tabular-nums" x-text="slice.value"></span>
                  <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums w-9 text-right" x-text="slice.pct+'%'"></span>
                </div>
              </template>
            </div>
          </div>
        </div>

        <div class="lg:col-span-3 bg-white border border-[#E4E7EC] rounded-[12px] p-5" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="min-w-0">
              <div class="text-[13px] font-bold text-[#172B3A]">Inquiry volume</div>
              <div class="text-[11px] text-[#667085] mt-0.5">
                <span x-text="rptVolumeBucketHint ? (rptVolumeBucketHint + ' bars') : 'Volume bars'"></span>
                · created · closed · overdue
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <select x-model="rptVolumeBucket"
                      class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer"
                      title="Bar grouping">
                <option value="auto">Auto</option>
                <option value="day">Daily</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
                <option value="quarter">Quarterly</option>
                <option value="year">Yearly</option>
              </select>
              <div class="flex items-center gap-3 text-[10px] font-semibold text-[#667085]">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-[#1268F3]"></span>Created</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-[#16803C]"></span>Closed</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-[3px] bg-[#B42318]"></span>Overdue</span>
              </div>
            </div>
          </div>
          <template x-if="rptVolumeSeries.length===0">
            <div class="h-[220px] flex items-center justify-center text-[12px] text-[#667085]">No inquiries in this period</div>
          </template>
          <div x-show="rptVolumeSeries.length>0" class="relative" style="height:220px">
            <canvas id="rpt-volume-chart"></canvas>
          </div>
        </div>
      </div>

      <!-- Overall team table (secondary) -->
      <template x-if="isAdmin && rptShowOverall && !rptUser">
        <div class="bg-white border border-[#E4E7EC] rounded-[12px]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
          <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex flex-wrap items-center justify-between gap-2">
            <div>
              <div class="text-[13px] font-bold text-[#172B3A]">Team performance</div>
              <div class="text-[11px] text-[#667085] mt-0.5">Click a member to open their full report</div>
            </div>
            <label class="flex items-center gap-1.5 text-[10px] font-semibold text-[#667085]">
              Sort
              <select x-model="rptSort"
                      class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer">
                <option value="risk">Most at risk</option>
                <option value="overdue">Most overdue</option>
                <option value="avgclose">Avg close time</option>
                <option value="ontime">On-time %</option>
                <option value="close">Close rate</option>
                <option value="name">Name</option>
              </select>
            </label>
          </div>
          <div class="overflow-x-auto" style="-webkit-overflow-scrolling:touch">
            <table class="w-full text-[11px]" style="min-width:920px">
              <thead>
                <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                  <th class="text-left px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Member</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Created</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Pending</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Overdue</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Late closed</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Avg close</th>
                  <th class="text-center px-3 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Overdue hrs</th>
                  <th class="text-right px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Pending hrs</th>
                </tr>
              </thead>
              <tbody>
                <template x-if="rptTeamStats.length===0">
                  <tr><td colspan="9" class="px-5 py-10 text-center text-[12px] text-[#667085]">No inquiries in this period</td></tr>
                </template>
                <template x-for="emp in rptTeamStats" :key="'tp-'+emp.name">
                  <tr class="border-b border-[#E4E7EC] last:border-0 hover:bg-[#F5F8FF] cursor-pointer transition-colors"
                      @click="openRptUser(emp.name)">
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-[#1268F3] flex items-center justify-center text-white text-[10px] font-bold shrink-0"
                             x-text="emp.name.charAt(0).toUpperCase()"></div>
                        <span class="font-semibold text-[#172B3A]" x-text="emp.name"></span>
                      </div>
                    </td>
                    <td class="px-3 py-3 text-center font-bold text-[#172B3A] tabular-nums" x-text="emp.inqs"></td>
                    <td class="px-3 py-3 text-center font-semibold text-[#026AA2] tabular-nums" x-text="emp.pending||'—'"></td>
                    <td class="px-3 py-3 text-center font-semibold tabular-nums"
                        :class="emp.tasksOD?'text-[#B42318]':'text-[#667085]'" x-text="emp.tasksOD||'—'"></td>
                    <td class="px-3 py-3 text-center font-semibold tabular-nums"
                        :class="emp.lateClosed?'text-[#B54708]':'text-[#667085]'" x-text="emp.lateClosed||'—'"></td>
                    <td class="px-3 py-3 text-center font-semibold text-[#172B3A] tabular-nums" x-text="emp.avgCloseLabel"></td>
                    <td class="px-3 py-3 text-center font-semibold tabular-nums"
                        :class="emp.overdueHrs?'text-[#B42318]':'text-[#667085]'" x-text="emp.overdueHrsLabel"></td>
                    <td class="px-5 py-3 text-right font-semibold text-[#026AA2] tabular-nums" x-text="emp.pendingHrsLabel"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </template>

    </div>
  </template>

</div><!-- /reports -->
