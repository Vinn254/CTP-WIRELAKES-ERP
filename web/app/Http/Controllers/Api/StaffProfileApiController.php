<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberDocument;
use App\Models\ProfileChange;
use App\Support\Profile;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My profile over the API — the same paper /admin/profile serves an office account: what Human Resources holds,
 * the documents sent in to be checked and the edits asked for. Everything runs through App\Support\Profile, so a
 * detail typed on a phone waits for the desk that owns the record exactly as one typed on the website does. Nothing
 * here writes onto a staff file by itself.
 */
class StaffProfileApiController extends Controller
{
    use UploadsFromJson;

    /** @return array{0: string, 1: \Illuminate\Database\Eloquent\Model} */
    private function subject(): array
    {
        $found = Profile::subject(auth()->user());

        abort_if(! $found, 403,
            'There is no staff file for this account yet. Human Resources opens one when a person is onboarded.');

        return $found;
    }

    /** GET /api/my-profile — the profile board, as the website's own page reads it. */
    public function profile(Request $request): JsonResponse
    {
        [$type, $record] = $this->subject();
        $board = Profile::board($type, $record);

        $photo = $record->photo_path ?? $record->passport_photo_path ?? null;
        $lines = collect($board['lines']);

        return response()->json([
            'kind' => $board['kindTitle'],
            'office' => $board['office'],
            'person' => Profile::person($type, $record),
            'initials' => collect(explode(' ', trim(Profile::person($type, $record))))
                ->filter()->take(2)->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
                ->join(''),
            'has_photo' => (bool) $photo,
            'lines' => $lines->map(fn (array $line) => $line + [
                'askable' => ! $line['ro'],
                'empty' => $line['raw'] === null || $line['raw'] === '',
            ])->values(),
            'papers' => $board['papers'],
            'missing' => collect($board['missing'])->map(fn (string $kind) => $board['papers'][$kind] ?? $kind)->values(),
            'need' => $lines->where('ro', false)
                ->filter(fn (array $line) => $line['raw'] === null || $line['raw'] === '')
                ->pluck('label')
                ->merge(collect($board['missing'])->map(fn (string $kind) => $board['papers'][$kind] ?? $kind))
                ->values(),
            'documents' => $board['documents']->map(fn (MemberDocument $paper) => [
                'id' => (int) $paper->id,
                'kind' => $paper->kind,
                'label' => $paper->kindLabel(),
                'file_name' => $paper->file_name,
                'size' => $paper->sizeLabel(),
                'sent_on' => $paper->created_at?->format('d M Y'),
                'status' => $paper->status,
                'status_label' => $paper->statusLabel(),
                'note' => $paper->decision_note,
                'answered_by' => $paper->decidedBy ? $paper->decidedBy->name.' · '.$paper->decided_at?->format('d M Y') : null,
                'openable' => $paper->hasFile(),
                'replaceable' => $paper->replaceable(),
                'waiting' => $paper->isWaiting(),
            ])->values(),
            'waiting' => $board['waiting'] ? $this->changeJson($board['waiting']) : null,
            'changes' => $board['changes']->map(fn (ProfileChange $change) => $this->changeJson($change))->values(),
        ]);
    }

    /** POST /api/my-profile/document — a paper sent in, or a better copy written over one not yet answered. */
    public function storeDocument(Request $request)
    {
        $this->liftUploads($request, ['file']);
        [, $record] = $this->subject();

        $document = Profile::upload($request->user(), $record, $request);

        return response()->json(['message' => $document->kindLabel().' is with '
            .Profile::office($document->subject_type).' to be checked.']);
    }

    /** POST /api/my-profile/document/{document}/withdraw — the person takes the paper back. */
    public function withdrawDocument(Request $request, MemberDocument $document): JsonResponse
    {
        return response()->json(['message' => Profile::dropPaper($document, $request->user())]);
    }

    /** GET /api/my-profile/document/{document} — the paper itself, only its own. */
    public function documentFile(Request $request, MemberDocument $document): JsonResponse
    {
        [$type, $record] = $this->subject();
        abort_unless($document->subject_type === $type && $document->subject_id === $record->id, 403,
            'That document is not on your profile.');
        abort_unless($document->hasFile(), 404, 'That file is no longer on record.');

        return response()->json($this->inline($document->file_path, $document->file_name ?: $document->kindLabel()));
    }

    /** POST /api/my-profile/change — details typed on the phone, which wait for the desk before they land. */
    public function requestChange(Request $request): JsonResponse
    {
        [, $record] = $this->subject();
        $change = Profile::request($request->user(), $record, $request);

        return response()->json(['message' => 'Your ask is with '
            .Profile::office($change->subject_type).'. Nothing on your record has changed yet.']);
    }

    /** POST /api/my-profile/change/{change}/withdraw */
    public function withdrawChange(Request $request, ProfileChange $change): JsonResponse
    {
        return response()->json(['message' => Profile::withdraw($change, $request->user())]);
    }

    /** GET /api/my-profile/photo — the face on the person's own file. */
    public function photo(Request $request): JsonResponse
    {
        [, $record] = $this->subject();

        return response()->json($this->inline($record->photo_path ?? $record->passport_photo_path ?? null, 'profile photo'));
    }

    private function changeJson(ProfileChange $change): array
    {
        return [
            'id' => (int) $change->id,
            'asked_on' => $change->created_at?->format('d M Y'),
            'status' => $change->status,
            'status_label' => $change->statusLabel(),
            'note' => $change->note,
            'fields' => collect($change->lines())->pluck('label')->join(', '),
            'lines' => collect($change->lines())->map(fn (array $line) => [
                'label' => $line['label'], 'from' => $line['from'], 'to' => $line['to'],
            ])->values(),
            'answered_by' => $change->decidedBy ? $change->decidedBy->name.' · '.$change->decided_at?->format('d M Y') : null,
            'pending' => $change->isPending(),
        ];
    }
}
