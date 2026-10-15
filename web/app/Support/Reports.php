<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\BudgetLine;
use App\Models\Certification;
use App\Models\Cohort;
use App\Models\Community;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\EWasteCollection;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\InventoryItem;
use App\Models\Need;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingVenue;
use Illuminate\Support\Facades\DB;

/**
 * Module 12 — the reports §15.5, one per reader. A report is built as sections of columns and rows, so the same
 * figures print on screen, go into the CSV the office exports, and cannot be edited into a nicer shape: every row
 * is read out of the register another module owns.
 */
class Reports
{
    public const READERS = [
        'system' => ['reader' => 'Super admin', 'title' => 'Full system report',
            'note' => 'Every module in one page — what is registered, what is running, what is waiting.'],
        'strategic' => ['reader' => 'Director', 'title' => 'Strategic report',
            'note' => 'Programme impact, the money summary, and the issues sitting on top of the desk.'],
        'portfolio' => ['reader' => 'Programme manager', 'title' => 'Project portfolio',
            'note' => 'Every project and its stage, budget against the money actually spent, and the payouts held up.'],
        'county' => ['reader' => 'County coordinator', 'title' => 'County report',
            'note' => 'One county: its communities, members, trainings, projects and collections.', 'county' => true],
        'training' => ['reader' => 'Trainer', 'title' => 'Training report',
            'note' => 'Cohorts and their seats, who finished, pass rates and the certificates waiting to be issued.'],
        'finance' => ['reader' => 'Finance officer', 'title' => 'Finance report',
            'note' => 'Cash position, money in by kind, money out against each budget line, and what is owed.'],
        'procurement' => ['reader' => 'Procurement officer', 'title' => 'Procurement report',
            'note' => 'Open requests and orders by step, suppliers and what they have been paid, and orders sitting undelivered.'],
        'stock' => ['reader' => 'Storekeeper', 'title' => 'Stock report',
            'note' => 'Items under their reorder level, goods still to be received, and what left the store per project.'],
        'ewaste' => ['reader' => 'E-waste manager', 'title' => 'E-waste report',
            'note' => 'Weight and items by type, what has not been weighed in, and the hazardous lines.'],
        'impact' => ['reader' => 'M&E officer', 'title' => 'Indicator report',
            'note' => 'The §15.2 indicators as rows, counties ranked, and work grouped on the nine budget lines.'],
        'member' => ['reader' => 'CTP or E-Fundi', 'title' => 'A member reads their own record',
            'note' => 'Not a staff report — a member sees their own seats, projects and earnings in the member portal.'],
        'donor' => ['reader' => 'Donor or partner', 'title' => 'Donor report', 'pending' => true,
            'note' => 'Waits for donor records tied to specific funds. A gift that arrives is on the money register today, with no donor file behind it.'],
    ];

    /** @return array{key: string, title: string, reader: string, county: ?string, sections: array, foot: string} */
    public static function build(string $key, ?string $county = null): array
    {
        $reader = self::READERS[$key];

        return [
            'key' => $key,
            'title' => $reader['title'],
            'reader' => $reader['reader'],
            'note' => $reader['note'],
            'county' => $county,
            'sections' => match ($key) {
                'system' => static::system(),
                'strategic' => static::strategic($county),
                'portfolio' => static::portfolio($county),
                'county' => static::county($county),
                'training' => static::training($county),
                'finance' => static::finance($county),
                'procurement' => static::procurement($county),
                'stock' => static::stock(),
                'ewaste' => static::ewaste($county),
                'impact' => static::impact($county),
                default => [static::section('This report', ['Note'], [[
                    $reader['note'].' — nothing is counted here that the modules do not already hold.',
                ]])],
            },
            'foot' => 'Built '.now()->format('d M Y H:i').'. Every figure is read from the registers the other modules write; none of it is stored here (§15.5).',
        ];
    }

