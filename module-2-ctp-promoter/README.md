# Module 2 — CTP Promoter Management

The operational centre of the ERP: the life of a Community Technology Promoter from application to deployment.

## Scope

| Area | Screen(s) | Owner department |
| --- | --- | --- |
| Applicant → CTP status machine | `pending payment` → `pending review` → `active` / `rejected` | membership (Module 2 views) |
| CTP profile | personal, location, education, track, referral, CTP ID, auto-counts | membership |
| Three parallel roles | Trainee / Mobilizer / Community Agent on one record | membership |
| Mobilisation | referral code, 15 / 30 term discounts, verified-enrollment counting | membership |
| Deployment | E-Fundi pool, internship / attachment, trainer opt-in | membership |
| Field activity log | visits, needs assessments, beneficiaries, e-waste drives — GPS + photos | membership |

## Module boundary

Module 2 **reads** from Modules 1, 3, 4, 5, 10, 11 and **writes** nothing that belongs to them:
training cohorts and certificates stay in Module 4, communities in Module 3, payments in Module 11.

## Status

**Built — CTP profile and ID (`/admin/promoters`), and the CTP trainee interface (`/admin/trainee`).**
Every CTP record now carries: identity, county / sub-county / ward / village + GPS pin, education level,
year left school and certificates held, track, mobiliser lineage (`referred_by`), service counties,
mobilisation availability, trainer opt-in, fee state, and the full lifecycle
`pending_payment → pending_review → active → graduated → deployed` (plus `suspended`, `rejected`).
The CTP ID is issued once on approval and never re-issued. Approving a CTP now requires the fee to be
recorded first (paid or waived) in Module 1 — Applicants, which is where the payment checkout will plug in.

The trainee side is the CTP's own four tabs — **My training / My mobilisation / My communities / My projects** —
rendered from one partial (`web/resources/views/portal/partials/trainee.blade.php`) in two places: the member sees
it at `/portal` after signing in, staff see the identical screen at `/admin/trainee/{ctp}` with a preview banner,
preview-scoped project links and no apply/withdraw controls. `/admin/trainee` is its own department card beside
CTP Promoter Management. RBAC is enforced on both sides: staff get 403 at `/portal`, a CTP gets 403 at every
`/admin/*` URL, and member routes carry no id parameter, so one member cannot open another's screen.

**Next — the mobilisation credit.** The interface already counts verified referrals inside the current term
(Jan–Apr / May–Aug / Sep–Dec) toward the 15 / 30 milestones; the discount a milestone earns is not yet issued
as a credit against a fee.
