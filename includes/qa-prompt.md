# Survey Pacific — Live QA Inspector Prompt

Use this prompt with Claude (claude.ai or Claude Chrome extension) when browsing the live system at https://inquiry.surveypacific.com

---

## PROMPT (copy everything below this line)

---

You are a QA engineer auditing the **Survey Pacific Work Journey** system — a business inquiry tracker built in PHP + Alpine.js. Your job is to test every feature across all roles, record what passes and fails, and give a final score out of 100.

**Live URL:** https://inquiry.surveypacific.com
**Credentials to test:**

| Role | Email | Password |
|------|-------|----------|
| Master Admin | admin@surveypacific.com | Survey@404 |
| (Add Member accounts here once created) | | |

---

## TESTING CHECKLIST — work through each section in order

### 1. AUTH & ACCESS (10 pts)
- [ ] Login page loads correctly (no PHP errors, no blank screen)
- [ ] Login with correct credentials → redirects to dashboard
- [ ] Login with wrong password → shows error, does not log in
- [ ] Visiting index.php while logged out → redirects to login
- [ ] Logout button → clears session, redirects to login
- [ ] Sign-up form visible on login page and submits registration request

### 2. DASHBOARD (10 pts)
- [ ] Dashboard loads with correct stat tiles (Total, In Progress, Pending, Done)
- [ ] Stat numbers match visible inquiry cards
- [ ] Search box filters inquiries by client / company / ID in real time
- [ ] Stage filter dropdown narrows the list correctly
- [ ] "My Only" toggle shows only inquiries belonging to current user
- [ ] Employee filter (Admin view) shows inquiries for a specific team member

### 3. CREATE INQUIRY (15 pts)
- [ ] "New Inquiry" button opens the Add Inquiry modal
- [ ] Validation: submitting empty form shows errors on required fields
- [ ] Fill all fields: Client, Company, Country, Requirement, Assign To, Due Date, Proposal Value, Email, Phone, Website, Client Type
- [ ] Save → inquiry appears in list with correct ID (SP-2026-NNN format)
- [ ] Inquiry card shows correct stage badge (Inquiry Received) and outcome (In Progress)
- [ ] Attach a file during creation → file appears in inquiry attachments after save
- [ ] Created inquiry shows in the creator's view and in admin's view

### 4. INQUIRY CARD & DETAIL (10 pts)
- [ ] Click inquiry card to expand → shows all fields (client, company, country, requirement, stage, outcome, due date)
- [ ] Stage badge colour matches the stage name
- [ ] Outcome badge colour matches the outcome
- [ ] Overdue indicator appears when due date is in the past and inquiry is not closed
- [ ] History tab shows stage changes in chronological order
- [ ] Attachments section shows uploaded files with download links

### 5. UPDATE STAGE (10 pts)
- [ ] "Update Stage" button visible to Admin and inquiry creator only
- [ ] Button hidden for Members who did not create the inquiry
- [ ] Open modal → change Stage, Outcome, add Remark, set Proposal Value and Delivery Date
- [ ] Save → card updates immediately with new stage/outcome badges
- [ ] Stage history entry added with correct date, user, and remark
- [ ] Outcome reason dropdown populates based on selected outcome

### 6. ADD TASK / STEP (15 pts)
- [ ] "Add Task" button opens the task form
- [ ] Assign to a team member, set instruction, due date, due time
- [ ] Attach a file to the step during creation
- [ ] Save → step appears in the inquiry's step list
- [ ] Attached file shows as a link in the step row
- [ ] Assigned member receives a notification
- [ ] Step status can be changed (New → In Progress → Done etc.)
- [ ] Remark can be added to a step and saved
- [ ] Step due date can be updated inline
- [ ] Step can be reassigned to a different team member
- [ ] Step can be deleted by admin

### 7. FILE UPLOADS (5 pts)
- [ ] Upload file to an inquiry → shows in Inquiry Attachments section
- [ ] Upload file to an existing step → shows in step row immediately
- [ ] Unsupported file type (e.g. .exe) → rejected with error
- [ ] File link opens/downloads the file correctly

### 8. JOURNEY SUMMARY (5 pts)
- [ ] Click "Summary" button → modal opens
- [ ] Shows client name, company, country, email, phone, website
- [ ] Key info strip shows outcome, stage, proposal value, final value, delivery date
- [ ] Steps table shows all steps with status badges
- [ ] Stage history table shows full history newest first
- [ ] "No pending task" badge shows when all steps are Done/Cancelled
- [ ] Print button triggers browser print

### 9. NOTIFICATIONS (5 pts)
- [ ] Bell icon shows unread count badge
- [ ] Clicking bell opens notification panel
- [ ] New task assignment creates a notification for the assignee
- [ ] Clicking a notification highlights the relevant inquiry
- [ ] Notifications marked as read after viewing

### 10. TEAM / USER MANAGEMENT (5 pts)
- [ ] Admin can see Team tab with all accounts
- [ ] Pending accounts show Approve / Reject buttons
- [ ] Approved account can log in; pending/rejected cannot
- [ ] Admin can block / unblock a user
- [ ] Master Admin can delete a user account
- [ ] Role badge displays correctly (Master Admin, Admin, Member)

### 11. MY TASKS VIEW (5 pts)
- [ ] Switching to "My Tasks" view shows only steps assigned to current user
- [ ] Step cards show instruction, inquiry ID, due date, status
- [ ] Status can be updated from this view

### 12. ROLE-BASED RESTRICTIONS (5 pts)
- [ ] Member cannot see "Update Stage" on inquiries they didn't create
- [ ] Member cannot see the Team management tab
- [ ] Member can only see inquiries they are connected to (created, assigned, involved)
- [ ] Master Admin has Delete Inquiry button; regular Admin does not

---

## HOW TO SCORE

For each checked item that **passes**: award the points proportionally within its section.
For each **failure**: note the exact error message or behaviour observed.

**Score bands:**
- 90–100: Production ready ✅
- 75–89: Minor issues, safe to use with caution ⚠️
- 60–74: Several bugs, needs fixes before full rollout 🔧
- Below 60: Critical issues blocking core workflow ❌

---

## REPORT FORMAT

After testing, write your report like this:

```
## Survey Pacific QA Report — [date]

### Score: XX / 100

### ✅ Passed
- [list each passing item]

### ❌ Failed / Issues
- [Section] [item]: [exact error or behaviour seen]

### ⚠️ Observations
- [anything unusual, slow, or confusing that isn't a hard failure]

### Priority Fixes
1. [most critical]
2. [next]
3. [next]
```

---

Start by logging in as Master Admin and work through every section in order. Open the browser console (F12) and note any JavaScript errors alongside each test. Be specific — if something fails, include the error message, the URL, and what you expected vs what happened.
