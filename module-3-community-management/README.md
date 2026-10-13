# Module 3 — Community Management

The unit the programme measures impact at. A community is a named place, the CTPs who mobilise there, what it
needs, who has benefited there and what has been invested in it. Every field module points back to this register.

## Scope

| Area | Screen(s) | Owner department |
| --- | --- | --- |
| Community profile + COM ID | `communities` table, `admin/communities/{id}` | communities (Module 3) |
| CTP assignment | any number of **mobilizers** + one named lead, `community_ctp` | communities |
| Needs assessment | `needs` rows: type, priority, photo, GPS pin, status | communities |
| Needs pipeline | `proposed → prioritised → converted / closed` | communities |
| Beneficiaries | person / household / business reached by a project | communities |
| Community timeline | projects here, investment value, cohorts and drives later | communities |
| Field entry | CTP proposes a community, logs needs and beneficiaries | member portal |

## Module boundary

Module 3 **owns** the community, its needs and its beneficiaries. It **creates** a project draft (a Module 1 record)
when a need is converted, and never edits one afterwards — publishing, awarding and payment stay where they belong.
A community's project list is a staff view: promoters see the place, the needs and the people, never the paperwork.
`projects.community_id` is the anchor the rest of the system reads: Module 4 books a cohort at a community,
Module 6 matches on it, Module 10 runs a drive there and Module 12 counts it.

## Status

**Built — the register (`/admin/communities`), the needs pipeline (`/admin/needs`) and the field side (`/portal/communities`).**
Each community carries: name, county / sub-county / ward / village + GPS pin, population, households, a written
summary, the CTP roster (one lead and any number of mobilizers), its needs, its beneficiaries, the people its CTPs
have brought into the programme, the projects invested here and the KSh committed.
The COM ID is issued once — immediately when staff add a community, on confirmation when a CTP proposes one.
A duplicate is refused on the full location path (county + sub-county + ward + village + name), case-insensitively,
ignoring rejected records so a wrong call can be re-proposed.

**A community is mobilised by a roster, not by one person.** `community_ctp` holds `lead` and `mobilizer` rows and
an area may carry any number of mobilizers on equal footing — naming a lead picks who answers for the place, it
does not restrict who works it. Assigning a second lead demotes the first, so there is exactly one at all times.
Each CTP on the area is credited for the people *they* brought and who converted here (a trainee CTP carrying their
referral code, or an E-Fundi registered `active` naming them as their mobiliser, matched on county + sub-county +
ward), so mobilizers in the same area never share one another's numbers. The roster and that count are decided and
read on this module's pages; the credit itself is Module 2's to issue.

**The CTP does the field work, the office decides.** `/portal → My communities` is where a CTP proposes a community,
flags a need with a photo and a pin, and registers beneficiaries. Proposals arrive as `pending_review`; staff
confirm them here, which issues the ID and puts the proposing CTP on the community as its lead. An unconfirmed
community can hold needs but counts no beneficiaries — impact numbers only ever come from confirmed places.
CTPs do not run projects, so the field screen carries none: no project list, no project picker on a beneficiary.
A CTP records who they mobilised and when; the office links that person to the project that reached them.

**Every community decision stays in Module 3.** Prioritising a need, closing it, converting it into a draft project,
assigning or removing a CTP, recording beneficiaries and confirming a proposal are all `admin/communities/…` or
`admin/needs/…` actions on this department's own pages. Converting drafts the project with the community, county and
ward already filled in and hands staff a link to publish it in Module 1 — the work is done here, the record's home is
not touched. The same validation runs on both sides (`Community::placeData`, `Beneficiary::dataFrom`), so a CTP's
proposal cannot pass a check a staff record would fail.

**RBAC.** Communities are staff-only under `/admin/*`; a CTP reaches only the communities resolved from their own
record — `Community::forCtp()` filters every portal read and every `community_id` posted back, so another CTP's
community returns 404 rather than 403 and leaks nothing. Only a CTP in `active / graduated / deployed` may propose a
community; an E-Fundi gets 403 on the whole section and the sidebar never offers it. A beneficiary's ID number is
stored only when consent is ticked, and is dropped when it is not.

## Next

Cohorts and e-waste drives land on the community timeline when Modules 4 and 10 open — the slot and the
`community_id` anchor are already in place. Beyond that: a per-community impact sheet for Module 12, and GPS import
from the field app (Module 13) so a need's pin comes from the device rather than a typed coordinate.
