<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberDocument;
use App\Models\ProfileChange;
use App\Support\Documents;
use App\Support\Profile;
use Illuminate\Http\Request;

/**
 * The office side of a person's file. Every account — staff, CTP student, E-Fundi — sends its papers and its edits
 * to the desk that keeps that kind of record: Human Resources answers staff and E-Fundis, the Academic Registrar
 * answers a student. This queue is cut by whoever is standing at it, so a desk never reads an answer that is not
 * its own to give. Nothing here deletes: a paper is taken onto the file or sent back for a better copy, an ask is
 * written onto the record or declined, and the row stays either way.
 */
class PapersController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless(Profile::answers($actor) !== [], 403,
            'Papers and profile asks are answered by the desk that keeps the record.');

        $queue = Profile::queue($actor);
        $tab = $request->input('tab') === 'answered' ? 'answered' : 'waiting';

        return view('admin.papers.index', $queue + [
            'tab' => $tab,
            'deskName' => collect($queue['types'])->map(fn ($t) => Profile::office($t))->unique()
                ->map(fn ($o) => trim($o, 'the '))->join(' · '),
            'waitingDocs' => collect($queue['documents'])->flatten(1)->values(),
            'waitingAsks' => collect($queue['changes'])->flatten(1)->values(),
        ]);
    }

    /** Take a paper onto the file, or send it back for a better copy. */
    public function decide(Request $request, MemberDocument $document)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,returned',
            'note' => 'nullable|string|max:500',
        ], ['decision' => 'Decision', 'note' => 'Note']);

        $message = Profile::decide($document, $data['decision'], $data['note'] ?? null, $request->user());

        return back()->with('status', $message);
    }

    /** Write an ask onto the record, or decline it with the reason. */
    public function answer(Request $request, ProfileChange $change)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:500',
        ], ['decision' => 'Decision', 'note' => 'Note']);

        $message = Profile::answer($change, $data['decision'], $data['note'] ?? null, $request->user());

        return back()->with('status', $message);
    }

    /**
     * The desk's own upload onto a person's file — a paper handed over at the counter is already seen, so it goes
     * straight on as approved. The record is taken from the posted type and id, then checked against this desk.
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        $record = $this->record($request, $actor);

        $document = Profile::upload($actor, $record, $request, true);

        return back()->with('status', $document->kindLabel().' is on '.
            $document->personLabel().'’s file.');
    }

    public function file(Request $request, MemberDocument $document)
    {
        abort_unless(Profile::decides($request->user(), $document->subject_type), 403,
            'These papers are answered by '.Profile::office($document->subject_type).'.');
        abort_unless($document->hasFile(), 404, 'That file is no longer on record.');

        return Documents::file($request, $document->file_path, $document->file_name);
    }

    /** The record a desk is filing a paper under, when this account is the one that answers it. */
    private function record(Request $request, $actor)
    {
        $data = $request->validate([
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
        ], ['subject_type' => 'Whose file', 'subject_id' => 'The record']);

        $type = $data['subject_type'];
        $record = Profile::SUBJECTS[$type] ?? null;
        abort_if(! $record, 403, 'That record has no profile desk.');

        $found = $record::query()->find((int) $data['subject_id']);
        abort_if(! $found, 404, 'That person is no longer on file.');
        abort_unless(Profile::decides($actor, $type), 403,
            'These papers are answered by '.Profile::office($type).'.');

        return $found;
    }
}
