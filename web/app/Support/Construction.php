<?php

namespace App\Support;

use App\Models\BoqItem;
use App\Models\LabourEntry;
use App\Models\Project;
use App\Models\SiteAssessment;
use App\Models\SiteInspection;
use App\Models\SiteInspectionItem;
use App\Models\VariationOrder;
use App\Models\WorkOrder;
use App\Models\WorkOrderMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Module 8 — Construction & Infrastructure. The engineering file that sits on a buildable project: the site
 * read, the priced design, the labour counted, the inspections and any change to what was agreed.
 *
 * It deliberately owns less than it might. The order's own progress and its photographs stay on Module 7's log; the
 * money a variation moves is worked out by Module 11. What lives here is the record a construction site produces
 * and no other module would think to keep.
 */
class Construction
{
    /**  — the trades the programme books for construction work. */
    public const SPECIALTIES = [
        'masonry' => 'Masonry',
        'carpentry' => 'Carpentry',
        'roofing' => 'Roofing',
        'plumbing' => 'Plumbing',
        'welding' => 'Welding',
        'concrete' => 'Concrete work',
        'painting' => 'Painting',
        'tiling' => 'Tiling',
        'flooring' => 'Flooring',
        'renovation' => 'Renovation',
        'fencing' => 'Fencing',
    ];

    /** Words that make a project's trade a construction job. */
    public const TRADE_WORDS = [
        'mason', 'carpent', 'roof', 'plumb', 'weld', 'concret', 'paint', 'til', 'floor',
        'renovat', 'fenc', 'construct', 'build', 'civil', 'structural',
    ];

    /** A project is a construction job when its trade says so, or once it has any record on this file. */
    public static function isConstruction(Project $project): bool
    {
        $trade = strtolower((string) $project->trade);

        return collect(self::TRADE_WORDS)->contains(fn (string $word) => str_contains($trade, $word))
            || static::hasFile($project);
    }

    public static function hasFile(Project $project): bool
    {
        return SiteAssessment::where('project_id', $project->id)->exists()
            || BoqItem::where('project_id', $project->id)->exists()
            || LabourEntry::where('project_id', $project->id)->exists()
            || SiteInspection::where('project_id', $project->id)->exists()
            || VariationOrder::where('project_id', $project->id)->exists();
    }

    /** The order the variation is priced against, when the project has one. */
    public static function orderFor(Project $project): ?WorkOrder
    {
        return WorkOrder::where('project_id', $project->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('id')
            ->first();
    }

    public static function boqTotal(Project $project): float
    {
        return round($project->boqItems->where('status', 'live')->sum(fn (BoqItem $line) => $line->totalKsh()), 2);
    }

    public static function labourCost(Project $project): float
    {
        return round($project->labourEntries->sum(fn (LabourEntry $day) => $day->costKsh()), 2);
    }

    /**  — the first visit, written up. Every visit after it is another row; none of them is edited away. */
    public static function recordAssessment(Project $project, Request $request): SiteAssessment
    {
        $data = SiteAssessment::dataFrom($request);

        $assessment = SiteAssessment::create($data + [
            'project_id' => $project->id,
            'assessed_by_user' => auth()->id(),
        ]);
        $assessment->storePhoto($request);

        return $assessment;
    }

    /**
     *  — the bill of quantities. The repeater sends the whole priced design at once: new lines are added, lines
     * already on the file are corrected in place (the trail records every quantity and price change), and a line that
     * should not be built is withdrawn with a reason rather than deleted. Lines an approved variation brought in are
     * read-only — that figure was agreed with the hirer, so a change to it is a new variation.
     */
    public static function saveBoq(Project $project, Request $request): void
    {
        $rows = BoqItem::rowsFrom($request);
        abort_if($rows === [], 422, 'Every line needs a description before the BoQ can be saved.');

        DB::transaction(function () use ($project, $rows) {
            $locked = $project->boqItems()->where('source', 'variation')->pluck('id')->all();

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $line = $id ? BoqItem::findOrFail($id) : null;

                if ($line) {
                    abort_unless((int) $line->project_id === (int) $project->id, 422,
                        'A BoQ line on this file belongs to another project.');

                    if (in_array($line->id, $locked, true)) {
                        $changed = abs((float) $row['quantity'] - (float) $line->quantity) > 0.0001
                            || abs((float) $row['unit_cost_ksh'] - (float) $line->unit_cost_ksh) > 0.004;

                        abort_unless(! $changed, 422,
                            '"'.str($line->description)->limit(70).'” came out of the approved variation '
                            .($line->variation->reference ?? '').'. A figure the hirer agreed is not re-typed here — '
                            .'raise another variation for the change.');

                        continue;
                    }

                    $line->update($row);

                    continue;
                }

                BoqItem::create($row + ['project_id' => $project->id, 'source' => 'design']);
            }
        });
    }

