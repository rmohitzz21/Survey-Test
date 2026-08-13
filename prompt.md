# Master Figma Prompt — Survey Pacific Work Journey: User Performance Report

## Project Context

**App:** Survey Pacific Work Journey — an internal CRM / inquiry management system used by surveyors, project managers, and admins.

**Purpose of this screen:** A "Reports" section inside the app. Two audiences see different versions:
- **Admin / Master Admin** → sees their own personal report + a full Team Performance view of all members.
- **Member / Non-admin user** → sees only their own personal performance report.

**Design language:** Clean, data-dense, professional SaaS dashboard. Inter font. Pixel-precise spacing. Muted backgrounds with bold accent colors. No decorative illustrations — data cards only.

---

## Design Tokens

### Colors
| Token | Hex | Usage |
|---|---|---|
| Brand Blue | `#1268F3` | Primary action, active state, links |
| Navy Dark | `#172B3A` | Primary text, headings |
| Navy Deep | `#071D2B` | Hero gradient start |
| Navy Mid | `#1D3461` | Hero gradient mid |
| Green | `#16803C` | Completed, success |
| Green Light BG | `#ECFDF3` | Completed badge background |
| Red | `#B42318` | Overdue, error |
| Red Light BG | `#FEF3F2` | Overdue badge background |
| Red Border | `#FECDCA` | Overdue card border |
| Salmon Bar | `#FCA5A5` | Overdue segment in bar chart |
| Blue Light BG | `#EEF4FF` | Info badge background |
| Blue Light Bar | `#BFDBFE` | Open/active segment in bar chart |
| Orange | `#B54708` | Warning, pending |
| Orange BG | `#FFFAEB` | Warning badge background |
| Purple | `#5925DC` | Role: Master Admin |
| Gray 1 | `#667085` | Secondary text, muted labels |
| Gray 2 | `#98A2B3` | Tertiary text, column headers |
| Gray 3 | `#D1D9E6` | Disabled / empty state color |
| Gray 4 | `#C8D2DF` | Section label uppercase text |
| Border | `#E4E7EC` | All card/divider borders |
| Surface 1 | `#F2F4F7` | Subtle background, icon containers |
| Surface 2 | `#F8FAFC` | Table header row background |
| White | `#FFFFFF` | Card backgrounds |

### Typography (Inter)
| Style | Size | Weight | Usage |
|---|---|---|---|
| Hero Name | 18px | 800 | User name in hero banner |
| KPI Number Large | 30px | 800 | Stat card numbers |
| KPI Number Medium | 28px | 800 | Inquiry activity card numbers |
| Section Heading | 14px | 700 | Card titles |
| Body | 13px | 700 | Tab labels, nav items |
| Small Body | 12px | 600–700 | Table cell text, badge text |
| Label Uppercase | 10px | 700 | Section labels (UPPERCASE + letter-spacing 0.1em) |
| Micro | 9px | 800 | Overdue warning badge text |

### Spacing & Shape
- Card border-radius: `12px` (KPI cards), `14px` (table containers + hero)
- Icon container: `36×36px`, `border-radius: 10px`
- Card padding: `16px` (compact), `20px` (standard)
- Shadow: `0 2px 8px rgba(7, 29, 43, 0.06)` on all white cards
- Accent bar (left edge of table rows): `3px wide`, `border-radius: 0 4px 4px 0`

---

## Screen 1 — My Performance (All Roles See This)

### Layout
Single-column scroll. No sidebar content. Max-width centered on desktop (~900px). Mobile-first.

### 1A. Tab Strip (Admin only — non-admins skip this)

Underline-style tab strip at the top of the Reports view.

- Two tabs: **My Performance** | **Team Performance**
- Active tab: `#1268F3` text, `2.5px` solid underline in `#1268F3`
- Inactive tab: `#98A2B3` text, no underline
- Border bottom: `1px solid #E4E7EC` across full width
- Tab padding: `12px 20px`

