# Module 4 — Training & Certification

The run of training a CTP goes through between being approved and being certified: where it happens, who teaches it,
who holds a seat, what was recorded about each trainee and the certificate an executive signs at the end.

## Scope

| Area | Screen(s) | Owner department |
| --- | --- | --- |
| Trainings on offer — the list of what the programme teaches | `trainings`, `admin/trainings` | training (Module 4) |
| Training venues — partner school / community site / programme centre | `training_venues`, `admin/venues` | training |
| Fundi-trainers (a Module 5 person in a teaching role) | `trainers`, `admin/trainers` | training |
| Cohort: which training, dates, place, seats, hours, fee | `cohorts`, `admin/cohorts/{id}` | training |
| Seats — CTP applies or the office places them | `enrollments`, `admin/cohorts/{id}` + `/portal/training` | training |
| Modules expected on a training, and which one a trainee got through | `training_modules`, `module_completions`, `admin/trainings` + the track | training |
| The track — one trainee's progress to 100 % | `admin/track`, `admin/track/{enrollment}` | training |
| Progress record: attendance, theory, practical, assessment, notes | `progress_entries`, `partials/progress` on every screen | training |
| Paperwork on the record (school report, competency sheet, assessment paper) | `training_documents`, private upload | training |
| Certification: track at 100 % → recommend → executive approves → code issued / revoked | `certifications`, `admin/certification` | training |
| Trainee side | apply, bring others in their area, own track, modules, documents, certificate | member portal |
| Trainer side | apply to train, mark own trainees and send the record to the office | member portal |

## The trainings are a closed list

`trainings` is the only answer to "which training?". It starts as what the programme runs — **ICT, Networking,
Programming, Robotics, AI, Electrical, Construction, E-Waste** — and every screen picks from it:

* a cohort is an **intake of one of these**. There is no course-name field anywhere: nobody types "Computer
  maintenance and repair" or invents a subject on the day.
* a trainer's **"What they can teach"** list is the same table (`trainer_training`), so a fundi is licensed against
  the programme's trainings, not against free text.
* a certificate code takes its stem from the training's own code — `CTP-0001-CERT-ICT-01`, `CTP-0002-CERT-SOLAR-01`.
* **the office can add a training** when the programme starts running one. Add it under Trainings on offer and it
  appears on the cohort form and the trainer form immediately.
* a training that has held an intake or a trainer cannot be deleted — it is taken off the list instead, so the
  records that exist keep what they were trained in.

## Module boundary

Module 4 **owns** the training list, the cohort, the seat, the progress record and the certificate. It reads, and never
edits, records that belong elsewhere:

* a **trainer** is a row on a Module 5 E-Fundi. `trainers` holds only the teaching role — venue, the trainings they
  are licensed for, hours, rate, approval. The person, their tier and their rating stay in Membership.
* a **venue** may link to a Module 1 `partners` row (the public partner wall keeps its own record) and/or a Module 3
  `community`. `partners` is only a logo wall, so Module 4 keeps its own venue table — capacity, contacts and type.
* a **certificate is not a fundi registration.** Approval issues the code and completes the trainee's record; joining
  the E-Fundi pool is confirmed by the office in Module 5. Nothing is created there behind anyone's back.
* a cohort booked at a community appears on that community's page (Module 3) as read-only — the screens stay in
  their own department.

## Status

**Built — `/admin/trainings`, `/admin/venues`, `/admin/trainers`, `/admin/cohorts`, `/admin/track`,
`/admin/certification`, plus the trainee and trainer screens in the portal.** The department is live on the dashboard
with six tiles and three queue counters
(seats awaiting a decision, trainers awaiting a decision, certificates awaiting an executive).

**The chain (§7.5).** CTP applies for an open intake, or the office gives them a seat → the office confirms the seat →
theory, practical and assessment are recorded as they happen → the track reaches **100 %** and the record lands in the
certification queue by itself → **a programme
executive approves and only then is a certificate code issued** (`CTP-0001-CERT-ICT-01`, one sequence per CTP per training). A certificate is revoked, never deleted (§7.7), and the revocation reason stays on the record.