    /**  — a line taken off the build, kept on the file with the reason and who took it off. */
    public static function removeBoqLine(BoqItem $line, Request $request): BoqItem
    {
        $data = $request->validate([
            'withdraw_note' => 'required|string|max:1000',
        ], [
            'withdraw_note.required' => 'Say why this line is off the build. A withdrawn BoQ line stays on the file with its reason.',
        ], ['withdraw_note' => 'Why it is withdrawn']);

        abort_if($line->isLocked(), 422,
            'This line came out of an approved variation ('.($line->variation->reference ?? 'a change order').
            '). A price the hirer agreed is changed by raising another variation, not by editing the line.');

        $line->update([
            'status' => 'withdrawn',
            'withdraw_note' => $data['withdraw_note'],
            'withdrawn_by_user' => auth()->id(),
        ]);

        return $line;
    }

    /**  — a day's labour counted against the project and the order it was done on. */
    public static function recordLabour(Project $project, Request $request): LabourEntry
    {
        $data = LabourEntry::dataFrom($request);
        $order = static::orderFor($project);

        return LabourEntry::create([
            'project_id' => $project->id,
            'work_order_id' => $order?->id,
            'worked_on' => $data['worked_on'],
            'worker_name' => $data['worker_name'],
            'fundi_id' => $data['fundi_id'] ?? null,
            'sub_contractor' => $data['sub_contractor'] ?? null,
            'trade' => $data['trade'] ?? null,
            'hours' => $data['hours'],
            'rate_ksh' => $data['rate_ksh'] ?? 0,
            'note' => $data['note'] ?? null,
            'recorded_by_user' => auth()->id(),
        ]);
    }

    /**  — the Construction Manager schedules the visit and lists what is to be checked. */
    public static function scheduleInspection(Project $project, Request $request): SiteInspection
    {
        $data = $request->validate([
            'scheduled_on' => 'required|date',
            'purpose' => 'required|string|max:500',
            'inspector_user' => 'nullable|integer|exists:users,id',
            'items' => 'required|string|max:6000',
        ], [
            'items.required' => 'List what the inspector is to check, one thing per line — an inspection with no items has nothing to mark.',
            'purpose.required' => 'What is this visit for? A foundation check and a completion check are different reports.',
        ], [
            'scheduled_on' => 'Date scheduled for',
            'purpose' => 'What is being inspected',
            'inspector_user' => 'Inspector',
            'items' => 'Items to check (one per line)',
        ]);

        $order = static::orderFor($project);

        $inspection = SiteInspection::create([
            'project_id' => $project->id,
            'work_order_id' => $order?->id,
            'scheduled_on' => $data['scheduled_on'],
            'purpose' => $data['purpose'],
            'inspector_user' => $data['inspector_user'] ?? auth()->id(),
            'status' => 'scheduled',
        ]);

        foreach (preg_split('/\r\n|\r|\n/', $data['items']) as $item) {
            if (filled($item = trim($item))) {
                SiteInspectionItem::create([
                    'inspection_id' => $inspection->id,
                    'item' => str($item)->limit(500, '')->toString(),
                ]);
            }
        }

        abort_if(! $inspection->items()->exists(), 422, 'List what the inspector is to check, one thing per line.');

        return $inspection;
    }

