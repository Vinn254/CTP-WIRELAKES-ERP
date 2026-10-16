# Module 7 — Project Execution & Payment

Blueprint §10: the part where an awarded project is actually built. The module opens **four tables**
(`work_orders`, `work_order_milestones`, `work_order_updates`, `work_order_disputes`), one support class
(`app/Support/Execution.php`), one office desk (**Delivery register**, `admin/execution`) and one member screen
(**My work orders** / **My attachment work**, `portal/work`). The department is top-level on the board and `live`;
its tile badge counts the orders that are waiting on somebody — issued, waiting on the hirer, in dispute or waiting
to be handed to Finance.

It does not duplicate a neighbour: the posting, its materials list and its applications are Module 1, the shortlist
and the award are Module 6, the fundi's own record is Module 5, **all money is Module 11** (`Payouts::raise()` is the
only thing this module does to a payout), and the counts M&E reads come from these rows.

## One order per award (§10.3)

`Project::awardTo()` — the single place any award happens in the app — calls `Execution::openFor()` and the work order
is born with it: `WO-2026-0001`, the project's scope, deliverables, site, start and deadline, the worker's name frozen
onto the row (`assignee_name`, so the sheet still reads correctly after the person changes), and the §10.2 default
split of **30 / 40 / 30**. Nothing else creates an order, so a marketplace award and an award from the project page
cannot produce two different shapes of record. An order is never deleted: it ends `closed` or `cancelled` with the
note and the date on it.

## The steps, and whose they are (§10.4)

| Step | Who | On the screen |
| --- | --- | --- |
| Order issued on the award | automatic | the award itself |
| Accept and sign the scope | the worker / trainee | `portal/work/{order}` — `accepted_on`, `signature`, `accept_note` |
| Plan: schedule, amount, dates, split | staff | the sheet, while the order is `issued`, `accepted` or `in_progress` |
| Materials handed over | staff at the store | the project's material lines, moved against the order |
| Daily progress + photos | the worker | **My work orders** |
| Site visit | staff / supervisor | the sheet |
| Sign off a milestone | staff | the stage row; a wrong sign-off is lifted with a reason |
| Completion requested | the worker | sets 100% and `completion_submitted` |
| The hirer inspects | **recorded by the office** | confirmation form: who, when, what they said, signed sheet attached |
| An issue is raised instead | staff | opens a dispute with the 48 hours running |
| Mediation | staff | the dispute's own `mediation_note` |
| The decision | **a programme executive** | one of five outcomes |
| Hand to Finance | staff | creates the payout row in Module 11 |
| Close | staff | the note stays, the row stays |

The hirer has no account, so step 6 is the office writing down what the hirer said and attaching their signed sheet.
The sheet is stored on the private disk and served only through `GET /admin/execution/{order}/confirmation`, behind
the same guard as the page it belongs to; so are the progress photos and the dispute evidence.

## Progress is one number, and it never goes down (§10.4 step 3)

Every entry — a worker's day, a supervisor's visit, a completion — carries an optional percentage. The order keeps the
**highest figure ever reported on it**; `rollUp()` returns that and every screen reads the same field, so the register
and the sheet can no longer disagree. A wrong 80% is answered by the next line saying 55%, not by editing yesterday's
entry, and both lines stay readable with who wrote them and their photo.

## The split, and what a signature means (§10.5)

Three schedules — fixed price, milestone-based, time and materials. Saving a split lays the shares over the stages
**nobody has signed yet**: stage names are reused, extra stages are dropped with a note, and the save is refused
(422) if the new split would move or drop a stage that carries a signature — that sign-off has to be lifted first.
A signed stage keeps its own figure and date. The plan and the stages are written in one transaction, so a refusal
leaves the order exactly as it was.

## Disputes (§10.6)

An issue is recorded with the hirer's words in full and any evidence; `dueOn()` is 48 hours from `raised_on`, and the
clock is on the record, not on anyone's reading of it. The worker answers inside or after the window — a late answer
is still taken, and the sheet marks it `after the 48 hours`. Staff mediate; **only a programme executive decides**,
through the same `Finance::requireRelease()` gate that approves a payout. The five outcomes are closed and each has a
fixed consequence: rework sends the order back to `in_progress` (a completion is asked again), refund closes it with no
payout, and the three payment outcomes confirm it, close the project and put the worker back on the available list.
A decision is never rewritten — a second issue is a second dispute.

## The handover, and where the money stops (§10.4 steps 8–10)

`Execution::handover()` opens the payout on Module 11's register at stage `awaiting_hirer_payment`: agreed amount,
materials **read from what the store actually issued**, service commission and mobilization credit as typed by the
office, and every deduction a Director decided on the order. Nothing is released here. Two guards keep the two records
honest: a confirmed fundi order **cannot be closed while no payout is open for it** (the worker would be left unpaid),
and a deduction decided *after* the handover is carried onto the payout row and its calculation rewritten — unless the
payout is already approved or released, which is refused, and never if the total would take the worker below zero.

A CTP attachment order is delivered, inspected and closed the same way but **never reaches Finance**: the handover
refuses it, because attachment is training credited on the trainee's record, not a payout. A trainee likewise has no
dispute answer box — the office settles it with the hirer and notes it.

## Not counted yet

§10.7 asks for worker and hirer **ratings**. The programme keeps no ratings table anywhere, so this module does not
invent one: performance is read from the records that exist — orders closed, work confirmed, disputes and their
outcomes, deductions. When a rating is introduced it belongs to Module 12 with the rest of the indicators.

## Verification

`php artisan migrate` (the four tables, plus `mediation_note` on `work_order_disputes` so a mediation record survives
its own decision), `php artisan view:cache` with `php -l storage/framework/views/*.php`, and the whole path run end to
end on `http://127.0.0.1:8001` (this app runs on 8001; 8000 belongs to another project): an award that generated
`WO-2026-00001` with the default 30/40/30 stages; materials issued against it and the project's supplied value moving
with it; a supervisor's site visit and a worker's logged days with photos; stages signed, a sign-off lifted and
re-signed; a 25/25/25/25 re-plan that refused to move a signed stage until it was lifted; completion asked, the hirer's
confirmation recorded with the signed sheet, and the handover that opened `PAY-2026-00006`. A second order was disputed
twice — rework, then a decided deduction — and the payout followed the decision to KSh 13,000 of deductions, while an
oversized deduction and a confirm-over-an-open-dispute were both refused, and a third dispute on the same order was
answered, mediated and sent back for rework. A third order, awarded to a CTP as
attachment, was delivered and closed and correctly refused at the handover. Refused with a readable 422 throughout:
logging before accepting, accepting twice, signing a stage a second time, handing over before confirmation,
confirming with unsigned stages, closing without the payout, and staff attempting a decision (403). Two members were
kept out of each other's orders by 404 — an outsider could not open the order or answer its dispute. `errors/422.blade.php` was added so a refusal says what is not open yet. The register's seven tabs,
its search, the department tile and `admin/activity` all read correctly, and a read sweep of every other department's
screens was run against the same data before committing.