    public static function system(): array
    {
        return [
            static::section('Members and places', ['Register', 'Recorded', 'Working or confirmed', 'Waiting on a decision'], [
                ['CTPs', CtpRegistration::count(), CtpRegistration::whereIn('status', CtpRegistration::WORKING)->count(),
                    CtpRegistration::whereIn('status', ['pending_payment', 'pending_review'])->count()],
                ['E-Fundis', FundiRegistration::count(), FundiRegistration::where('status', 'active')->count(),
                    FundiRegistration::whereIn('status', ['pending_payment', 'pending_review'])->count()],
                ['Hirers', HirerRegistration::count(), HirerRegistration::where('status', 'active')->count(),
                    HirerRegistration::where('status', 'pending_review')->count()],
                ['Fundi-trainers', Trainer::count(), Trainer::where('status', 'approved')->count(),
                    Trainer::where('status', 'applied')->count()],
                ['Communities', Community::count(), Community::where('status', 'active')->count(),
                    Community::where('status', 'pending_review')->count()],
                ['Beneficiaries', Beneficiary::count(), '—', '—'],
            ]),
            static::section('Training', ['Register', 'Recorded', 'Detail'], [
                ['Cohorts', Cohort::count(), Cohort::where('status', 'in_progress')->count().' running, '.Cohort::where('status', 'completed')->count().' completed'],
                ['Seats given', Enrollment::where('state', 'approved')->count(), Enrollment::where('stage', 'completed')->count().' completed the course'],
                ['Certificates issued', Certification::where('status', 'approved')->count(), Certification::where('status', 'recommended')->count().' recommended, waiting for an executive'],
                ['Training venues', TrainingVenue::where('status', 'active')->count(), Training::count().' trainings on the closed catalogue'],
            ]),
            static::section('Work', ['Register', 'Recorded', 'Detail'], [
                ['Projects', Project::count(), Project::whereIn('status', ['awarded', 'closed'])->count().' awarded or closed, '.Project::where('status', 'open')->count().' open for applications'],
                ['Applications', ProjectApplication::count(), ProjectApplication::where('status', 'pending_review')->count().' waiting to be decided'],
                ['Needs raised by the field', Need::count(), Need::where('status', 'proposed')->count().' still proposed, '.Need::where('status', 'converted')->count().' turned into a project'],
            ]),
            static::section('E-waste', ['Register', 'Recorded', 'Detail'], [
                ['Collection lines', EWasteCollection::count(), EWasteCollection::where('status', 'received')->count().' weighed in at the office'],
                ['Kg verified', round((float) EWasteCollection::sum('weighed_weight_kg'), 1), round((float) EWasteCollection::whereNull('weighed_weight_kg')->sum('estimated_weight_kg'), 1).' kg claimed but not weighed'],
            ]),
            static::section('Money and purchases', ['Register', 'Recorded', 'Detail'], [
                ['Ledger lines', FinanceEntry::count(), FinanceEntry::where('direction', 'income')->count().' in, '.FinanceEntry::where('direction', 'expense')->count().' out'],
                ['Money in', Finance::ksh((float) FinanceEntry::where('direction', 'income')->sum('amount_ksh')), 'All years on the register'],
                ['Money out', Finance::ksh((float) FinanceEntry::where('direction', 'expense')->sum('amount_ksh')), 'Charged against the budget lines'],
                ['Payouts', Payout::count(), Payout::where('stage', 'released')->count().' released, '.Payout::whereIn('stage', ['queued', 'approved'])->count().' waiting on an executive'],
                ['Purchase requests', PurchaseRequest::count(), PurchaseRequest::whereNotIn('status', ['paid', 'cancelled'])->count().' still open'],
                ['Purchase orders', PurchaseOrder::count(), PurchaseOrder::whereIn('status', ['received', 'matched'])->count().' received, waiting to be paid'],
                ['Stock cards', InventoryItem::where('status', 'active')->count(), (string) InventoryItem::totals()['low'].' below their reorder level'],
            ]),
        ];
    }