    /**  — the visit is reported: every item marked, the note and the photos filed against the project. */
    public static function reportInspection(SiteInspection $inspection, Request $request): SiteInspection
    {
        $inspection->mustBe('scheduled', 'reported');

        $data = $request->validate([
            'inspected_on' => 'required|date',
            'report_note' => 'required|string|max:5000',
            'photo' => 'nullable|image|max:8192',
            'items' => 'array',
            'items.*.result' => 'required_with:items|in:pass,fail,n_a',
            'items.*.note' => 'nullable|string|max:1000',
        ], [
            'report_note.required' => 'Write the report. The mark on each item says what was found; the note says what it means for the build.',
            'items.*.result.in' => 'Every item has to be marked pass, fail or not applicable before the report is filed.',
        ], [
            'inspected_on' => 'Date of the visit',
            'report_note' => 'The report',
            'photo' => 'Site photos',
        ]);

        $marked = 0;

        foreach ($inspection->items as $item) {
            $row = $data['items'][$item->id] ?? null;

            if (! $row) {
                continue;
            }

            if ($row['result'] === 'fail' && blank($row['note'] ?? null)) {
                abort(422, '"'.str($item->item)->limit(80).'” was marked failed — say what is wrong with it. '
                    .'A failure the worker cannot read is not an instruction.');
            }

            $item->update([
                'result' => $row['result'],
                'note' => str($row['note'] ?? '')->limit(1000, '')->toString() ?: $item->note,
            ]);
            $marked++;
        }

        //  — the report is only a report when every listed item has been marked.
        abort_if($marked === 0, 422, 'Mark at least one item pass, fail or not applicable — the report is read with those marks.');
        abort_if($inspection->items()->where('result', 'pending')->exists(), 422,
            'This report still has '.$inspection->items()->where('result', 'pending')->count()
            .' item(s) with no mark. Mark every item, or move the visit and re-check the rest.');

        $file = $request->hasFile('photo') ? $request->file('photo') : null;

        $inspection->update([
            'inspected_on' => $data['inspected_on'],
            'report_note' => $data['report_note'],
            'status' => 'reported',
            'inspector_user' => $inspection->inspector_user ?? auth()->id(),
        ]);

        if ($file) {
            $inspection->update([
                'photo_path' => $file->store('construction/inspections/'.$inspection->id, 'local'),
                'photo_name' => $file->getClientOriginalName(),
                'photo_size' => $file->getSize(),
            ]);
        }

        return $inspection;
    }

    /**  — the worker answers a failed item with the proof of the fix. */
    public static function proveFix(SiteInspectionItem $item, Request $request, int $fundiId): SiteInspectionItem
    {
        abort_unless($item->result === 'fail', 422, 'Only a failed item needs proof of a fix.');

        if ($item->rechecked_on !== null) {
            abort(422, 'The inspector has already re-checked this item on '.$item->rechecked_on->format('d M Y').'. '
                .'If it has failed again, the office schedules a new inspection.');
        }

        $data = $request->validate([
            'proof_note' => 'required|string|max:1000',
            'proof' => 'required|image|max:8192',
        ], [
            'proof.required' => 'Attach the photograph of the rework — that is what the inspector re-checks against.',
            'proof_note.required' => 'Say what you did to put it right.',
        ], ['proof_note' => 'What was done', 'proof' => 'Photo of the fix']);

        $item->storeProof($request->file('proof'), $data['proof_note'], $fundiId);

        return $item;
    }

