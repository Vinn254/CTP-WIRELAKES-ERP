# Module 11 — Finance, Procurement & Inventory

The programme's money: what comes in, what goes out, which budget line it belongs to, what the store holds, what the
office buys, and what an E-Fundi or a trainer is actually paid. Blueprint §14.

## Scope

| Area | Screen(s) / table | Owner |
| --- | --- | --- |
| Budget lines for a fiscal year, spent vs planned bars | `budget_lines`, `admin/finance/budget` | finance |
| Money register — every kobo in and out, one line each | `finance_entries`, `admin/finance/ledger` | finance |
| Suppliers, their terms and whether they are still used | `suppliers`, `admin/finance/suppliers` | finance |
| Purchase request → quotations → chosen supplier → order → goods in → payment | `purchase_requests`, `purchase_quotations`, `purchase_orders`, `admin/finance/procurement/{id}` | finance |
| Stock cards, movements, reorder level, goods out | `inventory_items`, `stock_movements`, `admin/finance/inventory/{id}` | finance |
| Payouts — two income streams, each with its own signed path | `payouts`, `admin/finance/payouts/{id}` | finance |
| Materials the programme provides for a project | `project_materials`, `admin/projects/{id}`, `/portal/projects/{id}` | the project page, the finance rule |
| Membership fee and course fee landing on the register | the CTP/E-Fundi fee form and the cohort seat, `admin/finance/ledger?kind=…` | Module 2 / 4 / 5 screens |
| A member raising a need or a purchase | `/portal/procurement` | member portal |
| A fundi seeing their own payouts and what was deducted | `/portal/earnings` | member portal |

Finance is a top-level department. Nothing here was moved out of it, and no other module's screen was moved into it —
each module keeps its own forms and hands the money to `FinanceEntry::post()`.

## One writer for the register

`FinanceEntry::post($kind, $title, $amount, $extra)` is the only way a line is booked. Everything else is a screen
collecting what the office saw and calling it.

* `kind` is a closed list (`INCOME_KINDS` + `EXPENSE_KINDS`). `direction` is derived from the kind, so nobody can file
  money in as money out by accident.
* Money out always carries a budget category. `KIND_BUDGET` maps a kind to its line — a trainer payout is
  `training`, transport and accommodation are `operations` — and a purchase order or a project payout keeps the
  category already on its own row. Money in is never charged to a budget.
* `reference` is `FIN-YYYY-#####`, counted inside the fiscal year, and `source_type`/`source_id` point back at the row
  that produced the money. A line is corrected with an **opposite line**, never edited away or deleted.
* The year filter on the register is real: totals are sums of that year only, so a plan is compared with what was
  actually spent in the same twelve months.

## Materials the programme provides (§14.5, §14.6 step 6)

A project has two material fields and they do different jobs:

* `projects.materials` — the office's plain-word list of what the job needs, shown to members on the project page.
* `project_materials` rows (`ProjectMaterial::providedMaterials()` on the project) — each line named, with a quantity,
  a unit, a cost each, and whether it was actually handed over.

The office adds the lines when it posts the project (five blank rows on the form) or later on the project page, either
by naming the material or by picking a **stock card**, which copies the name, the unit and the cost off the card. A
line is never deleted: a material that turns out not to be needed simply stays un-supplied, so the plan stays visible
next to the money. A correction is allowed only before hand-over — once `supplied_on` is set the line is frozen and
anything extra becomes a second line.

The fundi sees the same table on the project, with the cost of every line and the rule written beside it: *whatever is
recorded as handed over is deducted from the payout*. That deduction is read from
`Project::materialsSuppliedKsh()` — the office's materials box on the payout form is left empty on purpose and the
system fills it, so a typed figure can never disagree with what the store gave out. The payout page then lists the
lines behind the number, and the fundi's earnings page lists the same lines under "Total taken off the payout".

Nothing is counted twice: goods out of the store post `materials_issued` on the stock card, a market purchase posts
`procurement`, and the payout only *deducts* the handed-over value from what the fundi is paid.

## Fees are money in

A membership fee and a course fee are income, and both post their own ledger line the moment the office records them:

* `ReviewActions::postMembershipLine()` — one `membership_fee` line per member, titled with the name and the member
  code, method and reference from the application. The guard is on `kind + member`, so re-recording a fee cannot put
  the money in twice.
* `CohortController::fee()` — one `training_fee` line per **cohort and CTP**, recorded on the seat. Only a trainee
  holding an approved seat has a fee; a cohort with no course fee returns 422 rather than inventing an amount.

Both are visible to the person who paid: the CTP's training page carries their own course fee — amount, date, route
and reference — with the line "never pay a fee to an individual". The register filter `?kind=membership_fee` and
`?kind=training_fee` shows the same money as the programme sees it.

## The payout path (§14.6)

`Payout::PATH` is a closed, per-stream list of steps and every stage change walks it in order:

| Project income | Training income |
| --- | --- |
| work completed → hirer payment confirmed → payout calculated → Finance Officer approves → released | report filed and certified count confirmed → calculated → Programme Manager review → Finance releases → released |

* No project payout leaves `awaiting_hirer_payment` until the office records what the hirer actually paid, and that
  amount cannot be below the agreed amount on the payout.
* Staff prepare; **only a programme executive (`superadmin`, the Finance Officer) approves and releases a project
  payout and pays money out**, via `Finance::requireRelease()`. Where an action is theirs, the button is shown disabled
  with the reason instead of hidden, so a staff member can see what they are waiting for.
* A payout on hold keeps its reason and returns to the same step when resumed; a refused payout keeps its reason and
  is never deleted, so the fundi can read why on `/portal/earnings`.
* `net_ksh` is written once from the figures, and the calculation string is stored with the row — the fundi sees the
  arithmetic, not just the total.

## Procurement and stock

* A request is raised by the office, or by an E-Fundi for a project they are working on (`/portal/procurement`). A CTP
  never raises one — it mobilises, and the office buys on its behalf; the office form is where that request is typed in.
  It moves `submitted → approved → quoted → ordered → received → paid` (a request can also be `rejected`); a decision
  needs a reason the raiser can read.
* A supplier is only ever selected from the register. Choosing a quotation is what fixes the price, and the order line
  is what posts the `procurement` expense when it is paid.
* A stock card holds quantity, unit, reorder level and cost each. Every movement is a row — in, out, adjustment — and
  a goods-out cannot take the card below zero. Reorder level is a flag on the register, not a separate list.

## Verification

`php artisan migrate` then the read/write sweep on `http://127.0.0.1:8001` (this app runs on 8001 — 8000 belongs to
another project). Compiled Blade is linted with `php -l storage/framework/views/*.php` after `view:cache`, which
catches parse errors that the compiler itself does not.
