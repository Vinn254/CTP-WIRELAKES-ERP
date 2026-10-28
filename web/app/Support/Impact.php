<?php

namespace App\Support;

use App\Models\Beneficiary;
use App\Models\BudgetLine;
use App\Models\Certification;
use App\Models\Cohort;
use App\Models\Community;
use App\Models\CtpRegistration;
use App\Models\Donation;
use App\Models\Enrollment;
use App\Models\EWasteCollection;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\InventoryItem;
use App\Models\Need;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\Trainer;
use App\Models\TrainingVenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Module 12 — the indicators  measures. Nothing here is stored: every figure is read out of the tables the
 * other modules write, so a report can never drift from the records behind it. Where the blueprint asks for a
 * number no module keeps yet (an ICT centre, a refurbished device), the indicator says so instead of guessing.
 */
class Impact
{
    public const GROUPS = [
        'skills' => ['label' => 'Skills', 'note' => 'People the programme has trained, certified and put to work'],
        'technology' => ['label' => 'Technology', 'note' => 'Technology work being trained, asked for and stocked'],
        'infrastructure' => ['label' => 'Infrastructure', 'note' => 'Build and installation work in the programme'],
        'e_waste' => ['label' => 'E-waste', 'note' => 'What the field has collected and weighed in'],
        'economic' => ['label' => 'Economic impact', 'note' => 'Money that moved, and the work it paid for'],
    ];

    /** The courses that count as technology training, by their name on the closed catalogue. */
    public const TECHNOLOGY_FAMILIES = ['ICT', 'Networking', 'Programming', 'Robotics', 'AI'];

    /**
     * The nine lines the programme reports against — the same closed list finance budgets against.
     * A project is placed here by the trade typed when it was posted; the first matching group wins.
     */
    public const SECTOR_KEYWORDS = [
        'ewaste' => ['e-waste', 'ewaste', 'waste', 'recycle', 'scrap', 'salvage'],
        'construction' => ['construct', 'building', 'build', 'mason', 'carpenter', 'roof', 'plumb', 'weld', 'civil', 'fitout'],
        'electrical' => ['electric', 'wiring', 'power', 'solar', 'lighting', 'inverter', 'voltage'],
        'robotics' => ['robot', 'stem', 'drone', 'mechatronic'],
        'ai' => ['ai', 'artificial', 'machine learning', 'data science', 'automation'],
        'networking' => ['network', 'internet', 'router', 'switch', 'cabling', 'wifi', 'fiber', 'fibre', 'hotspot'],
        'ict' => ['ict', 'computer', 'laptop', 'printer', 'hardware', 'digital', 'it support'],
        'training' => ['course', 'cohort', 'class', 'workshop', 'training'],
        'operations' => ['office', 'operations', 'admin', 'logistics'],
    ];

    /**  — the live totals shown at the top of the master dashboard. */
    public static function headline(?string $county = null): array
    {
        $released = static::releasedPayouts($county);

        return [
            ['label' => 'CTPs', 'value' => static::count(CtpRegistration::query(), $county)],
            ['label' => 'E-Fundis', 'value' => static::count(FundiRegistration::where('status', 'active'), $county)],
            ['label' => 'Projects', 'value' => static::count(Project::query(), $county)],
            ['label' => 'Beneficiaries', 'value' => static::beneficiaries($county)->count()],
            ['label' => 'Students trained', 'value' => static::seats($county, 'state', 'approved')],
            ['label' => 'Certified', 'value' => static::certificates($county)],
            ['label' => 'E-waste weighed in', 'value' => round(static::weighedKg($county), 1), 'unit' => 'kg'],
            ['label' => 'Paid to E-Fundis', 'value' => $released['amount_ksh'], 'unit' => 'money'],
        ];
    }

