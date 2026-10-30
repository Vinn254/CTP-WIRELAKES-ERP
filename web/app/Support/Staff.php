<?php

namespace App\Support;

use App\Mail\StaffInviteEmail;
use App\Models\JobApplication;
use App\Models\StaffInvite;
use App\Models\StaffRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The people desk. A careers applicant becomes a member of the office here: the file is written, the login is
 * raised, and the person appears in the staff directory for Human Resources to keep up to date.
 */
class Staff
{
    /** What a personnel file holds, in the order the card asks for it. */
    public const FIELDS = [
        'name', 'email', 'phone', 'job_title', 'started_on', 'salary', 'id_number', 'kra_pin', 'nssf_number',
        'nhif_number', 'bank_name', 'bank_branch', 'bank_account', 'address',
        'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relation',
        'next_of_kin_2_name', 'next_of_kin_2_phone', 'next_of_kin_2_relation',
    ];

    /** Only Human Resources — and the account the programme itself holds — opens a personnel file. */
    public static function decides(?User $actor): bool
    {
        return (bool) ($actor?->isSuperAdmin() || in_array('hr', $actor?->departmentSlugs() ?? [], true));
    }

    /** Fetch a file for every staff account that has none, so the directory never misses a person already working. */
    public static function sync(): void
    {
        User::whereIn('role', ['admin', 'superadmin'])->whereDoesntHave('staffRecord')->get()
            ->each(fn (User $account) => StaffRecord::ensure($account));
    }

    /**
     * Turn a shortlisted or picked applicant into staff: their file, the invitation that opens their login, and the
     * application marked as answered. A person who already holds an account is written onto that file instead of
     * being given a second one.
     *
     * @return array{0: StaffRecord, 1: string} the file and the sentence to flash
     */
    public static function onboard(JobApplication $application, Request $request, User $actor): array
    {
        abort_unless(in_array($application->status, JobApplication::ONBOARDABLE, true), 422,
            'Shortlist the person and give them the job before they are onboarded.');

        $data = static::validate($request);
        $email = Accounts::email($data['email']);
        $account = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        [$roles, $departments] = static::grant($request->input('roles', []), $request->input('departments', []));

        if (! $account && $roles === [] && $departments === []) {
            throw ValidationException::withMessages(['departments' =>
                'Tick the role or the module this person will work in — an invitation with neither opens nothing.']);
        }

        $record = $account
            ? StaffRecord::ensure($account)
            : (StaffRecord::firstOrNew(['job_application_id' => $application->id]));

        $record->fill(collect($data)->only(self::FIELDS)->all());
        $record->email = $email;
        $record->job_application_id = $application->id;
        $record->recorded_by_user = $actor->id;

        if ($file = $request->file('photo')) {
            $record->keepPhoto($file);
        }

        $record->save();

        if ($account) {
            $record->forceFill(['user_id' => $account->id])->save();
        }

        $application->forceFill(['status' => 'onboarded', 'onboarded_at' => now(),
            'onboarded_by_user' => $actor->id])->save();

        Audit::record($record, $record->wasRecentlyCreated ? 'created' : 'updated',
            $actor->name.' onboarded '.$record->name.' as '.($record->job_title ?: 'staff')
            .' from the opening '.$application->opening?->reference.'.');

        if ($account) {
            return [$record, $record->name.' is on the staff directory, on the account they already hold.'];
        }

        $invite = static::raiseInvite($record, $roles, $departments, $data['trainer_training_id'] ?? null, $actor);

        if (! $invite) {
            return [$record, $record->name.' is on the staff directory. An invitation to '
                .$record->email.' is already waiting — send that one again from Staff & roles.'];
        }

        $sent = static::email($invite);

        return [$record, $record->name.' is on the staff directory. '.$sent];
    }

    /** The same card amends a file later: the fields are written over the old ones, and a new photo replaces the face on record. */
    public static function amend(StaffRecord $record, Request $request, User $actor): string
    {
        $data = static::validate($request, false);

        $record->fill(collect($data)->only(self::FIELDS)->all());

        if ($file = $request->file('photo')) {
            $record->keepPhoto($file);
        }

        $record->save();

        Audit::record($record, 'updated', $actor->name.' amended the staff file of '.$record->name.'.');

        return $record->name.'’s file is up to date.';
    }

