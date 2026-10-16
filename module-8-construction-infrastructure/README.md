# Module 8 — Construction & Infrastructure

Blueprint §11: the engineering record that sits behind a buildable project — the site read before anyone prices it,
the bill of quantities line by line, the labour counted on site, the inspections with every item marked, and the
changes the design did not carry. The module opens **five tables** (`site_assessments`, `boq_items`,
`labour_entries`, `site_inspections`, `site_inspection_items`) plus **variation orders** (`variation_order`),
one support class (`app/Support/Construction.php`), one office desk (**Construction files**, `admin/construction`)
and one member screen (**Items to put right**, `portal/fixes`). The department is top-level on the board and `live`;
its tile badge counts the inspection items the office and the worker still owe an answer on.

It is the *file*, not the job. The order itself, the worker's days-as-progress and the hirer's confirmation stay on
the Module 7 **delivery register**; every shilling is still worked out on the Module 11 register. Construction keeps
the drawing-and-engineering layer a delivery order does not carry: a BoQ priced line by line, a site read with
photographs, an inspection where each item passes or fails, and a variation that re-opens the priced total.

## The site is read before it is priced (§11.2)

An assessment records `visited_on`, ground conditions, access, utilities, risks, the findings in full and a
`recommendation` (`proceed`, `proceed_with_conditions`, `do_not_proceed` yet, `defer`), with a single site photograph
stored on the private disk and served only through `GET /admin/construction/assessment/{assessment}/photo`, behind the
same guard as the file it belongs to. A project keeps every visit — a second read is a second row, never an overwrite.

## The bill of quantities is a sum of lines, never a typed total

`Construction::boq()` takes description, unit, quantity and unit cost per line; `boqTotal()` is the sum of the **live**
lines. The figure on the file, the payout's materials and the report are all read from that one calculation, so the
desk and Finance can no longer disagree. A line is withdrawn, not deleted: it flips to `status = withdrawn` with the
`withdraw_note` and who withdrew it, and it stays readable under the total. Once a variation locks a line it can no
longer be edited in place (422) — the change is raised as a variation instead.

## Labour is counted on the ground, not the invoice

A `labour_entry` is one worker for one day: `worked_on`, the name (frozen on the row), the optional fundi, the trade,
`hours` and `rate_ksh`. The file shows days and their cost, but the money it feeds is Module 11's — this table counts
attendance, nothing releases a payment.

## The inspection, and who can clear what (§11.4)

The office schedules a visit with the items listed; the inspector marks each `pass`, `fail` or `not_applicable` with a
note. **Only an inspector clears a failure** — a `fail` that has not been re-checked sits on the worker's
**Items to put right** screen (`portal/fixes`), where they answer it with `proof_note` and a photograph of the rework
(`proved_by_fundi`, `proof_on`). The worker's upload never marks an item passed. `recheckItem()` refuses to clear a
`fail` that has no proof on it yet (422) — an inspector cannot wave through a failure the site has not answered. A
report cannot be signed off while an item of somebody's is still standing, and `proveFix()` refuses an item that is
already re-checked, pointing at a new inspection instead.

## A variation re-opens the price, and only an executive approves it (§11.5)

A change the drawing did not carry is raised against the project with its reason and amount. A proposed variation sits
`proposed` until a **programme executive** decides it through the same `Finance::requireRelease()` gate that approves a
payout — approving turns it into live BoQ lines (or moves the total), rejecting closes it with no change. Because a
variation drives lines, a project's priced total moves only along with an approved variation; the worker sees what a
variation did to their own scope on **My work orders** (Module 7), not on a separate screen.

## Not duplicated

The award, the schedule, the stage signatures and the handover are Module 7. The payout, the materials issued and the
deductions are Module 11. The worker's own progress and completion photographs are Module 7. Construction adds the
BoQ, the site read, the per-item inspection and the variation decision — and nothing else.

## Verification

`php artisan migrate` (the construction tables) and `php artisan view:cache` with `php -l storage/framework/views/*.php`,
then the whole path run end to end on `http://127.0.0.1:8001` (this app runs on 8001; 8000 belongs to another project):
a project's site read with its photograph; a BoQ built line by line to a sum that matched the total and refused a typed
figure; a line withdrawn with its note kept under the total; labour days counted; an inspection whose failed item was
answered by the worker's proof photograph and then re-checked by the inspector only — an inspector clearing a failure
with no proof was refused (422), as was a proof filed on an item already re-checked; a variation that moved an order's
`60,000 → 95,000` and its deadline once a Director approved it (unsigned stages re-based to the new total, a signed
Mobilization stage kept), while a locked BoQ line edit was refused (422) and a rejected variation left the project's
total untouched; the payout row carrying the approved agreed amount and net. Two members were kept out of each other's
inspection items by 404. The desk's tabs, its search, the department tile and `admin/activity` all read correctly.