    /** Every group of  with its indicators, ready for the dashboard. */
    public static function indicators(?string $county = null): array
    {
        $ctpWorking = static::count(CtpRegistration::whereIn('status', CtpRegistration::WORKING), $county);
        $ctpWaiting = static::count(CtpRegistration::whereIn('status', ['pending_payment', 'pending_review']), $county);
        $fundiAll = static::count(FundiRegistration::query(), $county);
        $fundiActive = static::count(FundiRegistration::where('status', 'active'), $county);

        $seats = static::seats($county, 'state', 'approved');
        $finished = static::seats($county, 'stage', 'completed');
        $incomplete = static::seats($county, 'stage', 'failed');
        $certified = static::certificates($county);
        $reached = static::beneficiaries($county)->count();

        $techRuns = static::cohorts($county, null);
        $techDone = static::cohorts($county, 'completed');
        $venues = static::count(TrainingVenue::where('status', 'active'), $county);
        $stock = (int) InventoryItem::where('status', 'active')->where('category', 'technology')->sum('qty_on_hand');

        $weighed = round(static::weighedKg($county), 1);
        $claimed = round(static::claimedKg($county), 1);
        $wasteLines = static::count(EWasteCollection::query(), $county);
        $reusable = static::count(EWasteCollection::where('condition', 'working'), $county);
        $hazardous = static::count(EWasteCollection::where('condition', 'hazardous'), $county);

        $income = static::sum('income', $county);
        $expense = static::sum('expense', $county);
        $released = static::releasedPayouts($county);
        $fees = static::feeIncome($county);
        $materials = static::materialsHanded($county);
        $awarded = static::count(Project::whereIn('status', ['awarded', 'closed']), $county);

        $where = $county ? 'in '.$county : 'programme-wide';

        return [
            'skills' => [
                ['label' => 'CTPs in the programme', 'value' => static::count(CtpRegistration::query(), $county), 'unit' => 'count',
                    'note' => $ctpWorking.' working ('.implode(', ', CtpRegistration::WORKING).'), '.$ctpWaiting.' still awaiting a decision'],
                ['label' => 'E-Fundis in the pool', 'value' => $fundiActive, 'unit' => 'count',
                    'note' => $fundiAll.' registered '.$where.', including any on hold, rejected or blocked'],
                ['label' => 'Seats given on a cohort', 'value' => $seats, 'unit' => 'count',
                    'note' => $finished.' finished the course, '.$incomplete.' did not complete it'],
                ['label' => 'Certificates issued', 'value' => $certified, 'unit' => 'count',
                    'note' => $seats > 0 ? round($certified / $seats * 100).'% of the seats given — signed off one at a time by an executive' : 'No certificate issued yet'],
                ['label' => 'Communities confirmed', 'value' => static::count(Community::where('status', 'active'), $county), 'unit' => 'count',
                    'note' => 'People recorded as reached in them: '.$reached],
                ['label' => 'Trainers approved', 'value' => (int) Trainer::where('status', 'approved')
                    ->when($county, fn ($q) => $q->where('county', $county))->count(), 'unit' => 'count',
                    'note' => 'Approved at one site each — an E-Fundi, a staff account or an applicant'],
            ],
            'technology' => [
                ['label' => 'Technology trainings run', 'value' => $techRuns, 'unit' => 'count',
                    'note' => $techDone.' completed. Counted where the cohort ran one of these courses: '.implode(', ', self::TECHNOLOGY_FAMILIES)],
                ['label' => 'Training centres and sites', 'value' => $venues, 'unit' => 'count',
                    'note' => 'Partner schools, community sites and programme centres that host cohorts'],
                ['label' => 'Technology needs raised by the field', 'value' => static::needs($county, ['ict', 'internet', 'robotics']), 'unit' => 'count',
                    'note' => 'What CTPs asked for, before any of it became a project'],
                ['label' => 'Technology on the shelf', 'value' => $stock, 'unit' => 'count',
                    'note' => 'Pieces held across the stores. An item is only counted as deployed when it leaves the store on a goods-out'],
                ['label' => 'ICT centres established', 'pending' => true,
                    'note' => 'Technology Services keeps assets and centres. Nothing is counted here until that register exists'],
            ],
            'infrastructure' => [
                ['label' => 'Build and installation projects', 'value' => static::projectsInSectors($county, ['construction', 'electrical']), 'unit' => 'count',
                    'note' => 'Projects whose typed trade falls on the construction or electrical budget line'],
                ['label' => 'Projects awarded and working', 'value' => $awarded, 'unit' => 'count',
                    'note' => 'One worker is assigned to each of these'],
                ['label' => 'Infrastructure needs prioritised', 'value' => static::needs($county, ['infrastructure']), 'unit' => 'count',
                    'note' => 'Needs the office has prioritised but not yet turned into a project'],
                ['label' => 'Sites inspected, BOQs and variations', 'pending' => true,
                    'note' => 'Construction Works keeps the site record. Until it opens, build work is measured by the projects and budget lines above'],
            ],
            'e_waste' => [
                ['label' => 'Weight collected and verified', 'value' => $weighed, 'unit' => 'kg',
                    'note' => 'Weighed in at the collection point. Field claims not yet weighed come to '.$claimed.' kg'],
                ['label' => 'Items registered', 'value' => $wasteLines, 'unit' => 'count',
                    'note' => 'One line is one item type, collected on one date at one place'],
                ['label' => 'Still working as it is', 'value' => $reusable, 'unit' => 'count',
                    'note' => 'Condition recorded as working — reusable without repair'],
                ['label' => 'Hazardous, awaiting certified disposal', 'value' => $hazardous, 'unit' => 'count',
                    'note' => 'These leave only through a disposal partner'],
                ['label' => 'Devices refurbished and material recycled', 'pending' => true,
                    'note' => 'The repair / refurbish / recycle decision is the next slice of the E-Waste register'],
            ],
            'economic' => [
                ['label' => 'Money in', 'value' => $income, 'unit' => 'money',
                    'note' => 'Every income line on the money register '.$where],
                ['label' => 'Money out', 'value' => $expense, 'unit' => 'money',
                    'note' => 'Charged against the budget lines'],
                ['label' => 'Balance', 'value' => $income - $expense, 'unit' => 'money',
                    'note' => 'What the register shows left'],
                ['label' => 'Released to E-Fundis', 'value' => $released['amount_ksh'], 'unit' => 'money',
                    'note' => $released['lines'].' payouts released, covering '.$released['people'].' members'],
                ['label' => 'Fees collected from members', 'value' => $fees, 'unit' => 'money',
                    'note' => 'Membership and course fees on the register — the field never holds these'],
                ['label' => 'Materials handed to members', 'value' => $materials, 'unit' => 'money',
                    'note' => 'Value the programme provided on projects, taken off those payouts'],
                ['label' => 'Businesses reached', 'value' => static::beneficiaries($county)->where('type', 'business')->count(), 'unit' => 'count',
                    'note' => 'Recorded as beneficiaries. A business the programme started is a record still to open'],
                ['label' => 'Donations pledged on the website', 'value' => (float) Donation::sum('amount'), 'unit' => 'money',
                    'note' => 'A pledge, not money received — a gift that arrives is posted on the register'],
            ],
        ];
    }