**The track is one score, not a pile of marks.** `Enrollment::trackParts()` weights the six things that have to happen
— attendance 30, modules 25, theory 15, practical 15, assessment 10, the school's evidence 5 — and a component that
cannot apply to a trainee counts as fully earned, so a trainee taught in-house is not held back by paperwork only a
partner school sends. `trackPct()` reaches 100 % only when the whole thing is there; `TRACK_PASS = 50` is the line a
certificate cannot be issued below.

**One record table on every screen.** `resources/views/partials/progress.blade.php` is the only training-record
table in the app: the office track page, the cohort page, the trainer's portal and the trainee's own screen all
include it, so nobody re-invents the columns. It reads in date order — Date, What happened, **Counts toward**,
Score /100, Hours, Attendance, What they did, Recorded by. *Counts toward* names the part of the track each line
feeds and what that part is worth ("Theory · 15 % of the track", "Attendance · 30 % of the track", a note says
"not counted — kept on the record"), so the table shows the flow of a trainee's theory, practical and assessment
performance instead of a column of bare marks. Under it, a line says how many marked days were attended against the
80 % rule. The trainee sees the same table read-only; only the office and their trainer get **+ Add a record**.

**The office can enter what a partner school sent.** The **+ Add a record** button on the track page is the office's
own record form — a school report, an assessment paper or a competency sheet, typed in as a session with its score and
hours. The trainer's button says **Record and send to the office**: the same fields, the same `progress_entries` row,
no separate queue to reconcile.

**The track tabs follow the marks, not the stage word.** On `/admin/track`, *Track not complete* and *At 100%* count
what `trackPct()` says for every confirmed seat — a run the office closed early, or one whose certificate was revoked,
is still short of 100% and must show up. Only *Still training* uses the stage.

**A record only lands inside the cohort's own dates.** `App\Support\TrainingTrack` is the single implementation both
sides call (period guard, module mark/unmark, progress record, document store, the auto-recommendation at 100 %). A
date outside the intake, a mark on a seat that is not confirmed, or a trainer touching a trainee outside their cohort
is refused — and the office is told which period is allowed rather than being left with a silent form.

**The track stays inside Module 4.** A track page carries the trainee's seat state and their trainer as tags, not as
links out: no *Cohort page* and no *CTP profile* button. Reading one trainee's progress should not walk you into
another department's screens.

**The trainer works one student at a time.** `/portal/trainer` is the roster: each cohort the trainer teaches lists
**every student on the roll in a table** — name and CTP ID, seat, stage, attendance, modules marked, track %, how many
records exist — with an **Open** button. `GET /portal/trainer/{enrollment}` is the per-student record page: the shared
record table with **+ Add a record for (name)** already open, the track, the module ticks and the upload form. Nobody
expands three trainees inside one card any more, and a mark cannot land on the wrong seat because two intakes carry
the same person. A seat that is not confirmed opens read-only; a student outside the trainer's own cohorts is a 404.

**Modules are expected, and typed in in one go.** A training lists the modules it must cover
(`training_modules`, ordered by `position`), and `/admin/trainings` has a **+ Add another module** repeater — several
rows, one submit, numbered in the order typed, a blank row simply not added and a validation miss returning exactly
what was written. The trainer ticks the modules they delivered, the office can tick them too, and the trainee sees the
same list on their record.

**Three ways a progress line gets in, and they are the same record.** A CTP's own trainer marks it in their portal;
the office records it; or a partner school sends its report and the office enters it on the school's behalf
(`recorded_by_user` vs `recorded_by_fundi` says which). Every line shows up on the trainee's page in the same table
with who put it there — "Trainer Mary Achieng" or "Super Admin (office)".

**Attendance is a hard rule.** `Enrollment::ATTENDANCE_MINIMUM = 80`: below it, putting a trainee forward is refused
with their percentage in the message. Attendance is the mean of the days someone actually marked, so an unmarked
trainee is not punished by an absent-but-unrecorded stretch.

