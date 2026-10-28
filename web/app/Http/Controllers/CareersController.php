<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobOpening;
use Illuminate\Http\Request;

/**
 * The public careers page. The office posts an opening here — a job it is hiring for, or project work that is not
 * one of the trainings on the programme catalogue — and any person can apply from the page without an account.
 * A draft posting never reaches this controller's pages and a closed one is shown without a form.
 */
class CareersController extends Controller
{
    public function index()
    {
        $open = JobOpening::where('status', 'open')
            ->where(fn ($q) => $q->whereNull('closing_on')->orWhereDate('closing_on', '>=', today()))
            ->orderByRaw('closing_on is null, closing_on')->orderByDesc('id')->get();

        $closed = JobOpening::where(fn ($q) => $q->where('status', 'closed')
                ->orWhereDate('closing_on', '<', today()))
            ->where('status', '!=', 'draft')
            ->latest('id')->limit(6)->get();

        return view('pages.careers', [
            'open' => $open,
            'closed' => $closed,
            'kinds' => JobOpening::KINDS,
        ]);
    }

    public function show(JobOpening $opening)
    {
        abort_if($opening->status === 'draft', 404);

        return view('pages.career-opening', ['opening' => $opening]);
    }

    /**
     * A visitor's answer to a posting. The CV is kept on the private disk and only ever served to the office —
     * a person who applies again with the same email address overwrites their own application rather than adding
     * a second copy to the pile.
     */
    public function apply(Request $request, JobOpening $opening)
    {
        abort_if($opening->status === 'draft', 404);
        abort_unless($opening->isOpen(), 410, 'Applications for this opening are closed.');

        $data = JobApplication::dataFrom($request);
        $data += $request->validate([
            'cv' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ], [], ['cv' => 'your CV']);

        $existing = $opening->applications()->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();

        $application = $existing ?? $opening->applications()->make();
        $application->fill($data);

        if ($file = $request->file('cv')) {
            $application->cv_path = $file->store('careers/'.$opening->reference, 'local');
            $application->cv_name = $file->getClientOriginalName();
            $application->cv_size = $file->getSize();
        }

        $application->save();

        return redirect()->route('careers.opening', $opening)
            ->with('status', 'Thank you, '.$application->name.'. Your application to '
                .$opening->title.' is with the programme office — they will reach you on '
                .$application->phone.' or by email once they have read it.')
            ->with('applied_to', $opening->id);
    }
}
