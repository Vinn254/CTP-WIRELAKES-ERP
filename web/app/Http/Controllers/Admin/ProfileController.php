<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberDocument;
use App\Models\ProfileChange;
use App\Support\Documents;
use App\Support\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A staff member's own profile: what Human Resources holds on them, the documents they send in to be checked, and
 * the edits they ask for. Nothing typed on this page reaches the file — a document waits to be checked and an ask
 * waits to be approved, both by the desk that owns the record.
 */
class ProfileController extends Controller
{
    /** The record this account's profile sits on — a staff file here, a member record in the portal. */
    private function subject(): array
    {
        $found = Profile::subject(auth()->user());

        abort_if(! $found, 403,
            'There is no staff file for this account yet. Human Resources opens one when a person is onboarded.');

        return $found;
    }

    private function board(): array
    {
        [$type, $record] = $this->subject();

        return Profile::board($type, $record) + [
            'photoRoute' => 'admin.profile.photo',
            'uploadRoute' => 'admin.profile.document',
            'changeRoute' => 'admin.profile.change',
            'withdrawChangeRoute' => 'admin.profile.change.withdraw',
            'dropRoute' => 'admin.profile.paper',
            'fileRoute' => 'admin.profile.file',
        ];
    }

    public function index()
    {
        return view('admin.profile.show', $this->board());
    }

    /** A paper sent in, or a better copy written over one that has not been answered yet. */
    public function document(Request $request)
    {
        [, $record] = $this->subject();
        $document = Profile::upload($request->user(), $record, $request);

        return redirect()->route('admin.profile.index')
            ->with('status', $document->kindLabel().' is with '.Profile::office($document->subject_type)
                .' to be checked.');
    }

    public function paper(MemberDocument $document)
    {
        Profile::dropPaper($document, auth()->user());

        return back()->with('status', 'The paper is withdrawn.');
    }

    public function change(Request $request)
    {
        [, $record] = $this->subject();
        $change = Profile::request($request->user(), $record, $request);

        return redirect()->route('admin.profile.index')
            ->with('status', 'Your ask is with '.Profile::office($change->subject_type)
                .'. Nothing on your record has changed yet.');
    }

    public function withdraw(ProfileChange $change)
    {
        Profile::withdraw($change, auth()->user());

        return back()->with('status', 'The ask is withdrawn. You can send another one now.');
    }

    /** The face on the person's own file. */
    public function photo()
    {
        [, $record] = $this->subject();
        $path = $record->photo_path ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404,
            'No profile photo is on your file yet. Ask Human Resources to capture one.');

        return response()->file(Storage::disk('local')->path($path));
    }

    public function file(Request $request, MemberDocument $document)
    {
        $this->owns($document);

        return Documents::file($request, $document->file_path, $document->file_name);
    }

    /** A person reads their own papers, and nobody else's. */
    private function owns(MemberDocument $document): void
    {
        [$type, $record] = $this->subject();

        abort_unless($document->subject_type === $type && $document->subject_id === $record->id, 403,
            'That document is not on your profile.');
        abort_unless($document->hasFile(), 404, 'That file is no longer on record.');
    }
}
