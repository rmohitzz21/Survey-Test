# stitch.md — Master Prompt Pack
### Redesigning the Survey Pacific Work Journey portal in Google Stitch

> **Scope note:** this file is a design brief only. No code in this repo is changed by it.
> The current build stays as-is; Stitch produces the new dashboard visuals, then we port.

---

## 0. How to use this file

Stitch works best **one screen per prompt**. It does not reliably remember your design system
between generations, so:

1. Paste **§2 (Design System)** at the top of *every* screen prompt. It is short on purpose.
2. Then paste the screen prompt from **§4**.
3. Generate in **Standard mode** for layout-heavy screens (lists, tables, reports).
   Use **Experimental mode** only for §4.8 (Warnings), where you want more visual invention.
4. Upload `image.png` (the current logo lockup) as a reference image on the first screen so
   the brand mark carries through.
5. Generate desktop first (1440px). Re-prompt the same screen with the mobile block in §4.11
   for the responsive pass.

**Prompt rules that matter for Stitch specifically**

- Name the *regions* before the components ("left sidebar 240px, top bar 56px, content grid").
- Give real data, not lorem. Real inquiry IDs (SP-2026-041), real names, real statuses.
  Stitch composes better tables when the strings have realistic length.
- Say the exact hex codes. If you say "blue", you get Material blue.
- Say "no illustrations, no stock photos, no gradients on cards."
- One sentence per requirement. Long paragraphs get averaged away.

---

## 1. Product context (paste only if a screen comes back off-brief)

Internal workflow portal for a market-research company. Every client **inquiry** has a
human-readable ID (SP-2026-041), a 5-stage lifecycle, one **current owner**, and a due date.
Work is delegated as **steps/tasks** with their own assignee and deadline. Admins get reports
ranking the team by missed deadlines. Roles: Master Admin, Admin, Member, Client, plus custom
roles. Used daily by a single office team in IST. Dense and data-first, no marketing gloss —
this is a tool people live in for eight hours, not a landing page.

---

## 2. Design System — PASTE THIS BLOCK INTO EVERY SCREEN PROMPT

```
DESIGN SYSTEM (follow exactly):
Font: Inter. Weights 400/500/600/700/800. Numbers use tabular figures.
Type scale: page title 17px/700 · card title 13px/700 · body 12px/500 ·
  table cell 11px/500 · column label 10px/800 uppercase letter-spacing 0.07em ·
  big stat number 28px/800.
Colors:
  page background #F4F6F8 · surface #FFFFFF · table header #F8FAFC · track #F2F4F7
  border #E4E7EC · text primary #172B3A · text secondary #667085 · text muted #98A2B3
  brand blue #1268F3 (hover #0F55D6) · brand navy #1D3461
  success #16803C on #ECFDF3 · danger #B42318 on #FEF3F2 · warning #B54708 on #FFFAEB
  info #026AA2 on #EFF8FF · violet #5925DC on #F4F3FF · orange #C4320A on #FFF6ED
Shape: cards radius 12px, controls and chips radius 8px, pills fully rounded.
Elevation: cards only — 0 1px 4px rgba(7,29,43,0.04). No other shadows.
Density: compact. Card padding 20px horizontal / 14px vertical. Table row height 44px.
Chips: 10px/700 text, 8px horizontal padding, 2px vertical, tinted background plus the
  matching darker text from the pairs above. Never solid saturated fills.
Style: flat, professional, data-dense enterprise SaaS. No gradients, no illustrations,
  no stock photos, no rounded-blob decoration, no emoji. Icons are 14px line icons only.
Layout shell: fixed left sidebar 240px (white, 1px right border), top bar 56px
  (white, search + notification bell + avatar), content area on #F4F6F8 with 20px gutters.
```

---

## 3. Navigation (the shell every screen sits in)

| Group | Items |
|---|---|
| Work | Dashboard, Inquiries, My Tasks, Calendar, Reminders |
| Team | Team Directory *(new)*, Approvals, Leave |
| Insight | **Tracking & Monitoring** *(renamed from Reports)*, Warnings *(new)* |
| Comms | Messages, Announcements |

Badges: My Tasks shows the overdue count in `#B42318`; Messages and Announcements show unread
counts in `#1268F3`; Approvals shows pending count in `#B54708`; Warnings shows the number of
members currently at 2 or 3 warnings, in `#B42318`.

---

## 4. Screen prompts

### 4.1 Dashboard

