<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A demand for money the office raises — on a donor, a CTP, an E-Fundi or anyone else. The invoice itself is never
 * a movement of money: what settles it is a payment line, and each payment posts its own income line on the money
 * register (§14.3), so the register stays the one place money is counted.
 */
class Invoice extends Model
{
    public const BILLED_TO = [
        'donor' => 'A donor or partner',
        'ctp' => 'A CTP',
        'fundi' => 'An E-Fundi',
        'other' => 'Another party',
    ];

    public const STATUSES = [
        'open' => 'Not paid',
        'settled' => 'Settled',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'reference', 'billed_to', 'ctp_id', 'fundi_id', 'party_name', 'party_email', 'party_phone', 'title',
        'details', 'amount_ksh', 'issued_on', 'due_on', 'status', 'project_id', 'donation_id', 'note',
        'emailed_at', 'raised_by_user', 'cancelled_by_user', 'cancel_reason',
    ];

    protected $casts = [
        'amount_ksh' => 'decimal:2',
        'issued_on' => 'date',
        'due_on' => 'date',
        'emailed_at' => 'datetime',
    ];

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('happened_on');
    }

    public function ctp()
    {
        return $this->belongsTo(CtpRegistration::class, 'ctp_id');
    }

    public function fundi()
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function donation()
    {
        return $this->belongsTo(Donation::class);
    }

    public function raiser()
    {
        return $this->belongsTo(User::class, 'raised_by_user');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user');
    }

    /** What the office types in when it raises an invoice. */
    public static function dataFrom(Request $request): array
    {
        $data = $request->validate([
            'billed_to' => 'required|in:'.implode(',', array_keys(self::BILLED_TO)),
            'ctp_id' => 'nullable|integer|exists:ctp_registrations,id',
            'fundi_id' => 'nullable|integer|exists:fundi_registrations,id',
            'party_name' => 'nullable|required_without_all:ctp_id,fundi_id|string|max:150',
            'party_email' => 'nullable|email|max:150',
            'party_phone' => 'nullable|string|max:30',
            'title' => 'required|string|max:190',
            'details' => 'nullable|string|max:1000',
            'amount_ksh' => 'required|numeric|min:0.01|max:1000000000',
            'issued_on' => 'required|date',
            'due_on' => 'nullable|date',
            'project_id' => 'nullable|integer|exists:projects,id',
            'donation_id' => 'nullable|integer|exists:donations,id',
            'note' => 'nullable|string|max:1000',
        ], [
            'party_name.required' => 'Say who the invoice is billed to — it carries their name on the document.',
            'amount_ksh.required' => 'An invoice needs the amount being asked for.',
        ], [
            'billed_to' => 'Billed to',
            'party_name' => 'Billed to',
            'amount_ksh' => 'Amount',
            'issued_on' => 'Issued on',
            'due_on' => 'Due by',
        ]);

        // A member invoice is always tied to the member record, so the balance can be found from either side.
        if ($data['billed_to'] !== 'ctp') {
            $data['ctp_id'] = null;
        }
        if ($data['billed_to'] !== 'fundi') {
            $data['fundi_id'] = null;
        }
        if ($data['billed_to'] !== 'donor') {
            $data['donation_id'] = null;
        }

        // The office rarely retypes a member's name, so the record supplies it — but the document always carries one.
        $data['party_name'] = ($data['party_name'] ?? null)
            ?: ($data['ctp_id'] ? CtpRegistration::whereKey($data['ctp_id'])->value('full_name') : null)
            ?: ($data['fundi_id'] ? FundiRegistration::whereKey($data['fundi_id'])->value('full_name') : null);

        return $data;
    }

    public static function nextReference(?string $on = null): string
    {
        $year = (int) date('Y', strtotime($on ?? now()->toDateString()));
        $prefix = 'INV-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 5, '0', STR_PAD_LEFT);
    }

    /** What has actually been paid on this invoice — voided payments do not count. */
    public function paidKsh(): float
    {
        return (float) $this->payments()->whereNull('voided_at')->sum('amount_ksh');
    }

    public function balanceKsh(): float
    {
        return round((float) $this->amount_ksh - $this->paidKsh(), 2);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_on && $this->due_on->lt(now()->startOfDay());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function billedToLabel(): string
    {
        return self::BILLED_TO[$this->billed_to] ?? $this->billed_to;
    }

    public function amountLabel(): string
    {
        return Finance::ksh((float) $this->amount_ksh);
    }

    public function paidLabel(): string
    {
        return Finance::ksh($this->paidKsh());
    }

    public function balanceLabel(): string
    {
        return Finance::ksh($this->balanceKsh());
    }

    /** Where the invoice goes: the address typed on it, or the member's own if none was typed. */
    public function email(): ?string
    {
        return $this->party_email ?: ($this->ctp?->email ?: $this->fundi?->email);
    }

    public function memberName(): string
    {
        return $this->ctp?->full_name ?: $this->fundi?->full_name ?: $this->party_name;
    }

    /**
     * The invoices raised on one member — the only invoices a portal may ever show, and the reason the balance
     * screen exists for a member at all. A CTP and an E-Fundi are separate records, so the match is on both sides.
     */
    public static function forMember(Model $member): \Illuminate\Database\Eloquent\Builder
    {
        return $member instanceof CtpRegistration
            ? static::where('billed_to', 'ctp')->where('ctp_id', $member->id)
            : static::where('billed_to', 'fundi')->where('fundi_id', $member->id);
    }
}
