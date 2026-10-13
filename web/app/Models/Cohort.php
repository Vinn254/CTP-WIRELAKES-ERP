<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One run of a course at one place — the unit CTPs apply for and training is tracked on (§7.4). */
class Cohort extends Model
{
    public const FAMILIES = [
        'ICT', 'Networking', 'Programming', 'Robotics', 'AI', 'Electrical', 'Construction', 'E-Waste',
    ];

    public const STATUSES = [
        'draft' => 'Draft — not posted yet',
        'open' => 'Open for applications',
        'in_progress' => 'Training in progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference', 'course', 'family', 'venue_id', 'trainer_id', 'community_id',
        'county', 'sub_county', 'ward', 'starts_on', 'ends_on', 'enrol_closes_on',
        'seats', 'theory_hours', 'practical_hours', 'fee_ksh', 'summary', 'status',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'enrol_closes_on' => 'date',
        'seats' => 'integer',
        'theory_hours' => 'integer',
        'practical_hours' => 'integer',
        'fee_ksh' => 'decimal:2',
    ];

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

    /** The window a CTP can put themselves forward in — posted, in date and with room. */
    public function isOpenForEnrol(): bool
    {
        return $this->status === 'open'
            && (! $this->enrol_closes_on || $this->enrol_closes_on->gte(today()))
            && ! $this->isFull();
    }

    public function enrolClosedReason(): string
    {
        if ($this->isFull()) {
            return 'all '.$this->seats.' seats are taken';
        }

        return $this->enrol_closes_on && $this->enrol_closes_on->lt(today())
            ? 'applications closed on '.$this->enrol_closes_on->format('d M Y')
            : 'it is not posted yet';
    }

    public function scopeOpenForEnrol(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('enrol_closes_on')->orWhereDate('enrol_closes_on', '>=', today()))
            ->where(fn ($q) => $q->where('seats', 0)->orWhereRaw('seats > (select count(*) from enrollments e where e.cohort_id = cohorts.id and e.state in (?, ?))', ['pending', 'approved']));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function dateLabel(): string
    {
        return $this->starts_on->format('d M Y').' – '.$this->ends_on->format('d M Y');
    }

    /** Where the training happens — the venue if there is one, otherwise the trainer's own site. */
    public function placeLabel(): string
    {
        if ($this->venue) {
            return $this->venue->name.', '.$this->venue->placeLabel();
        }

        if ($this->trainer) {
            return $this->trainer->placeLabel();
        }

        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    public function hoursLabel(): string
    {
        return $this->theory_hours.'h theory · '.$this->practical_hours.'h practical';
    }

    public static function nextReference(): string
    {
        $highest = self::where('reference', 'like', 'TRN-%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, 5) AS UNSIGNED)) n')
            ->value('n');

        return 'TRN-'.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
