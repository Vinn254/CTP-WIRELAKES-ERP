<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** One run of one of the programme's trainings at one place — the unit CTPs apply for. */
class Cohort extends Model
{
    public const STATUSES = [
        'draft' => 'Draft — not posted yet',
        'open' => 'Open for applications',
        'in_progress' => 'Training in progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** When in the academic calendar a run sits — a course is expected to run in more than one. */
    public const TERMS = [
        'term_1' => 'Term 1',
        'term_2' => 'Term 2',
        'term_3' => 'Term 3',
        'semester_1' => 'Semester 1',
        'semester_2' => 'Semester 2',
        'annual' => 'Full year',
    ];

    protected $fillable = [
        'reference', 'training_id', 'venue_id', 'trainer_id', 'community_id',
        'county', 'county_scope', 'sub_county', 'ward', 'starts_on', 'ends_on', 'academic_year', 'term',
        'enrol_closes_on', 'seats', 'fee_ksh', 'summary', 'status',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'enrol_closes_on' => 'date',
        'academic_year' => 'integer',
        'seats' => 'integer',
        'fee_ksh' => 'decimal:2',
    ];

    /** The office sets a term when it matters; a cohort always lands in the year and term its dates fall in. */
    protected static function booted(): void
    {
        static::saving(function (self $cohort) {
            if (! $cohort->starts_on) {
                return;
            }

            $cohort->academic_year ??= (int) $cohort->starts_on->year;
            $cohort->term ??= self::guessTerm($cohort->starts_on);
        });
    }

    /** The three-term calendar the programme's intake dates fall into. */
    public static function guessTerm($date): string
    {
        return match (true) {
            (int) $date->month <= 4 => 'term_1',
            (int) $date->month <= 8 => 'term_2',
            default => 'term_3',
        };
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /** Every programme this intake teaches. The id on the cohort row stays the lead one. */
    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'cohort_training')->withTimestamps()->orderBy('trainings.name');
    }

