<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One day on a person's week sheet. The sheet the office signs by hand — day, time in, time out, what was done,
 * initials, department, approval, hours, units — is one row here per day, so the printed page and the record are
 * the same document. A week is signed, approved or returned as a whole over its day rows, and it belongs to the
 * staff account that worked it, whichever department that person sits in.
 */
class TimeLog extends Model
{
    public const STATUSES = [
        'draft' => 'Being filled',
        'submitted' => 'Signed — waiting on the office',
        'approved' => 'Approved',
        'returned' => 'Returned for a correction',
    ];

    /** The five working days the sheet is ruled for; anything logged outside them still prints. */
    public const DAYS = ['MON', 'TUE', 'WED', 'THU', 'FRI'];

    protected $table = 'time_logs';

    protected $fillable = [
        'week_start', 'log_date', 'time_in', 'time_out', 'task', 'initials', 'department',
        'training_id', 'cohort_id', 'units', 'hours', 'status', 'approval_note',
        'signed_by_user', 'approved_by_user', 'approved_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'log_date' => 'date',
        'units' => 'integer',
        'hours' => 'float',
        'approved_at' => 'datetime',
    ];

    /**
     * A day line, corrected in place — the sheet is never two rows for one day. Who wrote it is taken from the
     * account standing at the screen, never typed: initials and department are recorded by the desk that saves it.
     */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'log_date' => 'required|date|before_or_equal:today',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'task' => 'required|string|max:500',
            'department' => 'nullable|string|max:90',
            'training_id' => 'nullable|integer|exists:trainings,id',
            'cohort_id' => 'nullable|integer|exists:cohorts,id',
            'units' => 'nullable|integer|min:0|max:99',
        ], [
            'time_out.after' => 'Time out must be after time in.',
            'log_date.before_or_equal' => 'A week sheet records days already worked — not a day in the future.',
        ], [
            'log_date' => 'Date', 'time_in' => 'Time in', 'time_out' => 'Time out', 'task' => 'Tasks accomplished',
            'department' => 'Department', 'units' => 'Units',
        ]);

        $data['task'] = trim($data['task']);
        $data['units'] = (int) ($data['units'] ?? 0);
        $data['hours'] = static::hoursBetween($data['time_in'] ?? null, $data['time_out'] ?? null);
        $data['week_start'] = static::weekStartOf($data['log_date'])->toDateString();

        return $data;
    }

    /** The initials a person's account signs with: first letter of each of their first three names. */
    public static function initialsOf(User $worker): string
    {
        return collect(preg_split('/\s+/', trim($worker->name)))->filter()
            ->filter(fn ($word) => preg_match('/^[\p{L}]/u', $word))
            ->take(3)->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
    }

    /** The departments this account can be recorded against — its own, never the whole list. */
    public static function departmentsOf(User $worker): array
    {
        return collect(Departments::visibleSlugs($worker))
            ->map(fn ($slug) => Departments::find($slug)['name'] ?? null)
            ->filter()->unique()->values()->all() ?: ['Training'];
    }

    /** Hours worked, counted from the clock times the trainer wrote — 8 hours max a day. */
    public static function hoursBetween(?string $in, ?string $out): ?float
    {
        if (! $in || ! $out) {
            return null;
        }

        [$ih, $im] = array_map('intval', explode(':', $in));
        [$oh, $om] = array_map('intval', explode(':', $out));
        $minutes = ($oh * 60 + $om) - ($ih * 60 + $im);

        return $minutes > 0 ? round(min($minutes, 480) / 60, 2) : null;
    }

    public static function weekStartOf(string|Carbon $date): Carbon
    {
        return Carbon::parse($date)->startOfWeek(Carbon::MONDAY);
    }

    public static function weekLabel(string|Carbon $start): string
    {
        $start = Carbon::parse($start);

        return $start->format('d M').'–'.$start->copy()->addDays(4)->format('d M Y');
    }

    /** Every row of one person's week, keyed by date — the office and the worker read the same sheet. */
    public static function forWeek(int $userId, string|Carbon $weekStart): Collection
    {
        return static::with(['training:id,name', 'cohort:id,reference', 'approvedBy:id,name', 'signedBy:id,name', 'user:id,name,email'])
            ->where('user_id', $userId)
            ->where('week_start', Carbon::parse($weekStart)->toDateString())
            ->orderBy('log_date')
            ->get();
    }

    /**
     * The weeks of one month, numbered the way the founder counts them: week 1 is the Monday that falls in that
     * month, and each week runs Monday to Friday. A month has four or five.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string, dates: string}>
     */
    public static function weeksOfMonth(string $month): array
    {
        $first = Carbon::parse($month.'-01');

        $weeks = [];
        $cursor = static::firstMondayOf($first);

        while ($cursor->month === $first->month) {
            $end = $cursor->copy()->addDays(4);
            $weeks[] = [
                'start' => $cursor->copy(),
                'end' => $end,
                'label' => 'Week '.(count($weeks) + 1),
                'dates' => $cursor->format('D d M').' – '.$end->format('D d M Y'),
            ];
            $cursor = $cursor->copy()->addWeek();
        }

        return $weeks;
    }

    /** The Monday the month's first working week starts on — the 1st itself when the month opens on a Monday. */
    public static function firstMondayOf(Carbon $anyDayOfMonth): Carbon
    {
        $start = $anyDayOfMonth->copy()->startOfMonth()->startOfDay();

        return $start->dayOfWeek === Carbon::MONDAY ? $start : $start->next(Carbon::MONDAY)->startOfDay();
    }

    /** Which week of its month a Monday starts — the number the picker shows back to the worker. */
    public static function weekNumberOf(string|Carbon $monday): int
    {
        $monday = static::weekStartOf($monday)->copy()->startOfDay();

        return (int) round(static::firstMondayOf($monday)->diffInDays($monday) / 7) + 1;
    }

    public static function monthOf(string|Carbon $date): string
    {
        return Carbon::parse($date)->format('Y-m');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user');
    }

    public function dayLabel(): string
    {
        return strtoupper($this->log_date->format('D'));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** The clock times as the trainer wrote them; a blank day prints as a dash. */
    public function clockIn(): string
    {
        return $this->time_in ? substr($this->time_in, 0, 5) : '-';
    }

    public function clockOut(): string
    {
        return $this->time_out ? substr($this->time_out, 0, 5) : '-';
    }
}
