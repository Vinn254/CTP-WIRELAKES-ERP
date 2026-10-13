<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A certificate put forward for a trainee and approved by an executive (§7.5 step 6, §7.7). The code is
 * issued once, on approval — a recommendation carries nothing a CTP can show anyone yet.
 */
class Certification extends Model
{
    public const STATUSES = [
        'recommended' => 'Recommended by the cohort',
        'approved' => 'Approved — certificate issued',
        'declined' => 'Declined',
        'revoked' => 'Revoked',
    ];

    protected $fillable = [
        'enrollment_id', 'certificate_code', 'theory_score', 'practical_score', 'attendance_pct',
        'status', 'reason', 'recommended_by', 'approved_by', 'approved_on',
    ];

    protected $casts = [
        'theory_score' => 'float',
        'practical_score' => 'float',
        'attendance_pct' => 'float',
        'approved_on' => 'date',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** Only an approved certificate counts as a qualification. */
    public function isLive(): bool
    {
        return $this->status === 'approved';
    }

    /** e.g. CTP-0001-CERT-ICT-01 — one sequence per CTP per skill family. */
    public static function nextCode(CtpRegistration $ctp, string $family): string
    {
        $stem = $ctp->ctp_code.'-CERT-'.Str::upper(str_replace([' ', '-', '/'], '', $family)).'-';
        $count = self::where('certificate_code', 'like', $stem.'%')->count();

        return $stem.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }
}