    /** Projects grouped on the reporting sectors, from the trade typed at posting. */
    public static function sectors(?string $county = null): array
    {
        $projects = Project::when($county, fn ($q) => $q->where('county', $county))->get(['trade', 'status']);
        $out = [];

        foreach (BudgetLine::categories() as $key => $label) {
            $matched = $projects->filter(fn (Project $p) => static::sectorOf($p->trade) === $key);

            $out[$key] = [
                'label' => $label,
                'total' => $matched->count(),
                'working' => $matched->whereIn('status', ['awarded', 'closed'])->count(),
                'trades' => $matched->pluck('trade')->filter()->unique()->values()->take(4)->all(),
            ];
        }

        $unmatched = $projects->filter(fn (Project $p) => static::sectorOf($p->trade) === null);
        $out['unmatched'] = [
            'label' => 'Trade not matched to a budget line',
            'total' => $unmatched->count(),
            'working' => $unmatched->whereIn('status', ['awarded', 'closed'])->count(),
            'trades' => $unmatched->pluck('trade')->map(fn ($t) => $t ?: '(not typed)')->unique()->values()->take(4)->all(),
        ];

        return $out;
    }

    /** Which reporting sector a typed trade falls in — the first keyword group that matches wins. */
    public static function sectorOf(?string $trade): ?string
    {
        $word = mb_strtolower(trim((string) $trade));

        if ($word === '') {
            return null;
        }

        foreach (static::SECTOR_KEYWORDS as $key => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($word, $needle)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Where each layer of the  map has records, counted county by county. */
    public static function layers(): array
    {
        return [
            'communities' => ['label' => 'Communities', 'route' => 'admin.communities.index',
                'counts' => static::byCounty(Community::query())],
            'ctps' => ['label' => 'CTPs', 'route' => 'admin.promoters.index',
                'counts' => static::byCounty(CtpRegistration::query())],
            'fundis' => ['label' => 'E-Fundis', 'route' => 'admin.fundis.index',
                'counts' => static::byCounty(FundiRegistration::query())],
            'venues' => ['label' => 'Training centres', 'route' => 'admin.venues.index',
                'counts' => static::byCounty(TrainingVenue::query())],
            'projects' => ['label' => 'Projects', 'route' => 'admin.projects.index',
                'counts' => static::byCounty(Project::query())],
            'ewaste' => ['label' => 'E-waste collections', 'route' => 'admin.ewaste.index',
                'counts' => static::byCounty(EWasteCollection::query())],
        ];
    }

    /** Counties holding any record on any layer, heaviest first — the rows a county report is written from. */
    public static function counties(): array
    {
        $out = [];

        foreach (static::layers() as $layer) {
            foreach ($layer['counts'] as $county => $n) {
                $out[$county] = ($out[$county] ?? 0) + $n;
            }
        }

        arsort($out);

        return $out;
    }

    /** The members, places and work one county holds — a county coordinator's report body. */
    public static function countyRows(string $county): array
    {
        return [
            ['Communities', static::count(Community::query(), $county), 'admin.communities.index', ['county' => $county]],
            ['CTPs', static::count(CtpRegistration::query(), $county), 'admin.promoters.index', ['county' => $county]],
            ['E-Fundis', static::count(FundiRegistration::query(), $county), 'admin.fundis.index', ['county' => $county]],
            ['Training centres', static::count(TrainingVenue::query(), $county), 'admin.venues.index', ['county' => $county]],
            ['Cohorts', (int) Cohort::where('county', $county)->count(), 'admin.cohorts.index', []],
            ['Seats given', static::seats($county, 'state', 'approved'), 'admin.cohorts.results', []],
            ['Certificates issued', static::certificates($county), 'admin.certification.index', []],
            ['Projects', static::count(Project::query(), $county), 'admin.projects.index', ['county' => $county]],
            ['E-waste lines', static::count(EWasteCollection::query(), $county), 'admin.ewaste.index', []],
            ['Kg weighed in', round(static::weighedKg($county), 1), 'admin.ewaste.index', []],
            ['Beneficiaries', static::beneficiaries($county)->count(), 'admin.beneficiaries.index', []],
            ['Needs raised', (int) Need::whereHas('community', fn ($c) => $c->where('county', $county))->count(), 'admin.needs.index', []],
        ];
    }

    private static function byCounty(Builder $query): array
    {
        return $query->whereNotNull('county')->where('county', '!=', '')
            ->select('county', DB::raw('count(*) as total'))->groupBy('county')
            ->pluck('total', 'county')->map(fn ($n) => (int) $n)->all();
    }

    private static function count(Builder $query, ?string $county): int
    {
        return (int) $query->when($county, fn ($q) => $q->where('county', $county))->count();
    }

    private static function seats(?string $county, string $column, string $value): int
    {
        return (int) Enrollment::where($column, $value)
            ->when($county, fn ($q) => $q->whereHas('cohort', fn ($c) => $c->where('county', $county)))
            ->count();
    }

    private static function certificates(?string $county): int
    {
        return (int) Certification::where('status', 'approved')
            ->when($county, fn ($q) => $q->whereHas('enrollment.cohort', fn ($c) => $c->where('county', $county)))
            ->count();
    }

    private static function beneficiaries(?string $county): Builder
    {
        return Beneficiary::when($county, fn ($q) => $q->whereHas('community', fn ($c) => $c->where('county', $county)));
    }

    private static function cohorts(?string $county, ?string $status): int
    {
        return (int) Cohort::whereHas('training', fn ($t) => $t->whereIn('name', self::TECHNOLOGY_FAMILIES))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($county, fn ($q) => $q->where('county', $county))->count();
    }

    private static function needs(?string $county, array $types): int
    {
        return (int) Need::whereIn('type', $types)
            ->when($county, fn ($q) => $q->whereHas('community', fn ($c) => $c->where('county', $county)))
            ->count();
    }

    private static function projectsInSectors(?string $county, array $keys): int
    {
        return (int) Project::when($county, fn ($q) => $q->where('county', $county))->get(['trade'])
            ->filter(fn (Project $p) => in_array(static::sectorOf($p->trade), $keys, true))->count();
    }

    private static function weighedKg(?string $county): float
    {
        return (float) EWasteCollection::whereNotNull('weighed_weight_kg')
            ->when($county, fn ($q) => $q->where('county', $county))->sum('weighed_weight_kg');
    }

    private static function claimedKg(?string $county): float
    {
        return (float) EWasteCollection::whereNull('weighed_weight_kg')
            ->when($county, fn ($q) => $q->where('county', $county))->sum('estimated_weight_kg');
    }

    /**
     * The register carries no county of its own — a line sits on a community, project, cohort or member instead,
     * so a county figure is the money tied to anything in that county.
     */
    private static function money(?string $direction, ?string $county, array $kinds = []): Builder
    {
        $q = FinanceEntry::where('direction', $direction)
            ->when($kinds, fn ($w) => $w->whereIn('kind', $kinds));

        return $q->when($county, fn ($w) => $w->where(function ($o) use ($county) {
            $o->whereHas('community', fn ($c) => $c->where('county', $county))
                ->orWhereHas('project', fn ($p) => $p->where('county', $county))
                ->orWhereHas('cohort', fn ($c) => $c->where('county', $county))
                ->orWhereHas('fundi', fn ($f) => $f->where('county', $county));
        }));
    }

    private static function sum(string $direction, ?string $county): float
    {
        return (float) static::money($direction, $county)->sum('amount_ksh');
    }

    private static function feeIncome(?string $county): float
    {
        return (float) static::money('income', $county, ['membership_fee', 'training_fee'])->sum('amount_ksh');
    }

    private static function materialsHanded(?string $county): float
    {
        return (float) ProjectMaterial::whereNotNull('supplied_on')
            ->whereHas('project', fn ($p) => $p->when($county, fn ($q) => $q->where('county', $county)))
            ->get(['quantity', 'unit_cost_ksh'])
            ->sum(fn (ProjectMaterial $m) => (float) $m->quantity * (float) $m->unit_cost_ksh);
    }

    /** @return array{amount_ksh: float, lines: int, people: int} */
    private static function releasedPayouts(?string $county): array
    {
        $q = fn () => Payout::where('stage', 'released')
            ->when($county, fn ($p) => $p->whereHas('fundi', fn ($f) => $f->where('county', $county)));

        return [
            'amount_ksh' => (float) $q()->sum('net_ksh'),
            'lines' => (int) $q()->count(),
            'people' => (int) $q()->distinct('fundi_id')->count('fundi_id'),
        ];
    }
}
