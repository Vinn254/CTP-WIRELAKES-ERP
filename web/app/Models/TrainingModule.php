<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One unit under a training. The office adds as many as the trade needs — a training with no modules still
 * runs — and every module says what the trainee must be able to do, which is what the trainer delivers and
 * what has to be marked before a certificate is put forward.
 */
class TrainingModule extends Model
{
    protected $fillable = ['training_id', 'title', 'outcome', 'hours', 'position'];

    protected $casts = ['hours' => 'integer', 'position' => 'integer'];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /** The marks on trainees' records — a module that has been awarded cannot be pulled off the list. */
    public function completions(): HasMany
    {
        return $this->hasMany(ModuleCompletion::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** Shared by the office screen that adds and edits a module. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'outcome' => 'nullable|string|max:500',
            'hours' => 'nullable|integer|min:0|max:2000',
            'position' => 'nullable|integer|min:0|max:999',
        ], [], ['title' => 'Module', 'outcome' => 'What the trainee must be able to do', 'hours' => 'Hours']);
    }

    public static function nextPosition(int $trainingId): int
    {
        return (int) self::where('training_id', $trainingId)->max('position') + 1;
    }

    /** The repeater on the office screen: every module row they filled, in the order they typed them. */
    public static function rowsFrom(Request $request): Collection
    {
        $data = $request->validate([
            'modules' => 'array',
            'modules.*.title' => 'nullable|string|max:150',
            'modules.*.outcome' => 'nullable|string|max:500',
            'modules.*.hours' => 'nullable|integer|min:0|max:2000',
        ], [
            'modules.*.title' => 'A module name is too long — 150 characters at most.',
            'modules.*.outcome' => 'One of the “what the trainee must be able to do” lines is too long — 500 characters at most.',
            'modules.*.hours' => 'Hours are whole numbers between 0 and 2000.',
        ]);

        return collect($data['modules'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->sortBy(fn ($row, $index) => $index)
            ->map(fn (array $row) => [
                'title' => trim($row['title']),
                'outcome' => trim((string) ($row['outcome'] ?? '')) ?: null,
                'hours' => (int) ($row['hours'] ?? 0),
            ])
            ->values();
    }

    public function hoursLabel(): string
    {
        return $this->hours > 0 ? $this->hours.' hours' : 'no set hours';
    }
}
