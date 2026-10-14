<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\TrainingDocument;
use App\Support\TrainingTrack;
use Illuminate\Http\Request;

/**
 * Module 4 — the track page. It answers one question for every CTP in training: how far have they actually
 * come. Attendance marks, theory and practical scores, the assessments, the modules the office put under the
 * training and the documents uploaded for them (a partner school's report, an assessment paper) all add up to
 * one percentage, and a CTP who reaches 100% goes into the certification queue for the executive to sign.
 */
class TrackController extends Controller
{
    public const FILTERS = [
        'all' => 'Every trainee',
        'open' => 'Still training',
        'short' => 'Track not complete',
        'complete' => 'At 100%',
        'certified' => 'Certificate issued',
    ];

    public function index(Request $request)
    {
        $filter = array_key_exists((string) $request->input('f'), self::FILTERS) ? $request->input('f') : 'all';
        $cohortId = (int) $request->input('cohort');

        $rows = Enrollment::with([
            'ctp:id,full_name,ctp_code,county,sub_county,ward',
            'cohort:id,reference,training_id,venue_id,status,starts_on,ends_on',
            'cohort.training:id,name,code',
            'cohort.venue:id,name,type',
            'moduleMarks', 'documents', 'certification',
        ])
            ->when($cohortId, fn ($q) => $q->where('cohort_id', $cohortId))
            ->orderBy('cohort_id')->orderBy('id')->get()
            ->map(fn (Enrollment $e) => ['e' => $e, 'pct' => $e->trackPct()]);

        $tests = $this->tests();

        return view('admin.track.index', [
            'rows' => $filter === 'all' ? $rows : $rows->filter($tests[$filter]),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'counts' => collect(self::FILTERS)->mapWithKeys(fn ($label, $key) => [
                $key => $key === 'all' ? $rows->count() : $rows->filter($tests[$key])->count(),
            ]),
            'cohorts' => Cohort::where('status', '!=', 'draft')->with('training:id,name')
                ->orderByDesc('starts_on')->get(['id', 'reference', 'training_id', 'status']),
            'cohortId' => $cohortId,
        ]);
    }

    /** One trainee's whole track: the parts that make the percentage, every mark, every module and every upload. */
    public function show(Enrollment $enrollment)
    {
        $enrollment->load([
            'ctp', 'cohort.training.modules', 'cohort.venue', 'cohort.trainer.fundi',
            'entries.staffUser', 'entries.trainerFundi', 'moduleMarks', 'documents', 'certification',
        ]);

        return view('admin.track.show', [
            'enrollment' => $enrollment,
            'parts' => $enrollment->trackParts(),
            'pct' => $enrollment->trackPct(),
            'board' => $enrollment->moduleBoard(),
            'documentKinds' => TrainingDocument::KINDS,
        ]);
    }

    /** The office records a mark on the track page itself — the same rules the cohort page uses. */
    public function storeProgress(Request $request, Enrollment $enrollment)
    {
        $enrollment->load(['cohort', 'ctp']);

        return back()->with('status', TrainingTrack::recordProgress(
            $enrollment, $request, ['recorded_by_user' => auth()->id()]));
    }

    /** The tests each tab runs on a row — the percentage is worked out once, not in SQL. */
    private function tests(): array
    {
        $closed = ['completed', 'failed'];

        // "Track not complete" and "At 100%" follow the marks, not the stage word — a seat that reached
        // completed can still be short of 100% (a revoked certificate, a run closed out early).
        return [
            'open' => fn ($r) => in_array($r['e']->state, ['pending', 'approved'], true)
                && ! in_array($r['e']->stage, $closed, true),
            'short' => fn ($r) => $r['e']->state === 'approved' && $r['pct'] < 100,
            'complete' => fn ($r) => $r['e']->state === 'approved' && $r['pct'] >= 100,
            'certified' => fn ($r) => $r['e']->certification
                && in_array($r['e']->certification->status, ['recommended', 'approved'], true),
        ];
    }
}
