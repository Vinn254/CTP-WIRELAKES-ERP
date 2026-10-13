<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Module 3 — Community Management. The unit the programme measures impact at:
 * a place, the CTP who looks after it, what it needs and who has benefited there.
 */
class Community extends Model
{
    public const STATUSES = [
        'pending_review' => 'Pending review',
        'active' => 'Confirmed',
        'rejected' => 'Rejected',
    ];

    /** An area is mobilised by every CTP on its roster; one of them carries the record. */
    public const ROLES = [
        'lead' => 'Lead CTP',
        'mobilizer' => 'Mobilizer',
    ];

    protected $fillable = [
        'community_code', 'name', 'county', 'sub_county', 'ward', 'village',
        'gps_lat', 'gps_lng', 'population', 'households', 'summary',
        'status', 'rejection_reason', 'created_by_ctp', 'created_by_user',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'population' => 'integer',
        'households' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function ctps(): BelongsToMany
    {
        return $this->belongsToMany(CtpRegistration::class, 'community_ctp', 'community_id', 'ctp_id')
            ->withPivot(['role', 'assigned_at'])->withTimestamps();
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class)->latest('id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class)->latest('benefited_on');
    }

    /** Training run here — Module 4 books a cohort against this place (§7.3). */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'created_by_ctp');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function nextCode(): string
    {
        $highest = self::where('community_code', 'like', 'COM-%')
            ->selectRaw('MAX(CAST(SUBSTRING(community_code, 5) AS UNSIGNED)) n')
            ->value('n');

        return 'COM-'.str_pad((string) ((int) $highest + 1), 4, '0', STR_PAD_LEFT);
    }

    /** The communities a CTP is on the roster for — confirmed ones plus their own proposals. */
    public static function forCtp(CtpRegistration $ctp)
    {
        return self::where('status', '!=', 'rejected')
            ->where(fn ($q) => $q
                ->whereHas('ctps', fn ($c) => $c->where('ctp_id', $ctp->id))
                ->orWhere('created_by_ctp', $ctp->id));
    }

    /** Ward + village names repeat across the country, so a duplicate is the whole location path. */
    public static function duplicateOf(string $county, string $subCounty, string $ward, ?string $village, string $name, ?int $ignoreId = null): bool
    {
        return self::where('status', '!=', 'rejected')
            ->whereRaw('LOWER(county) = ?', [mb_strtolower($county)])
            ->whereRaw('LOWER(sub_county) = ?', [mb_strtolower($subCounty)])
            ->whereRaw('LOWER(ward) = ?', [mb_strtolower($ward)])
            ->whereRaw('COALESCE(LOWER(village), "") = ?', [mb_strtolower((string) $village)])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * The place fields every community form asks for — the staff register and the CTP portal
     * validate through here so a proposal cannot pass a check a staff record would fail.
     */
    public static function placeData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'county' => 'required|string|max:100',
            'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100',
            'village' => 'nullable|string|max:100',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'population' => 'nullable|integer|min:1|max:5000000',
            'households' => 'nullable|integer|min:1|max:2000000',
            'summary' => 'nullable|string|max:4000',
        ], [], [
            'gps_lat' => 'GPS latitude', 'gps_lng' => 'GPS longitude',
            'sub_county' => 'Sub-county', 'population' => 'Population',
        ]);

        abort_if(self::duplicateOf(
            $data['county'], $data['sub_county'], $data['ward'], $data['village'] ?? null, $data['name'], $ignoreId
        ), 422, 'That community is already on the register — search the ward before adding it again.');

        return $data;
    }

    public function lead(): ?CtpRegistration
    {
        return $this->ctps->firstWhere('pivot.role', 'lead')
            ?? $this->ctps()->wherePivot('role', 'lead')->first();
    }

    /**
     * People a CTP on this roster brought into the programme from this exact area —
     * new trainees and registered fundis. This is what mobilisation credit is measured on.
     */
    public function peopleBrought(): \Illuminate\Support\Collection
    {
        $roster = $this->ctps()->pluck('ctp_id');
        if ($roster->isEmpty()) {
            return collect();
        }

        $inArea = fn ($q) => $q->whereRaw('LOWER(county) = ?', [mb_strtolower($this->county)])
            ->whereRaw('LOWER(sub_county) = ?', [mb_strtolower($this->sub_county)])
            ->whereRaw('LOWER(ward) = ?', [mb_strtolower($this->ward)]);

        $trainees = CtpRegistration::whereIn('referred_by', $roster)->where($inArea(...))->get()
            ->map(fn (CtpRegistration $c) => CtpRegistration::asBrought($c));

        $fundis = FundiRegistration::whereIn('mobilised_by_ctp', $roster)->where($inArea(...))->get()
            ->map(fn (FundiRegistration $f) => CtpRegistration::asBrought($f));

        return $trainees->concat($fundis)->sortByDesc('on')->values();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    public function locationLabel(): string
    {
        return collect([$this->ward, $this->sub_county, $this->county])->filter()->join(' · ');
    }

    public function shortLocation(): string
    {
        return collect([$this->village, $this->ward, $this->county])->filter()->join(', ');
    }

    public function hasPin(): bool
    {
        return (bool) $this->gps_lat && (bool) $this->gps_lng;
    }

    /** Needs that still have to be worked: the CTP flagged them, staff have not closed them. */
    public function openNeeds(): int
    {
        return $this->needs()->whereIn('status', ['proposed', 'prioritised'])->count();
    }

    public function profileGaps(): array
    {
        $gaps = [];
        if (! $this->hasPin()) {
            $gaps[] = 'GPS pin of the community';
        }
        if (! $this->population) {
            $gaps[] = 'Population';
        }
        if (! $this->households) {
            $gaps[] = 'Households';
        }
        if (! $this->lead()) {
            $gaps[] = 'A CTP assigned to look after it';
        }

        return $gaps;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim((string) $this->name)))->filter()
            ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    }
}