    /**  — the inspector re-checks a failed item and it either passes or stays failed for another round. */
    public static function recheckItem(SiteInspectionItem $item, Request $request): SiteInspectionItem
    {
        $data = $request->validate([
            'result' => 'required|in:pass,n_a,fail',
            'note' => 'required|string|max:1000',
        ], [
            'note.required' => 'Write what the re-check found. A failed item is only cleared by an inspector saying so.',
        ], ['result' => 'On the re-check', 'note' => 'What the re-check found']);

        abort_if($item->result === 'fail' && blank($item->proof_path), 422,
            'This item has no proof of a fix on it yet. The worker answers a failure first, then it is re-checked — '
            .'an inspector cannot clear a failure the site has not answered.');

        $item->update([
            'result' => $data['result'],
            'note' => str($data['note'])->limit(1000, '')->toString(),
            'rechecked_on' => today(),
        ]);

        return $item;
    }

    /**
     *  — a report is signed off when every item has been marked and nothing that failed is still standing:
     * each failure needs the worker's proof and a dated re-check. The signature is the inspector's own.
     */
    public static function signOffInspection(SiteInspection $inspection, Request $request): SiteInspection
    {
        $inspection->mustBe('reported');

        $data = $request->validate([
            'sign_off_note' => 'required|string|max:1000',
        ], [
            'sign_off_note.required' => 'Sign the report off with a line the site can read back.',
        ], ['sign_off_note' => 'Signing note']);

        if ($open = $inspection->items()->where('result', 'pending')->count()) {
            abort(422, $open.' item(s) on this report still have no mark. A report is signed off on checked items.');
        }

        $waiting = $inspection->openFailures();

        if ($waiting) {
            abort(422, $waiting.' failed item(s) have not been re-checked yet. The worker uploads proof and the inspector '
                .'re-checks each one before this report can be signed off.');
        }

        $inspection->update([
            'status' => 'signed_off',
            'report_note' => rtrim((string) $inspection->report_note).' — Signed off: '.str($data['sign_off_note'])->limit(600),
            'signed_off_on' => now(),
            'signed_off_by_user' => auth()->id(),
        ]);

        return $inspection;
    }

    /**  — the change is raised against the order the work is running on. */
    public static function raiseVariation(Project $project, Request $request): VariationOrder
    {
        $data = VariationOrder::dataFrom($request);
        $order = static::orderFor($project);

        abort_if(! $order, 422, 'There is no work order on '.$project->reference.' to vary yet — the project has to be '
            .'awarded and its order issued before a change can be priced.');

        return VariationOrder::create($data + [
            'reference' => VariationOrder::nextReference(),
            'project_id' => $project->id,
            'work_order_id' => $order->id,
            'status' => 'proposed',
            'raised_by_user' => auth()->id(),
        ]);
    }

