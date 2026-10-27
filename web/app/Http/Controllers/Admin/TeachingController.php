<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\FundiRegistration;
use App\Models\ModuleCompletion;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\Trainer;
use App\Support\TrainingTrack;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A trainer's own desk inside the office.
 *
 * A person can hold a job title and still teach a cohort, and both are worked from the one signed-in account.
 * The member portal's trainer surface is closed to a staff login, so the same record is opened here: the E-Fundi
 * is found from the account's own email, never from an id in the url, and every action still runs through
 * TrainingTrack so a mark reads the same as the office's own.
 */
class TeachingController extends Controller
{
    public function index()
    {
        $fundi = $this->fundi();

        $trainers = Trainer::with([
            'trainings:id,name',
            'venue:id,name,type,county,sub_county,ward',
            'cohorts' => fn ($q) => $q->where('status', '!=', 'draft')
                ->with([
                    'training.modules',
                    'enrollments' => fn ($e) => $e->with([
                        'ctp:id,full_name,ctp_code', 'entries', 'moduleMarks', 'documents', 'certification',
                    ])->orderBy('id'),
                ])
                ->orderByDesc('starts_on'),
        ])->where('fundi_id', $fundi->id)->where('status', 'approved')->orderBy('id')->get();

        return view('admin.teaching.index', [
            'fundi' => $fundi,
            'trainers' => $trainers,
            'cohorts' => $trainers->flatMap(fn ($t) => $t->cohorts),
        ]);
    }

    /** One trainee on the trainer's own roll, worked with the office's own record partials. */
    public function trainee(Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        $their->load([
            'ctp', 'cohort.training.modules', 'cohort.venue', 'entries', 'moduleMarks', 'documents', 'certification',
        ]);

        return view('admin.teaching.trainee', [
            'fundi' => $this->fundi(),
            'enrollment' => $their,
            'documentKinds' => TrainingDocument::KINDS,
        ]);
    }

    public function progress(Request $request, Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::recordProgress(
            $their, $request, ['recorded_by_fundi' => $this->fundi()->id]));
    }

    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::markModule(
            $their, $module, ModuleCompletion::dataFrom($request), ['recorded_by_fundi' => $this->fundi()->id]));
    }

    public function unmarkModule(Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::unmarkModule($their, $module));
    }

    public function storeDocument(Request $request, Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::storeDocument(
            $their, $request, ['uploaded_by_fundi' => $this->fundi()->id]));
    }

    public function document(Enrollment $enrollment, TrainingDocument $document)
    {
        $their = $this->theirTrainee($enrollment);
        abort_unless($document->enrollment_id === $their->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($document->file_path, $document->file_name ?: $document->title);
    }

    /**
     * The desk opens only for a staff account that carries the Fundi Trainer title and sits an approved posting —
     * there is no department to own the route, so the check lives here.
     */
    private function fundi(): FundiRegistration
    {
        $fundi = Workspace::teaching(auth()->user());

        abort_unless($fundi, 403, 'My teaching is for a staff account that also trains CTPs. '
            .'The programme office approves a trainer site by site before a cohort is handed over.');

        return $fundi;
    }

    /** A trainee outside a cohort this trainer teaches 404s, so no roster can be reached by id. */
    private function theirTrainee(Enrollment $enrollment): Enrollment
    {
        $mine = Trainer::approved()->where('fundi_id', $this->fundi()->id)->pluck('id');

        $enrollment->load(['cohort', 'ctp']);

        abort_unless($enrollment->cohort && $mine->contains($enrollment->cohort->trainer_id), 404);

        return $enrollment;
    }
}
