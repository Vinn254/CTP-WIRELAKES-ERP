# Module 2 — CTP Promoter Management

The operational centre of the ERP: the life of a Community Technology Promoter from application to deployment.

## Scope

| Area | Screen(s) | Owner department |
| --- | --- | --- |
| Applicant → CTP status machine | `pending payment` → `pending review` → `active` / `rejected` | membership (Module 2 views) |
| CTP profile | personal, location, education, track, referral, CTP ID, auto-counts | membership |
| Three parallel roles | Trainee / Mobilizer / Community Agent on one record | membership |
| Mobilisation | referral code, people brought, 15 / 30 term milestones, per-area attribution | membership |
| Membership fee | KSh 1,000 one-time fee, recorded / waived by the office, sponsored CTPs pay nothing | membership |
| Office entry | a CTP application typed in at the counter for someone who cannot reach the website | membership |
| Deployment | E-Fundi pool, internship / attachment, trainer opt-in | membership |
| Field activity log | visits, needs assessments, beneficiaries, e-waste drives — GPS + photos | membership |

## Module boundary

Module 2 **reads** from Modules 1, 3, 4, 5, 10, 11 and **writes** nothing that belongs to them:
training cohorts and certificates stay in Module 4, communities in Module 3, payments in Module 11.

## Status

**Built — CTP profiles (`/admin/promoters`) and the CTP's own trainee interface (`/portal`).**
Every CTP record carries: identity, county / sub-county / ward / village + GPS pin, education level,
year left school and certificates held, track, mobiliser lineage (`referred_by`), service counties,
mobilisation availability, trainer opt-in, fee state, and the full lifecycle
`pending_payment → pending_review → active → graduated → deployed` (plus `suspended`, `rejected`).
The CTP ID is issued once on approval and never re-issued.

**Every CTP decision is made inside this department.** The profile page records or waives the fee,
approves the CTP and issues the ID, rejects with a reason, re-issues the activation link, sets the
project level, moves the lifecycle and opens the uploaded documents — all under
`admin/promoters/{ctp}/…`. Staff are never bounced to Module 1 — Applicants to finish a CTP.
The shared rules live in `App\Support\ReviewActions`, so Module 1's queue and Module 2's profile cannot
drift apart.

**The membership fee is KSh 1,000, one time, and a sponsored promoter pays nothing.** The number lives in the
membership settings (`membership_fee_ctp`, mirrored on the public fees page) and is read through
`CtpRegistration::feeAmount()`, so no screen hard-codes it. An application that arrives without a sponsor waits in
`pending_payment` until the office records the money on the profile — paid (M-Pesa / bank / cash / other) or waived —
and only then enters the review queue. When a partner organisation covers it, the office names that partner on the
profile (`sponsor_id` → the Module 1 partner register, linked but never edited here): the fee becomes `sponsored`,
no money is asked for and the application goes straight to review. Removing a sponsor before approval puts the fee
back as owing; a fee already **paid is never rewritten** by a later sponsorship, and an approved member's record is
left alone. The public application asks the same question, so a sponsored applicant never sees a payment step.

**E-Fundis pay the same kind of one-time fee (KSh 1,000) in Module 5's queue.** `fundi_registrations` carries the
same five fee columns and the same `pending_payment → pending_review → active` opening, settled from
`admin/registrations/fundi/{id}` by the same `ReviewActions::recordFee()` — the shared implementation means the CTP
and E-Fundi fee rules cannot drift apart. Sponsorship is a CTP rule today; an E-Fundi who cannot pay is waived by
the office with the reason in the note.

**The office can open a CTP itself.** `/admin/promoters/create` (the *Add CTP from office* tile, and the button on
the profile list) asks exactly the public application questions — identity, location, education, track, mobilising
CTP code, sponsor — through the same validator (`CtpRegistration::applicationRules()`), and the record enters the
same lifecycle: reference `CTP-APP-XXXXXX`, `pending_payment` unless sponsored, then review, then the CTP ID and an
activation link. The only relaxations are practical: documents may be attached later at the counter, and the record
keeps `source = office` plus `added_by` (the staff user) so a trainee typed in at the office is never mistaken for a
website applicant. Nobody is approved straight into the programme from this screen.