    /**
     *  — a Director approves or rejects. Approval is the only thing that moves money from this module:
     * the order's agreed amount rises, the unsigned stages of a milestone split are re-priced around it, the BoQ gains
     * its line, and a payout already handed to Finance is corrected with it. A payout that is approved or released is
     * refused — that figure has been through the release gate and is settled on the finance register, not here.
     */
    public static function decideVariation(VariationOrder $variation, Request $request): VariationOrder
    {
        abort_if($variation->status !== 'proposed', 422,
            $variation->reference.' was already '.$variation->statusLabel().' on '
            .($variation->decided_on?->format('d M Y') ?? 'a date on the file').'. A decided change is answered by a new one.');

        Finance::requireRelease('Deciding the variation '.$variation->reference);

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'decision_note' => 'required|string|max:1000',
        ], [
            'decision_note.required' => 'Write the reason. A hirer and a fundi both read this figure.',
        ], ['decision' => 'Decision', 'decision_note' => 'The decision']);

        $order = $variation->workOrder;

        if ($data['decision'] === 'approved' && $order) {
            $payout = Execution::payoutFor($order);

            abort_if($payout && in_array($payout->stage, ['approved', 'released'], true), 422,
                $payout->reference.' has already been '.($payout->stage === 'released' ? 'released' : 'approved')
                .' on the Finance register. Settle '.$variation->reference.' as its own payout line there — '
                .'an amount that has passed the release gate is not edited from the construction file.');
        }

        $variation->update([
            'status' => $data['decision'],
            'decision_note' => $data['decision_note'],
            'decided_by_user' => auth()->id(),
            'decided_on' => now(),
        ]);

        if ($data['decision'] === 'rejected' || ! $order) {
            return $variation;
        }

        DB::transaction(function () use ($variation, $order, $data) {
            $extra = (float) $variation->extra_cost_ksh;

            BoqItem::create([
                'project_id' => $variation->project_id,
                'drawing_ref' => $variation->reference,
                'description' => 'Variation '.$variation->reference.': '.str($variation->scope)->limit(460, '')->toString(),
                'unit' => 'sum',
                'quantity' => 1,
                'unit_cost_ksh' => $extra,
                'position' => 9000,
                'source' => 'variation',
                'variation_order_id' => $variation->id,
            ]);

            $newTotal = (float) $order->agreed_amount_ksh + $extra;
            $order->update(['agreed_amount_ksh' => $newTotal]);

            // Unsigned stages are re-priced on the new total; a stage with a signature on it keeps the figure it was
            // signed at — the same rule the delivery sheet applies to a re-plan.
            if ($order->schedule === 'milestone') {
                foreach ($order->milestones()->where('status', '!=', WorkOrderMilestone::DROPPED)->get() as $stage) {
                    if ($stage->signed_on === null) {
                        $stage->update(['amount_ksh' => round($newTotal * ((int) $stage->share_percent / 100), 2)]);
                    }
                }
            }

            if ($extra > 0 && $order->deadline && $variation->extra_days > 0) {
                $order->update(['deadline' => $order->deadline->copy()->addDays((int) $variation->extra_days)]);
            }

            if ($payout = Execution::payoutFor($order)) {
                Payouts::carryAgreed($payout, $newTotal);
            }

            $variation->update([
                'decision_note' => $data['decision_note'].' — '.$order->reference.' now stands at '.Finance::ksh($newTotal).'.',
            ]);
        });

        return $variation;
    }

    /**
     *  — the failed items on this worker's own orders that still wait for the photograph of the rework.
     * Resolved from the signed-in member's record, never from a url: an item on somebody else's site does not exist here.
     */
    public static function fixesFor($member)
    {
        $orderIds = WorkOrder::where('assignee_type', $member instanceof \App\Models\CtpRegistration ? 'ctp' : 'fundi')
            ->where('assignee_id', $member->id)
            ->pluck('id');

        return SiteInspectionItem::with(['inspection.project', 'inspection.workOrder'])
            ->whereHas('inspection', fn ($q) => $q->whereIn('work_order_id', $orderIds))
            ->where('result', 'fail')
            ->whereNull('rechecked_on')
            ->orderByDesc('id')
            ->get();
    }

    /** The queues the register and the department badge count. */    public static function queues(): array
    {
        return [
            'Variations waiting on a Director' => VariationOrder::where('status', 'proposed')->count(),
            'Inspection items failed and open' => SiteInspectionItem::where('result', 'fail')->whereNull('rechecked_on')->count(),
            'Inspections waiting to be reported' => SiteInspection::where('status', 'scheduled')->count(),
            'Projects with no site assessment' => Project::where('status', 'awarded')
                ->whereDoesntHave('assessments')->get()
                ->filter(fn (Project $p) => static::isConstruction($p))->count(),
        ];
    }

    public static function badge(): int
    {
        return VariationOrder::where('status', 'proposed')->count()
            + SiteInspectionItem::where('result', 'fail')->whereNull('rechecked_on')->count();
    }

    /** A drawing, a photo or a signed report off the private disk, read through the record it belongs to. */
    public static function serve(?string $path, ?string $name, string $fallback): mixed
    {
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404,
            'That document is no longer on record.');

        return Storage::disk('local')->response($path, $name ?: $fallback);
    }
}
