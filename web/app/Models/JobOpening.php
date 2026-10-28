<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A careers posting the office opens to the public: a job it is hiring for, or project work that is not one of the
 * trainings on the catalogue. Only an open posting reaches /careers, and only the office can open or close one.
 */
class JobOpening extends Model
{
    public const KINDS = [
        'job' => 'Job opening',
        'project' => 'Project work',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Open for applications',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'reference', 'title', 'kind', 'area', 'county', 'positions', 'description', 'requirements',
        'closing_on', 'status', 'created_by_user', 'closed_by_user',
    ];

    protected $casts = ['closing_on' => 'date'];

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by_user');
    }

    /** What the office types in when it posts an opening. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:190',
            'kind' => 'required|in:'.implode(',', array_keys(self::KINDS)),
            'area' => 'nullable|string|max:90',
            'county' => 'nullable|string|max:90',
            'positions' => 'nullable|integer|min:1|max:999',
            'description' => 'required|string|max:1000',
            'requirements' => 'nullable|string|max:1000',
            'closing_on' => 'nullable|date',
        ], [
            'title.required' => 'Say what the opening is called — it is the headline on the public page.',
            'description.required' => 'Describe the work, or a person reading the page has nothing to apply to.',
        ], [
            'kind' => 'What kind of opening',
            'closing_on' => 'Applications close',
        ]);
    }

    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'JOB-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->closing_on || $this->closing_on->gte(now()->startOfDay()));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline($this->kind);
    }

    public function closingLabel(): string
    {
        if (! $this->closing_on) {
            return 'Open until the office closes it';
        }

        return 'Applications close '.$this->closing_on->format('d M Y');
    }
}