```
Design a desktop dashboard screen, 1440px wide, for an internal inquiry-management portal.

[PASTE DESIGN SYSTEM BLOCK]

Layout top to bottom inside the content area:

1. Header row: page title "Dashboard" on the left with the subtitle
   "September 2026 · 41 inquiries". On the right: a month selector dropdown reading
   "September 2026" with a small orange pill "3 open elsewhere" attached to its right
   edge, and a primary blue button "New Inquiry".

2. A row of 5 equal stat tiles, each a white card:
   Total 41 (blue dot), Received 12 (blue tint), In Progress 19 (info blue),
   Closed 7 (green), Overdue 3 (red). Each tile shows a 10px uppercase label, the
   28px number, and a 4px progress bar underneath in the tile's color showing its share
   of the total. Tiles are clickable filters — show the "Overdue" tile in a selected
   state with a 1.5px #B42318 border and a faint red background.

3. A two-column grid below, 2fr / 1fr:
   LEFT: card "Ownership Status" — a horizontal segmented bar split into
     Open 34 (#1268F3) and Closed 7 (#16803C), with counts and percentages beneath,
     then a compact list of 5 rows. Each row: avatar circle, member name, the number of
     inquiries they currently own, and an "Open" or "Closed" pill. Under each name a
     smaller muted line reading "last completed: SP-2026-038 · 2h ago".
   RIGHT: card "Needs Attention" — a stacked list of 4 items, each with a red or amber
     4px left border, showing inquiry ID, client name, and a relative deadline such as
     "overdue 2 days" or "due in 3h".

4. Full-width card "Recent Activity" — 6 rows, each with a 24px avatar, one line of text
   ("Priya moved SP-2026-041 to Proposal Submitted"), and a right-aligned muted timestamp.

No illustrations. No charts on this screen.
```

### 4.2 Inquiries list

```
Design the inquiry list screen for the same portal, desktop 1440px.

[PASTE DESIGN SYSTEM BLOCK]

1. Header: title "Inquiries", subtitle "41 in September 2026". Right side: a search input
   with a magnifier icon (placeholder "Search ID, client, company, requirement..."), then
   filter dropdowns in a row: Stage, Type, Owner, Date. Then an "Export CSV" ghost button.

2. Active filter chips row directly under the header: two removable chips reading
   "Stage: Decision x" and "Owner: Priya x", plus a text link "Clear all".

3. A dense table, 44px rows, sticky header, columns:
   ID · Date · Client / Company · Type · Stage · Owner · Ownership · Due · Status
   - ID is bold #1268F3.
   - Client column is two lines: client name bold, company name muted 10px underneath.
   - Stage column is a chip. Use these exact pairs: Inquiry #175CD3 on #EEF4FF,
     Communication / Proposal #5925DC on #F4F3FF, Decision #C4320A on #FFF6ED,
     Project Execution #026AA2 on #EFF8FF, Closed #166534 on #ECFDF3.
   - Owner is a 24px avatar plus the name.
   - Ownership column is an "Open" or "Closed" pill; when Closed it shows a muted second
     line "by Rahul · 04 Sep".
   - Due shows "12 Sep 2026 · 05:30 PM". If past due and not closed, render the whole
     cell in #B42318 with a small warning icon.
   - Status column: Active / Overdue / Closed late / Closed on time chips.
   Show 8 realistic rows including one overdue and one "Closed late".

4. Footer: "Showing 1-15 of 41" on the left, pagination on the right.
```

### 4.3 Inquiry detail (expanded row / drawer)

