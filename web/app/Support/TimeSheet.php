<?php

namespace App\Support;

use App\Models\TimeLog;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The trainer's week sheet. One implementation, because three desks touch it: the trainer fills and signs it,
 * the Training desk signs the week off, and Human Resources reads the same rows as proof before pay.
 * Nothing here is a second copy of the sheet — every read goes to the trainer_time_logs rows.
 */
class TimeSheet
{
    /**
     * The sheet for one trainer's week: the five ruled days, each carrying its row or nothing, plus the totals.
     * A day logged outside Monday–Friday still prints, so a weekend session is never lost.
     */
    public static function sheet(Trainer $trainer, Carbon|string $weekStart): array
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($trainer->id, $weekStart)->keyBy(fn ($r) => $r->log_date->toDateString());

        $days = collect(range(0, 4))->map(function ($i) use ($weekStart, $rows) {
            $date = $weekStart->copy()->addDays($i)->toDateString();

            return ['day' => TimeLog::DAYS[$i], 'date' => $date, 'row' => $rows->get($date)];
        });

        $last = $weekStart->copy()->addDays(4)->toDateString();
        $extra = $rows->reject(fn ($r) => $r->log_date->toDateString() <= $last
            && $r->log_date->toDateString() >= $weekStart->toDateString())->values();

        return [
            'trainer' => $trainer,
            'start' => $weekStart,
            'label' => TimeLog::weekLabel($weekStart),
            'days' => $days->concat($extra->map(fn ($r) => ['day' => $r->dayLabel(), 'date' => $r->log_date->toDateString(), 'row' => $r]))->values()->all(),
            'logs' => $rows->values(),
            'hours' => round($rows->sum(fn ($r) => (float) $r->hours), 2),
            'units' => (int) $rows->sum('units'),
            'status' => static::weekStatus($rows),
        ];
    }

    /** A week takes the status of its rows: signed once the last day is in, and back when one row is returned. */
    public static function weekStatus(Collection $rows): string
    {
        return match (true) {
            $rows->isEmpty() => 'draft',
            $rows->contains('status', 'returned') => 'returned',
            $rows->contains('status', 'draft') => 'draft',
            $rows->contains('status', 'submitted') => 'submitted',
            default => 'approved',
        };
    }

    /** Write one day of the week. A day already on the sheet is corrected in place, never added twice. */
    public static function saveDay(Trainer $trainer, Request $request): string
    {
        $data = TimeLog::dataFrom($request);
        $existing = TimeLog::where('trainer_id', $trainer->id)->where('log_date', $data['log_date'])->first();

        if ($existing && ! in_array($existing->status, ['draft', 'returned'], true)) {
            abort(422, 'That day is already signed off — the office corrects an approved week, not the trainer.');
        }

        $existing
            ? $existing->update($data + ['status' => 'draft', 'approval_note' => null])
            : TimeLog::create($data + ['trainer_id' => $trainer->id, 'status' => 'draft']);

        return 'Day written to the sheet.';
    }

    /** The trainer signs the whole week; the office then answers line by line. */
    public static function sign(Trainer $trainer, Carbon|string $weekStart): string
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($trainer->id, $weekStart);

        abort_unless($rows->isNotEmpty(), 422, 'Write at least one day before signing the week.');
        abort_unless(in_array(static::weekStatus($rows), ['draft', 'returned'], true), 422,
            'This week is already with the office.');

        $rows->each(fn (TimeLog $row) => $row->update([
            'status' => 'submitted',
            'approval_note' => null,
            'signed_by_user' => auth()->id(),
        ]));

        return 'Week of '.TimeLog::weekLabel($weekStart).' signed and sent to the Training desk for approval.';
    }

    /** The Training desk signs the week off, or returns it naming what is missing. */
    public static function decide(Trainer $trainer, Carbon|string $weekStart, bool $approve, ?string $note): string
    {
        $weekStart = TimeLog::weekStartOf($weekStart);
        $rows = TimeLog::forWeek($trainer->id, $weekStart);

        abort_unless($rows->isNotEmpty(), 404, 'That week has no days on it.');
        abort_unless(static::weekStatus($rows) === 'submitted', 422,
            'Only a signed week can be approved or returned.');

        if (! $approve) {
            abort_unless(filled($note), 422, 'Say what the trainer must correct before returning the week.');
        }

        $rows->each(fn (TimeLog $row) => $row->update($approve
            ? ['status' => 'approved', 'approval_note' => $note, 'approved_by_user' => auth()->id(), 'approved_at' => now()]
            : ['status' => 'returned', 'approval_note' => $note, 'approved_by_user' => null, 'approved_at' => null]));

        return $approve
            ? 'Week of '.TimeLog::weekLabel($weekStart).' approved — '.round($rows->sum('hours'), 2).' hours signed off.'
            : 'Week of '.TimeLog::weekLabel($weekStart).' returned to the trainer: '.$note;
    }

    /** Every week a trainer has ever filled, newest first — the office list and the trainer's own history. */
    public static function weeksFor(?Trainer $trainer = null): Collection
    {
        return TimeLog::query()
            ->when($trainer, fn ($q) => $q->where('trainer_id', $trainer->id))
            ->with(['trainer:id,holder_type,fundi_id,user_id,job_application_id,venue_id,status', 'trainer.venue:id,name',
                'trainer.fundi:id,full_name,ef_code', 'trainer.staff:id,name,email', 'trainer.applicant:id,name,email'])
            ->orderByDesc('week_start')->orderBy('trainer_id')
            ->get()
            ->groupBy(fn (TimeLog $row) => $row->trainer_id.'|'.$row->week_start->toDateString())
            ->map(function (Collection $rows) {
                return [
                    'trainer' => $rows->first()->trainer,
                    'start' => $rows->first()->week_start,
                    'label' => TimeLog::weekLabel($rows->first()->week_start),
                    'days' => $rows->count(),
                    'hours' => round($rows->sum('hours'), 2),
                    'units' => (int) $rows->sum('units'),
                    'status' => static::weekStatus($rows),
                    'note' => $rows->pluck('approval_note')->filter()->first(),
                ];
            })
            ->values();
    }

    /** The trainer a week belongs to, checked against whoever is allowed to open it. */
    public static function trainerFor(int $trainerId): Trainer
    {
        $trainer = Trainer::with(['venue:id,name,county', 'fundi:id,full_name,ef_code',
            'staff:id,name,email', 'applicant:id,name,email'])->find($trainerId);

        abort_if(! $trainer, 404, 'That trainer posting is not on the register.');

        return $trainer;
    }
}
