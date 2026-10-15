# Module 12 — M&E, GIS, Reports & Programme Administration

The reading end of the ERP. Blueprint §15: the impact indicators, the county map, one report per role, the trail of
who changed what, and the few switches the whole programme reads. Everything in here except the switches is
**read-only** — this module has no forms for creating a record, because every number it shows already lives in
another module's register.

## Scope

| Area | Screen / class | Table it writes |
| --- | --- | --- |
| Impact dashboard — the five groups of §15.2, county-filterable | `admin/impacts`, `app/Support/Impact.php` | nothing, it counts other modules' rows |
| Programme map — one square per county, six layers | `admin/gis`, `app/Support/KenyaMap.php` | nothing |
| Reports, one per role, printed or exported | `admin/reports`, `admin/reports/{key}`, `app/Support/Reports.php` | nothing |
| Activity trail — who did what, when | `admin/activity`, `app/Support/Audit.php`, `ActivityObserver` | `activity_log` (append-only) |
| Programme settings — the fees and contacts the code reads | `admin/programme` | `settings` |

The department is top-level like every other module; none of its screens were moved into another department and none
of another module's screens were moved in here.

## Indicators are read, never kept

There is no `indicators` table on purpose. `Impact::indicators($county)` runs the queries against the registers the
other modules write — seats from `enrollments`, certificates from `certifications`, kilograms from
`ewaste_collections`, money from `finance_entries`, work from `projects` — so a report can never disagree with the
records behind it, and a change in a module shows up on the dashboard the next time it is opened.

Two rules keep the figures honest:

* Where the blueprint asks for a number no module keeps yet (ICT centres established, devices refurbished, sites
  inspected and BOQs, businesses the programme started), the indicator is rendered as **"not counted yet"** with the
  module that has to open first named in the note. It is never shown as a zero, and never estimated.
* Every indicator carries the sentence saying what it is counted from, so a reader can go and check it.

Sectors are grouped on the **same nine lines finance budgets against** (`BudgetLine::CATEGORIES`) rather than a second
taxonomy invented for M&E. A project lands on a line through the trade typed when it was posted
(`Impact::SECTOR_KEYWORDS`, first match wins), and every sector table lists the literal trades that fell into it —
including a "trade not matched to a budget line" bucket, so a mistyped trade is visible instead of silently dropped.

## The county map, with no map library

Records carry a county **name**, not a coordinate, so §15.4's Kenya-wide map is drawn as a 10×8 CSS grid with one
square per county, laid out roughly as the country sits (`KenyaMap::TILES`, keyed to the 47 names
`DatabaseSeeder` puts in `counties`). A square shades into one of five bands by how many records the chosen layer
holds there; clicking it lists those records and links back to the module's own filtered register.

`layers()` covers communities, CTPs, E-Fundis, training centres, projects and e-waste. Because a county is free text,
any county string that is not on the board is shown in its own table under the map rather than hidden — a misspelled
county still holds real records, and the map must not make them disappear.

## One report structure, three outputs

`Reports::build($key, $county)` returns sections of `['heading', 'columns', 'rows', 'note']`. The Blade page renders
them, `@media print` prints them (sidebar, buttons and shadows removed), and `Reports::csv()` writes the identical
sections to a file, so an export cannot disagree with what is on the screen. The filename carries the report, the
county and the date.

The readers in §15.5 are **not staff user roles** — the app has `admin` and `superadmin` only. Each report is written
for the person named there: the portfolio report is the projects register read as a list with budget against actual
and the held payouts; the county report is what a county coordinator holds in their county; the finance, procurement
and stock reports are the finance module's own rows grouped for those desks; the training report counts seats given and
pass rate per course from the closed catalogue. The donor report is listed as **not open yet** because no donor
records module exists; the member report points at the member portal instead of duplicating it.

## The trail

`Audit::WATCHED` lists the records a decision changes — the three application types, communities, needs, projects,
cohorts, seats, trainers, certificates, e-waste lines, payouts, purchase requests and orders, money lines, stock
cards and movements, and the settings. They are observed once, in `AppServiceProvider`, so no controller has to
remember to log. Nothing is logged per-screen, so a new screen that edits a watched record is on the trail from the
day it is built.

* An `updated` line names the fields that carry a decision (`NOTABLE`) and never the rest: passwords, tokens and every
  upload path are excluded outright (`SECRET`), and a change that touches only undecorative columns is reported as
  "details updated" or "and N other field(s) changed".
* The trail is **append-only**. A wrong entry is answered by correcting the record, which writes the next line. The
  screen has no edit and no delete, and says so at the top.
* The observer is registered before the migration that creates `activity_log` exists, so `Audit::tableExists()` checks
  for the table once per request and stays quiet if the database is not there yet.
* The actor's name is copied onto the line, so a trail entry still reads properly after a staff account is closed.

## The programme switches

`Setting::EDITABLE` is the only thing this page offers, because those are the only settings the code actually reads:
the two membership fees, the programme email and phone. Adding a switch here that nothing consumes is how a settings
page turns into fiction, so anything else a module needs is configured on that module's own screen (fees' effects,
tracks, mobilisation tiers, the training catalogue, staff roles) and the page lists where each of those lives.

* **Only a programme executive (`superadmin`) saves** — the same rule as issuing a certificate or releasing money.
  Staff see the current values with the form disabled and the reason shown.
* Only a key whose value actually changed is written. A fee change applies to applications from now on; a fee already
  recorded keeps the amount charged at the time, which the save message says out loud.

## Verification

`php artisan migrate` (`activity_log`), then `php artisan view:cache` and `php -l storage/framework/views/*.php` —
the compiler does not lint, so this is what catches Blade parse errors. Then the sweep on
`http://127.0.0.1:8001` (this app runs on 8001; 8000 belongs to another project): all five screens, the map with every
layer and a county selected, the dashboard with and without a county, every report key, a county-filtered report, four
CSV exports, the trail with each filter, and a settings save checked as an executive — confirming the value landed,
the trail line was written, and the old value restored after.
