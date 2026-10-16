<?php

namespace App\Models;

use App\Support\Construction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Module 8 §11.2 — labour on the site: who turned up, under whose contract, for how many hours. A day's attendance
 * is a line, so the cost of labour on a project is counted from the days actually worked.
 */
class LabourEntry extends Model
{
    protected $fillable = [
        'project_id', 'work_order_id', 'worked_on', 'worker_name', 'fundi_id', 'sub_contractor',
        'trade', 'hours', 'rate_ksh', 'note', 'recorded_by_user',
    ];

    protected $casts = ['worked_on' => 'date', 'hours' => 'decimal:2', 'rate_ksh' => 'decimal:2'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withDefault();
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id')->withDefault();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user')->withDefault();
    }

    public function costKsh(): float
    {
        return round((float) $this->hours * (float) $this->rate_ksh, 2);
    }

    public function tradeLabel(): string
    {
        return Construction::SPECIALTIES[$this->trade] ?? (string) $this->trade;
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'worked_on' => 'required|date',
            'worker_name' => 'required|string|max:160',
            'fundi_id' => 'nullable|integer|exists:fundi_registrations,id',
            'sub_contractor' => 'nullable|string|max:160',
            'trade' => 'nullable|in:'.implode(',', array_keys(Construction::SPECIALTIES)),
            'hours' => 'required|numeric|min:0.25|max:24',
            'rate_ksh' => 'nullable|numeric|min:0|max:1000000',
            'note' => 'nullable|string|max:500',
        ], [
            'worker_name.required' => 'Name the person who worked. A day counted without a name cannot be paid or proved (§11.2).',
            'hours.required' => 'How many hours did they put in that day?',
        ], [
            'worked_on' => 'Date worked',
            'worker_name' => 'Worker',
            'fundi_id' => 'E-Fundi on the register',
            'sub_contractor' => 'Sub-contractor',
            'trade' => 'Trade',
            'hours' => 'Hours',
            'rate_ksh' => 'Hourly rate (KSh)',
            'note' => 'Note',
        ]);
    }
}
