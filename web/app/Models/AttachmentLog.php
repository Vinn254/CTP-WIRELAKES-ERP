<?php

namespace App\Models;

use App\Models\Concerns\ForwardedPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An attachee's week. A CTP on attachment is not paid for the placement, so what they hand in is the log of the days
 * they sat at the site — forwarded to the Academic Registrar on the same queue a trainer's register travels.
 *
 * One paper per attachee per week of Monday to Friday; the days themselves sit on AttachmentLogLine.
 */
class AttachmentLog extends Model
{
    use ForwardedPaper;

    protected $table = 'attachment_logs';

    protected $fillable = [
        'ctp_registration_id', 'work_order_id', 'cohort_id', 'week_start', 'status', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_note', 'note',
    ];

    protected $casts = [
        'week_start' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function ctp(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_registration_id');
    }

    /** The attachment order the week was worked under, when the office has placed the trainee at a site. */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AttachmentLogLine::class, 'attachment_log_id')->orderBy('log_date');
    }

    /** The week this attachee already holds for that Monday, or none yet. */
    public static function forWeek(int $ctpId, string $weekStart): ?self
    {
        return static::where('ctp_registration_id', $ctpId)->where('week_start', $weekStart)
            ->with('lines')->first();
    }

    public function weekLabel(): string
    {
        return TimeLog::weekLabel($this->week_start);
    }

    public function hoursTotal(): float
    {
        return round((float) $this->lines->sum('hours'), 2);
    }

    /** Sunday to Saturday read as the five working days, with the day written for each or null. */
    public function days(): array
    {
        $start = Carbon::parse($this->week_start)->startOfDay();
        $byDate = $this->lines->keyBy(fn (AttachmentLogLine $line) => $line->log_date->toDateString());

        return collect(range(0, 4))
            ->map(function (int $offset) use ($start, $byDate) {
                $date = $start->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'day' => $date->format('l'),
                    'row' => $byDate->get($date->toDateString()),
                ];
            })
            ->all();
    }
}
