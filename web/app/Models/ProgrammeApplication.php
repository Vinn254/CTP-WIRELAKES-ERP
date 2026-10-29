<?php

namespace App\Models;

use App\Support\ProgrammeAreas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a public area page takes in: someone asking the programme to do a piece of work for them. It has no account
 * behind it, so the contact details are the record — the office answers on the number or email given here. Someone
 * who wants to join registers instead, and someone who wants a job applies on Careers.
 */
class ProgrammeApplication extends Model
{
    public const STATUSES = [
        'received' => 'Received',
        'reviewing' => 'Under review',
        'contacted' => 'Contacted back',
        'taken' => 'Taken up',
        'closed' => 'Not taken forward',
    ];

    /** How long the office promises to answer, said out loud on the page and in the acknowledgement mail. */
    public const TURNAROUND = '72 hours';

    protected $fillable = [
        'reference', 'area', 'category', 'name', 'email', 'phone', 'whatsapp', 'town',
        'details', 'status', 'review_note', 'contacted_on', 'reviewed_by_user',
    ];

    protected $casts = ['contacted_on' => 'date'];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user');
    }

    public function areaLabel(): string
    {
        return ProgrammeAreas::name($this->area);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** The number the person quotes back when they ring. */
    public static function nextReference(): string
    {
        $prefix = 'APP-'.now()->year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** What a visitor is allowed to type into the public form. The service must be one the area actually offers. */
    public static function dataFrom(Request $request, string $area): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'town' => 'nullable|string|max:90',
            'category' => ['nullable', 'string', 'max:90', \Illuminate\Validation\Rule::in(ProgrammeAreas::allServices())],
            'details' => 'required|string|max:2000',
        ], [
            'name.required' => 'Give the name the office should call.',
            'email.required' => 'An email address is how the office answers you.',
            'phone.required' => 'A phone number is needed to reach you back.',
            'details.required' => 'Say in a sentence or two what you need doing.',
            'category.in' => 'Choose a service from the list, or leave it and explain below.',
        ], [
            'whatsapp' => 'WhatsApp number',
            'town' => 'town',
            'category' => 'service',
            'details' => 'what you need',
        ]);
    }

    /** The best number to ring: WhatsApp when the person gave one, otherwise the phone they registered with. */
    public function callNumber(): string
    {
        return $this->whatsapp ?: $this->phone;
    }

    /** The person's own note, cut down to one line for a list. */
    public function snippet(int $words = 18): string
    {
        return Str::words(trim((string) $this->details), $words, '…');
    }
}
