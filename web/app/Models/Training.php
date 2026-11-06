<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One of the trainings the programme itself runs. A cohort is an intake of one of these and a trainer
 * is licensed against these — staff never type a course name. A training the office adds here appears in
 * every "post a training" and "what can you teach" list straight away.
 */
class Training extends Model
{
    public const STATUSES = [
        'active' => 'Listed — cohorts and trainers can be set against it',
        'inactive' => 'Not listed for new cohorts',
    ];

    protected $fillable = ['name', 'code', 'summary', 'status'];

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    /** What has to be got through under this training. The office may leave a training with none. */
    public function modules(): HasMany
    {
        return $this->hasMany(TrainingModule::class)->ordered();
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'trainer_training');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->orderBy('name');
    }

    /**
     * The fields a staff or E-Fundi screen offers: the trainings the programme lists, with any field a record
     * already holds added back so an existing trainer is never asked to choose again.
     *
     * @param  iterable<int>  $held  ids held by the records on this screen
     * @return Collection<int, self>
     */
    public static function fieldChoices(iterable $held = []): Collection
    {
        $heldIds = collect($held)->merge(
            User::whereIn('role', ['admin', 'superadmin'])->whereNotNull('trainer_training_id')->pluck('trainer_training_id')
        )->merge(
            StaffInvite::whereNotNull('trainer_training_id')->pluck('trainer_training_id')
        )->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return self::where(fn (Builder $query) => $query
            ->where('status', 'active')->orWhereIn('id', $heldIds->all()))
            ->orderBy('status')->orderBy('name')->get(['id', 'name', 'status']);
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
        ], ['name' => 'Training', 'code' => 'Short code']);
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
