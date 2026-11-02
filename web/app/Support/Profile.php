<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\MemberDocument;
use App\Models\ProfileChange;
use App\Models\StaffRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A person's own profile: what the office holds on them, the papers they send in to prove it, and the edits they
 * ask for instead of writing over the record themselves. A staff member, a CTP student and an E-Fundi all work the
 * same screen and the same rules; only the desk that answers them differs — Human Resources for the people who work
 * here, the Academic Registrar for a student being trained.
 *
 * Nothing posted here touches a record directly. A paper waits until the desk checks it, an edit waits until the
 * desk approves it, and only then is it written onto the file, always over the old value, never beside it.
 */
class Profile
{
    /** The three kinds of record a profile sits on. */
    public const SUBJECTS = [
        'staff' => StaffRecord::class,
        'ctp' => CtpRegistration::class,
        'efundi' => FundiRegistration::class,
    ];

    public const STATUSES = [
        'pending' => 'Waiting to be checked',
        'approved' => 'On record',
        'returned' => 'Sent back — send a better copy',
    ];

    public const CHANGE_STATUSES = [
        'pending' => 'Waiting on the office',
        'approved' => 'Written onto the record',
        'rejected' => 'Declined',
        'cancelled' => 'Withdrawn',
    ];

    /** What each desk asks to see, in the words a person reads. */
    private const PAPERS = [
        'staff' => [
            'photo' => 'Profile photo', 'national_id' => 'National ID', 'kra' => 'KRA PIN certificate',
            'degree' => 'Degree or diploma', 'education' => 'Another education document',
            'cv' => 'Curriculum vitae', 'other' => 'Any other document',
        ],
        'ctp' => [
            'photo' => 'Profile photo', 'national_id' => 'National ID', 'certificate' => 'School certificate',
            'education' => 'Another education document', 'other' => 'Any other document',
        ],
        'efundi' => [
            'photo' => 'Profile photo', 'national_id' => 'National ID', 'business' => 'Business registration',
            'trade' => 'Trade certificate', 'cv' => 'Curriculum vitae', 'other' => 'Any other document',
        ],
    ];

    /** The papers a file is not complete without. */
    private const NEEDED = [
        'staff' => ['national_id', 'kra', 'degree'],
        'ctp' => ['national_id', 'certificate'],
        'efundi' => ['national_id', 'business'],
    ];

    /** What a person may read and ask to change: field, the words on the form, and how it is typed. A field marked
     *  'ro' is shown on the file but never typed by the person — the office keeps the pen on it. */
    private const ASK = [
        'staff' => [
            'name' => ['Name', 'names'], 'phone' => ['Phone', 'text'], 'email' => ['Email', 'text'],
            'job_title' => ['Title in the company', 'text'], 'started_on' => ['First day', 'date', 'ro'],
            'address' => ['Address', 'text'], 'id_number' => ['ID number', 'text'],
            'kra_pin' => ['KRA PIN', 'text'], 'nssf_number' => ['NSSF number', 'text'],
            'nhif_number' => ['NHIF / SHA number', 'text'], 'bank_name' => ['Bank', 'text'],
            'bank_branch' => ['Bank branch', 'text'], 'bank_account' => ['Account number', 'text'],
            'next_of_kin_name' => ['First next of kin — name', 'text'],
            'next_of_kin_phone' => ['First next of kin — contact', 'text'],
            'next_of_kin_relation' => ['First next of kin — relationship', 'text'],
            'next_of_kin_2_name' => ['Second next of kin — name', 'text'],
            'next_of_kin_2_phone' => ['Second next of kin — contact', 'text'],
            'next_of_kin_2_relation' => ['Second next of kin — relationship', 'text'],
        ],
        'ctp' => [
            'full_name' => ['Name', 'names'], 'phone' => ['Phone', 'text'], 'email' => ['Email', 'text'],
            'dob' => ['Date of birth', 'date'], 'gender' => ['Gender', 'choice'],
            'county' => ['County', 'text'], 'sub_county' => ['Sub-county', 'text'],
            'ward' => ['Ward', 'text'], 'village' => ['Village', 'text'],
            'education_level' => ['Highest education', 'choice'],
        ],
        'efundi' => [
            'full_name' => ['Name', 'names'], 'phone' => ['Phone', 'text'], 'email' => ['Email', 'text'],
            'dob' => ['Date of birth', 'date'], 'gender' => ['Gender', 'choice'],
            'business_name' => ['Business name', 'text'], 'business_reg_no' => ['Business registration number', 'text'],
            'years_in_business' => ['Years in the trade', 'number'],
            'referee_name' => ['Referee — name', 'text'], 'referee_phone' => ['Referee — contact', 'text'],
            'referee_role' => ['Referee — role', 'text'],
        ],
    ];

