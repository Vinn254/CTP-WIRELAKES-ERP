<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * Module 11 — a programme the office runs money for. The plan the budget was drawn on is built in; anything the
 * programme takes up later is added on the budget screen and joins every money dropdown at once. A programme
 * carries no money itself — an executive sets that on its budget line.
 */
class Programme extends Model
{
    protected $fillable = ['category', 'label', 'note', 'added_by_user'];

    /** @var array<string, string>|null */
    private static ?array $categories = null;

    /** Every programme money can be planned on: the plan the budget was drawn on, then the office's own. */
    public static function categories(): array
    {
        return static::$categories ??= BudgetLine::BUILT_IN
            + static::query()->orderBy('label')->pluck('label', 'category')->all();
    }

    public static function forgetCache(): void
    {
        static::$categories = null;
    }

    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:60',
            'note' => 'nullable|string|max:1000',
        ], [
            'label.required' => 'Name the programme — Finance will pick it on every money line.',
        ], ['label' => 'Programme']);

        $category = substr(Str::slug($data['label']), 0, 32);

        if (Str::length($category) < 3 || isset(static::categories()[$category])) {
            throw ValidationException::withMessages([
                'label' => 'That programme is already on the plan, or its name is too short to open a line.',
            ]);
        }

        return ['category' => $category, 'label' => $data['label'], 'note' => $data['note'] ?? null];
    }

    public function adder()
    {
        return $this->belongsTo(User::class, 'added_by_user')->withDefault();
    }
}
