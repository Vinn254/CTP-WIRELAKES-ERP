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

**The trainee interface belongs to the member alone.** `/portal` is a sidebar workspace with
**Overview · My training · My mobilisation · My communities · My projects · My applications**, each its own
url. An E-Fundi sees only Overview / My projects / My applications — the CTP pages 403 and the sidebar never
offers them. There is no staff preview of a member's screen: RBAC keeps staff out of `/portal` (403) and
members out of every `/admin/*` url (403), member routes take no id parameter, and project detail pages are
reached only through the member's own eligible list.

**Next — the mobilisation credit.** The interface already counts verified referrals inside the current term
(Jan–Apr / May–Aug / Sep–Dec) toward the 15 / 30 milestones; the discount a milestone earns is not yet issued
as a credit against a fee.
