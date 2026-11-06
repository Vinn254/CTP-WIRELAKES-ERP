<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

    protected $fillable = ['training_id', 'title', 'outcome', 'hours', 'theory_hours', 'practical_hours', 'term',
        'position', 'syllabus_path', 'syllabus_name', 'syllabus_uploaded_at'];

    protected $casts = ['hours' => 'integer', 'theory_hours' => 'integer', 'practical_hours' => 'integer',
        'position' => 'integer', 'syllabus_uploaded_at' => 'datetime'];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /** The marks on trainees' records — a module that has been awarded cannot be pulled off the list. */
    public function completions(): HasMany
    {
        return $this->hasMany(ModuleCompletion::class);
    }

    /** The intakes that teach this unit — where its trainers and its trainees come from. */
    public function cohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_module')->withPivot('trainer_id')->withTimestamps();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** Shared by the office screen that adds and edits a module. */
    public static function dataFrom(Request $request, ?int $keeps = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'outcome' => 'nullable|string|max:500',
            'theory_hours' => 'nullable|integer|min:0|max:2000',
            'practical_hours' => 'nullable|integer|min:0|max:2000',
            'term' => ['nullable', Rule::in(array_keys(self::TERMS))],
            'position' => 'nullable|integer|min:0|max:999',
        ], [
            'theory_hours.max' => 'Theory hours are whole numbers between 0 and 2000.',
            'practical_hours.max' => 'Practical hours are whole numbers between 0 and 2000.',
        ], ['title' => 'Module', 'outcome' => 'What the trainee must be able to do',
            'theory_hours' => 'Theory hours', 'practical_hours' => 'Practical hours', 'term' => 'Term']);

        // A unit is always taught in some term: left blank, it is the programme's own, taught every term.
        $term = $data['term'] ?? null;
        $data['term'] = isset(self::TERMS[$term]) ? $term : 'general';

        if (array_key_exists('outcome', $data)) {
            $data['outcome'] = trim((string) $data['outcome']) ?: null;
        }

        return self::hourSplit($data, $keeps);
    }

    /**
     * The theory/practical split, with the total kept in step. A unit that has only ever carried a total keeps it
     * until the Registrar fills one of the two boxes — then the two figures become the unit's hours.
     */
    public static function hourSplit(array $data, ?int $keeps = null): array
    {
        $theory = $data['theory_hours'] ?? null;
        $practical = $data['practical_hours'] ?? null;

        if ($theory === null && $practical === null) {
            unset($data['theory_hours'], $data['practical_hours']);

            return $keeps === null ? $data : [...$data, 'hours' => $keeps];
        }

        return [...$data, 'hours' => (int) ($theory ?? 0) + (int) ($practical ?? 0)];
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

    /**
     * The scheme of work the office files on this unit: the paper the course is actually taught from, week by week.
     * A new upload replaces the one on record — the file a trainee opens becomes the one saved now.
     */
    public function putSyllabus(UploadedFile $file): self
    {
        $this->takeSyllabusOff();

        $this->update([
            'syllabus_path' => $file->store('training/schemes', 'local'),
            'syllabus_name' => $file->getClientOriginalName(),
            'syllabus_uploaded_at' => now(),
        ]);

        return $this;
    }

    public function hasSyllabus(): bool
    {
        return filled($this->syllabus_path);
    }

    /** The paper off the disk, so a unit that goes loses its scheme with it. */
    public function takeSyllabusOff(): void
    {
        if ($this->syllabus_path) {
            Storage::disk('local')->delete($this->syllabus_path);
        }
    }

    /** How the scheme reads on a screen, with the day the office put it up. */
    public function syllabusLabel(): string
    {
        return 'Scheme of work'.($this->syllabus_uploaded_at
            ? ' — filed '.$this->syllabus_uploaded_at->format('d M Y') : '');
    }

    /** The repeater on the office screen: every module row they filled, in the order they typed them. */
    public static function rowsFrom(Request $request): Collection
    {
        $data = $request->validate([
            'modules' => 'array',
            'modules.*.title' => 'nullable|string|max:150',
            'modules.*.outcome' => 'nullable|string|max:500',
            'modules.*.theory_hours' => 'nullable|integer|min:0|max:2000',
            'modules.*.practical_hours' => 'nullable|integer|min:0|max:2000',
            'modules.*.term' => 'nullable|in:'.implode(',', array_keys(self::TERMS)),
        ], [
            'modules.*.title' => 'A module name is too long — 150 characters at most.',
            'modules.*.outcome' => 'One of the “what the trainee must be able to do” lines is too long — 500 characters at most.',
            'modules.*.theory_hours' => 'Theory hours are whole numbers between 0 and 2000.',
            'modules.*.practical_hours' => 'Practical hours are whole numbers between 0 and 2000.',
            'modules.*.term' => 'A module term must be one of the terms the programme runs.',
        ]);

        return collect($data['modules'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->sortBy(fn ($row, $index) => $index)
            ->map(fn (array $row) => self::hourSplit([
                'title' => trim($row['title']),
                'outcome' => trim((string) ($row['outcome'] ?? '')) ?: null,
                'theory_hours' => $row['theory_hours'] ?? null,
                'practical_hours' => $row['practical_hours'] ?? null,
                'term' => isset(self::TERMS[$row['term'] ?? null]) ? $row['term'] : 'general',
            ]))
            ->values();
    }

    /**
     * What the unit costs in teaching time. A unit that has been given the split reads as the split, because that is
     * what a timetable is built from; one that still only carries a total reads as the total.
     */
    public function hoursLabel(): string
    {
        if ($this->theory_hours !== null || $this->practical_hours !== null) {
            return ((int) $this->theory_hours).'h theory + '.((int) $this->practical_hours).'h practical';
        }

        return $this->hours > 0 ? $this->hours.' hours' : 'no set hours';
    }
}