```
Design an expanded inquiry detail panel, desktop 1440px, that opens inline under a table row.

[PASTE DESIGN SYSTEM BLOCK]

Header strip of the panel: inquiry ID "SP-2026-041" in 17px bold, client
"Meridian Research", company "Meridian Research Pvt Ltd", country "India", a
"Repeat client" chip, and on the right an "Ownership" block containing:
  - a large pill reading "Open" in blue (the Closed variant is green)
  - underneath, "Current owner: Priya Sharma" with an avatar
  - underneath that, a muted line
    "Last completed by Rahul Verma · Questionnaire · 04 Sep, 6:12 PM"
  - a small "Reassign" ghost button

Below, a 5-step horizontal stage tracker: Inquiry, Communication, Decision, Execution,
Closed. Completed steps filled #16803C with a check, current step ringed #1268F3, future
steps grey #E4E7EC. Under the current step show the outcome chip "Proposal Submitted".

Then a two-column body, 2fr / 1fr:
LEFT column, stacked cards:
  - "Requirement" — a paragraph of rich text with a bulleted list.
  - "Workflow Steps" — a table of delegated tasks. Columns: Instruction, Assigned to,
    Assigned by, Due, Status, Action. Status chips are:
    Pending #B54708 on #FFFAEB (this is the DEFAULT for a newly assigned task),
    In Progress #026AA2 on #EFF8FF, Submitted for Review #5925DC on #F4F3FF,
    Done #16803C on #ECFDF3, Overdue #B42318 on #FEF3F2, On Hold and Blocked grey.
    The Action column shows a small outline button "Mark Completed" on Pending and
    In Progress rows; on a Done row it shows instead a muted stamp
    "Completed 04 Sep, 6:12 PM" with a green check. One row must be overdue: red Overdue
    chip plus red muted text "3 days late".
    Add a "+ Assign Task" ghost button at the bottom of this card.
  - "Attachments" — 3 file chips with type icon, filename, size, download icon.
RIGHT column, stacked cards:
  - "Admin Remark" and "Team Remark" — each a small rich-text block with author and
    timestamp.
  - "Follow-ups" — 2 rows with date, note, assignee avatar and a checkbox.
  - "History" — a vertical timeline, 5 entries, each with a dot, the stage-change text,
    author and timestamp.
```

### 4.4 My Tasks

```
Design a personal task queue screen, desktop 1440px.

[PASTE DESIGN SYSTEM BLOCK]

1. Header: title "My Tasks", subtitle "9 open · 2 overdue". Right side: a two-option
   segmented toggle "Assigned to me / Assigned by me" (first selected), and a status
   filter dropdown.

2. Four collapsible groups, in this order, each with a colored count badge:
   "Overdue" (red, 2), "Due today" (amber, 3), "Upcoming" (blue, 4), "No due date"
   (grey, 1).

3. Each task is a white card row: a status chip on the left, the instruction text in
   12px medium, then a muted meta line "SP-2026-041 · Meridian Research · assigned by
   Ops Admin", and on the right the due datetime plus a "Mark Completed" outline button.
   Task status chips default to "Pending" in amber. Overdue tasks show a 4px red left
   border, the Overdue chip, and red due text like "due 06 Sep · 3 days ago".
   A completed card is dimmed to 60% opacity with a green "Done" chip, and the stamp
   "Completed 04 Sep, 6:12 PM" replaces the button entirely.

4. Empty state for the "No due date" group: a single centered muted line, no illustration.
```

### 4.5 Tracking & Monitoring — overview (THE PRIORITY SCREEN)

```
Design an admin analytics screen called "Tracking & Monitoring" for the same portal,
desktop 1440px. This is a reporting screen with charts.

[PASTE DESIGN SYSTEM BLOCK]

1. Header: title "Tracking & Monitoring", subtitle "All team · This Month".
   Right side controls in one row: a period dropdown (Today / This Week / This Month /
   This Quarter / This Year / All Time / Custom Range), a member dropdown "All Team",
   a "Compare to previous period" toggle switch, and a blue "Export" button with a caret
   for CSV / PDF.

2. A row of 4 KPI cards. Each card: 10px uppercase label, 28px number, a small delta pill
   showing change versus the previous period (green up or red down), and a 28px-tall
   sparkline in the card's color across the bottom.
   Cards: "Inquiries 41 +12%", "On-time delivery 78% -4%",
   "Avg. closure time 6.2 days +0.8", "Missed deadlines 8 +3".

3. Chart grid, two rows.
   ROW A, two cards side by side (2fr / 1fr):
     - "Inquiry Volume & Closure" — a combo chart: grouped vertical bars for Created
       (#1268F3) and Closed (#16803C) per day across the month, plus a thin #B54708 line
       for Overdue on a secondary axis. Legend top-right as small dot-plus-label chips.
       Gridlines 1px #F2F4F7, horizontal only. Axis labels 10px #98A2B3.
     - "Stage Distribution" — a donut chart with a 60% inner radius, five segments using
       the stage colors (#175CD3, #5925DC, #C4320A, #026AA2, #16803C). The donut center
       shows "41" over the label "Total". Legend is a vertical list to the right of the
       donut with color dot, stage name, count and percentage.
   ROW B, two cards side by side (1fr / 1fr):
     - "On-time vs Late by Member" — a horizontal 100% stacked bar chart, one bar per
       member, segments On time (#16803C), Late (#B54708), Still overdue (#B42318), with
       the percentage printed at the end of each bar. 6 members.
     - "Deadline Miss Trend" — a smooth area line chart over 12 weeks, a single series in
       #B42318 with a 10% opacity fill, and a dashed grey horizontal line labelled
       "target <= 2".

4. Full-width card "Workload Heatmap" — a grid where rows are members and columns are the
   days of the month. Each cell is a small rounded square shaded from #EFF8FF (light) to
   #1268F3 (heavy) by the number of tasks due that day. Overdue cells are #B42318 instead.
   A small legend "Less to More" with 5 swatches sits top-right.

Charts must be flat and 2D. No 3D, no drop shadows, no gradients except the single area fill.
```