### 1B. Hero Banner

Full-width card, `border-radius: 14px`, no white background — uses a dark gradient.

**Gradient:** `linear-gradient(135deg, #071D2B 0%, #1D3461 60%, #1268F3 100%)`

**Top section (horizontal flex):**
- Left: Avatar circle `48×48px`, `border: 2px solid rgba(255,255,255,0.3)`, `background: rgba(255,255,255,0.15)`, initials in `16px 800 white`
- Center: User full name `18px 800 white`; below it: `"Role · Performance Report"` in `11px 600 rgba(255,255,255,0.55)`
- Right (hidden on mobile): Label `"GENERATED"` in `10px 700 uppercase rgba(255,255,255,0.45)`; below it: today's date in `13px 800 white`

**Bottom stat strip (inside hero, separated by `1px solid rgba(255,255,255,0.1)` top border):**
Three equal columns, divided by `1px rgba(255,255,255,0.1)` vertical dividers.

| Column | Number Style | Label |
|---|---|---|
| Tasks Assigned | `24px 800 white` | `10px 700 uppercase rgba(255,255,255,0.5)` |
| Overdue | `24px 800 #FCA5A5` (red if >0, else white) | same |
| Completion Rate | `24px 800 #6EE7B7` (green) | same |

### 1C. Section Label — "TASK BREAKDOWN"

`10px 800 uppercase letter-spacing 0.1em #C8D2DF`  
No border, no background — plain text separator above the card grid.

### 1D. Task KPI Cards (4-column grid, 2-col on mobile)

Four identical-sized white cards with shadow. Each card: `border-radius: 12px`, `border: 1px solid #E4E7EC`, `padding: 16px`.

**Card anatomy (top to bottom):**
1. Row: Icon container (left) + color dot `8×8px` (right, top-aligned)
2. Large number: `30px 800`
3. Label: `10px 700 uppercase tracking-wide #98A2B3`

| Card | Icon | Icon BG | Icon Color | Dot Color | Number Color |
|---|---|---|---|---|---|
| Total Assigned | check-square | `#F2F4F7` | `#667085` | `#D1D9E6` | `#172B3A` |
| Completed | check-circle | `#ECFDF3` | `#16803C` | `#16803C` | `#16803C` |
| Overdue (Active) | alert-circle | `#FEF3F2` (if >0) else `#F2F4F7` | `#B42318` (if >0) else `#D1D9E6` | same | same |
| Active / Open | clock | `#EEF4FF` | `#1268F3` | `#1268F3` | `#1268F3` |

When Overdue card has 0 value: border stays `#E4E7EC`. When >0: border becomes `#FECDCA`.

### 1E. Task Completion Rate Card

Full-width white card. `border-radius: 12px`, `padding: 20px`.

**Header row (space-between):**
- Left: Title `14px 700 #172B3A`; subtitle `11px #98A2B3` — "X of Y tasks completed"
- Right: Large percentage `32px 800` — color: `#16803C` if ≥80%, `#B42318` if overdue>0, else `#172B3A`

**Segmented progress bar:** `height: 12px`, `border-radius: 9999px`, `overflow: hidden`, `background: #F2F4F7`

Three segments side by side (2px gap between):
- Completed: `#16803C`, rounded-left
- Overdue: `#FCA5A5`
- Open/Active: `#BFDBFE`, rounded-right

Width of each segment = its percentage of total tasks.

**Legend (horizontal flex, 16px gap):**
Each legend item: `10×10px` rounded-`2px` square + `11px text-#667085` label + bold count in segment color.

Legend items: Completed (green `#16803C`) · Overdue (red `#B42318`) · Open (blue `#1268F3`)

### 1F. Section Label — "INQUIRY ACTIVITY"

Same style as 1C.

### 1G. Inquiry Activity Cards (3-column grid)

Three white cards, horizontal layout inside each card. `border-radius: 12px`, `padding: 16px`.

