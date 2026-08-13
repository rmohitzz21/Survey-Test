# Survey Pacific — Create Real Inquiries Prompt

Use this prompt with Claude (claude.ai or Claude Chrome extension) when browsing the live system at https://inquiry.surveypacific.com

---

## PROMPT (copy everything below this line)

---

You are acting as a real team of **Survey Pacific**, a market research company. Your job is to create a new team member, create 2 realistic inquiries, and simulate the full work journey — each inquiry passes tasks down a 3-person chain in opposite directions. Follow every phase in order. Do not skip any step.

**Live URL:** https://inquiry.surveypacific.com

**Accounts:**

| Name | Email | Password | Role |
|------|-------|----------|------|
| Admin | admin@surveypacific.com | Survey@404 | Master Admin |
| Mohit | rmohit@gmail.com | Admin@123 | Member |
| Rudra | (to be created) | Admin@123 | Member |

---

## ── PHASE 0: Create new member Rudra ──

**Login as:** `admin@surveypacific.com` / `Survey@404`

1. Log out if already logged in. Go to https://inquiry.surveypacific.com
2. On the **login page**, find the **Sign Up / Register** form.
3. Register a new account with:

| Field | Value |
|-------|-------|
| Name | Rudra |
| Email | rudra@surveypacific.com |
| Password | Admin@123 |

4. Submit the registration.
5. Now log in as **Admin** (`admin@surveypacific.com` / `Survey@404`).
6. Go to the **Team** tab.
7. Find **Rudra** in the pending accounts list and click **Approve**.
8. Confirm Rudra's status shows as Active/Approved.

---

## ── PHASE 1: Admin creates INQ1 — assigns first task to Mohit ──

Still logged in as Admin.

9. Click **"New Inquiry"** and fill in:

| Field | Value |
|-------|-------|
| Client Name | Rajesh Mehta |
| Company | Tata Consumer Products Ltd |
| Country | India |
| Client Type | Existing Contact / Prospect |
| Proposal Value | 185000 |
| Email | rajesh.mehta@tataconsumer.com |
| Phone | +91 98201 44567 |
| Website | www.tataconsumerproducts.com |
| Due Date | 7 days from today |

**Requirement:**
> We require a comprehensive retail consumer satisfaction survey across 6 metro cities — Mumbai, Delhi, Bangalore, Chennai, Hyderabad, and Pune. The study should cover 1,200 respondents (200 per city), focusing on brand perception, product quality, and purchase intent for our new beverage line. Deliverables include a detailed report with city-wise analysis and an executive summary presentation.

**Assign To:** Mohit

**First Task Instruction:**
> Review the client brief and prepare a detailed project proposal — include methodology (CAPI/F2F), city-wise sample plan, project timeline, and cost breakup. Share the draft with Admin before sending to the client.

10. Save the inquiry. Note the ID (SP-2026-XXX). Call it **INQ1**.

---

## ── PHASE 2: Admin updates INQ1 stage ──

11. On **INQ1**, click **"Update Stage"** and set:

| Field | Value |
|-------|-------|
| Stage | Client Communication Ongoing |
| Outcome | In Progress |
| Outcome Reason | Requirement Under Discussion |
| Remark | Initial call completed with Rajesh Mehta. Client confirmed budget of ₹1.5–2L and expects proposal by next week. Mohit to lead the proposal drafting. |
| Proposal Value | 185000 |

12. Save.

---

## ── PHASE 3: Logout → Login as Mohit ──

13. Log out. Log in as **Mohit** (`rmohit@gmail.com` / `Admin@123`).

---

## ── PHASE 4: Mohit works on his INQ1 task, then assigns next task to Rudra ──

14. From the dashboard, find **INQ1** (Tata Consumer Products). Expand it.
15. Find the task assigned to Mohit ("Review the client brief and prepare a detailed project proposal…").
16. Change the status to **"In Progress"**.
17. In the **"✎ Your update"** remark box, type and save:
> Proposal draft is 70% complete. Methodology section and city-wise sample plan done. Cost section pending — need competitor pricing data. Will complete by tomorrow and share with Admin for review.

18. Now click **"Add Task"** on **INQ1** and fill in:

| Field | Value |
|-------|-------|
| Assign To | Rudra |
| Due Date | 4 days from today |
| Instruction | Research competitor pricing for retail satisfaction surveys in FMCG sector — check HUL, ITC, Marico benchmarks. Prepare a 1-page pricing brief and share with Mohit before EOD tomorrow so it can be included in the Tata proposal. |

19. Save the task.

---

## ── PHASE 5: Logout → Login as Rudra ──

20. Log out. Log in as **Rudra** (`rudra@surveypacific.com` / `Admin@123`).

---

## ── PHASE 6: Rudra works on his INQ1 task ──