### 4.6 Tracking & Monitoring — Team Performance table

```
Design the team performance section that sits below the charts on the Tracking & Monitoring
screen. Desktop 1440px, full width.

[PASTE DESIGN SYSTEM BLOCK]

Card header: title "Team Performance", subtitle "Click a member to open their profile ·
Overdue = still open past due · Late = submitted after due". Right side: a small sort
dropdown "Sort by: On-time %".

Table, sticky header, 48px rows, columns:
Member · Role · Inquiries · Open · Closed · Inq. Overdue · Task Overdue · Task Late ·
On Time · Warnings · Close Rate

- Member: 28px avatar with the initial on #1268F3, name bold, and beneath it a muted 10px
  line with their role description, e.g. "Owns proposal drafting and client follow-up".
- Role: a chip. Master Admin #5925DC on #F4F3FF, Admin #175CD3 on #EEF4FF,
  Member #344054 on #F2F4F7, custom roles #026AA2 on #EFF8FF.
- Numeric columns centered, tabular figures. Zero renders as a muted em-dash.
- Inq. Overdue and Task Overdue in #B42318 when above zero. Task Late in #B54708 when
  above zero.
- On Time is a percentage, green at 80% or above, amber below.
- Warnings column shows three small dots side by side: filled #B42318 for each issued
  warning, hollow #E4E7EC otherwise. A member with all three filled also shows a
  "Deduction" chip in #B42318 next to the dots.
- Close Rate is an 80px track bar plus the percentage: green at 70% or above, amber at
  40% or above, red below.

Show 6 rows with varied values, including one member at 3 warnings with a Deduction chip
and one member at 100% on time with zero warnings.
```

### 4.7 Member profile with Role Description

```
Design a member profile drawer that slides in from the right, 520px wide, over a dimmed
portal background. Desktop 1440px.

[PASTE DESIGN SYSTEM BLOCK]

Top: 56px avatar, name "Priya Sharma" in 17px bold, email in muted 11px, a green online
dot with "Active now", a role chip "Member", and a status chip "Approved". A ghost
"Message" button and a kebab menu on the right.

Section 1 — "Role & Responsibilities":
  - A role selector dropdown showing "Member" with an "Edit" pencil.
  - Below it a "Role Description" block: a multi-line area in read mode showing 3 to 4
    bullet points, e.g. "Owns proposal drafting for Asia-Pacific inquiries",
    "First responder on inquiries tagged Survey Programming",
    "May assign tasks but not close inquiries". Bullets use a small #1268F3 dot.
  - A muted footer line "Last updated by Ops Admin · 28 Aug 2026" and an
    "Edit description" ghost button.
  - Show a second variant of this block in edit mode: the same content inside a bordered
    textarea with a character counter "312 / 600", plus Cancel and blue Save buttons.

Section 2 — "Performance this month": a 2x3 grid of small stat tiles —
  Inquiries 14, Open 6, Closed 8, Task overdue 2, Task late 1, On time 82%.

Section 3 — "Warnings": a horizontal 3-step indicator labelled Warning 1, Warning 2,
  Warning 3. Steps 1 and 2 filled #B42318 with a check, step 3 hollow grey. Under it a
  muted line "2 of 3 · next warning triggers salary deduction review". Then two compact
  warning entries, each with date, reason, issued-by and a "View" link. A red outline
  button "Issue warning" at the bottom right.

Section 4 — "Recent activity": 5 timeline rows.
```

### 4.8 Warnings & Deduction console