**Card anatomy (horizontal flex, gap 14px):**
- Icon container: `44×44px`, `border-radius: 10px`
- Right: Number `28px 800` + label `10px 700 uppercase #98A2B3` below

| Card | Icon | Icon BG | Icon Color | Number Color |
|---|---|---|---|---|
| Created | plus | `#EEF4FF` | `#175CD3` | `#172B3A` |
| Handling | user | `#EFF8FF` | `#026AA2` | `#1268F3` |
| Inq Overdue | alert-circle | `#FEF3F2` (if >0) else `#F2F4F7` | `#B42318` (if >0) else `#D1D9E6` | `#B42318` (if >0) else `#172B3A` |

Overdue card: border `#FECDCA` if >0, else `#E4E7EC`.

### 1H. All My Tasks Table

Full-width white container. `border-radius: 14px`, `border: 1px solid #E4E7EC`, `overflow: hidden`.

**Table header bar:** `background: #F8FAFC`, `border-bottom: 1px solid #E4E7EC`, `padding: 16px 20px`
- Left: Title `14px 700 #172B3A` + subtitle `11px #98A2B3`
- Right: Pill badge with task count — `10px 700 #344054 background #F2F4F7 border-radius: 9999px padding: 4px 10px`

**Column headers:** `10px 700 uppercase tracking-wide #C8D2DF`

| # | Inquiry | Instruction | Assigned By | Due | Status |
|---|---|---|---|---|---|

**Table rows:**

Each row has a `3px` left accent bar (full row height, `border-radius: 0 4px 4px 0`):
- Green `#16803C` → Done/Completed
- Red `#B42318` → Overdue
- Blue `#1268F3` → New or In Progress
- Gray `#E4E7EC` → All other statuses

Row columns:
- **#** — row number `11px 700 #D1D9E6`
- **Inquiry** — ID in `12px 700 #1268F3` (link style) + client name `10px #98A2B3` below (truncated)
- **Instruction** — `12px #344054` max 2 lines, line-clamp
- **Assigned By** — `20×20px` avatar circle (`#EEF4FF` bg, `#175CD3` initials `8px 800`) + name `11px 600 #344054`
- **Due** — date `11px 600` (`#B42318` if overdue, `#344054` if not) with alert-circle icon if overdue; time below `10px #B8CCED`; "TBD" in `11px #D1D9E6`
- **Status** — badge using step status colors + optional `⚠ Overdue` sub-badge in `9px 800 #B42318 bg-#FEF3F2 border-radius:4px`

Hover row: `background #F8FAFC`. Cursor pointer (rows are clickable).
Row border-bottom: `1px solid #F2F4F7`. Last row: no border.

**Empty state:** centered icon + `13px 600 #344054` heading + `11px #98A2B3` subtitle. 64px vertical padding.

---

## Screen 2 — Team Performance (Admin Only)

Accessed via the "Team Performance" tab in the tab strip (1A above).

### 2A. Loading State

Full-width white card. Centered spinner (animated, `#1268F3`) + `12px 600 #98A2B3` "Loading team data…" label. `80px` vertical padding.

### 2B. Team KPI Cards (4-column grid, 2-col on mobile)

Same card style as 1D. Team-wide aggregated numbers.

| Card | Icon | Icon BG | Icon Color | Number Color |
|---|---|---|---|---|
| Team Members | user | `#EEF4FF` | `#175CD3` | `#172B3A` |
| Total Tasks | check-square | `#F2F4F7` | `#667085` | `#172B3A` |
| Completed | check-circle | `#ECFDF3` | `#16803C` | `#16803C` |
| Team Overdue | alert-circle | dynamic (same logic as 1D Overdue card) | dynamic | dynamic |

### 2C. Performance by Member Table

Full-width white container. `border-radius: 14px`, `border: 1px solid #E4E7EC`.

