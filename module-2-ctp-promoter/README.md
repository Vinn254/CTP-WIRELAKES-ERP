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

**Built — CTP profile and ID (`/admin/promoters`).**
Every CTP record now carries: identity, county / sub-county / ward / village + GPS pin, education level,
year left school and certificates held, track, mobiliser lineage (`referred_by`), service counties,
mobilisation availability, trainer opt-in, fee state, and the full lifecycle
`pending_payment → pending_review → active → graduated → deployed` (plus `suspended`, `rejected`).
The CTP ID is issued once on approval and never re-issued. Approving a CTP now requires the fee to be
recorded first (paid or waived) in Module 1 — Applicants, which is where the payment checkout will plug in.

**Next — Mobilisation.** 15 / 30 verified referrals inside the current term, the discount as a term credit,
and the shareable referral link that the profile page already exposes.