    public static function strategic(?string $county): array
    {
        $waiting = collect(['Applicants waiting' => Departments::badge('applicants'),
            'CTP profiles' => Departments::badge('promoters'),
            'Project applications' => Departments::badge('applications'),
            'Communities' => Departments::badge('communities'),
            'Needs to prioritise' => Departments::badge('needs'),
            'Seats awaiting a decision' => Departments::badge('seats'),
            'Trainer applications' => Departments::badge('trainers'),
            'Certificates to issue' => Departments::badge('certificates'),
            'E-Fundis to approve' => Departments::badge('fundis'),
            'Collections to weigh in' => Departments::badge('collections'),
            'Purchases to quote or approve' => Departments::badge('requests'),
            'Payouts to approve or release' => Departments::badge('payouts'),
        ])->filter(fn ($n) => $n > 0)->sortDesc();

        $reached = Impact::counties();

        return [
            static::section('Impact'.($county ? ' — '.$county : ''), ['Measure', 'Figure'],
                collect(Impact::headline($county))->map(fn ($h) => [
                    $h['label'], static::figure($h),
                ])->all()),
            static::section('Money summary', ['Line', 'Figure'], [
                ['Money in, all years', Finance::ksh((float) FinanceEntry::where('direction', 'income')->sum('amount_ksh'))],
                ['Money out, all years', Finance::ksh((float) FinanceEntry::where('direction', 'expense')->sum('amount_ksh'))],
                ['Released to E-Fundis', Finance::ksh((float) Payout::where('stage', 'released')->sum('net_ksh'))],
                ['Budget set for '.now()->year, Finance::ksh((float) BudgetLine::planFor(now()->year)->sum('amount'))],
            ]),
            static::section('Top issues — waiting on a decision now', ['Queue', 'Count'],
                $waiting->isEmpty() ? [['Nothing is waiting on the programme right now', 0]]
                    : $waiting->map(fn ($n, $k) => [$k, $n])->all()),
            static::section('Where the programme is present', ['Counties with records', 'Counties on the register with nothing yet'], [
                [count($reached), max(0, count(KenyaMap::TILES) - count($reached))],
            ]),
        ];
    }

    public static function portfolio(?string $county): array
    {
        $projects = Project::with(['hirer:id,organisation', 'applications'])
            ->when($county, fn ($q) => $q->where('county', $county))
            ->latest('id')->limit(80)->get();

        $year = now()->year;
        $lines = [];

        foreach (BudgetLine::planFor($year) as $line) {
            $spent = (float) $line['spent'];
            $lines[] = [$line['label'], Finance::ksh((float) $line['amount']),
                Finance::ksh($spent), Finance::ksh((float) $line['amount'] - $spent),
                $spent > (float) $line['amount'] ? 'Over the line' : 'Within the line'];
        }

        return [
            static::section('Projects'.($county ? ' in '.$county : ''), ['Reference', 'Project', 'Trade', 'County', 'Stage', 'Applications', 'Budget'],
                $projects->map(fn (Project $p) => [
                    $p->reference, $p->title, $p->trade ?: '—', $p->county ?: '—',
                    Project::STATUSES[$p->status] ?? $p->status, $p->applications->count(),
                    $p->budget ? Finance::ksh((float) $p->budget) : '—',
                ])->all(), 'The 80 newest projects. Nothing here is added to the portfolio — it is the project register read as a list.'),
            static::section('Budget against actual — '.$year, ['Budget line', 'Given', 'Charged', 'Left', 'Standing'], $lines),
            static::section('Payouts held up', ['Reference', 'Stream', 'Stage', 'Net', 'Fundi'],
                Payout::with(['fundi:id,full_name'])->whereIn('stage', ['awaiting_hirer_payment', 'awaiting_report', 'queued', 'approved', 'held'])
                    ->orderBy('id')->limit(40)->get()
                    ->map(fn (Payout $p) => [$p->reference, $p->streamLabel(), $p->stageLabel(), $p->netLabel(), $p->fundi->full_name ?? '—'])->all()),
        ];
    }

