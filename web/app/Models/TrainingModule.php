<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * One unit under a training. The office adds as many as the trade needs — a training with no modules still
 * runs — and every module says what the trainee must be able to do, which is what the trainer delivers and
 * what has to be marked before a certificate is put forward.
 */
class TrainingModule extends Model
{
    /** Which term the unit is taught in. General covers every term, so an intake at any point of the year runs it. */
    public const TERMS = [
        'term_1' => 'Term 1',
        'term_2' => 'Term 2',
        'term_3' => 'Term 3',
        'general' => 'Every term',
    ];

    protected $fillable = ['training_id', 'title', 'outcome', 'hours', 'term', 'position'];

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
            'term' => ['nullable', Rule::in(array_keys(self::TERMS))],
            'position' => 'nullable|integer|min:0|max:999',
        ], [], ['title' => 'Module', 'outcome' => 'What the trainee must be able to do', 'hours' => 'Hours',
            'term' => 'Term']);
    }

    public static function termChoices(): array
    {
        return self::TERMS;
    }

    /** The term as the office reads it. A unit with no term recorded is one the programme runs all year. */
    public function termLabel(): string
    {
        return self::TERMS[$this->term] ?? self::TERMS['general'];
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
            'modules.*.term' => 'nullable|in:'.implode(',', array_keys(self::TERMS)),
        ], [
            'modules.*.title' => 'A module name is too long — 150 characters at most.',
            'modules.*.outcome' => 'One of the “what the trainee must be able to do” lines is too long — 500 characters at most.',
            'modules.*.hours' => 'Hours are whole numbers between 0 and 2000.',
            'modules.*.term' => 'A module term must be one of the terms the programme runs.',
        ]);

        return collect($data['modules'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->sortBy(fn ($row, $index) => $index)
            ->map(fn (array $row) => [
                'title' => trim($row['title']),
                'outcome' => trim((string) ($row['outcome'] ?? '')) ?: null,
                'hours' => (int) ($row['hours'] ?? 0),
                'term' => isset(self::TERMS[$row['term'] ?? null]) ? $row['term'] : 'general',
            ])
            ->values();
    }

    public function hoursLabel(): string
    {
        return $this->hours > 0 ? $this->hours.' hours' : 'no set hours';
    }
}
