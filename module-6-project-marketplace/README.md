# Module 6 — Project Marketplace

Blueprint §9: the place demand meets supply. A project is posted by the office and members apply to it — that half is
Module 1. This module is the other half: the system reads the E-Fundi register, **puts the project to the fundis who
actually fit it**, waits for their answer inside a fixed window, and hands the work to the one who takes it. It opens
one table (`project_matches`), one support class (`app/Support/Marketplace.php`), one office desk
(`admin/marketplace`) and one member screen (`portal/invitations`).

Nothing here duplicates another module: the posting, its materials list and its applications stay on the **project**
(Module 1); the work order, progress and completion certificate are **Module 7**; the payout and the material deduction
are **Module 11**; the fundi's grade, skills, counties and availability are **Module 5**, which this desk only reads.

## Scope

| Area | Screen / class | Table it writes |
| --- | --- | --- |
| Matching desk — every open project and how far its search has got | `admin/marketplace` | `project_matches` |
| Match sheet — one project, its rounds, its shortlist and the actions | `admin/marketplace/{project}` | `project_matches` |
| The search itself — score, bar, widening | `app/Support/Marketplace.php` | `project_matches` |
| Neighbouring counties, derived from the programme's own county board | `app/Support/KenyaMap::neighbours()` | nothing |
| "Invited to you" — the fundi's own asks, with the 48 hours on each | `portal/invitations` | `project_matches` |

The department is top-level like every other module, and it is now `live` on the department board with
**Matching desk** as its only screen; the tile badge counts the invites still waiting for an answer.

## How a fundi is chosen (§9.4)

The blueprint asks for members "above a certain rating". **The programme keeps no review scores** — there is no ratings
table anywhere — so the desk does not pretend to have one. Instead it runs a *standing bar* built out of the facts the
register already holds, and writes each fact out on the card so a reader can check it:

| Factor | Most it can add | Where it comes from |
| --- | --- | --- |
| Skills that match the trade | 40 | the trade and title, read against `FundiRegistration::SPECIALISATIONS` |
| Works in this county | 25 | the fundi's own county and the counties they said they cover |
| Availability | 15 | `availability = available`, which the fundi keeps themselves (§8.3) |
| Years in trade | 10 | `years_in_business`, scaled over ten |
| Projects already done | 10 | applications on record as approved |

The score is the share of the points **actually available on that project**: when the typed trade names no skill, those
40 points are taken out of the denominator rather than given to everyone or scored as zero, and the card says so. The
bar is 55%.

Two tests run before anything is scored, and a fundi who fails one is never shortlisted no matter how high they score:
their **grade does not carry that category** (`Project::fits()`, the same rule the portal uses to decide who may apply)
and **they are not saying they are free**. Passed-over fundis are not silently dropped — `Marketplace::search()` keeps
the reason and the desk shows it.

If the trade names nothing on the register, or the project has no county, the search says so in words rather than
returning an empty table.

## Rounds, not overwrites (§9.6)

Every run of the search writes a **new round** underneath the ones already there: `round` is part of the row's unique
key (`project_id`, `fundi_id`, `round`). Round 1 stays exactly as it was found, so the desk can show what a widened
search added, and a coordinator can see the history of a project that took two attempts to fill. The invite form asks
for a round by number; an unanswered shortlist from round 1 is still there after round 2 runs.

Two relaxations are allowed and both are stamped on every row they touched, so a loosened rule is never invisible:

* **Widened to neighbouring counties** — the neighbours are read off the county board `KenyaMap::TILES` (the same source
  the §15.4 map uses) by tile adjacency, because a record carries a county *name*, not a coordinate. A neighbour scores
  half the county points.
* **The standing bar lowered** — only a programme executive (`superadmin`) may run a search below 55%, and only down to
  30%. Staff get the standard search and see why the field is disabled.

## Asking, and the 48 hours

The top five of a round are put to the fundis (§9.4 step 3). The clock starts **when the office sends the ask**, not
when the fundi reads it: `invited_on` is stamped on the row and the answer is due `ProjectMatch::WINDOW_HOURS` later.
An invite whose window has passed is closed the moment anyone next opens the desk or the member screen — it becomes
`no_reply` and stays on its round, because "the search went out and nobody answered" is precisely the fact §9.6 acts
on. Nothing is deleted to make room.

On the fundi's side, **Invited to you** shows the project, what the search found in their record that led to the ask,
the materials the programme will supply on that job (with the line saying the handed-over value is what the payout
deducts), and two buttons: take it, or turn it down with a reason. Answering twice is refused with a sentence a person
can read, and a fundi cannot answer someone else's invite — the list is resolved from the signed-in account, never from
the url. **CTPs get no such screen** and the route refuses them: a CTP mobilises, they do not take the work.

## The award

Awarding from this desk goes through `Project::awardTo()`, the single place any award happens in the app, so a
marketplace award and an award from the project page cannot disagree: the project becomes `awarded`, every other
waiting applicant is declined with the reason, and the previous holder is told rather than lost. The row becomes
`awarded` with who issued it and when, and the fundi is moved to `on_job` on the E-Fundi register, which takes them out
of the next search until they say they are free again.

Where nobody is chosen, the desk can be left to the rule: **award the best-scoring fundi who accepted**. A Director can
also bring someone forward whom the search passed over — typed with a reason that stays on the card, executive-only,
and the same 48 hours apply to them.

The gates therefore sit on the two things that bend the rule: **only a programme executive (`superadmin`) lowers the
standing bar or hand-picks a fundi**, and every round they did either is stamped on its rows. Running the search,
widening it, sending asks and awarding to a fundi who accepted are the coordinator's steps — the same award any staff
account already makes from the project page. Closed rows are reopened or re-run as a new round: a match is never
deleted and a project is never re-posted to fix a match.

## Verification

`php artisan migrate` (`project_matches`), `php artisan view:cache` and `php -l storage/framework/views/*.php`, then
the desk worked end to end on `http://127.0.0.1:8001` (this app runs on 8001; 8000 belongs to another project): the
standard search on a junior-fundi job that names a skill the register does not hold, which correctly kept **nobody**
and said so; a widened search for a senior job in Siaya that brought in a fundi covering two neighbouring counties; a
lowered-bar run by an executive that let in a fundi the standard bar passed over; asks sent and answered from the
fundi's own login (one accepted, one declined, a second answer refused, a cross-account answer refused with 403); an
invite pushed past 48 hours and closed to `no_reply` on the next page load; and the accepted fundi awarded, after which
the project read `awarded`, the sibling rows read declined, the fundi's availability read `on_job`, and the trail on
`admin/activity` carried each of those changes with the actor's name. A CTP login on `portal/invitations` returns 403.
