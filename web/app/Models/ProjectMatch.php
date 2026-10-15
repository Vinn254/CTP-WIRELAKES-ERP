<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 6 §9.4 — one E-Fundi against one project in one round. The row says what the system found, whether the
 * fundi was asked, and what they answered inside the 48 hours. A second run of the search is a second round, never an
 * overwrite, so the desk can show what changed between the two.
 */
class ProjectMatch extends Model
{
    /** §9.4 step 4 — the window a matched fundi has to answer. */
    public const WINDOW_HOURS = 48;

    public const STATUSES = [
        'shortlisted' => 'On the shortlist, not asked yet',
        'invited' => 'Asked, waiting for an answer',
        'accepted' => 'Took the project',
        'declined' => 'Declined',
        'no_reply' => 'No answer in time',
        'awarded' => 'Awarded the project',
    ];

    /** What the score is made of, in the order the card lists it. */
    public const FACTORS = [
        'skills' => 'Skills that match the trade',
        'county' => 'Works in this county',
        'availability' => 'Availability',
        'standing' => 'Standing on the register',
        'experience' => 'Years in trade',
        'jobs' => 'Projects already done',
    ];

    protected $fillable = [
        'project_id', 'fundi_id', 'round', 'score', 'matched', 'status',
        'invited_on', 'responded_on', 'widened', 'below_bar', 'note',
        'awarded_by_user', 'awarded_on',
    ];

    protected $casts = [
        'matched' => 'array',
        'invited_on' => 'datetime',
        'responded_on' => 'datetime',
        'awarded_on' => 'datetime',
        'widened' => 'boolean',
        'below_bar' => 'boolean',
        'score' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class);
    }

    public function awardee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_user');
    }

    /** Rows still inside the answer window — the list a fundi opens, and the count the desk waits on. */
    public function scopeAwaiting(Builder $q): Builder
    {
        return $q->where('status', 'invited')->whereNull('responded_on');
    }

    public function scopeFor(Builder $q, FundiRegistration $fundi): Builder
    {
        return $q->where('fundi_id', $fundi->id);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? str_replace('_', ' ', $this->status);
    }

    /** When the answer is due: 48 hours from the moment the invite went out. */
    public function dueOn(): ?\Illuminate\Support\Carbon
    {
        return $this->invited_on?->copy()->addHours(self::WINDOW_HOURS);
    }

    /** True once the window has passed with no answer — the row is closed off on the desk, not deleted. */
    public function isOverdue(): bool
    {
        return $this->status === 'invited' && ! $this->responded_on && $this->dueOn() && $this->dueOn()->isPast();
    }

    /** '23 hours left' / '4 hours left' / 'Time up' — the countdown §9.4 step 4 puts on the card. */
    public function timeLabel(): string
    {
        if ($this->responded_on) {
            return 'answered '.$this->responded_on->diffForHumans(null, true);
        }

        if (! $this->invited_on) {
            return 'not asked yet';
        }

        $left = $this->dueOn();

        if (! $left || $left->isPast()) {
            return 'time up';
        }

        $hours = (int) floor(now()->diffInMinutes($left) / 60);

        return $hours >= 24
            ? round($hours / 24, 1).' days left to answer'
            : $hours.' hours left to answer';
    }

    /** The score explained, one line per factor the system actually used. */
    public function matchedLines(): array
    {
        $out = [];

        foreach ((array) $this->matched as $key => $value) {
            $label = self::FACTORS[$key] ?? ucfirst((string) $key);
            $out[] = $label.': '.(is_array($value) ? implode(', ', $value) : (string) $value);
        }

        if ($this->widened) {
            $out[] = 'Brought in by widening to neighbouring counties';
        }

        if ($this->below_bar) {
            $out[] = 'Below the standing bar — an admin decision to look at';
        }

        return $out;
    }
}
