<?php

namespace App\Support;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\ModuleCompletion;
use App\Models\ProgressEntry;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The rules of the training track, in one place. The programme office and the fundi-trainer both act through
 * here, so a mark, an upload or a module cannot be recorded one way on staff screens and another way in the
 * member portal — and the same guard on the cohort's own period applies to both.
 */
class TrainingTrack
{
    /** A date on a trainee's record must fall inside the period the office set for that training. */
    public static function guardPeriod(Enrollment $enrollment, string $date): void
    {
        $cohort = $enrollment->cohort;
        $day = Carbon::parse($date)->toDateString();

        if ($cohort && $cohort->starts_on && $day < $cohort->starts_on->toDateString()) {
            abort(422, $cohort->reference.' runs from '.$cohort->starts_on->format('d M Y')
                .' to '.$cohort->ends_on->format('d M Y').'. A record dated '.$day.' is before the training started — '
                .'put it on the right date, or the office can widen the cohort period.');
        }

        if ($cohort && $cohort->ends_on && $day > $cohort->ends_on->toDateString()) {
            abort(422, $cohort->reference.' ended on '.$cohort->ends_on->format('d M Y')
                .'. Nothing is recorded on a trainee outside the period the office set for the training.');
        }
    }

    /** A module is only markable on a seat that is held, under the training that cohort runs. */
    public static function guardModule(Enrollment $enrollment, TrainingModule $module): void
    {
        abort_unless($enrollment->state === 'approved', 422, 'This CTP does not hold a seat in the cohort.');

        abort_unless($module->training_id === $enrollment->cohort->training_id, 422,
            $module->title.' is a module of '.$module->training->name.', not of the training this cohort runs.');
    }

    public static function markModule(Enrollment $enrollment, TrainingModule $module, array $data, array $actor): string
    {
        self::guardModule($enrollment, $module);
        self::guardPeriod($enrollment, today()->toDateString());

        if ($enrollment->moduleMarks()->where('training_module_id', $module->id)->exists()) {
            return $module->title.' is already marked on '.$enrollment->ctp->full_name.'’s record.';
        }

        ModuleCompletion::create([
            ...$data,
            'enrollment_id' => $enrollment->id,
            'training_module_id' => $module->id,
            'done_on' => today(),
        ] + $actor);

        return self::afterChange($enrollment, $actor, $module->title.' marked for '.$enrollment->ctp->full_name.'.');
    }

    /** A wrong mark is taken back while the run is open; once a certificate exists the record is closed. */
    public static function unmarkModule(Enrollment $enrollment, TrainingModule $module): string
    {
        self::guardModule($enrollment, $module);

        $mark = $enrollment->moduleMarks()->where('training_module_id', $module->id)->first();
        abort_if(! $mark, 422, $module->title.' is not marked on '.$enrollment->ctp->full_name.'’s record.');

        $cert = $enrollment->certification;
        abort_unless(! $cert || $cert->status === 'recommended', 422,
            $enrollment->ctp->full_name.' has a '.$cert->statusLabel().' certificate on this cohort — the mark stays. '
            .'An executive revokes the certificate first if the whole thing was wrong.');

        $mark->delete();

        return $module->title.' unmarked for '.$enrollment->ctp->full_name.' — their track is now '
            .$enrollment->fresh()->trackPct().'%.';
    }

    public static function recordProgress(Enrollment $enrollment, Request $request, array $actor): string
    {
        abort_unless($enrollment->state === 'approved', 422, 'This CTP does not hold a seat in the cohort.');

        $data = ProgressEntry::dataFrom($request);
        self::guardPeriod($enrollment, $data['entry_date']);

        ProgressEntry::create([...$data, 'enrollment_id' => $enrollment->id] + $actor);

        return self::afterChange($enrollment, $actor, $data['kind'] === 'note'
            ? 'Note added to '.$enrollment->ctp->full_name.'’s record.'
            : ProgressEntry::KINDS[$data['kind']].' recorded for '.$enrollment->ctp->full_name.'.');
    }

    /** The paperwork — a partner school's report, an assessment paper, a photo of the board they built. */
    public static function storeDocument(Enrollment $enrollment, Request $request, array $actor): string
    {
        abort_unless($enrollment->state === 'approved', 422, 'This CTP does not hold a seat in the cohort.');

        $data = TrainingDocument::dataFrom($request);
        self::guardPeriod($enrollment, $data['dated_on']);

        if (! empty($data['training_module_id'])) {
            $module = TrainingModule::findOrFail($data['training_module_id']);
            self::guardModule($enrollment, $module);
        }

        $file = $request->file('file');

        $document = TrainingDocument::create([
            'enrollment_id' => $enrollment->id,
            'training_module_id' => $data['training_module_id'] ?? null,
            'title' => $data['title'],
            'kind' => $data['kind'],
            'dated_on' => $data['dated_on'],
            'note' => $data['note'] ?? null,
            'file_path' => $file->store('training/'.$enrollment->cohort->reference.'/'.$enrollment->id, 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ] + $actor);

        return self::afterChange($enrollment, $actor, $document->title.' uploaded for '.$enrollment->ctp->full_name.'.');
    }

    /**
     * After anything is added, a trainee whose track is whole is put forward on the spot — the executive only
     * has to verify and sign. A recommendation that is already waiting is refreshed with the new figures.
     * A trainer's mark carries no staff id, so the record itself is shown as having put them forward.
     */
    public static function syncCertification(Enrollment $enrollment, ?int $staffId = null): ?Certification
    {
        $enrollment = $enrollment->fresh(['cohort', 'certification']);

        if (! $enrollment->isTrackComplete()) {
            return null;
        }

        $cert = $enrollment->certification;

        if ($cert && in_array($cert->status, ['recommended', 'declined'], true)) {
            $cert->update([
                'theory_score' => $enrollment->avgScore('theory'),
                'practical_score' => $enrollment->avgScore('practical'),
                'attendance_pct' => $enrollment->attendancePct(),
                'status' => 'recommended',
                'reason' => $cert->status === 'declined' ? null : $cert->reason,
            ]);

            return $cert;
        }

        if ($cert) {
            return null;
        }

        if (! in_array($enrollment->stage, ['assessment', 'completed'], true)) {
            $enrollment->update(['stage' => 'assessment']);
        }

        return Certification::create([
            'enrollment_id' => $enrollment->id,
            'theory_score' => $enrollment->avgScore('theory'),
            'practical_score' => $enrollment->avgScore('practical'),
            'attendance_pct' => $enrollment->attendancePct(),
            'status' => 'recommended',
            'reason' => 'Their track reached 100% — the office verifies and an executive signs.',
            'recommended_by' => $staffId,
        ]);
    }

    /** The flash line every screen shows after a change: the new percentage, and what is still missing. */
    private static function afterChange(Enrollment $enrollment, array $actor, string $line): string
    {
        $cert = self::syncCertification($enrollment, $actor['recorded_by_user'] ?? $actor['uploaded_by_user'] ?? null);
        $fresh = $enrollment->fresh();
        $missing = $fresh->trackMissing();

        if ($cert) {
            return $line.' Their track is at 100% — '.$enrollment->ctp->full_name.' is in the certification queue '
                .'for the office to verify and an executive to sign.';
        }

        if ($missing->isEmpty()) {
            return $line.' Track is at '.$fresh->trackPct().'% — the office verifies and an executive signs the certificate.';
        }

        return $line.' Track is now '.$fresh->trackPct().'% — still short: '
            .$missing->pluck('label')->join(', ').'.';
    }
}