**A trainee mobilises for their own area.** The portal splits the open intakes into *Trainings open in your area*
(the same county as the CTP's record) and *Open in other areas*. The area list is what a trainee is expected to carry
to the other CTPs they work with; a seat is still confirmed by the office either way.

**Seats are guarded on both sides.** Only a CTP in `active / graduated / deployed` can hold one; a cohort must be
`open`, within its enrol window and not full. A declined or withdrawn application never blocks a seat, and a CTP has
exactly one record per cohort — applying again after a decline reopens the same row rather than duplicating it.
One CTP, one record, so a seat taken back can be given again without a second line appearing on the roll.

**Trainers are paid and still workers.** Only a **senior fundi** may be added — a junior, an intern or an attachment
applicant teaches nothing and works under a senior on a job (Module 5, `FundiRegistration::CAPABILITIES`). The rate is
recorded per trainer (`rate_ksh`) and their weekly hours. A cohort can only be handed to an **approved** trainer who is
registered for the training the intake runs **and approved at that cohort's venue**. Training does not remove a fundi's
right to apply for open projects.

**RBAC.** Every staff screen sits under `/admin/*` in the `training` department. In the portal, the trainee screens
`abort_unless($member instanceof CtpRegistration, 403)` and the trainer screens require a `FundiRegistration`, so a
CTP cannot open `/portal/trainer` and a fundi cannot open `/portal/training`; the sidebar only emits the items that
belong to the signed-in member. A trainer may only mark a trainee **in a cohort they teach** — anyone else's
`enrollment_id` returns 404, never 403, so no roster is leaked. Only a `superadmin` (a programme executive) may
approve or revoke a certificate; the approve buttons are disabled for other staff and the routes return 403. A
completed or failed run cannot be withdrawn from, and a seat that reached `completed` cannot be taken back — records
stay.

## Demo data in the dev database

The catalogue holds the eight trainings the programme runs, plus **Solar Installation** (`SOLAR`) added by the office
to show how a new one arrives.

Venue *Nyanda Technical School* (partner school, Kisumu, 40 places) · trainer *Mary Achieng* EF-0002 (senior fundi,
approved at that site, licensed for ICT + Networking + Solar Installation, 15 h/week, KSH 1,200) · *TRN-0001* — an
**ICT** intake at Nyanda, 01 Oct 2026 – 29 Jan 2027, 5 seats, in progress and closed out — Alice, Jane and Peter are
all through it · *TRN-0002* — a second **ICT** intake at the same venue, **05 Oct 2026 – 05 Feb 2027**, 5 seats, in
progress and the run a trainer marks today. On its roll: *Alice Testa* CTP-0001 (seat confirmed, at **Theory** with
one session the trainer recorded), *Peter Otieno* CTP-0003 (placed by the office to retake the training after his
certificate was revoked, nothing marked yet) and *Sam Kiplagat* CTP-0004 (applied from the portal, seat still awaiting
the office, so his row opens read-only) · *Alice Testa* also holds `CTP-0001-CERT-ICT-01` from the first intake at a
100 % track · *Jane Odhiambo* CTP-0002 placed by the office, at a 100 % track with a partner-school report uploaded —
her record went into the certification queue on its own the moment the last mark was recorded, and the executive
signed it · *Peter Otieno* at 70 % on the first intake with a certificate that was issued and then **revoked**, the
reason still on the record.

Trying the guard: hand *TRN-0001* an Electrical trainer, or tick only Solar on a trainer and post an ICT cohort for
them — both are refused until the trainer is registered for the training the intake runs. So is a mark dated outside
the intake, and a record typed on the trainee's own screen (there isn't one).

Test accounts on the dev app (**127.0.0.1:8001** — this project never uses 8000, that is tich-erp):
`superadmin@ctp-wirelakes.ke` (the executive —
the only role that can approve a certificate), `alice@test.ke` (CTP trainee) and `mary.achieng@example.ke` (E-Fundi
who trains). Their passwords are seeded in the local database only and are deliberately not written into this repo.

## Next

* Public certificate verification (a code checked without logging in) plus QR and PDF download.
* The trainer's own session marks feeding a per-cohort attendance sheet.
* Charging the cohort `fee_ksh` through the Module 2 mobilisation credit (15/30) — the fee field exists, the
  discount is not applied to it yet, and payment is still recorded by the office.
* Trainee placement after certification (Module 5/6) and the impact count per cohort for Module 12.
