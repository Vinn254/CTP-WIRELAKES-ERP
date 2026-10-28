<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One line on the e-waste register: what was found, where, how much, in what condition and who put it
 * in. Most of what the programme collects is electronics, so the condition decides whether it goes to repair,
 * refurbishment, recycling or safe disposal.
 */
class EWasteCollection extends Model
{
    /** The name does not pluralise the way Laravel guesses, so the table is stated. */
    protected $table = 'ewaste_collections';
    /** The items the programme deals with — . The office extends this list when a new kind arrives. */
    public const ITEM_TYPES = [
        'computer' => 'Computers',
        'laptop' => 'Laptops',
        'phone' => 'Phones and tablets',
        'printer' => 'Printers',
        'tv' => 'Televisions',
        'monitor' => 'Monitors',
        'router' => 'Routers and networking gear',
        'battery' => 'Batteries and inverters',
        'cable' => 'Cables and leads',
        'component' => 'Electronic components and boards',
        'solar' => 'Solar equipment',
        'electrical' => 'Electrical equipment',
        'other' => 'Other',
    ];

    public const CONDITIONS = [
        'working' => 'Working — can be reused as it is',
        'mixed' => 'Mixed — some of it still works',
        'broken' => 'Broken — needs repair or parts recovery',
        'hazardous' => 'Hazardous — certified disposal only',
    ];

    /** Field to store: registered → taken to a collection point → weighed in by the office. */
    public const STATUSES = [
        'submitted' => 'Registered in the field',
        'forwarded' => 'Forwarded to the office',
        'received' => 'Weighed in at the collection point',
    ];

    protected $fillable = [
        'reference', 'collected_on', 'community_id', 'county', 'ward', 'ctp_id', 'fundi_id',
        'item_type', 'quantity', 'estimated_weight_kg', 'weighed_weight_kg', 'condition', 'status', 'note',
        'photo_path', 'photo_name', 'photo_size',
        'forwarded_by_fundi', 'forwarded_on', 'forward_note', 'received_by_user', 'received_on',
    ];

    protected $casts = [
        'collected_on' => 'date',
        'forwarded_on' => 'datetime',
        'received_on' => 'datetime',
        'estimated_weight_kg' => 'decimal:2',
        'weighed_weight_kg' => 'decimal:2',
    ];

    /** What the field and the office both fill in — one validator so neither side drifts. */
    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'item_type' => 'required|in:'.implode(',', array_keys(self::ITEM_TYPES)),
            'quantity' => 'required|integer|min:1|max:100000',
            'collected_on' => 'required|date|before_or_equal:today',
            'condition' => 'required|in:'.implode(',', array_keys(self::CONDITIONS)),
            'county' => 'required|string|max:100',
            'ward' => 'nullable|string|max:120',
            'community_id' => 'nullable|integer|exists:communities,id',
            'estimated_weight_kg' => 'nullable|numeric|min:0|max:1000000',
            'note' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:6144',
        ], [
            'collected_on.before_or_equal' => 'You register what you have already picked up, so the date cannot be in the future.',
            'photo.image' => 'A photo of the items — jpg or png, up to 6 MB. It is what the collection point sorts from.',
        ], [
            'item_type' => 'What it is',
            'quantity' => 'How many',
            'collected_on' => 'Date collected',
            'estimated_weight_kg' => 'Estimated weight',
        ]);
    }

    /** The picture that goes with a line on the register. Kept private, opened from the record. */
    public function storePhoto(Request $request): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $file = $request->file('photo');

        $this->update([
            'photo_path' => $file->store('ewaste/'.$this->reference, 'local'),
            'photo_name' => $file->getClientOriginalName(),
            'photo_size' => $file->getSize(),
        ]);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function ctp(): BelongsTo
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_id');
    }

    public function fundi(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(FundiRegistration::class, 'forwarded_by_fundi');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('collected_on')->orderByDesc('id');
    }

    /**
     * The lines one E-Fundi is allowed to carry: still unclaimed, registered by somebody else, in their county.
     * The list on their screen and the forward that closes a line both read from here, so the two can never drift.
     */
    public function scopeWaitingFor($query, FundiRegistration $fundi)
    {
        return $query->where('status', 'submitted')
            ->whereNull('fundi_id')
            ->where(fn ($q) => $q->where('county', $fundi->county)->orWhereNull('county'))
            ->where(fn ($q) => $q->where('ctp_id', '!=', $fundi->id)->orWhereNull('ctp_id'));
    }

    public function itemTypeLabel(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? Str::headline($this->item_type);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? Str::headline($this->condition);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline($this->status);
    }

    /** Whichever member put it in — a CTP in the field or an E-Fundi. */
    public function collectorLabel(): string
    {
        if ($this->ctp) {
            return $this->ctp->full_name.' ('.($this->ctp->ctp_code ?: 'CTP').')';
        }

        return $this->fundi ? $this->fundi->full_name.' ('.($this->fundi->ef_code ?: 'E-Fundi').')' : 'The programme office';
    }

    public function placeLabel(): string
    {
        return collect([$this->ward, $this->county, $this->community?->name])->filter()->join(' · ') ?: 'No place given';
    }

    public function weightLabel(): string
    {
        if ($this->weighed_weight_kg !== null) {
            return $this->weighed_weight_kg.' kg weighed in';
        }

        return $this->estimated_weight_kg !== null ? 'about '.$this->estimated_weight_kg.' kg (estimated)' : 'weight not recorded';
    }

    /** EW-2026-00125 — the sequence restarts each year, the way the collection points number their bags. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'EW-'.$year.'-';

        $highest = self::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