    public static function county(?string $county): array
    {
        if (!$county) {
            return [static::section('Counties', ['County', 'Records on every layer', 'CTPs', 'E-Fundis', 'Communities', 'Projects', 'E-waste lines'],
                collect(Impact::counties())->map(fn ($n, $c) => [
                    $c, $n,
                    CtpRegistration::where('county', $c)->count(),
                    FundiRegistration::where('county', $c)->count(),
                    Community::where('county', $c)->count(),
                    Project::where('county', $c)->count(),
                    EWasteCollection::where('county', $c)->count(),
                ])->all(), 'Choose a county above to read one county the way its coordinator does.')];
        }

        $wards = Community::where('county', $county)->select('ward', DB::raw('count(*) as total'))
            ->groupBy('ward')->orderBy('ward')->pluck('total', 'ward');

        return [
            static::section($county.' — what the programme holds', ['Register', 'Records'],
                collect(Impact::countyRows($county))->map(fn ($r) => [$r[0], $r[1]])->values()->all()),
            static::section('Projects in '.$county, ['Reference', 'Project', 'Trade', 'Stage'],
                Project::where('county', $county)->latest('id')->limit(60)->get()
                    ->map(fn (Project $p) => [$p->reference, $p->title, $p->trade ?: '—', Project::STATUSES[$p->status] ?? $p->status])->all()),
            static::section('Cohorts in '.$county, ['Reference', 'Course', 'Term', 'Seats', 'Stage'],
                Cohort::where('county', $county)->orderByDesc('id')->limit(60)->get()
                    ->map(fn (Cohort $c) => [$c->reference, $c->courseLabel(), $c->periodLabel(), $c->seatsUsed().'/'.$c->seats, $c->statusLabel()])->all()),
            static::section('Wards with a confirmed community', ['Ward', 'Communities'],
                $wards->map(fn ($n, $w) => [$w ?: '(not stated)', $n])->all()),
            static::section('E-waste collected in '.$county, ['Reference', 'Item', 'Collected', 'Kg', 'Stage'],
                EWasteCollection::where('county', $county)->latest('id')->limit(60)->get()
                    ->map(fn (EWasteCollection $e) => [
                        $e->reference, $e->itemTypeLabel(), $e->collected_on->format('d M Y'),
                        round((float) ($e->weighed_weight_kg ?? $e->estimated_weight_kg), 1), $e->statusLabel(),
                    ])->all()),
        ];
    }

    public static function training(?string $county): array
    {
        $cohorts = Cohort::withCount(['enrollments as seats_given_count' => fn ($q) => $q->where('state', 'approved')])
            ->when($county, fn ($q) => $q->where('county', $county))
            ->orderByDesc('id')->limit(60)->get();

        $byCourse = Training::orderBy('name')->get()->map(function (Training $t) {
            $rows = Enrollment::whereHas('cohort', fn ($q) => $q->where('training_id', $t->id))->get();
            $done = $rows->where('stage', 'completed')->count();
            $failed = $rows->where('stage', 'failed')->count();

            return [$t->name, $rows->where('state', 'approved')->count(), $done, $failed,
                ($done + $failed) > 0 ? round($done / ($done + $failed) * 100).'%' : '—'];
        })->all();

        return [
            static::section('Cohorts', ['Reference', 'Course', 'Term', 'Seats given', 'Of the seats', 'Stage'],
                $cohorts->map(fn (Cohort $c) => [
                    $c->reference, $c->courseLabel(), $c->periodLabel(), $c->seats_given_count,
                    $c->seats > 0 ? round($c->seats_given_count / $c->seats * 100).'%' : '—', $c->statusLabel(),
                ])->all()),
            static::section('Who finished, per training', ['Training', 'Seats given', 'Completed', 'Did not complete', 'Pass rate'], $byCourse,
                'A pass rate is counted only where a trainee reached a final stage.'),
            static::section('Certificates', ['Stage', 'Count'], [
                ['Recommended by the cohort, waiting for an executive', Certification::where('status', 'recommended')->count()],
                ['Issued', Certification::where('status', 'approved')->count()],
                ['Declined', Certification::where('status', 'declined')->count()],
                ['Revoked', Certification::where('status', 'revoked')->count()],
            ]),
            static::section('Trainers', ['Fundi', 'County', 'Status'],
                Trainer::with('fundi:id,full_name')->when($county, fn ($q) => $q->where('county', $county))
                    ->orderByDesc('status')->limit(40)->get()
                    ->map(fn (Trainer $t) => [$t->fundi->full_name ?? '—', $t->county ?: '—', $t->status])->all()),
        ];
    }

