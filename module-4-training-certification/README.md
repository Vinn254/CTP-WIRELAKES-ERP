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
| Progress record: attendance, theory, practical, assessment, notes | `progress_entries` | training |
| Certification: recommend → executive approves → code issued / revoked | `certifications`, `admin/certification` | training |
| Trainee side | apply, bring others in their area, own record, certificate | member portal |
| Trainer side | apply to train, mark own trainees | member portal |

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

**Built — `/admin/trainings`, `/admin/venues`, `/admin/trainers`, `/admin/cohorts`, `/admin/certification`, plus the
trainee and trainer screens in the portal.** The department is live on the dashboard with five tiles and three queue
counters
(seats awaiting a decision, trainers awaiting a decision, certificates awaiting an executive).

**The chain (§7.5).** CTP applies for an open intake, or the office gives them a seat → the office confirms the seat →
theory, practical and assessment are recorded as they happen → the office puts the trainee forward → **a programme
executive approves and only then is a certificate code issued** (`CTP-0001-CERT-ICT-01`, one sequence per CTP per training). A certificate is revoked, never deleted (§7.7), and the revocation reason stays on the record.

**Three ways a progress line gets in, and they are the same record.** A CTP's own trainer marks it in their portal;
the office records it; or a partner school sends its report and the office enters it on the school's behalf
(`recorded_by_user` vs `recorded_by_fundi` says which). Every line shows up on the trainee's page with who put it
there — "Trainer Mary Achieng" or "Super Admin (office)".

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

**Trainers are paid and still workers.** Only a `junior_fundi` or `senior_fundi` may be added; the rate is recorded
per trainer (`rate_ksh`) and their weekly hours. A cohort can only be handed to an **approved** trainer who is
registered for that training — checked when the cohort is saved and again when its status is changed. Training
does not remove a fundi's right to apply for open projects.

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

Venue *Nyanda Technical School* (partner school, Kisumu, 40 places) · trainer *Mary Achieng* EF-0002 (junior fundi,
approved, licensed for ICT + Networking + Solar Installation, 15 h/week, KSH 1,200) · *TRN-0001* — an **ICT** intake
at Nyanda, 02 Nov 2026 – 26 Feb 2027, 5 seats, in progress · *TRN-0002* — a second **ICT** intake, open for
applications · *Alice Testa* CTP-0001 self-applied to both, seat confirmed, certified `CTP-0001-CERT-ICT-01` at 100 %
attendance and then `CTP-0001-CERT-ICT-02` on the second intake · *Jane Odhiambo* CTP-0002 placed by the office, at
assessment on 20 % attendance (cannot be certified) · *Peter Otieno* CTP-0003 certified then revoked, with the
reason on the record.

Trying the guard: hand *TRN-0001* an Electrical trainer, or tick only Solar on a trainer and post an ICT cohort for
them — both are refused until the trainer is registered for the training the intake runs.

Test accounts on the dev app (**127.0.0.1:8000** — tich-erp is 8001): `superadmin@ctp-wirelakes.ke` (the executive —
the only role that can approve a certificate), `alice@test.ke` (CTP trainee) and `mary.achieng@example.ke` (E-Fundi
who trains). Their passwords are seeded in the local database only and are deliberately not written into this repo.

## Next

* Public certificate verification (a code checked without logging in) plus QR and PDF download.
* The trainer's own session marks feeding a per-cohort attendance sheet, and a school report uploaded as a file.
* Charging the cohort `fee_ksh` through the Module 2 mobilisation credit (15/30) — the fee field exists, the
  discount is not applied to it yet, and payment is still recorded by the office.
* Trainee placement after certification (Module 5/6) and the impact count per cohort for Module 12.