**Table header bar:** Same style as 1H header bar.
- Right: Pill badge with member count in `#EEF4FF` text `#175CD3` (blue variant)

**Column headers:** same style as 1H

| Member | Assigned | Completed | Overdue | Open | Completion | Inq Created | Handling | Inq Overdue | (action) |
|---|---|---|---|---|---|---|---|---|---|

**Row left accent bar (3px, same logic as 1H):**
- Red if member has overdue tasks
- Green if all tasks completed
- Gray otherwise

**Member cell (leftmost):**
- `3px` accent bar + `32×32px` avatar circle with colored bg:
  - Overdue: `#FEF3F2` bg, `#B42318` initials
  - All done: `#ECFDF3` bg, `#16803C` initials
  - Default: `#EEF4FF` bg, `#175CD3` initials
- Initials `11px 800`
- Name `12px 700 #172B3A`
- Role badge below name (uses app role badge style)

**Count cells (Assigned, Inq Created, Handling):** `13px 700 #172B3A` centered.

**Badge cells (Completed, Overdue, Open):**
Small pill `min-width:26px height:24px border-radius:6px` centered.

| Cell | Active Color |
|---|---|
| Completed > 0 | `bg-#ECFDF3 text-#16803C` |
| Overdue > 0 | `bg-#FEF3F2 text-#B42318` |
| Open > 0 | `bg-#EEF4FF text-#1268F3` |
| Zero value | plain `#D1D9E6` text, no background |

**Completion column:**
- Mini progress bar: `height:8px border-radius:9999px bg-#F2F4F7`, `min-width:80px`
  - Bar fill color: `#16803C` if ≥80%, `#F97066` if has overdue, else `#1268F3`
  - Width = completion %
- Percentage label `11px 800 text-right`: green/red/gray matching bar

**Inq Overdue cell:** If >0 → `11px 700 #B42318 bg-#FEF3F2 px-6 py-2 border-radius:4px`. If 0 → `12px #D1D9E6` plain.

**Action cell (rightmost):**
"View" button: `10px 700 px-10 py-6 border-radius:6px bg-#EEF4FF text-#175CD3`
Hover: `bg-#1268F3 text-white`
Includes a `file-text` icon `10×10px` before the label.

Row hover: `background #F8FAFC`.

---

---

## Screen 3 — Follow-Up Calendar (All Roles)

A dedicated **Reminders** view accessible from the left nav (`calendar` icon). Displays all follow-ups scheduled for the current user (members) or all users (admin) in a modern month-grid calendar.

### 3A. Calendar Header

Full-width bar with `background: white`, `border-bottom: 1px solid #E4E7EC`, `padding: 16px 20px`.

**Left:** `←` / `→` chevron buttons (`28×28px`, `border-radius: 8px`, `bg: #F2F4F7`, icon `#344054`) flanking the current month+year in `16px 800 #172B3A`.

**Center:** `Today` pill button — `11px 700 #1268F3 bg-#EEF4FF border-radius:9999px padding:6px 14px`.

**Right (admin only):** User filter select — same style as existing period selects. "All Team" default.

### 3B. Day-of-Week Strip

Seven equal columns. `10px 700 uppercase tracking-[0.08em] #98A2B3`. `background: #F8FAFC`. `border-bottom: 1px solid #E4E7EC`. `padding: 10px 0`. Center-aligned text.

### 3C. Month Grid

7-column CSS grid. Each day cell: `min-height: 100px`, `border-right: 1px solid #F2F4F7`, `border-bottom: 1px solid #F2F4F7`. Last column and last row: no border.

**Day number:** Top-right corner. `12px 700`.
- Today: `24×24px` circle `bg-#1268F3 text-white`
- Other days in current month: `#172B3A`
- Days outside current month: `#D1D9E6`

**Follow-up pills (inside day cell, stacked vertically, gap 2px, max 3 visible):**
Each pill: `border-radius: 4px`, `padding: 2px 6px`, `font: 10px 700`, truncated with ellipsis, `max-width: 100%`.

