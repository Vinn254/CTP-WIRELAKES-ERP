<?php

namespace App\Support;

use App\Models\AttachmentLog;
use App\Models\AttachmentLogLine;
use App\Models\CtpRegistration;
use App\Models\TimeLog;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * An attachee's own week. A CTP placed at a site is not paid for the placement — attachment is training — so what
 * they hand in is the log of the days they sat there, kept as a draft and sent to the Academic Registrar on the same
 * queue a trainer's register travels. Nothing here touches money.
 */
class AttachmentDesk
{
    /**
     * The placement the trainee is logging against: the attachment order the office has them on, taken off their own
     * record rather than a url. A trainee the office has not placed yet has none, and their page stays blank.
     */
    public static function placement(CtpRegistration $ctp): ?WorkOrder
    {
        return WorkOrder::with(['project.hirer:id,organisation', 'project:id,title,category,county,ward,reference'])
            ->where('assignee_type', 'ctp')
            ->where('assignee_id', $ctp->id)
            ->whereNotIn('status', ['cancelled'])
            ->latest('id')
            ->first();
    }

    /** The week opened on the screen: the picker's Monday to Friday, with any day already written on it. */
    public static function sheet(CtpRegistration $ctp, ?string $month = null, int|string|null $week = null): array
    {
        $sheet = TeachingDesk::week($month, $week);
        $start = $sheet['start'];
        $log = AttachmentLog::forWeek($ctp->id, $start->toDateString());
        $rows = $log ? collect($log->days()) : collect(range(0, 4))->map(fn ($i) => [
            'date' => $start->copy()->addDays($i)->toDateString(),
            'day' => $start->copy()->addDays($i)->format('l'),
            'row' => null,
        ]);

        // The week's own five days replace the picker's six, so the sheet the trainee writes is Monday to Friday.
        return array_merge($sheet, [
            'log' => $log,
            'days' => $rows->all(),
            'status' => $log?->status ?? 'draft',
            'hours' => $log?->hoursTotal() ?? 0.0,
            'written' => $log?->lines->count() ?? 0,
            'canEdit' => $log === null || $log->isEditable(),
        ]);
    }

    /** Every week this trainee has written, newest first — the list under their own sheet. */
    public static function history(CtpRegistration $ctp): Collection
    {
        return AttachmentLog::where('ctp_registration_id', $ctp->id)
            ->with('lines')->orderByDesc('week_start')->get()
            ->map(fn (AttachmentLog $log) => [
                'id' => $log->id,
                'label' => $log->weekLabel(),
                'month' => TimeLog::monthOf($log->week_start),
                'week_no' => TimeLog::weekNumberOf($log->week_start),
                'days' => $log->lines->count(),
                'hours' => $log->hoursTotal(),
                'status' => $log->status,
            ]);
    }

    /**
     * Write the week, then keep it as a draft or send it to the Registrar. A line left blank is left alone, so a
     * half-filled week can be finished on another day, and a week already handed in is locked until the Registrar
     * answers it — the attachee never rewrites a paper that is with the office.
     */
    public static function save(CtpRegistration $ctp, Request $request, bool $submit): string
    {
        $order = static::placement($ctp);
        abort_unless($order, 422, 'The office has not placed you at a site yet, so there is no attachment week to write.');

        $start = TimeSheet::monday($request->input('month'), $request->input('week'));
        $end = $start->copy()->addDays(4);
        $log = AttachmentLog::where('ctp_registration_id', $ctp->id)
            ->where('week_start', $start->toDateString())->first();

        abort_unless(! $log || $log->isEditable(), 422,
            'This week is with the Registrar — it is answered there, never rewritten here.');

        $written = 0;
        foreach ((array) $request->input('days', []) as $date => $day) {
            $task = trim((string) ($day['task'] ?? ''));
            $in = filled($day['time_in'] ?? null) ? $day['time_in'] : null;
            $out = filled($day['time_out'] ?? null) ? $day['time_out'] : null;
            $initials = trim((string) ($day['initials'] ?? ''));

            // An untouched line is not a day — it stays off the record.
            if ($task === '' && ! $in && ! $out && $initials === '') {
                continue;
            }

            $when = (string) $date;
            abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $when), 422, 'A day must be picked from the week shown.');
            abort_unless($when >= $start->toDateString() && $when <= $end->toDateString(), 422,
                'Only Monday to Friday of the week you opened can be written.');
            abort_unless($when <= today()->toDateString(), 422,
                'An attachment log records days already sat at the site — not a day in the future.');
            abort_unless($task !== '', 422, Carbon::parse($when)->format('l').' needs what you did that day.');

            $hours = TimeLog::hoursBetween($in, $out);
            abort_unless(! ($in && $out) || $hours !== null, 422,
                Carbon::parse($when)->format('l').': time out must be after time in.');

            $log ??= AttachmentLog::create([
                'ctp_registration_id' => $ctp->id, 'work_order_id' => $order->id,
                'cohort_id' => static::intakeOf($ctp), 'week_start' => $start->toDateString(), 'status' => 'draft',
            ]);

            AttachmentLogLine::updateOrCreate(
                ['attachment_log_id' => $log->id, 'log_date' => $when],
                ['time_in' => $in, 'time_out' => $out, 'task' => $task, 'hours' => $hours,
                    'initials' => $initials !== '' ? Str::upper(Str::limit($initials, 10, '')) : null]);

            $written++;
        }

        $note = trim((string) $request->input('note', ''));
        if ($log && $note !== '' && $note !== $log->note) {
            $log->update(['note' => $note]);
        }

        if (! $submit) {
            return $written === 0
                ? 'Nothing was written — the week is kept as it stands.'
                : $written.' '.Str::plural('day', $written).' kept as a draft for the week of '
                .TimeLog::weekLabel($start).'. Send it to the Registrar when it is complete.';
        }

        $held = $log?->lines()->exists() ?? false;
        abort_unless($written > 0 || $held, 422, 'Write at least one day before sending the week.');

        return TeachingDesk::forward($log);
    }

    /** The intake the trainee came from, so the Registrar reads the week with its programme beside it. */
    private static function intakeOf(CtpRegistration $ctp): ?int
    {
        return $ctp->activeEnrollments()->value('cohort_id');
    }
}
