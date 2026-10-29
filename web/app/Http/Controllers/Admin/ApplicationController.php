<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgrammeApplication;
use App\Models\User;
use App\Support\Departments;
use App\Support\ProgrammeAreas;
use Illuminate\Http\Request;

/**
 * The register of service requests taken in by the public area pages: someone asking the programme to do a piece of
 * work for them. Website & Membership keeps the register and answers every line; the desk that runs an area reads what
 * came in on its own page as its own copy and answers those. A line is never deleted — a request that goes nowhere
 * stays on the register with the reason written on it. Someone who wants to join or to work with the programme
 * registers or applies on Careers, so they never reach this register.
 */
class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $answerable = Departments::areasFor($request->user());

        $status = in_array($request->query('f'), array_keys(ProgrammeApplication::STATUSES), true)
            ? $request->query('f') : 'all';

        $areas = collect(ProgrammeAreas::AREAS)
            ->filter(fn (array $a, string $slug) => $answerable === null || in_array($slug, $answerable, true));

        $area = $areas->has($request->query('area')) ? $request->query('area') : 'all';

        $applications = ProgrammeApplication::with('reviewer:id,name')
            ->when($answerable !== null, fn ($q) => $q->whereIn('area', $answerable ?: ['__none__']))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($area !== 'all', fn ($q) => $q->where('area', $area))
            ->orderByRaw("case status when 'received' then 0 when 'reviewing' then 1 else 2 end")
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.applications.index', [
            'applications' => $applications,
            'status' => $status,
            'area' => $area,
            'statuses' => ProgrammeApplication::STATUSES,
            'areas' => $areas->all(),
            'counts' => $this->counts($answerable),
            'waiting' => Departments::waitingAreaApplications($request->user()),
            'publicPages' => $answerable === null ? array_keys(ProgrammeAreas::AREAS) : $answerable,
        ]);
    }

    public function show(Request $request, ProgrammeApplication $application)
    {
        $this->check($request->user(), $application);

        return view('admin.applications.show', [
            'application' => $application->load('reviewer:id,name'),
            'statuses' => ProgrammeApplication::STATUSES,
            'answering' => $this->mayAnswer($request->user(), $application) && ! Departments::hosted(),
        ]);
    }

    /** The office's answer to a line, recorded with the date the person was reached. */
    public function review(Request $request, ProgrammeApplication $application)
    {
        $this->check($request->user(), $application);
        abort_if(Departments::hosted(), 403, 'This register is read-only outside the desk that keeps it.');
        abort_unless($this->mayAnswer($request->user(), $application), 403,
            'This line came in on another area’s page — that desk answers it.');

        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(ProgrammeApplication::STATUSES)),
            'review_note' => 'nullable|string|max:1000',
            'contacted_on' => 'nullable|date',
        ]);

        $application->fill([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'contacted_on' => $data['contacted_on'] ?? null,
            'reviewed_by_user' => $request->user()->id,
        ])->save();

        return back()->with('status', $application->name.' — '.$application->statusLabel().'.');
    }

    /** A line from an area the account does not run is not this desk's record to open. */
    private function check(?User $user, ProgrammeApplication $application): void
    {
        $answerable = Departments::areasFor($user);

        abort_if($answerable !== null && ! in_array($application->area, $answerable, true), 403,
            'That application came in on another area’s page.');
    }

    private function mayAnswer(?User $user, ProgrammeApplication $application): bool
    {
        // The register is kept by one desk — Website & Membership — the way Human Resources alone approves a week.
        return Departments::areasFor($user) === null;
    }

    private function counts(?array $answerable): array
    {
        $byStatus = ProgrammeApplication::query()
            ->when($answerable !== null, fn ($q) => $q->whereIn('area', $answerable ?: ['__none__']))
            ->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return collect(ProgrammeApplication::STATUSES)
            ->map(fn ($_, $key) => (int) ($byStatus[$key] ?? 0))
            ->put('all', ProgrammeApplication::query()
                ->when($answerable !== null, fn ($q) => $q->whereIn('area', $answerable ?: ['__none__']))->count())
            ->all();
    }
}