    private const CHOICES = [
        'gender' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'],
        'education_level' => CtpRegistration::EDUCATION_LEVELS,
    ];

    /** The same rules the office applies when it types these fields itself. */
    private static function rules(): array
    {
        return [
            'name' => 'required|string|max:150', 'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:160', 'phone' => 'required|string|max:30',
            'job_title' => 'nullable|string|max:120', 'started_on' => 'nullable|date', 'dob' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'address' => 'nullable|string|max:180', 'id_number' => 'nullable|string|max:40',
            'kra_pin' => 'nullable|string|max:40', 'nssf_number' => 'nullable|string|max:40',
            'nhif_number' => 'nullable|string|max:40', 'bank_name' => 'nullable|string|max:90',
            'bank_branch' => 'nullable|string|max:90', 'bank_account' => 'nullable|string|max:40',
            'next_of_kin_name' => 'required|string|max:150', 'next_of_kin_phone' => 'required|string|max:30',
            'next_of_kin_relation' => 'required|string|max:60',
            'next_of_kin_2_name' => 'nullable|string|max:150', 'next_of_kin_2_phone' => 'nullable|string|max:30',
            'next_of_kin_2_relation' => 'nullable|string|max:60',
            'county' => 'required|string|max:100', 'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100', 'village' => 'nullable|string|max:100',
            'education_level' => 'nullable|in:'.implode(',', array_keys(CtpRegistration::EDUCATION_LEVELS)),
            'business_name' => 'nullable|string|max:150', 'business_reg_no' => 'nullable|string|max:40',
            'years_in_business' => 'nullable|integer|min:0|max:80',
            'referee_name' => 'nullable|string|max:150', 'referee_phone' => 'nullable|string|max:20',
            'referee_role' => 'nullable|string|max:120',
        ];
    }

    /** Which of the three this record is. */
    public static function type(Model $record): string
    {
        $found = array_search($record::class, self::SUBJECTS, true);

        abort_if($found === false, 403, 'That record has no profile desk.');

        return $found;
    }

    public static function kindLabel(string $type): string
    {
        return match ($type) {
            'staff' => 'Office staff',
            'ctp' => 'CTP student',
            'efundi' => 'E-Fundi',
            default => 'A person',
        };
    }

    /** The desk that answers this kind of person. A student's papers and edits go to the Registrar, not the counter. */
    public static function office(string $type): string
    {
        return $type === 'ctp' ? 'the Academic Registrar' : 'Human Resources';
    }

    public static function desk(string $type): string
    {
        return $type === 'ctp' ? 'training' : 'hr';
    }