    public static function finance(?string $county): array
    {
        $year = now()->year;
        $income = FinanceEntry::where('direction', 'income')->where('fiscal_year', $year);
        $expense = FinanceEntry::where('direction', 'expense')->where('fiscal_year', $year);

        $kinds = (clone $income)->select('kind', DB::raw('sum(amount_ksh) as total'))->groupBy('kind')
            ->pluck('total', 'kind')->map(fn ($n) => Finance::ksh((float) $n))->all();

        $lines = [];
        foreach (BudgetLine::CATEGORIES as $key => $label) {
            $spent = FinanceEntry::spent($year, $key);
            $planned = (float) BudgetLine::where('fiscal_year', $year)->where('category', $key)->sum('amount_ksh');
            if ($spent <= 0 && $planned <= 0) {
                continue;
            }
            $lines[] = [$label, Finance::ksh($planned), Finance::ksh($spent), Finance::ksh($planned - $spent)];
        }

        return [
            static::section('Cash position — '.$year, ['Line', 'Figure'], [
                ['Money in', Finance::ksh((float) (clone $income)->sum('amount_ksh'))],
                ['Money out', Finance::ksh((float) (clone $expense)->sum('amount_ksh'))],
                ['Balance on the register', Finance::ksh((float) (clone $income)->sum('amount_ksh') - (float) (clone $expense)->sum('amount_ksh'))],
                ['Budget given for the year', Finance::ksh((float) BudgetLine::planFor($year)->sum('amount'))],
            ]),
            static::section('Money in, by what it is', ['Kind', 'Amount'],
                empty($kinds) ? [['No income posted yet this year', 0]] : $kinds),
            static::section('Money out against each budget line', ['Budget line', 'Given', 'Charged', 'Left'], $lines),
            static::section('Payouts due', ['Reference', 'Fundi', 'Stream', 'Stage', 'Net'],
                Payout::with('fundi:id,full_name')->whereIn('stage', ['queued', 'approved', 'held'])
                    ->orderBy('id')->limit(60)->get()
                    ->map(fn (Payout $p) => [$p->reference, $p->fundi->full_name ?? '—', $p->streamLabel(), $p->stageLabel(), $p->netLabel()])->all(),
                'Only a programme executive releases these (§14.6 steps 5–7).'),
            static::section('Waiting before it can be paid', ['Reference', 'Fundi', 'Why it is held', 'Net'],
                Payout::with('fundi:id,full_name')->whereIn('stage', ['awaiting_hirer_payment', 'awaiting_report'])
                    ->orderBy('id')->limit(60)->get()
                    ->map(fn (Payout $p) => [$p->reference, $p->fundi->full_name ?? '—', $p->stageLabel(), $p->netLabel()])->all()),
        ];
    }

