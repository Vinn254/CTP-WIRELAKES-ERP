<?php

namespace App\Models;

use App\Support\Accounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * What Human Resources knows about a person who works in the organisation: who to call if anything happens to
 * them, the C14 on file, and what they are paid. It is kept beside the login account rather than inside it,
 * because a person is onboarded before their account is opened and can leave while it stays for the trail.
 */
class StaffRecord extends Model
{
    protected $fillable = [
        'job_application_id', 'name', 'email', 'phone', 'job_title', 'started_on', 'salary', 'id_number',
        'kra_pin', 'nssf_number', 'nhif_number', 'bank_name', 'bank_branch', 'bank_account', 'address',
        'next_of_kin_name', 'next_of_kin_relation', 'next_of_kin_phone',
        'next_of_kin_2_name', 'next_of_kin_2_relation', 'next_of_kin_2_phone', 'recorded_by_user',
    ];

    protected $casts = ['started_on' => 'date', 'salary' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by_user');
    }

    /**
     * Every staff account has a directory record. What the account already holds is fetched into it once, so a
     * person appears in the directory with their name and the day they joined filled in before HR types anything.
     *
     * The link is written outside fill() on purpose: a posted form must never be able to point a file at an
     * account it chooses.
     */
    public static function ensure(User $user): self
    {
        $record = static::where('user_id', $user->id)->first();

        if ($record) {
            return $record;
        }

        $record = new self([
            'name' => $user->name,
            'email' => $user->email,
            'started_on' => $user->created_at?->toDateString(),
            'job_title' => $user->isSuperAdmin() ? 'Super admin' : null,
        ]);

        $record->user_id = $user->id;
        $record->save();

        return $record;
    }

    /** An account opened for a record already on file — the directory row stops waiting and becomes the person. */
    public static function claim(User $user): self
    {
        $record = static::whereNull('user_id')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->latest('id')->first();

        if (! $record) {
            return static::ensure($user);
        }

        $record->forceFill(['user_id' => $user->id])->save();

        return $record;
    }

    public function hasC14(): bool
    {
        return $this->c14_path !== null && Storage::disk('local')->exists($this->c14_path);
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null && Storage::disk('local')->exists($this->photo_path);
    }

    /** The face on the file. A new picture replaces the one on record and its file, never a second copy. */
    public function keepPhoto(UploadedFile $file): void
    {
        if ($this->photo_path && Storage::disk('local')->exists($this->photo_path)) {
            Storage::disk('local')->delete($this->photo_path);
        }

        $this->photo_path = $file->store('staff/photos', 'local');
    }

    /** What is still missing for this to be a complete personnel file. */
    public function missing(): array
    {
        $needed = [
            'phone' => 'phone', 'id_number' => 'ID number', 'kra_pin' => 'KRA PIN',
            'next_of_kin_name' => 'next of kin', 'salary' => 'salary', 'started_on' => 'first day',
        ];

        $short = collect($needed)->filter(fn ($_, $field) => ! $this->{$field} && $this->{$field} !== 0)->values()->all();

        if (! $this->hasPhoto()) {
            $short[] = 'profile photo';
        }

        return $short;
    }

    public function fileLabel(): string
    {
        return $this->c14_name ?: 'C14';
    }
}