| State | Background | Text Color |
|---|---|---|
| Pending (future) | `#EEF4FF` | `#175CD3` |
| Due today | `#1268F3` | `white` |
| Completed | `#ECFDF3` | `#16803C` |
| Overdue (past, not completed) | `#FEF3F2` | `#B42318` |

If more than 3 follow-ups on a day: show `+N more` pill in `#F2F4F7 text-#667085`.

### 3D. Follow-Up Detail Popover

Clicking a pill opens a floating popover (not a full modal). `border-radius: 12px`, `border: 1px solid #E4E7EC`, `box-shadow: 0 8px 24px rgba(7,29,43,0.12)`, `width: 280px`, `padding: 16px`, `background: white`.

**Header:** Inquiry ID in `11px 700 #1268F3` + client name `13px 700 #172B3A`.

**Body rows (12px, gap 8px):**
- `calendar` icon + date + time (or "No time set")
- `user` icon + assigned-to name
- `file-text` icon + note text (2-line clamp)

**Footer (space-between):**
- Status badge: same pill style as inquiry list
- Action buttons: `Complete` (`bg-#ECFDF3 text-#16803C`) · `Delete` (`bg-#FEF3F2 text-#B42318`) — `10px 700 border-radius:6px padding:5px 10px`

Popover closes on outside click. Arrow pointer toward the pill that triggered it.

### 3E. Add Follow-Up Button

Floating `+` button fixed bottom-right. `52×52px`, `border-radius: 9999px`, `bg-#1268F3`, white `plus` icon `20×20px`. `box-shadow: 0 4px 12px rgba(18,104,243,0.35)`. Opens the existing Add Follow-Up modal.

---

## Screen 4 — Group Chat (All Roles)

A **Messages** view in the left nav (`message-square` icon). Real-time group messaging. Admin can create groups and add members. All users can chat.

### 4A. Messages Layout

Two-column layout: **sidebar** (left, `280px` fixed) + **chat panel** (right, flex-1). On mobile: sidebar collapses to a list view; tap a conversation to open it full-screen with a back button.

### 4B. Sidebar

`background: white`, `border-right: 1px solid #E4E7EC`, full height.

**Sidebar header:** `padding: 16px`, `border-bottom: 1px solid #E4E7EC`.
- Title `14px 700 #172B3A` "Messages"
- Right: `+` icon button (`32×32px bg-#EEF4FF text-#1268F3 border-radius:8px`) — opens New Group modal (admin only; members see it disabled/hidden)

**Search bar:** `margin: 12px`, `border-radius: 8px`, `bg: #F2F4F7`, `height: 36px`. `search` icon `14px #98A2B3`. Placeholder `12px #98A2B3` "Search conversations…".

**Conversation list (scrollable):**
Each item: `padding: 12px 16px`, `cursor: pointer`. Selected: `bg-#EEF4FF`. Hover: `bg-#F8FAFC`.

Row layout (horizontal flex):
- Left: Group avatar `40×40px border-radius:12px bg-#1268F3` with initials or group icon in white `13px 800`
- Center: Group name `13px 700 #172B3A` + last message preview `11px #98A2B3` (1-line clamp)
- Right: Timestamp `10px #98A2B3` + unread badge `18×18px border-radius:9999px bg-#1268F3 text-white 9px 800` (hidden when 0)

### 4C. Chat Panel

**Chat header:** `height: 60px`, `border-bottom: 1px solid #E4E7EC`, `padding: 0 20px`. Horizontal flex.
- Left: Group avatar `36×36px` + group name `14px 700 #172B3A` + member count `11px #98A2B3`
- Right: `info` icon button (`32×32px bg-#F2F4F7 border-radius:8px`) → opens Group Info panel (slide-in from right)

**Message area (scrollable, flex-col-reverse):** `padding: 16px 20px`, `background: #F8FAFC`.

