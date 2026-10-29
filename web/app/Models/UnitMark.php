<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One CTP's mark for one kind on a unit sheet. Every mark is out of 100 — the same scale the track already reads,
 * so a mark needs no conversion before it feeds the percentage.
 */
class UnitMark extends Model
{
    protected $fillable = ['sheet_id', 'enrollment_id', 'kind', 'score', 'note'];

    protected $casts = ['score' => 'float'];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(UnitMarkSheet::class, 'sheet_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function kindLabel(): string
    {
        return UnitMarkSheet::KINDS[$this->kind] ?? Str::headline((string) $this->kind);
    }

    /**
     * The marks as the sheet posted them: marks[enrollmentId][kind] = score, blank cells left out rather than
     * written as zero — an empty cell means the mark was not taken, which is not the same as scoring nothing.
     *
     * @return array<int, array<string, float>>
     */
    public static function gridFrom(Request $request): array
    {
        $request->validate([
            'marks' => 'nullable|array',
            'marks.*.*' => 'nullable|numeric|min:0|max:'.UnitMarkSheet::OUT_OF,
        ], [
            'marks.*.*' => 'Every mark is a score out of :max.',
        ]);

        return collect($request->input('marks', []))
            ->map(fn ($row) => collect($row)->filter(fn ($value) => is_numeric($value) && $value !== '')
                ->map(fn ($value) => round((float) $value, 2))->all())
            ->filter(fn (array $row) => $row !== [])
            ->all();
    }
}