    public static function procurement(?string $county): array
    {
        $open = PurchaseRequest::whereNotIn('status', ['paid', 'cancelled'])
            ->when($county, fn ($q) => $q->where('county', $county))
            ->orderBy('id')->limit(60)->get();

        $orders = PurchaseOrder::with(['request:id,item_name', 'supplier:id,name'])->orderByDesc('id')->limit(60)->get();
        $late = $orders->filter(fn (PurchaseOrder $o) => $o->status === 'ordered' && $o->issued_on && $o->issued_on->lt(now()->subDays(14)));

        return [
            static::section('Open requests — where each one sits', ['Reference', 'Item', 'County', 'Step', 'Estimate'],
                $open->map(fn (PurchaseRequest $r) => [
                    $r->reference, $r->item_name, $r->county ?: '—', $r->statusLabel(), $r->costLabel(),
                ])->all(), 'The chain is submitted → approved → quoted → ordered → received → paid (§14.4).'),
            static::section('Purchase orders', ['Reference', 'Supplier', 'Ordered', 'Received', 'Invoice', 'Stage'],
                $orders->map(fn (PurchaseOrder $o) => [
                    $o->reference, $o->supplier->name ?? '—', $o->orderedLabel(), $o->receivedLabel(),
                    $o->invoiceLabel(), $o->statusLabel(),
                ])->all()),
            static::section('Orders placed more than two weeks ago and not received', ['Reference', 'Supplier', 'Ordered on'],
                $late->map(fn (PurchaseOrder $o) => [$o->reference, $o->supplier->name ?? '—', $o->issued_on?->format('d M Y') ?? '—'])->all(),
                'Nothing is chased from this page — it is the list a procurement officer works down.'),
            static::section('Suppliers', ['Code', 'Supplier', 'Orders', 'Value ordered', 'Paid so far', 'Standing'],
                Supplier::orderBy('name')->limit(40)->get()->map(fn (Supplier $s) => [
                    $s->code ?: '—', $s->name,
                    PurchaseOrder::where('supplier_id', $s->id)->count(),
                    Finance::ksh((float) PurchaseOrder::where('supplier_id', $s->id)->sum('amount_ksh')),
                    $s->payLabel(), $s->statusLabel(),
                ])->all()),
        ];
    }

    public static function stock(): array
    {
        $items = InventoryItem::where('status', 'active')->orderBy('name')->get();

        $issued = StockMovement::where('direction', 'out')->with(['item:id,name', 'project:id,title,reference'])
            ->orderByDesc('id')->limit(60)->get();

        return [
            static::section('Below the reorder level', ['SKU', 'Item', 'On hand', 'Reorder at'],
                $items->filter(fn (InventoryItem $i) => $i->isLow())
                    ->map(fn (InventoryItem $i) => [$i->sku, $i->name, $i->quantityLabel(), $i->reorderLabel()])->all(),
                'A card with nothing on the shelf and nothing on order is what a storekeeper chases first.'),
            static::section('Stock at a value', ['Card', 'Item', 'On hand', 'Value'],
                $items->sortByDesc(fn (InventoryItem $i) => (float) $i->qty_on_hand * (float) $i->unit_cost_ksh)
                    ->take(20)->map(fn (InventoryItem $i) => [$i->sku, $i->name, $i->quantityLabel(), $i->valueLabel()])->all(),
                'The 20 dearest cards by value on the shelf.'),
            static::section('Goods received but not yet paid', ['Reference', 'Supplier', 'Invoice', 'Amount', 'Stage'],
                PurchaseOrder::with('supplier:id,name')->where('status', 'received')->orderBy('id')->get()
                    ->map(fn (PurchaseOrder $o) => [$o->reference, $o->supplier->name ?? '—', $o->invoiceLabel(), $o->amountLabel(), $o->statusLabel()])->all()),
            static::section('What left the store', ['Date', 'Item', 'Quantity', 'Value', 'Gone to', 'Reason'],
                $issued->map(fn (StockMovement $m) => [
                    $m->moved_on->format('d M Y'), $m->item->name ?? '—', $m->quantityLabel(), $m->valueLabel(),
                    $m->forLabel(), $m->reasonLabel(),
                ])->all(), 'Each of these lines already posted its own entry on the money register (§14.5).'),
        ];
    }

