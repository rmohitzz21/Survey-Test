<?php /* Reports view — included by index.php */ ?>

<!-- ── REPORTS VIEW ──────────────────────────────────────────────────────────── -->
<div x-show="view==='reports'" x-cloak class="space-y-4 anim-fade min-w-0"
     x-init="
       $watch('view',    v => { if(v==='reports') initRptCharts(); });
       $watch('rptPeriod', () => { if(view==='reports') initRptCharts(); });
       $watch('rptUser',   () => { if(view==='reports') initRptCharts(); });
       $watch('rptFrom',   () => { if(view==='reports') initRptCharts(); });
       $watch('rptTo',     () => { if(view==='reports') initRptCharts(); });
     ">

  <!-- ── Header ── -->
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div>
      <div class="text-[17px] font-bold text-[#172B3A]">Reports</div>
      <div class="text-[11px] text-[#667085] mt-0.5"
           x-text="(rptUser ? rptUser+' · ' : (isAdmin ? 'All team · ' : 'My inquiries · '))+rptPeriodLabel"></div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <!-- Period -->
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
      <!-- Custom dates -->
      <template x-if="rptPeriod==='custom'">
        <div class="flex items-center gap-1">
          <input type="date" x-model="rptFrom"
                 class="text-[11px] border border-[#E4E7EC] rounded-[8px] px-2 py-1.5 bg-white text-[#344054]" />
          <span class="text-[#98A2B3]">–</span>
          <input type="date" x-model="rptTo"
                 class="text-[11px] border border-[#E4E7EC] rounded-[8px] px-2 py-1.5 bg-white text-[#344054]" />
        </div>
      </template>
      <!-- User filter (admin only) -->
      <template x-if="isAdmin">
        <select x-model="rptUser"
                class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 py-1.5 bg-white text-[#344054] cursor-pointer"
                style="max-width:160px">
          <option value="">All Team</option>
          <template x-for="a in accounts.filter(a=>a.status==='approved')" :key="a.id">
            <option :value="a.name" x-text="a.name"></option>
          </template>
        </select>
      </template>
      <!-- Export -->
      <button @click="exportRptCSV()"
              class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors whitespace-nowrap">
        <svg class="w-3.5 h-3.5"><use href="#icon-download"/></svg> Export CSV
      </button>
    </div>
  </div>

  <!-- ── Inquiry Summary ── -->
  <div class="bg-white border border-[#E4E7EC] rounded-[12px]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)"
       x-data="{
         get _closed(){ return rptInquiries.filter(i=>_rptClosed(i)).length; },
         get _overdue(){ return rptInquiries.filter(i=>!_rptClosed(i)&&i.overdue).length; },
         get _active(){ return rptInquiries.length - this._closed - this._overdue; },
         get _total(){ return rptInquiries.length; },
         pct(n){ return this._total ? Math.round(n/this._total*100) : 0; }
       }">
    <div class="px-5 py-3.5 border-b border-[#E4E7EC]">
      <div class="text-[13px] font-bold text-[#172B3A]">Inquiry Summary</div>
      <div class="text-[11px] text-[#667085]" x-text="rptInquiries.length+' total · '+rptPeriodLabel"></div>
    </div>
    <div class="grid grid-cols-3 divide-x divide-[#E4E7EC]">

      <div class="px-5 py-4">
        <div class="flex items-center gap-1.5 mb-2">
          <span class="w-2 h-2 rounded-full bg-[#1268F3] shrink-0"></span>
          <span class="text-[10px] font-bold uppercase tracking-[0.07em] text-[#98A2B3]">Active</span>
        </div>
        <div class="text-[28px] font-extrabold text-[#172B3A] leading-none tabular-nums" x-text="_active"></div>
        <div class="mt-2.5 h-1 bg-[#F2F4F7] rounded-full overflow-hidden">
          <div class="h-1 bg-[#1268F3] rounded-full" :style="'width:'+pct(_active)+'%'"></div>
        </div>
        <div class="text-[11px] text-[#98A2B3] mt-1" x-text="pct(_active)+'% of total'"></div>
      </div>

      <div class="px-5 py-4">
        <div class="flex items-center gap-1.5 mb-2">
          <span class="w-2 h-2 rounded-full bg-[#16803C] shrink-0"></span>
          <span class="text-[10px] font-bold uppercase tracking-[0.07em] text-[#98A2B3]">Closed</span>
        </div>
        <div class="text-[28px] font-extrabold text-[#172B3A] leading-none tabular-nums" x-text="_closed"></div>
        <div class="mt-2.5 h-1 bg-[#F2F4F7] rounded-full overflow-hidden">
          <div class="h-1 bg-[#16803C] rounded-full" :style="'width:'+pct(_closed)+'%'"></div>
        </div>
        <div class="text-[11px] text-[#98A2B3] mt-1" x-text="pct(_closed)+'% of total'"></div>
      </div>

      <div class="px-5 py-4">
        <div class="flex items-center gap-1.5 mb-2">
          <span class="w-2 h-2 rounded-full bg-[#B42318] shrink-0"></span>
          <span class="text-[10px] font-bold uppercase tracking-[0.07em] text-[#98A2B3]">Overdue</span>
        </div>
        <div class="text-[28px] font-extrabold text-[#172B3A] leading-none tabular-nums" x-text="_overdue"></div>
        <div class="mt-2.5 h-1 bg-[#F2F4F7] rounded-full overflow-hidden">
          <div class="h-1 bg-[#B42318] rounded-full" :style="'width:'+pct(_overdue)+'%'"></div>
        </div>
        <div class="text-[11px] text-[#98A2B3] mt-1" x-text="pct(_overdue)+'% of total'"></div>
      </div>

    </div>
  </div>

  <!-- ── Team Performance (admin, no specific user selected) ── -->
  <template x-if="isAdmin && !rptUser">
    <div class="bg-white border border-[#E4E7EC] rounded-[12px]" style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">
      <div class="px-5 py-3.5 border-b border-[#E4E7EC]">
        <div class="text-[13px] font-bold text-[#172B3A]">Team Performance</div>
        <div class="text-[11px] text-[#667085]">Click a member to filter their inquiries below</div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[11px]" style="min-width:480px">
          <thead>
            <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
              <th class="text-left px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Member</th>
              <th class="text-center px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Inquiries</th>
              <th class="text-center px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Open</th>
              <th class="text-center px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Closed</th>
              <th class="text-center px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Overdue</th>
              <th class="text-right px-5 py-3 text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Close Rate</th>
            </tr>
          </thead>
          <tbody>
            <template x-if="rptTeamStats.length===0">
              <tr>
                <td colspan="6" class="px-5 py-10 text-center text-[12px] text-[#667085]">
                  No inquiries in this period
                </td>
              </tr>
            </template>
            <template x-for="emp in rptTeamStats" :key="emp.name">
              <tr class="border-b border-[#E4E7EC] last:border-0 hover:bg-[#F5F8FF] cursor-pointer transition-colors"
                  @click="rptUser=emp.name">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-[#1268F3] flex items-center justify-center text-white text-[10px] font-bold shrink-0"
                         x-text="emp.name.charAt(0).toUpperCase()"></div>
                    <span class="font-semibold text-[#172B3A]" x-text="emp.name"></span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center font-bold text-[#172B3A]" x-text="emp.inqs"></td>
                <td class="px-4 py-3 text-center font-semibold text-[#026AA2]" x-text="emp.active"></td>
                <td class="px-4 py-3 text-center font-semibold text-[#16803C]" x-text="emp.closed"></td>
                <td class="px-4 py-3 text-center font-semibold"
                    :class="emp.inqs-emp.closed-emp.active<0?'text-[#667085]':emp.tasksOD>0?'text-[#B42318]':'text-[#667085]'"
                    x-text="emp.tasksOD||'—'"></td>
                <td class="px-5 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <div class="w-20 h-1.5 bg-[#F2F4F7] rounded-full overflow-hidden">
                      <div class="h-1.5 rounded-full"
                           :class="emp.inqs&&Math.round(emp.closed/emp.inqs*100)>=70?'bg-[#16803C]':emp.inqs&&Math.round(emp.closed/emp.inqs*100)>=40?'bg-[#D97706]':'bg-[#B42318]'"
                           :style="'width:'+(emp.inqs?Math.round(emp.closed/emp.inqs*100):0)+'%'"></div>
                    </div>
                    <span class="font-bold text-[#172B3A] w-9 text-right tabular-nums"
                          x-text="emp.inqs?Math.round(emp.closed/emp.inqs*100)+'%':'—'"></span>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </template>


</div><!-- /reports -->