    /** The counties this intake is offered in when it is not offered in all of them. */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'cohort_county')->withTimestamps()->orderBy('counties.name');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(TrainingVenue::class, 'venue_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'trainer_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** The units the office ticked onto this intake — always units of the training the cohort runs. */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(TrainingModule::class, 'cohort_module')->withPivot('trainer_id')->withTimestamps()
            ->orderBy('training_modules.position')->orderBy('training_modules.id');
    }

    /**
     * The trainer the Registrar named for each unit this intake teaches, keyed by unit id. A unit with no name of its
     * own falls back to the cohort's single trainer, so an intake handed to one person reads the same as it always did.
     *
     * @return array<int, Trainer>
     */
    public function unitTrainers(): array
    {
        $units = $this->unitList();
        $ids = $units->pluck('pivot.trainer_id')->filter()->unique()->all();
        $byId = $ids === [] ? collect() : Trainer::with(self::TRAINER_WITH)->whereIn('id', $ids)->get()->keyBy('id');

        return $units->mapWithKeys(fn (TrainingModule $unit) => [
            $unit->id => ($unit->pivot?->trainer_id ? $byId[$unit->pivot->trainer_id] ?? null : null) ?? $this->trainer,
        ])->filter()->all();
    }

    /** How a trainer is named on a screen: whichever of the three records the account stands behind. */
    public const TRAINER_WITH = ['fundi:id,full_name', 'staff:id,name', 'applicant:id,name'];

    /** Every trainer named on this intake's units — a unit may be taught by more than one hand. */
    public function hands(): HasMany
    {
        return $this->hasMany(UnitTrainer::class);
    }

    /**
     * The hands on each unit of this intake, keyed by unit id, the lead name first. A unit nobody was named to falls
     * back to the trainer the intake as a whole carries, so a run handed to one person reads exactly as it always did.
     *
     * @return array<int, Collection<int, Trainer>>
     */
    public function handsMap(): array
    {
        $units = $this->unitList();
        $rows = $this->relationLoaded('hands') ? $this->hands : $this->hands()->get();
        $leads = $units->pluck('pivot.trainer_id')->filter()->unique()->all();

        $ids = collect($rows->pluck('trainer_id')->all())->merge($leads)->filter()->unique()->values()->all();
        $byId = $ids === [] ? collect() : Trainer::with(self::TRAINER_WITH)->whereIn('id', $ids)->get()->keyBy('id');

        return $units->mapWithKeys(function (TrainingModule $unit) use ($rows, $byId) {
            $hands = $rows->where('training_module_id', $unit->id)
                ->sortBy(fn ($row) => (int) $row->trainer_id === (int) ($unit->pivot?->trainer_id ?? 0) ? 0 : 1)
                ->pluck('trainer_id')->map(fn ($id) => $byId[$id] ?? null)->filter()->unique('id')->values();

            if ($hands->isEmpty()) {
                $hands = collect([
                    ($unit->pivot?->trainer_id ? $byId[$unit->pivot->trainer_id] ?? null : null) ?? $this->trainer,
                ])->filter()->values();
            }

            return [$unit->id => $hands];
        })->all();
    }

    /** The names on one unit as one line — “Mary Achieng, Joseph Kiprotich”. */
    public function handsLine(?int $unitId): ?string
    {
        $names = ($this->handsMap()[$unitId ?? 0] ?? collect())->map->personName()->filter()->all();

        return $names === [] ? null : implode(', ', $names);
    }

    /** The trainers on one of this intake's units, the lead name first. */
    public function handsFor(?int $unitId): Collection
    {
        return $this->handsMap()[$unitId ?? 0] ?? collect();
    }

    /**
     * The programme ids this intake runs — the list on the pivot plus the lead id on the row, so an intake saved
     * before the pivot existed is still read whole.
     *
     * @return array<int, int>
     */
    public function programmeIds(): array
    {
        $ids = $this->relationLoaded('trainings')
            ? $this->trainings->pluck('id')->all()
            : $this->trainings()->pluck('trainings.id')->all();

        return collect($ids)->push($this->training_id)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Only the units of one of the intake's programmes — a trainee is read on their own course's units. */
    public function unitsFor(?int $trainingId): Collection
    {
        if (! $trainingId) {
            return $this->unitList();
        }

        $mine = $this->unitList()->filter(fn (TrainingModule $unit) => (int) $unit->training_id === $trainingId);

        return $mine->isNotEmpty() ? $mine : collect();
    }

    /**
     * What this run teaches. The ticked units, or the whole programme while nothing has been ticked — a cohort
     * posted before the office started choosing units keeps every unit its students were already marked on.
     */
    public function unitList(): Collection
    {
        $units = $this->units;

        if ($units->isNotEmpty()) {
            return $units;
        }

        if ($this->relationLoaded('trainings') && $this->trainings->isNotEmpty()) {
            return $this->trainings->flatMap->modules->sortBy(['training_id', 'position', 'id'])->values();
        }

        return TrainingModule::whereIn('training_id', $this->programmeIds())->ordered()->get();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Seats that hold a person — a declined or withdrawn application never blocks a seat. */
    public function seatsUsed(): int
    {
        return $this->relationLoaded('enrollments')
            ? $this->enrollments->whereIn('state', ['pending', 'approved'])->count()
            : $this->enrollments()->whereIn('state', ['pending', 'approved'])->count();
    }

    public function seatsLeft(): ?int
    {
        return $this->seats > 0 ? max(0, $this->seats - $this->seatsUsed()) : null;
    }

    public function isFull(): bool
    {
        return $this->seats > 0 && $this->seatsUsed() >= $this->seats;
    }

    /** The window this intake runs in, in the words the office uses on a page. */
    public function runLabel(): string
    {
        if (! $this->starts_on || ! $this->ends_on) {
            return 'dates not set';
        }

        return $this->starts_on->equalTo($this->ends_on)
            ? $this->starts_on->format('d M Y')
            : $this->starts_on->format('d M').' – '.$this->ends_on->format('d M Y');
    }

    /** The day teaching begins. */
    public function hasStarted(): bool
    {
        return $this->starts_on !== null && $this->starts_on->lte(today());
    }

    /** The day the run was due to finish. */
    public function hasEnded(): bool
    {
        return $this->ends_on !== null && $this->ends_on->lt(today());
    }

    /** What the dates say the intake is doing, whatever the office last picked in the status box. */
    public function stageLabel(): string
    {
        return match (true) {
            $this->hasEnded() => 'Finished',
            $this->hasStarted() => 'Running',
            default => 'Yet to start',
        };
    }

    /**
     * The status the run's own dates call for, or null when the box already agrees. A posted intake whose start day
     * has come is running, and one whose end day has gone is finished — this is what the page offers to correct it to.
     * A draft stays the office's decision: dates never post an intake by themselves.
     */
    public function impliedStatus(): ?string
    {
        if ($this->status === 'draft' || $this->status === 'cancelled') {
            return null;
        }

        return match (true) {
            $this->hasEnded() && $this->status !== 'completed' => 'completed',
            $this->hasStarted() && $this->status === 'open' => 'in_progress',
            default => null,
        };
    }

    /** The window a CTP can put themselves forward in — posted, in date and with room. */
    public function isOpenForEnrol(): bool
    {
        return $this->status === 'open'
            && (! $this->enrol_closes_on || $this->enrol_closes_on->gte(today()))
            && ! $this->hasEnded()
            && ! $this->isFull();
    }

    public function enrolClosedReason(): string
    {
        if ($this->isFull()) {
            return 'all '.$this->seats.' seats are taken';
        }

        if ($this->enrol_closes_on && $this->enrol_closes_on->lt(today())) {
            return 'applications closed on '.$this->enrol_closes_on->format('d M Y');
        }

        if ($this->hasEnded()) {
            return 'it ran out on '.$this->ends_on->format('d M Y');
        }

        return 'it is not posted yet';
    }

    /**
     * Whether a seat can still be given at all — the office's own guard, and it is wider than the CTP's: the
     * Registrar seats a late trainee by hand while the run is still going, but never into a draft or a finished one.
     */
    public function canHoldSeats(): bool
    {
        return ! in_array($this->status, ['draft', 'completed', 'cancelled'], true) && ! $this->hasEnded();
    }

    public function seatRefusalReason(): string
    {
        return match (true) {
            $this->status === 'draft' => 'it is still a draft — post the intake before giving anyone a seat',
            $this->hasEnded() => 'it ran out on '.$this->ends_on->format('d M Y').', and a finished run takes no seats',
            default => 'it is '.$this->statusLabel(),
        };
    }

    public function scopeOpenForEnrol(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('enrol_closes_on')->orWhereDate('enrol_closes_on', '>=', today()))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->where(fn ($q) => $q->where('seats', 0)->orWhereRaw('seats > (select count(*) from enrollments e where e.cohort_id = cohorts.id and e.state in (?, ?))', ['pending', 'approved']));
    }

    /** Intakes that reach this county: the ones open in every county, plus those that list it. */
    public function scopeReachingCounty(Builder $query, ?string $county): Builder
    {
        return $query->when($county, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('county_scope', '!=', 'listed')
            ->orWhere('county', $county)
            ->orWhereHas('counties', fn (Builder $c) => $c->where('name', $county))));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** The intake is named after the training it runs — there are no free-typed course names. */
    public function courseLabel(): string
    {
        return $this->training?->name ?? 'Training';
    }

    /** The intake's own programme first, then how many others it also runs. */
    public function programmeLabel(): string
    {
        $extra = max(0, count($this->programmeIds()) - 1);

        return $extra ? $this->courseLabel().' + '.$extra.' '.Str::plural('programme', $extra) : $this->courseLabel();
    }

    /** The programmes taught here, with the intake's own named first whatever order the list is in. */
    public function programmeList(): Collection
    {
        $ids = $this->programmeIds();
        $byId = Training::whereIn('id', $ids)->orderBy('name')->get()->keyBy('id');

        $ordered = collect([$this->training_id])->concat($ids)->filter()->unique()
            ->map(fn ($id) => $byId[(int) $id] ?? null)->filter()->values();

        return $ordered->isEmpty() ? collect([$this->training])->filter()->values() : $ordered;
    }

    public function termLabel(): ?string
    {
        return self::TERMS[$this->term] ?? null;
    }

    public function yearLabel(): ?string
    {
        return (string) ($this->academic_year ?: $this->starts_on?->year);
    }

    /** Where this run sits in the history of the course — 2026 · Term 3. */
    public function periodLabel(): string
    {
        return collect([$this->yearLabel(), $this->termLabel()])->filter()->join(' · ') ?: 'period not set';
    }

    public function dateLabel(): string
    {
        return $this->runLabel();
    }

    /** An intake offered in every county needs no list — and belongs to no community's or one county's reach. */
    public function coversAllCounties(): bool
    {
        return $this->county_scope !== 'listed';
    }

    /** Does this intake reach a CTP in this county? A county-less intake (no list, no county named) reaches all. */
    public function coversCounty(?string $county): bool
    {
        if (blank($county)) {
            return false;
        }

        if ($this->coversAllCounties()) {
            return true;
        }

        $names = $this->countyNames();

        return $names->contains(fn (string $name) => strcasecmp($name, trim($county)) === 0);
    }

    /** The counties the office ticked, with the county written on the row kept along the way for old records. */
    public function countyNames(): Collection
    {
        $names = ($this->relationLoaded('counties') ? $this->counties : $this->counties()->get())
            ->pluck('name');

        return $this->county ? $names->push($this->county)->filter()->unique() : $names;
    }

    /** Where the training physically happens. The reach is stated separately, by coverageLabel(). */
    public function coverageLabel(): string
    {
        return $this->coversAllCounties() ? 'All counties' : $this->countyNames()->join(', ');
    }

    /** Where the training happens — the venue if there is one, otherwise the trainer's own site. */
    public function placeLabel(): string
    {
        if ($this->venue) {
            return trim($this->venue->name.' — '.$this->venue->placeLabel(), ' —');
        }

        if ($this->trainer) {
            return $this->trainer->placeLabel();
        }

        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ') ?: 'No fixed place';
    }

    /** The teaching time is the units' own: a welder's unit and a solar unit are not run over the same hours. */
    public function hoursLabel(): string
    {
        $units = $this->unitList();

        $theory = (int) $units->sum(fn (TrainingModule $u) => (int) ($u->theory_hours ?? 0));
        $practical = (int) $units->sum(fn (TrainingModule $u) => (int) ($u->practical_hours ?? 0));

        // A unit the office has not split yet still teaches its hours, so they are counted on their own line.
        $whole = (int) $units->whereNull('theory_hours')->whereNull('practical_hours')->sum('hours');

        if (! $theory && ! $practical) {
            return $whole
                ? $whole.' hours over '.$units->count().' '.Str::plural('unit', $units->count())
                : 'Hours are set on the units';
        }

        $label = $theory.'h theory · '.$practical.'h practical';

        return $whole ? $label.' · '.$whole.'h still on one total' : $label;
    }

    public static function nextReference(): string
    {
        $highest = self::where('reference', 'like', 'TRN-%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, 5) AS UNSIGNED)) n')
            ->value('n');

        return 'TRN-'.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
