<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProjectApplication extends Model
{
    public const STATUSES = ['pending_review' => 'Pending review', 'approved' => 'Awarded', 'rejected' => 'Not selected'];

    protected $fillable = [
        'project_id', 'applicant_type', 'applicant_id', 'full_name', 'phone',
        'email', 'note', 'status', 'rejection_reason', 'reviewed_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The member record behind this application (CTP or E-Fundi), if still present. */
    public function memberRecord(): ?Model
    {
        return $this->applicant_type === 'fundi'
            ? FundiRegistration::find($this->applicant_id)
            : CtpRegistration::find($this->applicant_id);
    }

    public function levelLabel(): string
    {
        $member = $this->memberRecord();

        if (!$member) {
            return Str::headline(str_replace('_', ' ', $this->applicant_type));
        }

        if ($member instanceof FundiRegistration) {
            return FundiRegistration::tierLabel($member->tier);
        }

        return $member->fundi_tier
            ? $member->gradeLabel()
            : 'CTP · '.str_replace('_', ' ', $member->track ?? 'member');
    }

    /** Member ID staff issued — EF-0002 / CTP-0001 style. */
    public function memberCode(): string
    {
        $member = $this->memberRecord();

        if (!$member) {
            return '—';
        }

        return $member->ef_code ?? $member->ctp_code ?? $member->reference ?? '—';
    }

    /** What this member can actually do, as recorded on their application. */
    public function skillsLabel(): string
    {
        $member = $this->memberRecord();

        if ($member instanceof FundiRegistration) {
            return collect([
                implode(', ', (array) $member->specialisations),
                $member->years_in_business ? $member->years_in_business.' years in trade' : null,
            ])->filter()->implode(' · ');
        }

        if ($member instanceof CtpRegistration) {
            return collect([
                $member->track ? Str::headline(str_replace('_', ' ', $member->track)).' track' : null,
                $member->county,
            ])->filter()->implode(' · ');
        }

        return '';
    }
}