    public static function validate(Request $request, bool $needPhoto = true): array
    {
        // A trainer is onboarded with the field they teach, exactly as the invitation asks for it.
        $training = in_array('trainer', (array) $request->input('roles', []), true);

        return $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:160',
            'phone' => 'required|string|max:30',
            'job_title' => 'nullable|string|max:120',
            'started_on' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0|max:99999999',
            'id_number' => 'nullable|string|max:40',
            'kra_pin' => 'nullable|string|max:40',
            'nssf_number' => 'nullable|string|max:40',
            'nhif_number' => 'nullable|string|max:40',
            'bank_name' => 'nullable|string|max:90',
            'bank_branch' => 'nullable|string|max:90',
            'bank_account' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:180',
            'next_of_kin_name' => 'required|string|max:150',
            'next_of_kin_phone' => 'required|string|max:30',
            'next_of_kin_relation' => 'required|string|max:60',
            'next_of_kin_2_name' => 'nullable|required_with:next_of_kin_2_phone,next_of_kin_2_relation|string|max:150',
            'next_of_kin_2_phone' => 'nullable|required_with:next_of_kin_2_name,next_of_kin_2_relation|string|max:30',
            'next_of_kin_2_relation' => 'nullable|required_with:next_of_kin_2_name,next_of_kin_2_phone|string|max:60',
            'photo' => ($needPhoto ? 'required|' : 'nullable|').'image|mimes:jpg,jpeg,png|max:2048',
            'roles' => 'nullable|array',
            'roles.*' => 'in:'.implode(',', array_keys(Roles::all())),
            'departments' => 'nullable|array',
            'departments.*' => 'in:'.implode(',', array_column(Departments::live(), 'slug')),
            'trainer_training_id' => ($training ? 'required' : 'nullable').'|integer|exists:trainings,id',
        ], [
            'name.required' => 'Give the name the office will call.',
            'email.required' => 'An email address is where the activation link goes.',
            'phone.required' => 'A phone number is needed to reach this person.',
            'photo.required' => 'Attach the profile photo — a clear face picture in a JPG or PNG.',
            'photo.image' => 'The profile photo must be a JPG or PNG picture, not a document scan.',
            'next_of_kin_name.required' => 'The C14 needs a first next of kin — write their name.',
            'next_of_kin_phone.required' => 'The C14 needs a contact number for the first next of kin.',
            'next_of_kin_relation.required' => 'Say how the first next of kin is related to this person.',
            'trainer_training_id.required' => 'Tick the field of training for this trainer.',
        ], [
            'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'job_title' => 'Job title',
            'started_on' => 'First day', 'salary' => 'Monthly pay', 'id_number' => 'ID number',
            'kra_pin' => 'KRA PIN', 'nssf_number' => 'NSSF number', 'nhif_number' => 'NHIF number',
            'bank_name' => 'Bank', 'bank_branch' => 'Branch', 'bank_account' => 'Account number',
            'address' => 'Address',
            'next_of_kin_name' => 'first next of kin — name',
            'next_of_kin_phone' => 'first next of kin — contact',
            'next_of_kin_relation' => 'first next of kin — relationship',
            'next_of_kin_2_name' => 'second next of kin — name',
            'next_of_kin_2_phone' => 'second next of kin — contact',
            'next_of_kin_2_relation' => 'second next of kin — relationship',
            'photo' => 'the photo',
            'trainer_training_id' => 'Field of training',
        ]);
    }

    /** @return array{0: array<int,string>, 1: array<int,string>} */
    private static function grant(array $roleSlugs, array $departments): array
    {
        $live = array_column(Departments::live(), 'slug');
        $known = array_values(array_filter((array) $roleSlugs, fn ($slug) => isset(Roles::all()[$slug])));
        $granted = array_values(array_unique(array_merge(
            Roles::departmentsFor($known),
            array_intersect(array_filter((array) $departments), $live)
        )));

        return [$known, $granted];
    }

    /** The invitation that opens their login. An address already invited keeps the link that is out there. */
    private static function raiseInvite(StaffRecord $record, array $roles, array $departments, ?int $field, User $actor): ?StaffInvite
    {
        $pending = StaffInvite::where('status', 'pending')
            ->whereRaw('LOWER(email) = ?', [strtolower($record->email)])->first();

        if ($pending) {
            return null;
        }

        $invite = StaffInvite::create([
            'name' => $record->name,
            'email' => $record->email,
            'role' => 'admin',
            'roles' => StaffInvite::list($roles),
            'trainer_training_id' => in_array('trainer', $roles, true) ? $field : null,
            'department' => StaffInvite::list($departments),
            'token' => Str::random(48),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(14),
        ]);

        Audit::auth('created', 'Onboarding raised the staff invitation for '.$invite->email.'.', $actor);

        return $invite;
    }

    /** The link stays on the screen whatever the mail server answers, so a failed email never loses the invite. */
    private static function email(StaffInvite $invite): string
    {
        try {
            Mail::to($invite->email)->send(new StaffInviteEmail($invite));
        } catch (Throwable $e) {
            return 'Its activation link did not post by email ('.$e->getMessage().') — copy it from Staff & roles.';
        }

        return 'The activation link is on its way to '.$invite->email.'.';
    }
}