**Every member joins with a face on their profile.** The public CTP application requires a profile photo
(`passport_photo`, jpg/png up to 1 MB) and the office screen can capture it at the counter. The picture is stored on the
private `local` disk under the application's own folder and is never a public url: it opens through
`admin/promoters/{ctp}/file/passport` for staff and `portal/photo` for the member. `partials/avatar` is the one markup
every screen includes — the photo when the record carries one, initials when it does not — so a CTP looks the same on
their own portal topbar and overview, on the office profile, and in the Module 1 review queue. A member who joined
without one, or whose face has changed, puts their own up on **Overview → Your profile photo**
(`portal/photo`); staff can capture or replace it on the profile (`admin/promoters/{ctp}/photo`). Replacing deletes the
old file — one face per profile, never two. Every image link marked `data-zoom` (including the profile photo) opens in
the zoom viewer rather than a new tab.
**The trainee interface belongs to the member alone.** `/portal` is a sidebar workspace with
**Overview · My training · My mobilisation · My communities · My projects · My applications**, each its own
url. An E-Fundi sees only Overview / My projects / My applications — the CTP pages 403 and the sidebar never
offers them. There is no staff preview of a member's screen: RBAC keeps staff out of `/portal` (403) and
members out of every `/admin/*` url (403), member routes take no id parameter, and project detail pages are
reached only through the member's own eligible list.

**Mobilisation is counted on people brought, not on clicks.** A CTP's term score is the registrations that
actually converted into the programme *in the areas they mobilise*: trainee CTPs carrying their referral code,
plus E-Fundi registrations that name them as `mobilised_by_ctp` and are already `active`. Both kinds are
matched to the community's county / sub-county / ward and surfaced as one list (`CtpRegistration::broughtList()`,
`Community::peopleBrought()`) so a promoter sees a trainee and a fundi in the same column. Many CTPs may mobilise
one area — each is credited only for the people they personally brought. The score drives the 15 / 30 milestones
against `CtpRegistration::currentTerm()`.

**A CTP is a trainee first, so projects are read-only for them.** They see every published project
(`Project::viewsTo()`), may read the detail page of one they cannot apply to, and may submit an application only
to CTP attachment. Fundis keep the tighter rule: they see only the tiers they hold. Drafts are invisible to both
(404, not 403) — an unpublished project does not exist for a member yet.

**Alumni sit under the register on `/admin/promoters`.** A CTP who has finished a training (a seat marked
`completed`, or a certification the office approved) is listed once in **Alumni — CTPs who finished their
training**, with the run they sat, the certificate code and the theory / practical / attendance behind it, the
attachment work they have handed over, how many people they mobilised, and the grade select that moves them on.
The move is still one row for life: `ctp_registrations.fundi_tier` holds `junior_fundi` / `senior_fundi`, nothing
is copied into `fundi_registrations`, and the same `POST /admin/promoters/{ctp}/tier` the register uses does it.
A revoked or missing certificate keeps them in the list — the office reads the reason, it does not lose the person.

**The grade on the record follows the move.** `CtpRegistration::gradeLabel()` is what the person reads as on
their own profile, the office register, the review queue, the member sidebar and the mobile app; a CTP with no
pool reads *CTP trainee*, one moved on reads *Junior Fundi* / *Senior Fundi* and starts taking projects. The
track they trained under stays visible underneath as a hint — the move changes the grade, not the history.

**Next — the mobilisation credit.** The interface already counts people brought inside the current term
(Jan–Apr / May–Aug / Sep–Dec) toward the 15 / 30 milestones; the discount a milestone earns is not yet issued
as a credit against a fee.
