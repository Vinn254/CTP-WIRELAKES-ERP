# Module 10 — E-Waste Management

The lifecycle of what a community throws away: registered in the field by the people who find it, carried to a
collection point, weighed in by the programme office, and then routed — repair, refurbishment, recycling or safe
disposal. Most of what the programme collects is electronics, so the **condition** of a line is what decides its
route, and a photo is the evidence behind it (§13).

## Scope

| Area | Screen | Owner |
| --- | --- | --- |
| The e-waste register — every collection line, its weight and its route | `ewaste_collections`, `admin/ewaste` | Module 10 |
| Registering what was picked up in the community | `portal/ewaste` (CTP) | Module 10, field side |
| Registering an E-Fundi's own collection, and carrying a CTP's line to a collection point | `portal/ewaste` (E-Fundi) | Module 10, field side |
| Weighing in at the collection point | `admin/ewaste` | the programme office |
| Sorting, testing, routing to repair / refurbish / recycle / disposal | the same row, next slice | Module 10 |
| Refurbished assets going back to a community | Module 9 (Technology Services) | reads Module 10 |
| Sale of recovered material and refurbished devices | Module 11 (Finance) | reads Module 10 |
| Kg collected, devices reused, jobs created | Module 12 (M&E) | reads Module 10 |

## Module boundary

Module 10 **owns the register**. A CTP or an E-Fundi never edits it from the office side and the office never
invents a collection that nobody reported:

* the portal screens only **collect** — one **+ Add a collection** form and the lines registered under the signed-in
  member. No sorting, no routing, no weight the office has not put on a scale.
* the sidebar item is called **E-Waste collection** on both member interfaces. Registering e-waste is one of the
  things every E-Fundi grade carries, and a CTP's field work (§5.2) includes it.
* a **trainer (senior fundi) forwards**: they see the lines a CTP registered in their own county that are still in
  the field, carry them to a collection point, correct the condition as they saw it and send it to the office.
* the register is not the inventory. Stock, SKUs and any money from recovered material are Module 11's table —
  a weighed-in line is what hands over to it.
* nothing is deleted and no line is opened twice: a wrong weight is corrected on the same row and the field's
  original estimate stays beside it.

## The chain (§13.3, steps 2–4)

```
a CTP or an E-Fundi registers a collection   →   an E-Fundi carries it and forwards it
   status `submitted`                              status `forwarded`
                                                   the condition is corrected on the way
        →   the office weighs it in at a collection point   →   (next slice) sort, test, route
                    status `received`, weighed kg           repair / refurbish / recycle / disposal
```

A **Collection ID is issued on registration** (`EW-2026-00001`, the sequence restarting each year) so the field, the
trainer and the office always talk about the same line. The blueprint numbers a bag at the weighing step; the app
numbers it earlier on purpose — a photo and a note sent from a village without a scale still need an ID.

## What a line holds

`App\Models\EWasteCollection` — one row per kind of item, per day, per place:

| Field | Notes |
| --- | --- |
| `reference` | `EW-2026-00001`, unique, issued by `nextReference()` |
| `item_type` | the twelve kinds in §13.2 — computers, laptops, phones, printers, TVs, monitors, routers, batteries, cables, components, solar, electrical |
| `quantity` | how many |
| `collected_on` | the day it was picked up — never in the future |
| `condition` | working / mixed / broken / **hazardous**. Hazardous is flagged on the register: batteries and mercury go to a certified disposal partner |
| `status` | `submitted` → `forwarded` → `received` |
| `estimated_weight_kg` | what the field judged |
| `weighed_weight_kg` | what the scale said — recorded only by the office |
| `county`, `ward`, `community_id` | where it came from; a community link is what feeds Module 3's count |
| `ctp_id` / `fundi_id` | who registered it — exactly one of the two |
| `forwarded_by_fundi`, `forwarded_on`, `forward_note` | who carried it and what they reported |
| `received_by_user`, `received_on` | the office that weighed it in |
| `photo_path`, `photo_name`, `photo_size` | the picture, kept on the private disk and opened only from the record — on both screens it is a thumbnail that opens in the zoom viewer (`data-zoom`), so a pile of cables can be inspected without leaving the register |

`EWasteCollection::dataFrom()` is the **one validator** the member form and any office entry share, so the two sides
cannot drift. A validation miss returns exactly what was written and re-opens the form.

## RBAC

* the register lives at `/admin/ewaste`, inside the **E-Waste Management** department (Module 10 is now `live` on the
  department dashboard, with the count of lines still waiting to be weighed in on its tile and sidebar entry).
* weighing in is a staff action. `received` is only ever set with a weight, and the staff account that did it stays
  on the row. A second attempt is refused — the weight is corrected on the same line, not by opening another.
* `/portal/ewaste` is member-only (`auth` + `member`). A **CTP** sees their own lines and cannot forward — the
  forward route calls the E-Fundi guard and returns 403. An **E-Fundi** sees their own lines plus the CTP lines
  waiting **in their own county**.
* a photo is served through the member route only for the line's own collector or the E-Fundi who carried it. Anyone
  else gets 404, so the register cannot be walked row by row from the portal.
* a line is never deleted and never re-registered: the office corrects the weight, the condition or the route on the
  same row.

## Demo data in the dev database

*EW-2026-00001* — 12 desktops the school wrote off, registered by *Alice Testa* CTP-0001 in Kisumu, carried by
*Mary Achieng* EF-0002 and **weighed in at 103.5 kg** against a 96 kg estimate (the register keeps both) ·
*EW-2026-00002* — 30 lead-acid cells from a market inverter shed, **hazardous**; Mary forwarded it as *broken* after
collecting it, which is the correction the form is for · *EW-2026-00003* — 25 routers in Uasin Gishu, registered by
*Sam Kiplagat* CTP-0004: it is still in the field and Mary does **not** see it, because it is not in her county ·
*EW-2026-00004* — Mary's own 60 leads, forwarded to the office and waiting to be weighed.

## Next

* Sorting, testing and the route on the same row (§13.3 steps 4–6): `repair / refurbish / recycle / disposal`, the
  e-waste E-Fundi the line is matched to, and the certified disposal partner for hazardous fractions.
* Refurbished devices leaving the register — the asset that goes back to a school or community centre (Module 9),
  with the reuse count and value for Module 12.
* Weighing at a collection point with a store note, so the inventory and any material sale (Module 11) can pick the
  line up.
* The public partner-facing numbers: kilos kept out of the community per county, per partner.