21. From the dashboard, find **INQ1** (Tata Consumer Products). Expand it.
22. Find the task assigned to Rudra ("Research competitor pricing…").
23. Change the status to **"In Progress"**.
24. In the remark box, type and save:
> Competitor research done. HUL quoted ₹1.8L for similar 6-city FMCG study last quarter. ITC did a smaller 4-city study at ₹1.35L. Marico's 2-city NPS study was ₹75K. Tata's budget of ₹1.5–2L is well-positioned. Full brief sent to Mohit.

25. Change the status to **"Done"**.

---

## ══════════════════════════════════════════════════════════════
## REVERSE CHAIN — INQ2: Rudra → Mohit → Admin
## ══════════════════════════════════════════════════════════════

---

## ── PHASE 7: Rudra creates INQ2 — assigns first task to Mohit ──

Still logged in as Rudra.

26. Click **"New Inquiry"** and fill in:

| Field | Value |
|-------|-------|
| Client Name | Priya Nair |
| Company | Flipkart Internet Pvt Ltd |
| Country | India |
| Client Type | New |
| Proposal Value | 95000 |
| Email | priya.nair@flipkart.com |
| Phone | +91 80471 23890 |
| Website | www.flipkart.com |
| Due Date | 10 days from today |

**Requirement:**
> Flipkart requires an online customer experience survey targeting 800 recent buyers who made a purchase in the last 30 days. The study should measure delivery satisfaction, app usability, and Net Promoter Score (NPS). Responses to be collected via mobile-optimised online link shared through email and SMS. Final dataset in SPSS and Excel with a summary report.

**Assign To:** Mohit

**First Task Instruction:**
> Draft an online survey questionnaire — maximum 15 questions covering delivery time, packaging quality, app ease of use, and NPS. Share the draft with Rudra for internal review before presenting to the client.

27. Save the inquiry. Note the ID. Call it **INQ2**.

---

## ── PHASE 8: Logout → Login as Mohit ──

28. Log out. Log in as **Mohit** (`rmohit@gmail.com` / `Admin@123`).

---

## ── PHASE 9: Mohit works on INQ2 task, then assigns next task to Admin ──

29. From the dashboard, find **INQ2** (Flipkart). Expand it.
30. Find the task assigned to Mohit ("Draft an online survey questionnaire…").
31. Change status to **"In Progress"**.
32. In the remark box, type and save:
> Questionnaire draft ready — 13 questions covering delivery experience (Q1–5), app usability (Q6–10), and NPS (Q11–13). Draft shared with Rudra for review. Awaiting feedback before sending to client.

33. Change status to **"Submitted for Review"**.

34. Click **"Add Task"** on **INQ2** and fill in:

| Field | Value |
|-------|-------|
| Assign To | Admin |
| Due Date | 7 days from today |
| Instruction | Review the finalised questionnaire and Flipkart project proposal. Approve the budget of ₹95,000 and send the formal proposal email to Priya Nair at priya.nair@flipkart.com. Follow up within 2 days if no response. |

35. Save the task.

---

## ── PHASE 10: Logout → Login as Admin ──

36. Log out. Log in as **Admin** (`admin@surveypacific.com` / `Survey@404`).

---

## ── PHASE 11: Admin works on INQ2 task and updates stage ──

37. From the dashboard, find **INQ2** (Flipkart). Expand it.
38. Find the task assigned to Admin ("Review the finalised questionnaire…").
39. Change status to **"In Progress"**.
40. In the remark box, type and save:
> Proposal reviewed and approved. Formal proposal email sent to Priya Nair with project scope, timeline (3 weeks), and budget of ₹95,000. Awaiting client sign-off. Follow-up scheduled for Friday.

41. Click **"Update Stage"** on **INQ2** and set:

| Field | Value |
|-------|-------|
| Stage | Proposal Sent |
| Outcome | In Progress |
| Outcome Reason | Proposal Sent |
| Remark | Proposal sent to Flipkart (Priya Nair) on ₹95,000 budget. Client to revert within 3 working days. Mohit and Rudra on standby for fieldwork planning once confirmed. |
| Proposal Value | 95000 |

42. Save.

---

## ── PHASE 12: Final verification ──

Still logged in as Admin.

43. On the dashboard confirm:
- **INQ1** — Stage: "Client Communication Ongoing", 2 tasks visible (Mohit: In Progress, Rudra: Done)
- **INQ2** — Stage: "Proposal Sent", 2 tasks visible (Mohit: Submitted for Review, Admin: In Progress)
- Click **"Summary"** on both — verify client details, steps table, and key info strip all display correctly.

Report back:
```
INQ1 ID: SP-2026-___  (Rajesh Mehta / Tata Consumer Products)
INQ2 ID: SP-2026-___  (Priya Nair / Flipkart)
INQ1 Stage: ___   INQ1 Tasks: ___
INQ2 Stage: ___   INQ2 Tasks: ___
Rudra account created & approved: Yes / No
All remarks saved: Yes / No
Any errors: ___
```