    public static function decides(?User $actor, string $type): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->isSuperAdmin() || in_array(self::desk($type), $actor->departmentSlugs(), true);
    }

    /** The kinds of record this account answers, which is what its own queue is cut by. */
    public static function answers(?User $actor): array
    {
        if (! $actor) {
            return [];
        }

        return array_values(array_filter(array_keys(self::SUBJECTS),
            fn (string $type) => static::decides($actor, $type)));
    }

    /** The record the signed-in account's profile sits on. */
    public static function subject(User $user): ?array
    {
        if (in_array($user->role, ['admin', 'superadmin'], true)) {
            return ['staff', StaffRecord::ensure($user)];
        }

        $member = MemberPortal::resolve($user);

        return $member ? [static::type($member), $member] : null;
    }

    public static function person(string $type, ?Model $record): string
    {
        return $record?->name ?? $record?->full_name ?? 'A person no longer on file';
    }

    public static function papers(string $type): array
    {
        return self::PAPERS[$type] ?? [];
    }

    public static function paperLabel(string $type, string $kind): string
    {
        return self::papers($type)[$kind] ?? Str::headline($kind);
    }

    /** The papers still missing from a file, in the words the person needs. */
    public static function missing(string $type, iterable $documents): array
    {
        $held = collect($documents)->where('status', 'approved')->pluck('kind')->all();

        return array_values(array_diff(self::NEEDED[$type] ?? [], $held));
    }

    /** Every detail the person reads on their own file, including the ones only the office may type. */
    public static function fields(string $type): array
    {
        return self::ASK[$type] ?? [];
    }

    /** The details the person may type themselves. A field marked 'ro' stays out of the form. */
    public static function askable(string $type): array
    {
        return array_filter(static::fields($type), fn (array $ask) => ($ask[2] ?? null) !== 'ro');
    }

    /** Every field the person may read, with the value on file formatted for the screen. */
    public static function lines(string $type, ?Model $record): array
    {
        return array_map(fn (string $field, array $ask) => [
            'field' => $field,
            'label' => $ask[0],
            'kind' => $ask[1],
            'ro' => ($ask[2] ?? null) === 'ro',
            'raw' => $ask[1] === 'date' && $record?->{$field}
                ? Carbon::parse((string) $record->{$field})->toDateString() : $record?->{$field},
            'parts' => $ask[1] === 'names' ? static::nameParts($record?->{$field}) : [],
            'choices' => $ask[1] === 'choice' ? (self::CHOICES[$field] ?? []) : [],
            'value' => static::value($record, $field, $ask[1]),
        ], array_keys(self::fields($type)), self::fields($type));
    }

    /** The one name the file holds, read back as the first, middle and last a person types. */
    private static function nameParts($full): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim((string) $full)) ?: [],
            fn (string $word) => $word !== ''));

        return [
            'first' => $words[0] ?? '',
            'middle' => count($words) > 2 ? implode(' ', array_slice($words, 1, -1)) : '',
            'last' => count($words) > 1 ? $words[count($words) - 1] : '',
        ];
    }

    /** What is on file beside what is being asked for — the whole ask reads from this. */
    public static function compare(string $type, ?Model $record, array $changes): array
    {
        $fields = self::fields($type);

        return collect($changes)->map(fn ($wanted, string $field) => [
            'field' => $field,
            'label' => $fields[$field][0] ?? Str::headline($field),
            'kind' => $fields[$field][1] ?? 'text',
            'from' => static::value($record, $field, $fields[$field][1] ?? 'text'),
            'to' => static::shown($wanted, $fields[$field][1] ?? 'text', $field),
        ])->values()->all();
    }

    public static function value(?Model $record, string $field, string $kind): string
    {
        return static::shown($record?->{$field}, $kind, $field);
    }

    /** A value the way a person reads it: a date with its month named, a choice with its words, nothing as a gap. */
    private static function shown($raw, string $kind, ?string $field = null): string
    {
        if ($raw === null || $raw === '') {
            return 'Not on file';
        }

        return match ($kind) {
            'date' => Carbon::parse((string) $raw)->format('d M Y'),
            'choice' => (self::CHOICES[$field] ?? [])[$raw] ?? (string) $raw,
            default => (string) $raw,
        };
    }

    /** Everything a person's own page needs, whoever they are and whichever desk answers them. */
    public static function board(string $type, Model $record): array
    {
        $documents = MemberDocument::forSubject($type, $record->id);

        return [
            'subjectType' => $type,
            'record' => $record,
            'office' => self::office($type),
            'kindTitle' => self::kindLabel($type),
            'lines' => static::lines($type, $record),
            'papers' => static::papers($type),
            'documents' => $documents,
            'missing' => static::missing($type, $documents),
            'changes' => ProfileChange::forSubject($type, $record->id),
            'waiting' => ProfileChange::pendingFor($type, $record->id),
        ];
    }

    /** The desk's own view: every paper and every ask waiting on the desks this account answers. */
    public static function queue(?User $actor): array
    {
        $types = static::answers($actor);

        return [
            'types' => $types,
            'documents' => MemberDocument::waiting($types)->groupBy('subject_type')->all(),
            'changes' => ProfileChange::waiting($types)->groupBy('subject_type')->all(),
            'held' => MemberDocument::whereIn('subject_type', $types)->whereIn('status', ['approved', 'returned'])
                ->with('decidedBy:id,name')->latest('id')->limit(60)->get(),
            'answered' => ProfileChange::whereIn('subject_type', $types)->whereIn('status', ['approved', 'rejected'])
                ->with('decidedBy:id,name')->latest('id')->limit(60)->get(),
        ];
    }

    /** How much this account has itself sent in and is waiting on a desk for. */
    public static function waitingOnMine(User $user): int
    {
        return MemberDocument::where('uploaded_by_user', $user->id)->where('status', 'pending')->count()
            + ProfileChange::where('requested_by_user', $user->id)->where('status', 'pending')->count();
    }

    /** How much is waiting on this account's desks — the count on the sidebar badge. */
    public static function waitingFor(?User $actor): int
    {
        $types = static::answers($actor);

        return $types === [] ? 0
            : MemberDocument::waitingCount($types) + ProfileChange::waitingCount($types);
    }

    /** The folder a person's own papers are filed in — private, like every other upload. */
    private static function folder(Model $record): string
    {
        return match (static::type($record)) {
            'staff' => 'profiles/staff/'.$record->id,
            'ctp' => 'profiles/ctp/'.$record->reference,
            default => 'profiles/efundi/'.$record->reference,
        };
    }

    /**
     * Take a paper onto the file. A person's upload waits for the desk; a desk's own upload is already seen, so it
     * goes straight onto the record. Sending a better copy writes over the one that was sent back.
     */
    public static function upload(User $actor, Model $record, Request $request, bool $byDesk = false): MemberDocument
    {
        $type = static::type($record);

        $data = $request->validate([
            'kind' => 'required|in:'.implode(',', array_keys(static::papers($type))),
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ], [
            'file.required' => 'Choose the document to send in.',
            'file.mimes' => 'A document is a JPG, PNG or PDF — a photo of the paper is enough.',
            'file.max' => 'A document must be under 8 MB. Photograph it closer rather than sending a full scan.',
        ], [
            'kind' => 'Document', 'file' => 'The file',
        ]);

        $stored = $request->file('file')->store(static::folder($record), 'local');

        $document = $byDesk ? null : MemberDocument::find((int) $request->input('document'));

        if ($document && ($document->subject_type !== $type || $document->subject_id !== $record->id
            || ! $document->replaceable())) {
            throw ValidationException::withMessages(['file' =>
                'That paper is already answered — send a new one instead of writing over it.']);
        }

        $fields = [
            'kind' => $data['kind'],
            'file_path' => $stored,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_size' => $request->file('file')->getSize(),
            'status' => $byDesk ? 'approved' : 'pending',
            'decision_note' => null,
            'decided_by_user' => $byDesk ? $actor->id : null,
            'decided_at' => $byDesk ? now() : null,
        ];

        $old = $document?->file_path;

        if ($document) {
            $document->forceFill($fields)->save();
        } else {
            $document = new MemberDocument([
                'subject_type' => $type, 'subject_id' => $record->id, 'uploaded_by_user' => $actor->id,
                'kind' => $fields['kind'], 'file_path' => $fields['file_path'],
                'file_name' => $fields['file_name'], 'file_size' => $fields['file_size'],
            ]);

            $document->forceFill(['status' => $fields['status'], 'decided_by_user' => $fields['decided_by_user'],
                'decided_at' => $fields['decided_at']])->save();
        }

        if ($old && $old !== $stored && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }

        Audit::record($document, $document->wasRecentlyCreated ? 'created' : 'updated',
            $actor->name.' sent in a '.strtolower($document->kindLabel()).' for '.
            ($byDesk ? $document->personLabel().'’s file' : 'their own file').'.');

        return $document;
    }

    /** The desk's answer to a paper. An approved profile photo also becomes the face on the file. */
    public static function decide(MemberDocument $document, string $status, ?string $note, User $actor): string
    {
        abort_unless(static::decides($actor, $document->subject_type), 403,
            'These papers are answered by '.self::office($document->subject_type).'.');
        abort_unless(in_array($status, ['approved', 'returned'], true), 422,
            'Take the paper onto the record or send it back for a better copy.');

        $document->forceFill([
            'status' => $status,
            'decision_note' => $note ? trim($note) : null,
            'decided_by_user' => $actor->id,
            'decided_at' => now(),
        ])->save();

        if ($status === 'approved' && $document->kind === 'photo') {
            static::putPhoto($document);
        }

        Audit::record($document, 'updated', $actor->name.' '.$status.' the '.
            strtolower($document->kindLabel()).' of '.$document->personLabel().($note ? ': '.trim($note) : '.'));

        return $document->kindLabel().' for '.$document->personLabel().' is '
            .($status === 'approved' ? 'on record.' : 'sent back — '.static::office($document->subject_type)
                .' needs a better copy.');
    }

    /** The face on the file: the approved copy is kept, the picture it replaces is removed. */
    private static function putPhoto(MemberDocument $document): void
    {
        $record = $document->subject();

        if (! $record) {
            return;
        }

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        $path = dirname($document->file_path).'/'.Str::random(24).'.'.$extension;

        Storage::disk('local')->copy($document->file_path, $path);

        $column = $document->subject_type === 'staff' ? 'photo_path' : 'passport_photo_path';
        $old = $record->{$column};

        $record->forceFill([$column => $path])->save();

        if ($old && $old !== $path && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }
    }

    /**
     * Write down what a person wants changed. Nothing moves onto the record from here: the ask waits with the rest,
     * and only one ask can wait at a time, so the desk is never given two answers for the same field.
     */
    public static function request(User $asker, Model $record, Request $request): ProfileChange
    {
        $type = static::type($record);
        $askable = static::askable($type);

        abort_if(ProfileChange::pendingFor($type, $record->id), 422,
            'A change you asked for is still waiting on '.self::office($type)
            .' — withdraw it first if you want to ask again.');

        $data = $request->validate(
            // The form carries every field, including ones this person is not touching and the office has left
            // blank — an ask must not be blocked by a field it does not change. The desk still applies the office's
            // own rules to the values it is about to write.
            collect($askable)->mapWithKeys(fn (array $ask, string $field) => [
                $field => preg_replace('/\brequired\b/', 'nullable', self::rules()[$field] ?? 'nullable|string|max:150'),
            ])->all()
                + ['note' => 'nullable|string|max:500'],
            [],
            collect($askable)->mapWithKeys(fn (array $ask, string $field) => [$field => $ask[0]])->all()
        );

        $changes = collect($askable)->keys()
            ->mapWithKeys(fn (string $field) => [$field => $askable[$field][1] === 'names'
                ? static::named($request, $record, $field) : ($data[$field] ?? null)])
            ->filter(fn ($wanted, string $field) => static::differs($record, $field, $wanted))
            ->all();

        if ($changes === []) {
            throw ValidationException::withMessages([
                collect($askable)->keys()->first() => 'Fill at least one detail — nothing you sent differs from what '
                    .self::office($type).' already holds.',
            ]);
        }

        $change = ProfileChange::create([
            'subject_type' => $type,
            'subject_id' => $record->id,
            'changes' => $changes,
            'note' => $data['note'] ?? null,
            'requested_by_user' => $asker->id,
        ]);

        Audit::record($change, 'created', $asker->name.' asked '.self::office($type).' to change '
            .collect($change->lines())->pluck('label')->join(', ').' on their profile.');

        return $change;
    }

    /** The first, middle and last a person types, joined back into the single name the file holds. Typing none of
     *  them asks for no change at all — an empty set of boxes cannot wipe a name off the record. */
    private static function named(Request $request, Model $record, string $field): string
    {
        $typed = collect(['name_first', 'name_middle', 'name_last'])
            ->map(fn (string $part) => trim((string) $request->input($part, '')))
            ->filter()->implode(' ');

        return $typed === '' ? (string) ($record->{$field} ?? '') : $typed;
    }

    private static function differs(Model $record, string $field, $wanted): bool
    {
        $now = $record->{$field};

        if (is_array($now)) {
            return false;
        }

        $wanted = is_string($wanted) ? trim($wanted) : $wanted;

        if ($wanted === null || $wanted === '') {
            return $now !== null && $now !== '';
        }

        if ((self::fields(static::type($record))[$field][1] ?? 'text') === 'date') {
            return \Illuminate\Support\Carbon::parse($wanted)->toDateString()
                !== ($now ? \Illuminate\Support\Carbon::parse((string) $now)->toDateString() : null);
        }

        return (string) $now !== (string) $wanted;
    }

    /**
     * The desk's answer to an ask. Approving is the only way a posted form reaches a person's record, and it is
     * checked again here — an email or a phone number that belongs to someone else is refused before it lands.
     */
    public static function answer(ProfileChange $change, string $decision, ?string $note, User $actor): string
    {
        abort_unless(static::decides($actor, $change->subject_type), 403,
            'These asks are answered by '.self::office($change->subject_type).'.');
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422,
            'Write the change onto the record or decline it.');

        if ($decision === 'rejected') {
            $change->forceFill(['status' => 'rejected', 'decision_note' => $note ? trim($note) : null,
                'decided_by_user' => $actor->id, 'decided_at' => now()])->save();

            Audit::record($change, 'updated', $actor->name.' declined a profile change for '
                .$change->personLabel().($note ? ': '.trim($note) : '.'));

            return 'The ask is declined. '.$change->personLabel().' can read why on their own profile.';
        }

        $record = $change->subject();
        abort_if(! $record, 422, 'The record this ask belongs to is no longer on file.');

        static::check($change, $record);

        // These are the fields the desk has just checked, and the only columns an ask can ever carry.
        $record->forceFill($change->changes ?? [])->save();

        $change->forceFill(['status' => 'approved', 'decision_note' => $note ? trim($note) : null,
            'decided_by_user' => $actor->id, 'decided_at' => now()])->save();

        Audit::record($change, 'updated', $actor->name.' wrote a profile change onto the file of '
            .$change->personLabel().': '.collect($change->lines())->pluck('label')->join(', ').'.');

        return $change->personLabel().'’s profile is updated: '
            .collect($change->lines())->pluck('label')->join(', ').'; and the ask is closed.';
    }

    /** The same rules as the office's own edit, applied to the values that were asked for. */
    private static function check(ProfileChange $change, Model $record): void
    {
        $askable = static::askable($change->subject_type);

        $rules = collect($change->changes ?? [])
            ->mapWithKeys(fn ($_, string $field) => [$field => self::rules()[$field] ?? 'nullable|string|max:150'])
            ->all();

        $bad = Validator::make($change->changes ?? [], $rules, [],
            collect($askable)->mapWithKeys(fn (array $ask, string $field) => [$field => $ask[0]])->all())
            ->errors()->all();

        if ($bad !== []) {
            throw ValidationException::withMessages(['decision' => implode(' ', $bad)]);
        }

        foreach (['email', 'phone'] as $field) {
            if (! array_key_exists($field, $change->changes ?? [])) {
                continue;
            }

            $taken = $record::query()
                ->when($field === 'email', fn ($q) => $q->whereRaw('LOWER(email) = ?',
                    [Str::lower($change->changes[$field])]))
                ->when($field === 'phone', fn ($q) => $q->where('phone', $change->changes[$field]))
                ->where('id', '!=', $record->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['decision' => 'That '
                    .($askable[$field][0] ?? $field).' is already on another record — the office cannot write it twice.']);
            }
        }
    }

    /** The person takes their own ask back. The row stays, marked withdrawn. */
    public static function withdraw(ProfileChange $change, User $asker): string
    {
        abort_unless($change->isPending(), 422, 'That ask is already answered.');
        abort_unless($change->requested_by_user === $asker->id, 403, 'That ask is not yours.');

        $change->forceFill(['status' => 'cancelled', 'decided_by_user' => $asker->id, 'decided_at' => now()])->save();

        Audit::record($change, 'updated', $asker->name.' withdrew a profile change they had asked for.');

        return 'The ask is withdrawn. You can submit another one now.';
    }

    /** A paper the person no longer wants the office to look at. */
    public static function dropPaper(MemberDocument $document, User $asker): string
    {
        abort_unless($document->isWaiting(), 422, 'That paper is already answered.');
        abort_unless($document->uploaded_by_user === $asker->id, 403, 'That paper is not yours.');

        $document->forceFill(['status' => 'returned', 'decision_note' => 'Withdrawn by the person who sent it.',
            'decided_by_user' => $asker->id, 'decided_at' => now()])->save();

        Audit::record($document, 'updated', $asker->name.' withdrew a '.$document->kindLabel().' they had sent in.');

        return 'The paper is withdrawn. Send it again whenever you are ready.';
    }
}
