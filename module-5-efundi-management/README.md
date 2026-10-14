# Module 5 — E-Fundi Management

The programme's technical workforce: the fundi record every other module reads — grade, trade skills, the counties
they cover, whether they are free for work, and the sites they are approved to train CTPs at.

## Scope

| Area | Screen(s) | Owner department |
| --- | --- | --- |
| E-Fundi register — search, grade, availability, skill, county filters | `fundi_registrations`, `admin/fundis` | efundi (Module 5) |
| One fundi's record: identity, grade, skills, place, standing, documents, portal account | `admin/fundis/{id}` | efundi |
| Grade (intern / attachment / junior / senior) and what each grade carries | `fundi_registrations.tier`, `FundiRegistration::CAPABILITIES` | efundi |
| Availability — available / on a job / on leave, with the fundi's own note | `admin/fundis/{id}` + `/portal/efundi` | efundi |
| Trade skills and the counties they work in | `specialisations`, `service_counties` | efundi |
| Membership fee, approval, EF ID issue, rejection and reissue | `admin/fundis/{id}` | efundi |
| Suspend / blacklist / reinstate | `admin/fundis/{id}` (executive only) | efundi |
| Papers on file (ID, business registration, trade certificates, portfolio) | private `local` disk, streamed per record | efundi |
| The office typing in an application for someone who cannot reach the website | `admin/fundis/create` | efundi |
| Fundi's own portal: record, availability, skills, projects, sites they train at | `/portal/efundi` | member portal |

## What the module owns and what it only reads

Module 5 owns **the person and their skill record**. Everything else is a separate row that points back at it:

* the teaching role is **Module 4** — `trainers` holds the site, the hours, the rate and the office decision. Module 5
  shows which sites a fundi is approved at and refuses to let anyone edit that decision from a fundi screen.
* the job applications are **Module 6**, the money is **Module 11**, the e-waste a fundi collects is **Module 10**.
* the CTP who mobilised a fundi is a Module 2/3 record — the credit stays with the promoter, `mobilised_by_ctp` only
  points at it.

## The grades decide the interface

`FundiRegistration::CAPABILITIES` is one map, read by the portal, the sidebar and the controllers:

| Grade | Carries |
| --- | --- |
| **Senior Fundi** | projects, supervising, mobilising, e-waste collection, **training CTPs at a site** |
| **Junior Fundi** | projects, mobilising, e-waste collection |
| **Fundi-Intern** | projects, mobilising, e-waste collection |
| **Attachment applicant** | projects, e-waste collection |

A junior, an intern and an attachment applicant work **under a senior fundi** on a job. Only a senior can be handed a
cohort, so `/portal/trainer` is not in their sidebar, the training card is not on their record page, and
`POST /portal/trainer` returns 403 with the reason. `PortalController::teachingFundi()` is the single gate every
trainer route passes through, so a legacy approval cannot let a lower grade mark a trainee.

## Trainers are approved site by site

A fundi is never "a trainer" in the abstract. `trainers` is unique on `['fundi_id','venue_id']`: being approved at
Nyanda says nothing about Chemelod. Applying for a second site makes a second row; a decline or suspension is
**reopened on the same row**, never duplicated, and the office cannot move an approval between sites by editing it —
that returns 422 and tells them to take a separate application. A cohort can only be handed to a fundi **approved at
that cohort's venue**.

## One person, one row, records stay

`national_id`, `phone` and `email` are unique on the register and the office form repeats that rule with a message
that points at the existing record instead of a silent SQL error. An approval issues `ef_code` and opens the portal
account; a rejection keeps its reason; a suspension or a blacklist drops the fundi out of matching and locks their
login but never deletes the row. Only a `superadmin` (a programme executive) blocks or reinstates — staff prepare the
record, the executive signs it.

Documents are private: the paths never leave the disk as a url, they stream through
`admin/fundis/{fundi}/file/{slot}` and `portal.efundi.file`, each behind an ownership check on the record it belongs to.

## Validation lives on the model

`availabilityFrom()`, `skillsFrom()` and `officeApplicationFrom()` are the same validators on the office screen and on
the fundi's own portal, so the two surfaces cannot disagree about what a grade, a skill or an availability means.
Skills only come from `SPECIALISATIONS` — nobody types in a trade the programme does not recognise.

## Status

**Built — `/admin/fundis` (register with the four filters in a two-by-two grid), `/admin/fundis/{id}` (the record),
`/admin/fundis/create` (office entry), and `/portal/efundi` for every grade.** The department is live on the dashboard
with its own tile and a queue counter for applications awaiting a fee or a review.

The register filters on grade, availability, trade skill and county; the record edits grade, skills, place,
availability and standing, records the fee, approves, rejects, blocks, reinstates and reissues the portal invite. The
fundi's own screen keeps two things editable by them — **availability** and **skills/counties** — and shows everything
the office holds as read-only, plus the sites they train at and the projects their grade covers.

## Demo data in the dev database

One signed-in account per grade, so each interface can be seen as the person who owns it:

| Login | Record | Grade | Training surface |
| --- | --- | --- | --- |
| `mary.achieng@example.ke` | EF-0002 | Senior Fundi | approved at Nyanda Technical School, teaches TRN-0001 and TRN-0002 |
| `brian.mason@example.ke` | EF-0001 | Senior Fundi | no site yet — the apply-to-train empty state |
| `peter.otieno@example.ke` | EF-0004 | Junior Fundi | none — 403 on the trainer routes |
| `grace.mobilised@example.ke` | EF-0003 | Fundi-Intern | none — 403 on the trainer routes |
| `naomi.wanjiru@example.ke` | EF-0005 | Attachment | none — 403 on the trainer routes |

Their passwords are seeded in the local database only and are deliberately not written into this repo.

Trying the guards: as a junior, post `POST /portal/trainer` (403 — only a senior fundi trains); as a fundi, mark a
trainee in a cohort you are not approved to teach (404, no roster leaked); add an E-Fundi from the office with a
National ID already on the register (refused, with the record to open instead).

Dev app: **127.0.0.1:8001** — this project never uses 8000, that is tich-erp.

## Next

* E-waste collection intake on the fundi and CTP screens, forwarded to the office (Module 10 owns the register).
* Taking a junior, an intern or an attachment applicant onto a job **under** the senior who supervises them (Module 7).
* Rating and job history on the fundi record once assignments exist (Module 7/12).
