<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One person's answer to a careers posting. A visitor applies without an account, so the contact details live here
 * and nowhere else; the office moves the application through review and records when the person was contacted back.
 */
class JobApplication extends Model
{
    public const STATUSES = [
        'received' => 'Received',
        'reviewing' => 'Under review',
        'shortlisted' => 'Shortlisted',
        'contacted' => 'Contacted back',
        'declined' => 'Not taken forward',
    ];

    protected $fillable = [
        'job_opening_id', 'name', 'email', 'phone', 'id_number', 'town', 'experience',
        'cv_path', 'cv_name', 'cv_size', 'cover_note', 'status', 'review_note', 'contacted_on', 'reviewed_by_user',
    ];

    protected $casts = ['contacted_on' => 'date'];

    public function opening()
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user');
    }

    /** What a visitor is allowed to type into the public form. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:30',
            'id_number' => 'nullable|string|max:40',
            'town' => 'nullable|string|max:90',
            'experience' => 'nullable|string|max:90',
            'cover_note' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Give the name the office should call.',
            'email.required' => 'An email address is how the office answers you.',
            'phone.required' => 'A phone number is needed to reach you back.',
        ]);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function hasCv(): bool
    {
        return $this->cv_path !== null;
    }
}
