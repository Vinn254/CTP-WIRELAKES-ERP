<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One of the trainings the programme itself runs (§7.4). A cohort is an intake of one of these and a trainer
 * is licensed against these — staff never type a course name. A training the office adds here appears in
 * every "post a training" and "what can you teach" list straight away.
 */
class Training extends Model
{
    public const STATUSES = [
        'active' => 'On offer',
        'inactive' => 'Not on offer right now',
    ];

    protected $fillable = ['name', 'code', 'summary', 'status'];

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'trainer_training');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->orderBy('name');
    }

    public static function codeFromName(string $name): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', trim($name)));
    }

    /** The rules are shared by the office screen and anything else that files a training. */
    public static function dataFrom(Request $request, ?self $training = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:trainings,name'.($training ? ','.$training->id : '')],
            'code' => ['nullable', 'string', 'max:30', 'alpha_num',
                'unique:trainings,code'.($training ? ','.$training->id : '')],
            'summary' => 'nullable|string|max:500',
            'status' => 'required|in:'.implode(',', array_keys(self::STATUSES)),
        ], [], ['name' => 'Training', 'code' => 'Short code']);
    }

    public function label(): string
    {
        return $this->name;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }
}
