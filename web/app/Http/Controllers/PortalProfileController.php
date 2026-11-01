<?php

namespace App\Http\Controllers;

use App\Models\MemberDocument;
use App\Models\ProfileChange;
use App\Support\Documents;
use App\Support\Profile;
use Illuminate\Http\Request;

/**
 * The member's own profile in the portal: what the programme holds on them, the papers they send in to be checked
 * and the edits they ask for. A CTP student and an E-Fundi are answered by different offices — the Registrar and
 * Human Resources — but the page is the same, and neither writes onto their own record from here.
 */
class PortalProfileController extends Controller
{
    private function subject(): array
    {
        $found = Profile::subject(request()->user());

        abort_if(! $found, 403, 'There is no member record for this account yet.');

        return $found;
    }

    public function index()
    {
        return view('portal.profile', $this->board());
    }

    public function document(Request $request)
    {
        [, $record] = $this->subject();
        $document = Profile::upload($request->user(), $record, $request);

        return redirect()->route('portal.profile.index')
            ->with('status', $document->kindLabel().' is with '.Profile::office($document->subject_type)
                .' to be checked.');
    }

    public function paper(Request $request, MemberDocument $document)
    {
        Profile::dropPaper($document, $request->user());

        return back()->with('status', 'The paper is withdrawn.');
    }

    public function change(Request $request)
    {
        [, $record] = $this->subject();
        $change = Profile::request($request->user(), $record, $request);

        return redirect()->route('portal.profile.index')
            ->with('status', 'Your ask is with '.Profile::office($change->subject_type)
                .'. Nothing on your record has changed yet.');
    }

    public function withdraw(Request $request, ProfileChange $change)
    {
        Profile::withdraw($change, $request->user());

        return back()->with('status', 'The ask is withdrawn. You can send another one now.');
    }

    /** A member's papers and photo open only for the record they belong to. */
    public function file(Request $request, MemberDocument $document)
    {
        [$type, $record] = $this->subject();

        abort_unless($document->subject_type === $type && $document->subject_id === $record->id, 403,
            'That document is not on your profile.');
        abort_unless($document->hasFile(), 404, 'That file is no longer on record.');

        return \App\Support\Documents::file($request, $document->file_path, $document->file_name);
    }

    private function board(): array
    {
        [$type, $record] = $this->subject();

        return Profile::board($type, $record) + [
            'member' => $record,
            'photoRoute' => 'portal.photo',
            'uploadRoute' => 'portal.profile.document',
            'changeRoute' => 'portal.profile.change',
            'withdrawChangeRoute' => 'portal.profile.change.withdraw',
            'dropRoute' => 'portal.profile.paper',
            'fileRoute' => 'portal.profile.file',
        ];
    }
}
