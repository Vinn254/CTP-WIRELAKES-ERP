<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The careers desk inside Membership. The office writes an opening, puts it on the public page, takes the
 * applications that come in, moves each one through review and records when the person was contacted back.
 * Closing an opening takes the form down but never deletes what people applied to.
 */
class CareersController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('f'), array_keys(JobOpening::STATUSES), true) ? $request->query('f') : 'all';

        $openings = JobOpening::withCount('applications')->with('creator:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'open' then 0 when 'draft' then 1 else 2 end")
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.careers.index', [
            'openings' => $openings,
            'status' => $status,
            'statuses' => JobOpening::STATUSES,
            'kinds' => JobOpening::KINDS,
            'counts' => collect(JobOpening::STATUSES)->map(fn ($_, $key) => JobOpening::where('status', $key)->count())
                ->put('all', JobOpening::count())->all(),
            'newApplications' => JobApplication::where('status', 'received')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $opening = new JobOpening(JobOpening::dataFrom($request));
        $opening->reference = JobOpening::nextReference();
        $opening->status = $request->input('publish') ? 'open' : 'draft';
        $opening->positions = (int) ($request->input('positions') ?: 1);
        $opening->created_by_user = $request->user()->id;
        $opening->save();

        return redirect()->route('admin.careers.show', $opening)
            ->with('status', $opening->status === 'open'
                ? $opening->reference.' is on the careers page.'
                : $opening->reference.' is saved as a draft — nothing is public until you open it.');
    }

    public function show(JobOpening $opening)
    {
        return view('admin.careers.show', [
            'opening' => $opening->load('creator:id,name', 'closer:id,name'),
            'applications' => $opening->applications()->with('reviewer:id,name')->latest('id')->get(),
            'statuses' => JobApplication::STATUSES,
            'newApplications' => $opening->applications->where('status', 'received')->count(),
        ]);
    }

    /** Open, keep as a draft or take the posting down. A closed posting stays on the page without a form. */
    public function setStatus(Request $request, JobOpening $opening)
    {
        $data = $request->validate(['status' => 'required|in:'.implode(',', array_keys(JobOpening::STATUSES))]);

        $opening->status = $data['status'];
        $opening->closed_by_user = $data['status'] === 'closed' ? $request->user()->id : null;
        $opening->save();

        return back()->with('status', $opening->reference.' is now '.$opening->statusLabel().'.');
    }

    /** The office's own edit of a posting. The reference and anything already applied to by name is left alone. */
    public function update(Request $request, JobOpening $opening)
    {
        $opening->fill(JobOpening::dataFrom($request))->save();

        return back()->with('status', 'The posting was amended. Anyone who already applied keeps their application.');
    }

    public function review(Request $request, JobApplication $application)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(JobApplication::STATUSES)),
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

    /** A CV is a person's private document: it is served to staff with the careers screens, to nobody else. */
    public function cv(JobApplication $application)
    {
        abort_if(! $application->hasCv(), 404, 'That person did not attach a CV.');
        abort_unless(Storage::disk('local')->exists($application->cv_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($application->cv_path, $application->cv_name ?: 'CV');
    }
}
