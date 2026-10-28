<?php /* Teams — included by index.php as the teams view */ ?>

<!-- ── TEAMS ───────────────────────────────────────────────────────────────────
     Messages-style layout: left rail picks the team, right pane is the board.
     My work sits above as a compact strip so assignees see their plate first. -->
<div x-show="view==='teams'" x-cloak class="space-y-4">

  <!-- ── My work — assignee's plate, title-first cards, one-tap status ── -->
  <div x-show="myOpenTeamTasks>0 || myDoneTeamTasks>0"
       class="bg-white rounded-[12px] border border-[#E4E7EC] overflow-hidden"
       style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">

    <div class="px-5 py-3 border-b border-[#E4E7EC] flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5 min-w-0">
        <svg class="w-4 h-4 text-[#1268F3] shrink-0"><use href="#icon-check-square"/></svg>
        <span class="text-[14px] font-bold text-[#172B3A]">My tasks</span>
        <span class="text-[11px] font-semibold text-[#98A2B3] tabular-nums"
              x-text="myTeamTasks.length+' shown'"></span>
      </div>
      <div class="flex items-center gap-1.5 flex-wrap">
        <button type="button" @click="myWorkFilter='open'"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full border transition-colors"
                :class="myWorkFilter==='open' ? 'bg-[#EEF4FF] text-[#175CD3] border-[#EEF4FF]' : 'bg-white text-[#667085] border-[#E4E7EC] hover:bg-[#F9FAFB]'">
          Open <span class="tabular-nums" x-text="myOpenTeamTasks"></span>
        </button>
        <button type="button" @click="myWorkFilter='overdue'"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full border transition-colors"
                :class="myWorkFilter==='overdue' ? 'bg-[#FEF3F2] text-[#B42318] border-[#FEF3F2]' : 'bg-white text-[#667085] border-[#E4E7EC] hover:bg-[#F9FAFB]'">
          Overdue <span class="tabular-nums" x-text="myOverdueTeamTasks"></span>
        </button>
        <button type="button" @click="myWorkFilter='done'"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full border transition-colors"
                :class="myWorkFilter==='done' ? 'bg-[#ECFDF3] text-[#16803C] border-[#ECFDF3]' : 'bg-white text-[#667085] border-[#E4E7EC] hover:bg-[#F9FAFB]'">
          Done <span class="tabular-nums" x-text="myDoneTeamTasks"></span>
        </button>
      </div>
    </div>

    <div class="overflow-y-auto divide-y divide-[#E4E7EC]" style="max-height:320px" x-show="myTeamTasks.length>0">
      <template x-for="t in myTeamTasks" :key="t.id">
        <div class="px-5 py-4 transition-colors hover:bg-[#F9FAFB]"
             :style="'border-left:3px solid '+teamTaskColor(t)">

          <!-- Title first — the thing a person actually scans for -->
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="text-[14px] font-bold text-[#172B3A] leading-snug" x-text="t.title"></div>
              <div class="flex items-center gap-2 flex-wrap mt-1.5">
                <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                      :class="teamTaskPill(t).cls" x-text="teamTaskPill(t).text"></span>
                <span class="text-[10px] font-bold text-[#175CD3] px-2 py-0.5 rounded-full bg-[#EEF4FF]" x-text="t._team"></span>
                <span class="text-[11px] tabular-nums"
                      :class="teamTaskState(t)==='overdue' ? 'text-[#B42318] font-bold' : 'text-[#667085] font-semibold'"
                      x-text="teamDueLabel(t)"></span>
              </div>
              <div x-show="t.requirement" class="text-[12px] text-[#667085] mt-2 leading-relaxed" style="white-space:pre-line" x-text="t.requirement"></div>
              <div class="text-[10px] text-[#98A2B3] mt-1.5" x-text="'From '+t.assigned_by"></div>
            </div>
          </div>

          <!-- Actions: one tap for the common moves, full status if needed -->
          <div class="flex flex-wrap items-center gap-2 mt-3">
            <button type="button" x-show="t.status==='Pending'" @click="setTeamTaskStatus(t,'In Progress')"
                    class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#EEF4FF] text-[#175CD3] hover:bg-[#1268F3] hover:text-white transition-colors">
              Start
            </button>
            <button type="button" x-show="t.status!=='Done'" @click="setTeamTaskStatus(t,'Done')"
                    class="inline-flex items-center gap-1 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#ECFDF3] text-[#16803C] hover:bg-[#16803C] hover:text-white transition-colors">
              <svg class="w-3 h-3"><use href="#icon-check-circle"/></svg> Mark done
            </button>
            <button type="button" x-show="t.status==='Done'" @click="setTeamTaskStatus(t,'Pending')"
                    class="inline-flex items-center text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">
              Reopen
            </button>
            <div class="relative" x-show="t.status!=='Done'">
              <select :value="t.status" @change="setTeamTaskStatus(t, $event.target.value)"
                      class="text-[11px] font-semibold border border-[#E4E7EC] rounded-[8px] px-2.5 pr-7 py-1.5 bg-white text-[#344054] cursor-pointer focus:outline-none focus:border-[#1268F3]">
                <template x-for="s in teamStatuses" :key="s">
                  <option :value="s" x-text="s"></option>
                </template>
              </select>
              <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
            </div>
            <button type="button" @click="t._remarkOpen = !t._remarkOpen"
                    class="text-[11px] font-bold px-2.5 py-1.5 rounded-[8px] text-[#667085] hover:bg-[#F2F4F7] transition-colors ml-auto"
                    x-text="t._remarkOpen || t.remark ? (t._remarkOpen ? 'Hide remark' : 'Edit remark') : 'Add remark'"></button>
          </div>

          <div x-show="t._remarkOpen || (t.remark && myWorkFilter==='done')" class="mt-2.5" x-cloak>
            <textarea :value="t.remark || ''" @change="setTeamTaskRemark(t, $event.target.value)" rows="2"
                      placeholder="Note for your team lead…"
                      class="w-full text-[12px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white resize-none focus:outline-none focus:border-[#1268F3]"></textarea>
          </div>
          <div x-show="!t._remarkOpen && t.remark && myWorkFilter!=='done'" class="mt-2 text-[11px] text-[#026AA2] leading-snug" style="white-space:pre-line" x-text="'Remark: '+t.remark"></div>
        </div>
      </template>
    </div>

    <div x-show="myTeamTasks.length===0" class="px-5 py-8 text-center">
      <svg class="w-8 h-8 text-[#D1D9E6] mb-2 mx-auto"><use href="#icon-check-circle"/></svg>
      <div class="text-[13px] font-bold text-[#344054] mb-1"
           x-text="myWorkFilter==='done' ? 'No completed tasks yet' : myWorkFilter==='overdue' ? 'Nothing overdue' : 'All caught up'"></div>
      <div class="text-[12px] text-[#667085]"
           x-text="myWorkFilter==='open' && myDoneTeamTasks>0
             ? ('You finished '+myDoneTeamTasks+(myDoneTeamTasks===1?' task':' tasks')+'.')
             : 'Tasks assigned to you show up here.'"></div>
      <button type="button" x-show="myWorkFilter==='open' && myDoneTeamTasks>0" @click="myWorkFilter='done'"
              class="text-[11px] font-bold text-[#1268F3] hover:underline mt-1.5">View completed</button>
      <button type="button" x-show="myWorkFilter!=='open'" @click="myWorkFilter='open'"
              class="text-[11px] font-bold text-[#1268F3] hover:underline mt-1.5">Back to open tasks</button>
    </div>
  </div>

  <!-- ── Main: team rail + board (Messages layout) ── -->
  <div class="teams-shell bg-white rounded-[12px] border border-[#E4E7EC] flex overflow-hidden"
       style="box-shadow:0 1px 4px rgba(7,29,43,0.04)">

    <!-- Left rail -->
    <div class="teams-rail w-[240px] border-r border-[#E4E7EC] flex flex-col shrink-0 overflow-hidden">
      <div class="px-4 py-3 border-b border-[#E4E7EC] shrink-0">
        <div class="text-[14px] font-bold text-[#172B3A]">Teams</div>
        <div class="text-[11px] text-[#98A2B3] mt-0.5 tabular-nums"
             x-text="teams.length ? (teams.length+(teams.length===1?' team':' teams')) : 'None yet'"></div>
      </div>

      <div class="flex-1 overflow-y-auto">
        <template x-for="team in teams" :key="team.id">
          <button type="button" @click="teamSelected=team.id"
                  class="w-full text-left px-4 py-3 transition-colors relative border-b border-[#F2F4F7]"
                  :class="teamSelected===team.id ? 'bg-[#EEF4FF]' : 'hover:bg-[#F9FAFB]'">
            <span x-show="teamSelected===team.id"
                  class="absolute left-0 top-1 bottom-1 w-[3px] bg-[#1268F3] rounded-r-full"></span>
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="text-[12px] font-bold text-[#172B3A] truncate" x-text="team.name"></div>
                <div class="text-[10px] text-[#98A2B3] truncate mt-0.5" x-text="'Lead · '+team.lead_name"></div>
              </div>
              <span x-show="teamStats(team).overdue>0"
                    class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-full bg-[#FEF3F2] text-[#B42318] shrink-0 tabular-nums"
                    x-text="teamStats(team).overdue"></span>
            </div>
            <div class="w-full h-1 rounded-full overflow-hidden bg-[#F2F4F7] flex mt-2.5">
              <div :style="'width:'+teamStats(team).pct.done+'%;background:#16803C'"></div>
              <div :style="'width:'+teamStats(team).pct.overdue+'%;background:#B42318'"></div>
              <div :style="'width:'+teamStats(team).pct.open+'%;background:#1268F3'"></div>
            </div>
            <div class="flex items-center gap-2 mt-1.5 text-[10px] font-bold tabular-nums">
              <span class="text-[#175CD3]" x-text="teamStats(team).open+' open'"></span>
              <span class="text-[#16803C]" x-text="teamStats(team).done+' done'"></span>
              <span class="text-[#98A2B3] ml-auto" x-text="team.members.length+' ppl'"></span>
            </div>
          </button>
        </template>

        <div x-show="teams.length===0" class="flex flex-col items-center justify-center p-6 text-center">
          <svg class="w-8 h-8 text-[#D1D9E6] mb-2"><use href="#icon-users"/></svg>
          <div class="text-[12px] font-bold text-[#344054] mb-1">No teams</div>
          <div class="text-[11px] text-[#667085]">Create one from the top bar.</div>
        </div>
      </div>
    </div>

    <!-- Right board -->
    <div class="teams-board flex-1 flex flex-col min-w-0 overflow-hidden">

      <template x-if="!selectedTeam">
        <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
          <svg class="w-10 h-10 text-[#D1D9E6] mb-3"><use href="#icon-users"/></svg>
          <div class="text-[14px] font-bold text-[#344054] mb-1" x-text="teams.length ? 'Select a team' : 'Create your first team'"></div>
          <div class="text-[12px] text-[#667085]" x-text="teams.length ? 'Pick one on the left to see tasks and members.' : 'A team needs a lead, members, and assigned work.'"></div>
          <button type="button" x-show="teams.length===0" @click="openTeamForm()"
                  class="mt-4 text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
            <svg class="w-3 h-3"><use href="#icon-plus"/></svg> New Team
          </button>
        </div>
      </template>

      <template x-if="selectedTeam">
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

          <!-- Board header -->
          <div class="px-5 py-3.5 border-b border-[#E4E7EC] flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[15px] font-bold text-[#172B3A] truncate" x-text="selectedTeam.name"></span>
                <span x-show="selectedTeam.can_manage"
                      class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-[#F4F3FF] text-[#5925DC] shrink-0">You manage</span>
              </div>
              <div class="text-[11px] text-[#667085] mt-0.5 truncate"
                   x-text="'Lead · '+selectedTeam.lead_name+(selectedTeam.description ? ' · '+selectedTeam.description : '')"></div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap">
              <label class="flex items-center gap-1.5 text-[11px] font-semibold text-[#667085] cursor-pointer" style="user-select:none"
                     x-show="!(myTeamTasks.length>0 || myDoneTeamTasks>0)">
                <input type="checkbox" x-model="teamShowDone" class="cursor-pointer accent-[#1268F3]"> Show completed
              </label>
              <template x-if="selectedTeam.can_manage">
                <div class="flex items-center gap-2">
                  <button type="button" @click="openTeamForm(selectedTeam)"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Edit</button>
                  <button type="button" @click="openTeamTask(selectedTeam)"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5">
                    <svg class="w-3 h-3"><use href="#icon-plus"/></svg> Assign task
                  </button>
                  <button type="button" @click="deleteTeam(selectedTeam)"
                          class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#FEF3F2] text-[#B42318] hover:bg-[#B42318] hover:text-white transition-colors">Delete</button>
                </div>
              </template>
            </div>
          </div>

          <!-- Roster -->
          <div class="px-5 py-2.5 bg-[#FAFBFC] border-b border-[#E4E7EC] flex items-center gap-2 flex-wrap shrink-0">
            <span class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#98A2B3] mr-1">Members</span>
            <template x-for="m in teamRoster(selectedTeam)" :key="m.account_id">
              <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-1 rounded-full"
                    :class="Number(m.account_id)===selectedTeam.lead_id ? 'bg-[#EEF4FF] text-[#175CD3]' : 'bg-white border border-[#E4E7EC] text-[#344054]'">
                <span class="w-5 h-5 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[8px] font-extrabold text-[#175CD3]"
                      x-text="initials(m.name)"></span>
                <span x-text="m.name"></span>
                <span x-show="Number(m.account_id)===selectedTeam.lead_id" class="text-[9px] font-extrabold opacity-70">Lead</span>
                <span x-show="teamOpenFor(selectedTeam, m.account_id)" class="text-[9px] font-extrabold tabular-nums text-[#B54708]"
                      x-text="teamOpenFor(selectedTeam, m.account_id)+' open'"></span>
              </span>
            </template>
            <span x-show="selectedTeam.members.length===0" class="text-[11px] text-[#98A2B3]">No members yet — use Edit to add people.</span>
          </div>

          <!-- Tasks -->
          <div class="sp-scroll flex-1 overflow-y-auto overflow-x-auto">
            <table class="w-full border-collapse" style="min-width:680px" x-show="teamTasksOf(selectedTeam).length>0">
              <thead>
                <tr class="bg-[#F8FAFC]" style="border-bottom:2px solid #E4E7EC">
                  <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]">Task</th>
                  <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:150px">Assigned to</th>
                  <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:140px">Due</th>
                  <th class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:120px">Status</th>
                  <th x-show="selectedTeam.can_manage" class="px-5 py-3 text-left text-[10px] font-extrabold uppercase tracking-[0.07em] text-[#98A2B3]" style="width:90px"></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="t in teamTasksOf(selectedTeam)" :key="t.id">
                  <tr class="transition-colors hover:bg-[#F9FAFB]" style="border-bottom:1px solid #F2F4F7">
                    <td class="px-5 py-3.5">
                      <div class="text-[12px] font-semibold text-[#172B3A] leading-snug" x-text="t.title"></div>
                      <div x-show="t.requirement" class="text-[11px] text-[#667085] mt-1 leading-snug" style="white-space:pre-line" x-text="t.requirement"></div>
                      <div x-show="t.remark" class="text-[11px] text-[#026AA2] mt-1 leading-snug" style="white-space:pre-line" x-text="'Remark: '+t.remark"></div>
                    </td>
                    <td class="px-5 py-3.5">
                      <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[9px] font-extrabold text-[#175CD3] shrink-0"
                             x-text="initials(t.assignee)"></div>
                        <span class="text-[12px] font-semibold text-[#344054] truncate" x-text="t.assignee"></span>
                      </div>
                    </td>
                    <td class="px-5 py-3.5 text-[12px] tabular-nums"
                        :class="teamTaskState(t)==='overdue' ? 'text-[#B42318] font-bold' : 'text-[#344054]'" x-text="t.due"></td>
                    <td class="px-5 py-3.5">
                      <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-full"
                            :class="teamTaskPill(t).cls" x-text="teamTaskPill(t).text"></span>
                    </td>
                    <td x-show="selectedTeam.can_manage" class="px-5 py-3.5">
                      <button type="button" @click="deleteTeamTask(t)"
                              class="text-[11px] font-bold px-3 py-1.5 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors">Remove</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div x-show="selectedTeam.tasks.length===0" class="flex flex-col items-center justify-center p-10 text-center">
              <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-check-square"/></svg>
              <div class="text-[14px] font-bold text-[#344054] mb-1">No tasks yet</div>
              <div class="text-[12px] text-[#667085]">Assign work to members of this team.</div>
              <button type="button" x-show="selectedTeam.can_manage" @click="openTeamTask(selectedTeam)"
                      class="mt-3 text-[11px] font-bold text-[#1268F3] hover:underline">Assign the first task</button>
            </div>

            <div x-show="selectedTeam.tasks.length>0 && teamTasksOf(selectedTeam).length===0"
                 class="flex flex-col items-center justify-center p-10 text-center">
              <svg class="w-9 h-9 text-[#D1D9E6] mb-3"><use href="#icon-check-circle"/></svg>
              <div class="text-[14px] font-bold text-[#344054] mb-1">Everything done</div>
              <div class="text-[12px] text-[#667085]"
                   x-text="teamStats(selectedTeam).done+(teamStats(selectedTeam).done===1?' task is':' tasks are')+' hidden.'"></div>
              <button type="button" @click="teamShowDone=true" class="mt-2 text-[11px] font-bold text-[#1268F3] hover:underline">Show completed</button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</div><!-- /teams -->

<!-- ═══ NEW / EDIT TEAM ══════════════════════════════════════════════════════ -->
<div x-show="teamFormOpen" x-cloak @click.self="teamFormOpen=false" @keydown.escape.window="teamFormOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">

    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(18,104,243,0.25)">
          <svg class="w-4 h-4 text-[#B8CCED]"><use href="#icon-users"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight" x-text="teamForm.id ? 'Edit team' : 'New team'"></h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)"
             x-text="teamForm.id ? 'Rename it and set who is in it' : 'Name it, pick the people — you become its lead'"></p>
        </div>
      </div>
      <button @click="teamFormOpen=false" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>

    <div class="overflow-y-auto flex-1 p-5 space-y-4">
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Team name <span class="text-[#B42318]">*</span></label>
        <input type="text" x-model="teamForm.name" maxlength="100" placeholder="e.g. Fieldwork QC"
               class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" />
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Description <span class="font-normal normal-case text-[#98A2B3]">(optional)</span></label>
        <textarea x-model="teamForm.description" rows="3" placeholder="What this team is responsible for…"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white resize-none focus:outline-none focus:border-[#1268F3]"></textarea>
      </div>

      <div>
        <div class="flex items-center justify-between gap-2 mb-1">
          <label class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Members</label>
          <span class="text-[10px] font-bold text-[#98A2B3] tabular-nums"
                x-text="teamMemberDraft.length+' of '+teamCandidates.length+' selected'"></span>
        </div>

        <div class="relative mb-1.5">
          <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#98A2B3] pointer-events-none"><use href="#icon-search"/></svg>
          <input type="text" x-model="teamMemberSearch" placeholder="Search by name or role…"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] pl-9 pr-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" />
        </div>

        <div class="border border-[#E4E7EC] rounded-[8px] overflow-y-auto" style="max-height:200px">
          <template x-for="a in teamPickList" :key="a.id">
            <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-[#F9FAFB] cursor-pointer"
                   style="user-select:none;border-bottom:1px solid #F2F4F7">
              <input type="checkbox" :value="a.id" x-model.number="teamMemberDraft"
                     :disabled="a.id===teamFormLeadId" class="cursor-pointer accent-[#1268F3] shrink-0" />
              <div class="w-7 h-7 rounded-full bg-[#EEF4FF] flex items-center justify-center text-[9px] font-extrabold text-[#175CD3] shrink-0"
                   x-text="initials(a.name)"></div>
              <div class="min-w-0 flex-1">
                <div class="text-[12px] font-semibold text-[#172B3A] truncate" x-text="a.name"></div>
                <div class="text-[10px] text-[#98A2B3] truncate" x-text="a.role"></div>
              </div>
              <span x-show="a.id===teamFormLeadId"
                    class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-[#EEF4FF] text-[#175CD3] shrink-0">Lead</span>
            </label>
          </template>
          <div x-show="teamPickList.length===0" class="px-3 py-4 text-center text-[11px] text-[#98A2B3]">No one matches that.</div>
        </div>

        <p class="text-[11px] text-[#98A2B3] mt-1.5">The lead always stays in. Someone with open tasks can’t be removed until those are done or reassigned.</p>
      </div>

      <p x-show="teamErr" class="text-[12px] font-semibold text-[#B42318]" x-text="teamErr"></p>
    </div>

    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="teamFormOpen=false" :disabled="_teamSaving"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="saveTeam()" :disabled="_teamSaving || !teamForm.name.trim()"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
        <svg x-show="_teamSaving" class="w-3 h-3 spin"><use href="#icon-refresh-cw"/></svg>
        <svg x-show="!_teamSaving" class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
        <span x-text="_teamSaving ? 'Saving…' : (teamForm.id ? 'Save changes' : 'Create team')"></span>
      </button>
    </div>
  </div>
</div>

<!-- ═══ ASSIGN TEAM TASK ═════════════════════════════════════════════════════ -->
<div x-show="teamTaskFor" x-cloak @click.self="teamTaskFor=null" @keydown.escape.window="teamTaskFor=null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(7,29,43,0.60);backdrop-filter:blur(4px)">
  <div class="bg-white rounded-[14px] w-full max-w-[580px] flex flex-col" style="box-shadow:0 20px 60px rgba(7,29,43,0.18);max-height:90vh">

    <div class="flex items-center justify-between px-5 py-4 rounded-t-[14px] shrink-0" style="background:#071D2B">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" style="background:rgba(18,104,243,0.25)">
          <svg class="w-4 h-4 text-[#B8CCED]"><use href="#icon-check-square"/></svg>
        </div>
        <div>
          <h2 class="text-[16px] font-bold text-white leading-tight">Assign a task</h2>
          <p class="text-[11px] mt-0.5" style="color:rgba(255,255,255,0.55)"
             x-text="teamTaskFor ? teamTaskFor.name : ''"></p>
        </div>
      </div>
      <button @click="teamTaskFor=null" class="hover:text-white transition-colors ml-4 text-xl" style="color:rgba(255,255,255,0.5)">✕</button>
    </div>

    <div class="overflow-y-auto flex-1 p-5 space-y-4">
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Assign to <span class="text-[#B42318]">*</span></label>
        <div class="relative">
          <select x-model.number="teamTaskForm.assignedTo"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 pr-7 py-2 bg-white focus:outline-none focus:border-[#1268F3] cursor-pointer">
            <option value="">Select a member…</option>
            <template x-for="m in (teamTaskFor ? teamTaskFor.members : [])" :key="m.account_id">
              <option :value="m.account_id"
                      x-text="m.name+' · '+m.role+' · '+teamOpenFor(teamTaskFor, m.account_id)+' open'"></option>
            </template>
          </select>
          <svg class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-[#667085] pointer-events-none"><use href="#icon-chevron-down"/></svg>
        </div>
        <p x-show="teamTaskFor && teamTaskFor.members.length<=1" class="text-[11px] text-[#98A2B3] mt-1.5">
          Only you are in this team so far — add people from <span class="font-bold">Edit</span>.
        </p>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Task <span class="text-[#B42318]">*</span></label>
        <input type="text" x-model="teamTaskForm.title" maxlength="200" placeholder="e.g. Verify 200 completes from wave 2"
               @keydown.enter="assignTeamTask()"
               class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" />
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085] mb-1">Requirement <span class="font-normal normal-case text-[#98A2B3]">(optional)</span></label>
        <textarea x-model="teamTaskForm.requirement" rows="3" placeholder="What done looks like…"
                  class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white resize-none focus:outline-none focus:border-[#1268F3]"></textarea>
      </div>

      <div>
        <div class="flex items-center justify-between gap-2 flex-wrap mb-1">
          <label class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#667085]">Due</label>
          <div class="flex items-center gap-1 flex-wrap">
            <template x-for="p in teamDuePresets" :key="p.label">
              <button type="button" @click="teamTaskForm.due = p.date"
                      class="text-[10px] font-bold px-2 py-0.5 rounded-full transition-colors"
                      :class="teamTaskForm.due === p.date ? 'bg-[#1268F3] text-white' : 'bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC]'"
                      x-text="p.label"></button>
            </template>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <input type="date" x-model="teamTaskForm.due"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" />
          <input type="time" x-model="teamTaskForm.dueTime"
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[8px] px-3 py-2 bg-white focus:outline-none focus:border-[#1268F3]" />
        </div>
        <p class="text-[11px] text-[#98A2B3] mt-1.5">Blank date means TBD. No time means end of that day.</p>
      </div>

      <p x-show="teamTaskErr" class="text-[12px] font-semibold text-[#B42318]" x-text="teamTaskErr"></p>
    </div>

    <div class="border-t border-[#E4E7EC] px-5 py-3.5 flex justify-end gap-2 shrink-0 rounded-b-[14px] bg-white">
      <button @click="teamTaskFor=null" :disabled="_teamSaving"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#F2F4F7] text-[#344054] hover:bg-[#E4E7EC] transition-colors disabled:opacity-50">Cancel</button>
      <button type="button" @click="assignTeamTask()" :disabled="_teamSaving || !teamTaskForm.assignedTo || !teamTaskForm.title.trim()"
              class="text-[12px] font-bold px-4 py-2 rounded-[8px] bg-[#1268F3] text-white hover:bg-[#0f55d6] transition-colors flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
        <svg x-show="_teamSaving" class="w-3 h-3 spin"><use href="#icon-refresh-cw"/></svg>
        <svg x-show="!_teamSaving" class="w-3 h-3"><use href="#icon-arrow-right"/></svg>
        <span x-text="_teamSaving ? 'Saving…' : 'Assign task'"></span>
      </button>
    </div>
  </div>
</div>
