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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A trainer's own desk inside the office.
 *
 * A person can hold a job title and still teach a cohort, and both are worked from the one signed-in account. The
 * member portal's trainer surface is closed to a staff login, so the same record is opened here. The trainer is
 * found from the account itself — a staff posting named on the row, an E-Fundi or an accepted applicant matched on
 * the account's own email — never from an id in the url, and every mark still runs through TrainingTrack so it
 * reads the same as the office's own. What the desk shows is the programmes that posting is licensed for.
 */
class TeachingController extends Controller
{
    public function index()
    {
        $trainers = $this->postings();

        return view('admin.teaching.index', [
            'fundi' => $this->fundi(),
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

        return back()->with('status', TrainingTrack::recordProgress($their, $request, $this->actor()));
    }

    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::markModule($their, $module, ModuleCompletion::dataFrom($request), $this->actor()));
    }

    public function unmarkModule(Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::unmarkModule($their, $module));
    }

    public function storeDocument(Request $request, Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::storeDocument($their, $request, $this->actor()));
    }

    public function document(Enrollment $enrollment, TrainingDocument $document)
    {
        $their = $this->theirTrainee($enrollment);
        abort_unless($document->enrollment_id === $their->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($document->file_path, $document->file_name ?: $document->title);
    }

    /**
     * The desk opens only for a staff account that stands behind an approved teaching posting — there is no
     * department to own the route, so the check lives here.
     *
     * @return Collection<int, Trainer>
     */
    private function postings(): Collection
    {
        $postings = Trainer::forAccount(auth()->user())->load([
            'trainings:id,name',
            'venue:id,name,type,county,sub_county,ward',
            'fundi:id,full_name,ef_code,tier',
            'staff:id,name',
            'applicant:id,name,email',
            'cohorts' => fn ($q) => $q->where('status', '!=', 'draft')
                ->with([
                    'training.modules',
                    'enrollments' => fn ($e) => $e->with([
                        'ctp:id,full_name,ctp_code', 'entries', 'moduleMarks', 'documents', 'certification',
                    ])->orderBy('id'),
                ])
                ->orderByDesc('starts_on'),
        ])->sortBy(fn (Trainer $t) => $t->venue?->name ?? $t->placeLabel())->values();

        // The desk opens on the title alone; a cohort is still only reachable through an approved posting.
        abort_unless(Workspace::trainerDesk(auth()->user()), 403, 'My teaching is for a staff account the programme has '
            .'licensed to train. The office posts a trainer site by site under Trainers before a cohort is handed over.');

        return $postings;
    }

    /** The E-Fundi record behind the account, when the trainer is one. Marks are still credited to it. */
    private function fundi(): ?FundiRegistration
    {
        return Workspace::teaching(auth()->user());
    }

    private function actor(): array
    {
        return array_filter([
            'recorded_by_fundi' => $this->fundi()?->id,
            'recorded_by_user' => auth()->id(),
        ], fn ($value) => $value !== null);
    }

    /** A trainee outside a cohort this trainer teaches 404s, so no roster can be reached by id. */
    private function theirTrainee(Enrollment $enrollment): Enrollment
    {
        $mine = $this->postings()->pluck('id');

        $enrollment->load(['cohort', 'ctp']);

        abort_unless($enrollment->cohort && $mine->contains($enrollment->cohort->trainer_id), 404);

        return $enrollment;
    }
}
