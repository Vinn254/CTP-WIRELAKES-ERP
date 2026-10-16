# Module 9 — Technology Services

Blueprint §12: the desk that keeps the programme's technology alive — the devices, licences and kits it owns, the
Robotics, Programming and AI work it delivers, and the faults reported and repaired against them. The five sub-areas
(ICT, Networking, Robotics, Programming, AI) are **one column** (`category`) on **three tables**
(`technology_assets`, `technology_projects`, `technology_tickets`), not five near-empty copies. They share the same
fundis, the same finance and the same M&E, so they share this register and differ only by their label. The module
opens one support class (`app/Support/Technology.php`), one office desk (**Technology**, `admin/technology`, four tabs:
assets, projects, tickets, alerts) and one member screen (**Repair jobs**, `portal/tech`). The department is top-level
on the board and `live`; its tile badge counts the tickets not yet closed plus the assets down or in repair.

It is the *register*, not the money. A purchase posts through Module 11, a dead device is handed to Module 10, an
impact figure is read by Module 12. Technology keeps the layer those modules do not carry: what the asset is, where it
sits, whose warranty is running out, who is repairing the fault and what the fix was.

## One asset register, five sub-areas (§12.2–12.6)

An asset carries `asset_code` (auto-per-category, e.g. `ICT-0007`), `category`, `name`, `kind`, serial, `status`
(`operational`, `degraded`, `in_repair`, `down`, `retired`), its `assigned_type` — community, organisation or a named
holder (§12.7) — location and county, purchase date, cost and supplier, and its tracking dates: `warranty_expires_on`,
`licence_key`, `licence_seats`, `licence_renews_on`. `source` records whether it was bought, donated or
**refurbished from E-Waste** (`refurbished_ewaste`), and a refurbished device keeps its `ewaste_collection_id`. Every
asset carries a `qr_payload` (its code) for the tag printed on it; a scanned tag reads back to this record. Nothing is
deleted — an edit is an edit, and a retired asset stays on the register with its reason.

## The alerts are read off the register (§12.7)

`Technology::expiringSoon()` returns every non-retired asset whose warranty or licence runs out inside
`ALERT_WINDOW_DAYS = 60`, ordered by whichever date falls first, with `daysLeft()` showing the countdown (negative once
it has passed). The Alerts tab and the queue count along the top are that one query — the desk cannot show a
different expiry picture from the reports.

## The repair ticket: open → assign → resolve → close (§12.7)

A fault is opened on the asset with the description written out (`fault` is required — "a ticket with no description
cannot be assigned or fixed") and a priority. Opening a ticket flips an in-service asset to `down`; the register and
the fault can no longer disagree. The office assigns it to an E-Fundi, who sees it on **Repair jobs**
(`portal/tech`) — resolved from the signed-in account (`Technology::jobsFor()`), never from a url, and only a
`FundiRegistration` is ever handed a job; a CTP's screen stays empty. The fundi records the fix with `resolution`
("the ticket closes on that record, not a word"), which flips the ticket to `resolved` and returns the asset to
`operational`. **Only the office closes it** — `closeTicket()` refuses anything that is not `resolved` (422). The
asset's own page carries its whole repair history and the per-status forms.

## A technology project runs the AI lifecycle (§12.4–12.6)

A Robotics build, a Programming job or an AI solution is one row: `reference` (auto, `TECH-PROJ-0001`), `category`,
title, summary, the `problem` it starts from, the `technology` used, team size, the `mentor_fundi_id` (a Robotics or AI
E-Fundi mentors the team), the `community_id` it belongs to, and an optional `project_id` when the same work also runs
as a Module 6 job — the thread is kept, not duplicated. `status` runs the §12.6 lifecycle (`identified → data_collection
→ designing → developing → prototype → testing → deployed → monitoring`, plus `on_hold` / `completed`) with a
`progress_percent` the office sets.

## Decommission routes to E-Waste (§12.7)

Retiring an asset needs the reason written down — "that reason is what Module 10 and M&E read" — and takes an optional
`ewaste_collection_id` to point at the collection it joins. A retired asset leaves the alerts queue. **An asset with a
ticket still open cannot be retired** (422) — the fault is answered or closed first, so a device never disappears with
an unanswered job against it.

## Not duplicated

Buying, invoicing and the asset's cost in the ledger are Module 11. Collection, weighing and disposal of the dead
device are Module 10. The impact figures a technology project feeds are Module 12. The fundi's own training and rating
are Modules 4 and 5. Technology adds the register, the expiry alerts, the ticket lifecycle and the decommission —
and nothing else.

## Verification

`php artisan migrate` (the three technology tables) and `php artisan view:cache` with
`php -l storage/framework/views/*.php`, then the whole path run end to end on `http://127.0.0.1:8001` (this app runs
on 8001; 8000 belongs to another project): an asset created with its auto code, edited and re-read; a ticket opened
against it, which flipped the asset to `down`; a decommission attempted while that ticket was open and **refused
(422)**; the ticket assigned to a fundi, resolved by that fundi on `portal/tech` (ticket `resolved`, asset back to
`operational`) and closed by the office; an asset retired with its note and pointed at an E-Waste collection; a
project created and moved along its lifecycle; a CTP logged in and shown an **empty** repair screen; the alerts tab
listing the two assets whose warranty or licence falls inside 60 days; the department tile badge, the four tabs and
`admin/activity` (15 audit rows) all reading correctly.
