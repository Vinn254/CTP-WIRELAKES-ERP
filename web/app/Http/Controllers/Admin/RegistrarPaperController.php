<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\LessonLine;
use App\Models\LessonPlan;
use App\Models\Timetable;
use App\Models\UnitMarkSheet;
use App\Support\TeachingDesk;
use Illuminate\Http\Request;

/**
 * The Academic Registrar's queue. Every paper a trainer forwards lands here, and only this desk answers it —
 * approving is what writes the ticks and the marks onto each trainee's own record.
 *
 * The Training department owns the route, so a login without that module never reaches it.
 */
class RegistrarPaperController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();

        $kind = $request->input('kind');
        $kind = isset(TeachingDesk::PAPERS[$kind]) ? $kind : null;

        return view('admin.registers.index', [
            'rows' => TeachingDesk::queue($kind),
            'kind' => $kind,
            'tabs' => $this->tabs(),
            'waiting' => TeachingDesk::pending(),
        ]);
    }

    public function show(Request $request, string $kind, int $id)
    {
        $this->guard();

        $paper = $this->paper($kind, $id);

        return view('admin.registers.show', [
            'kind' => $kind,
            'paper' => $paper,
            'back' => route('admin.registers.index', ['kind' => $kind]),
            'url' => fn (string $name, array $params = []) => route($name, $params),
            'week' => $paper instanceof Timetable ? $paper->load('slots.module:id,title')->week() : [],
            'lines' => $paper instanceof LessonPlan
                ? LessonLine::where('lesson_plan_id', $paper->id)->with('module:id,title')->orderBy('position')->get()
                : collect(),
        ]);
    }

    public function decide(Request $request, string $kind, int $id)
    {
        $this->guard();

        $data = $request->validate([
            'decision' => 'required|in:approve,return',
            'note' => 'nullable|string|max:500|required_if:decision,return',
        ], [], ['note' => 'What to fix']);

        $paper = $this->paper($kind, $id);
        $line = TeachingDesk::decide($paper, $data['decision'] === 'approve', $data['note'] ?? null, $request->user());

        return redirect()->route('admin.registers.index', ['kind' => $kind])->with('status', $line);
    }

    /** @return array<int, array{key: string, label: string, count: int}> */
    private function tabs(): array
    {
        return collect(TeachingDesk::PAPERS)
            ->map(fn (string $class, string $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'register' => 'Attendance registers',
                    'marks' => 'Mark sheets',
                    'timetable' => 'Timetables',
                    default => 'Lesson plans',
                },
                'count' => $class::where('status', 'submitted')->count(),
            ])
            ->values()
            ->all();
    }

    private function paper(string $kind, int $id)
    {
        $class = TeachingDesk::PAPERS[$kind] ?? null;
        abort_unless($class !== null, 404);

        $paper = $class::with([
            'cohort:id,reference,training_id,venue_id,trainer_id,starts_on,ends_on,academic_year,term,status',
            'cohort.training:id,name',
            'cohort.venue:id,name,type,county',
            'trainer:id,holder_type,fundi_id,user_id',
        ])->findOrFail($id);

        // The lines a paper is answered on come with it, so the desk reads the whole sheet before deciding.
        return match (true) {
            $paper instanceof CohortSession => $paper->load('marks.enrollment.ctp:id,full_name,ctp_code'),
            $paper instanceof UnitMarkSheet => $paper->load(
                'module:id,title', 'marks.enrollment.ctp:id,full_name,ctp_code'),
            $paper instanceof Timetable => $paper->load('slots.module:id,title'),
            default => $paper,
        };
    }

    private function guard(): void
    {
        abort_unless(TeachingDesk::approves(auth()->user()), 403,
            'The Academic Registrar answers the papers trainers forward, at Training & Certification.');
    }
}
