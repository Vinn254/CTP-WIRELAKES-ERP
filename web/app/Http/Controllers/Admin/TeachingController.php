<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\FundiRegistration;
use App\Models\LibraryItem;
use App\Models\ModuleCompletion;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\Trainer;
use App\Support\Documents;
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

    public function document(Request $request, Enrollment $enrollment, TrainingDocument $document)
    {
        $their = $this->theirTrainee($enrollment);
        abort_unless($document->enrollment_id === $their->id, 404);

        return Documents::file($request, $document->file_path, $document->file_name ?: $document->title);
    }

    /**
     * The Registrar's shelf, opened for a trainer to prepare from. A trainer is handed the same books, notes and
     * past papers the students pay to keep and is never charged for one of them — reading and saving are both open
     * here. The Registrar still owns the shelf; this desk only reads it.
     */
    public function library(Request $request)
    {
        abort_unless(Workspace::trainerDesk($request->user()), 403,
            'The library is the Registrar\'s shelf for the students. A trainer opens it to prepare what they teach.');

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        $courses = LibraryItem::onShelf()->whereNotNull('training_id')->with('course:id,name')->get()
            ->pluck('course')->filter()->unique('id')->values();

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $courses->contains('id', (int) $course) ? (int) $course : null);

        $items = LibraryItem::onShelf()->with(['course:id,name', 'poster:id,name'])
            ->when($course === 'general', fn ($q) => $q->whereNull('training_id'))
            ->when(is_int($course), fn ($q) => $q->where('training_id', $course))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest('id')->get();

        return view('admin.teaching.library', [
            'fundi' => $this->fundi(),
            'blocks' => $items->groupBy(fn (LibraryItem $i) => $i->courseLabel())->sortKeys(),
            'items' => $items,
            'courses' => $courses,
            'course' => $course,
            'kind' => $kind,
            'kinds' => LibraryItem::KINDS,
        ]);
    }

    /** A trainer saves a book to their own machine for the class. Nothing on the shelf is charged to them. */
    public function libraryFile(Request $request, LibraryItem $item)
    {
        abort_unless(Workspace::trainerDesk($request->user()), 403,
            'The library is the Registrar\'s shelf for the students. A trainer opens it to prepare what they teach.');
        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');

        return Documents::file($request, $item->file_path, $item->file_name ?: $item->title);
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