**Message bubble:**
- Other user: `background: white`, `border: 1px solid #E4E7EC`, `border-radius: 4px 12px 12px 12px`, aligned left. Sender name `10px 700 #1268F3` above bubble.
- Current user: `background: #1268F3`, `color: white`, `border-radius: 12px 4px 12px 12px`, aligned right. No sender label.
- Bubble: `max-width: 68%`, `padding: 10px 14px`, `font: 13px 600`. Timestamp `10px` below bubble, outside, muted.
- Date dividers: centered `10px #98A2B3 bg-#F2F4F7 border-radius:9999px padding:4px 12px` — e.g. "Today", "Yesterday", "08 Aug 2026".

**Compose bar:** `background: white`, `border-top: 1px solid #E4E7EC`, `padding: 12px 16px`. Horizontal flex, `gap: 8px`.
- Textarea: `flex-1`, `border-radius: 10px`, `border: 1px solid #E4E7EC`, `padding: 10px 14px`, `font: 13px`, auto-resize up to 5 lines. Placeholder "Type a message…"
- Send button: `40×40px`, `border-radius: 10px`, `bg-#1268F3`, white `send` icon. Disabled state: `bg-#E4E7EC icon-#98A2B3` when textarea empty.

### 4D. New Group Modal (Admin Only)

`border-radius: 14px`, `width: 440px`, `padding: 24px`.

**Header:** "Create Group" `16px 800 #172B3A` + `×` close icon.

**Fields (stacked, gap 16px):**
1. **Group Name** — text input, placeholder "e.g. Project Alpha Team"
2. **Add Members** — searchable multi-select. Shows approved accounts. Each selected member appears as a removable pill `bg-#EEF4FF text-#175CD3 border-radius:9999px padding:4px 10px font:11px 700` with `×` to remove.

**Footer:** Cancel (ghost) + "Create Group" (`bg-#1268F3 text-white`) buttons, `border-radius: 8px`, `height: 40px`.

### 4E. Group Info Panel (Slide-in)

Slides in from the right over the chat panel. `width: 300px`, `background: white`, `border-left: 1px solid #E4E7EC`.

**Header:** "Group Info" `14px 700` + close `×`.

**Group avatar + name** (centered): `64×64px border-radius:16px bg-#1268F3` + name `16px 800 #172B3A` + created-by `11px #98A2B3`.

**Members list:** Each row — avatar `32×32px` + name `12px 700 #172B3A` + role badge. Admin row gets a `crown` icon `12px #D97706`. Admin only: `Remove` text button `11px #B42318` on each non-admin row.

**Footer (admin only):** "Add Members" button (full-width outline style) + "Delete Group" text link `11px #B42318`.

---

## Icon Set

All icons from Lucide (stroke-based, `stroke-width: 2`–`2.5`):

`check-square` · `check-circle` · `alert-circle` · `clock` · `user` · `plus` · `bar-chart-2` · `file-text` · `shield-check` · `tag` · `calendar` · `message-square` · `search` · `send` · `info` · `chevron-left` · `chevron-right` · `crown` · `x`

---

## Responsive Notes

- Mobile (<640px): KPI card grid collapses to 2 columns; inquiry activity cards stay 3-column but scale down.
- Tables become horizontally scrollable at `min-width: 680px` (task table) and `min-width: 880px` (team table).
- Hero stat strip stays 3 columns at all sizes.
- "Generated" date hidden on mobile (space-between flex item).

---

## Interaction States

| Element | Default | Hover | Active/Selected |
|---|---|---|---|
| Tab | `#98A2B3` text | `#344054` text | `#1268F3` text + underline |
| Table row | white | `#F8FAFC` bg | — |
| View button | `#EEF4FF` bg, `#175CD3` text | `#1268F3` bg, white text | — |
| Overdue card | `#E4E7EC` border | — | `#FECDCA` border when value >0 |