    public static function ewaste(?string $county): array
    {
        $q = fn () => EWasteCollection::when($county, fn ($w) => $w->where('county', $county));

        $byType = $q()->select('item_type', DB::raw('count(*) as line_count'), DB::raw('sum(coalesce(weighed_weight_kg, estimated_weight_kg, 0)) as kg'))
            ->groupBy('item_type')->orderByDesc('kg')->get();

        return [
            static::section('Collected'.($county ? ' in '.$county : ''), ['Type', 'Lines', 'Kg (weighed where done)'],
                $byType->map(fn ($r) => [EWasteCollection::ITEM_TYPES[$r->item_type] ?? $r->item_type, (int) $r->line_count, round((float) $r->kg, 1)])->all()),
            static::section('Waiting to be weighed in', ['Reference', 'Item', 'County', 'Collected', 'Claimed kg'],
                $q()->where('status', '!=', 'received')->orderBy('id')->limit(60)->get()
                    ->map(fn (EWasteCollection $e) => [
                        $e->reference, $e->itemTypeLabel(), $e->county ?: '—', $e->collected_on->format('d M Y'),
                        round((float) $e->estimated_weight_kg, 1),
                    ])->all()),
            static::section('Condition recorded at intake', ['Condition', 'Lines', 'Kg'],
                collect(EWasteCollection::CONDITIONS)->map(fn ($label, $key) => [
                    $label, $q()->where('condition', $key)->count(),
                    round((float) $q()->where('condition', $key)->sum('weighed_weight_kg'), 1),
                ])->all(), 'Hazardous lines leave only through a certified disposal partner (§13.4).'),
            static::section('Counties', ['County', 'Lines', 'Kg verified'],
                $q()->whereNotNull('county')->where('county', '!=', '')->select('county', DB::raw('count(*) as line_count'), DB::raw('sum(weighed_weight_kg) as kg'))
                    ->groupBy('county')->orderByDesc('line_count')->limit(47)->get()
                    ->map(fn ($r) => [$r->county, (int) $r->line_count, round((float) $r->kg, 1)])->all()),
        ];
    }

    public static function impact(?string $county): array
    {
        $rows = [];

        foreach (Impact::indicators($county) as $key => $group) {
            foreach ($group as $item) {
                $rows[] = [Impact::GROUPS[$key]['label'], $item['label'],
                    isset($item['pending']) ? 'not counted yet' : static::figure($item), $item['note']];
            }
        }

        $sectors = collect(Impact::sectors($county))->map(fn ($s) => [
            $s['label'], $s['total'], $s['working'], $s['trades'] ? implode(', ', $s['trades']) : '—',
        ])->values()->all();

        return [
            static::section('Indicators'.($county ? ' — '.$county : ''), ['Group', 'Indicator', 'Figure', 'What it is counted from'], $rows),
            static::section('Counties, heaviest first', ['County', 'Records on every layer'],
                collect(Impact::counties())->take(47)->map(fn ($n, $c) => [$c, $n])->all()),
            static::section('Projects on the nine budget lines', ['Budget line', 'Projects', 'Awarded or closed', 'Trades typed'], $sectors),
        ];
    }

    /** @param  array{columns: array, rows: array}[]  $sections */
    public static function csv(array $sections): string
    {
        $lines = [];

        foreach ($sections as $section) {
            $lines[] = array_merge([$section['heading']], array_fill(1, max(0, count($section['columns']) - 1), ''));
            $lines[] = $section['columns'];
            foreach ($section['rows'] as $row) {
                $lines[] = array_values($row);
            }
            $lines[] = [];
        }

        $out = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($out, $line);
        }
        rewind($out);

        return stream_get_contents($out);
    }

    private static function section(string $heading, array $columns, array $rows, ?string $note = null): array
    {
        return ['heading' => $heading, 'columns' => $columns,
            'rows' => array_values(array_map(fn ($r) => array_values((array) $r), $rows)), 'note' => $note];
    }

    private static function figure(array $item): string
    {
        return match ($item['unit'] ?? 'count') {
            'money' => Finance::ksh((float) $item['value']),
            'kg' => number_format((float) $item['value'], 1).' kg',
            default => number_format((int) $item['value']),
        };
    }
}