```
Design an admin screen called "Warnings" for the same portal, desktop 1440px. It tracks
disciplinary warnings for missed deadlines, where three warnings trigger a salary deduction
review.

[PASTE DESIGN SYSTEM BLOCK]

1. Header: title "Warnings", subtitle "3 warnings trigger a salary deduction review".
   Right: a period dropdown "This Quarter" and a red outline button "Issue Warning".

2. A row of 3 stat tiles: "Members with warnings 5", "At final warning 2",
   "Deductions pending review 1" — the last tile has a 4px #B42318 left border.

3. Full-width card "Warning Ledger". Table columns:
   Member · Role · Warning level · Reason · Issued by · Issued on · Acknowledged · Status
   - Warning level renders as the three-dot indicator (filled #B42318, hollow #E4E7EC)
     followed by the text "2 of 3".
   - Reason is one line, truncated with an ellipsis, e.g.
     "4 tasks submitted after due date in August".
   - Acknowledged is a green check with a date, or an amber "Awaiting" chip.
   - Status chips: Active #B54708 on #FFFAEB, Expired grey, Deduction applied #B42318
     on #FEF3F2.
   Show 7 rows spread across 5 members.

4. A separate card below, "Deduction Review Queue", with a #B42318 top border. It lists
   members who have reached the third warning. Each entry is a wide row: avatar, name,
   role, a red "3 of 3" indicator, the three warning dates listed inline as small chips,
   a suggested deduction amount field, a notes input, and two buttons — "Waive" ghost and
   "Approve deduction" solid red. Show 1 entry.

5. A modal, shown open over the screen, titled "Issue Warning": a member picker with
   avatar, a read-only warning-level field showing "This will be warning 3 of 3" with a
   red inline alert bar reading "Third warning — this moves the member into deduction
   review", a reason dropdown (Missed deadlines / Unacknowledged tasks / Quality / Other),
   a details textarea, an "attach evidence" file row listing two overdue task references,
   a checkbox "Notify member by email and in-app", and Cancel / "Issue warning" buttons.
```

### 4.9 Team Directory

```
Design a team directory screen, desktop 1440px.

[PASTE DESIGN SYSTEM BLOCK]

1. Header: title "Team", subtitle "14 members · 2 pending approval". Right: search input,
   role filter dropdown, status filter dropdown, blue "Add Member" button.

2. Card grid, 4 across, 16px gap. Each member card is white, radius 12px, padding 20px:
   - 44px avatar with an online status ring (green online, grey offline).
   - Name 13px bold, email muted 10px.
   - Role chip, and a status chip (Approved green / Pending amber / Blocked grey).
   - A 2-line clamped role description in muted 11px.
   - A thin divider, then a 3-up mini stat row: Owned 6 · Overdue 1 · On time 82%.
   - Bottom row: the three-dot warning indicator on the left, "View profile" text link
     on the right.
   Show 8 cards, including one Pending (with "Approve" and "Reject" buttons replacing the
   stat row) and one at 3 warnings with a red card border.
```

### 4.10 Calendar

```
Design a month calendar screen, desktop 1440px.

[PASTE DESIGN SYSTEM BLOCK]

Header: title "Calendar", subtitle "September 2026". Left and right month arrows and a
"Today" ghost button. A member dropdown "All Team" (admin only). A view toggle:
Month / Week / List.

Month grid, 7 columns, Monday-first, cells about 120px tall, 1px #E4E7EC borders.
Each cell: the date number top-left (today's number is a filled #1268F3 circle in white),
and up to 3 event chips stacked, each 18px tall with a color dot and truncated text:
  inquiry created (green tint), task due (blue tint), follow-up (violet tint),
  overdue item (red tint, bold).
Cells with more show a muted "+2 more" link. Days outside the month are 40% opacity.
A right-hand rail 300px wide titled "Sep 12" lists that day's items in full, grouped by
type, each with time, title, inquiry ID and assignee avatar.
Legend row under the grid with the four color dots and labels.
```

### 4.11 Mobile pass

Re-run screens 4.1, 4.2, 4.4 and 4.5 with this appended:

```
Now redesign this for mobile, 390px wide. The sidebar becomes a bottom tab bar with 5 items
(Dashboard, Inquiries, Tasks, Reports, More), and the top bar keeps only the logo mark,
search icon and avatar. Stat tiles become a horizontally scrollable row of 140px cards.
Tables become stacked cards: the primary line is the inquiry ID and client, the secondary
muted line is stage and owner, and the status chip sits top-right. Charts stay full width
and move their legends behind a tappable info icon. All tap targets at least 44px tall.
```

### 4.12 Dark mode pass

Append to any accepted screen:

```
Now produce the dark variant. Page background #0F1720, surface #17212B, elevated surface
#1D2A36, border #2A3946, text primary #E6EDF3, secondary #9AA9B8, muted #6B7B8C.
Keep the same accent hues but lift them for contrast: blue #4D90FF, green #35B36B,
red #F0685E, amber #E0912F, violet #A78BFA, info #38A3D1. Chip backgrounds become the
accent at 14% opacity with the lifted accent as the text color. Charts keep the same series
colors on a #17212B plot area with #2A3946 gridlines. Do not invert the logo — use the
white-on-transparent lockup.
```

---

## 5. The six requested changes, as design requirements

Each is already embedded in the prompts above. This section is the checklist to verify the
Stitch output against before anything is ported.

### 5.1 Better reporting graphs — §4.5
Today `initRptCharts()` at `index.php:4829` is an empty stub; the Reports view has stat bars
and a table and nothing else. The redesign adds five visuals: a volume/closure combo chart,
a stage donut, on-time-vs-late stacked bars per member, a deadline-miss trend, and a workload
heatmap — plus period-over-period deltas on the KPI cards, which the current report has no
concept of at all.

### 5.2 Role description per member — §4.7, §4.6, §4.9
`accounts.role` is a free string with no description field. The design treats role description
as first-class: an editable multi-line block on the member profile, a 2-line clamp on the
directory card, and a one-line muted subtitle under the name in Team Performance.

### 5.3 Three warnings, then salary deduction — §4.8, §4.7
A three-dot indicator is the single visual language for warning level everywhere it appears
(profile, team table, directory card, ledger). The third warning is a distinct state, not just
a third dot: it moves the member into a Deduction Review Queue with its own card, its own red
top border, and an explicit Approve / Waive decision. The issue-warning modal warns before the
third is issued.

### 5.4 Ownership status — last team member who completed — §4.1, §4.2, §4.3
Every ownership surface carries a second muted line naming who last completed something on
that inquiry, what it was, and when: "Last completed by Rahul Verma · Questionnaire ·
04 Sep, 6:12 PM". On the dashboard it appears per owner row; in the list it is the second line
of the Ownership cell; on the detail panel it sits directly under the current owner.

### 5.5 Ownership status Open / Closed — §4.2, §4.3
Ownership becomes an explicit binary pill, separate from stage and separate from outcome.
Blue "Open", green "Closed"; Closed additionally shows by-whom and when. It gets its own column
in the inquiry table and its own block in the detail header, and the dashboard gets a segmented
Open/Closed bar summarising it.

### 5.6 Task status — Pending by default, plus Overdue — §4.3, §4.4
"Pending" amber becomes the default chip for a newly assigned task, replacing "New". The
completion CTA is an outline "Mark Completed" button that is *replaced* by a completion stamp
once done, so a completed task can never show a completion button. Overdue is a distinct red
chip with a red left border and relative lateness text — a derived state shown alongside the
status, never a status the assignee can pick.

---

## 6. Handoff — what each new screen will eventually need

Nothing here is built yet. Recorded so the design does not promise data the system cannot
produce. Roughly in order of effort:

| Design element | Backing needed |
|---|---|
| Task chip "Pending" default | Rename only — `$stepStatuses` at `index.php:38` already contains `Pending`. |
| Ownership Open / Closed pill | Derivable now from `stage` / `outcome` / `closed_at`. No schema change. |
| "Last completed by ..." | Derivable now — max `completed_at` across `inquiry_steps` for that inquiry. |
| Role description | New column `accounts.role_description TEXT NULL`, plus an edit action in `api/accounts.php`. |
| Charts | Aggregations over data already loaded client-side; needs a chart library decision. |
| Period-over-period deltas | A second aggregation pass over the previous window. |
| Warnings + deduction | New table `warnings` (account_id, level, reason, issued_by, issued_at, acknowledged_at, status) plus a review/decision flow. |

Warnings are the one item that is a new subsystem rather than a new view. Everything else is
presentation over data the portal already holds.

---

## 7. Generation order

Do these in sequence — later screens reuse components Stitch has already settled on:

1. §4.1 Dashboard — establishes the shell, tiles and chips.
2. §4.2 Inquiries list — establishes the table and the Ownership column.
3. §4.3 Inquiry detail — establishes task rows and the stage tracker.
4. §4.5 + §4.6 Tracking & Monitoring — the priority screens.
5. §4.7 Member profile — role description and warning indicator.
6. §4.8 Warnings console.
7. §4.4 My Tasks, §4.9 Team Directory, §4.10 Calendar.
8. §4.12 dark pass, then §4.11 mobile pass.

Export each accepted screen from Stitch to Figma, keep them on one page named
`Work Journey — v2`, and only then start the port.
