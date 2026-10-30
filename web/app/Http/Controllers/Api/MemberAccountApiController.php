<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Finance;
use App\Support\Leave;
use App\Support\MemberPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two money-and-time screens a member holds: what the office has billed them, and the leave an E-Fundi books.
 * Both read the exact queries the portal pages run — the same Invoice::forMember, the same seven balances counted
 * off the leave rows, and the same Leave::record() writer — so a phone never sees an invoice it was not billed for
 * or books a week the website would refuse.
 */
class MemberAccountApiController extends Controller
{
    /** GET /api/me/invoices — only a member the office has actually invoiced reaches this. */
    public function invoices(): JsonResponse
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        $invoices = Invoice::forMember($member)->with('payments')
            ->orderByRaw("case status when 'open' then 0 when 'settled' then 1 else 2 end")
            ->orderByRaw('due_on is null, due_on')->orderByDesc('id')->get();

        abort_if($invoices->isEmpty(), 404, 'The office has not invoiced you.');

        return response()->json([
            'paybill' => Finance::paybill(),
            'totals' => [
                'billed' => Finance::ksh((float) $invoices->where('status', '!=', 'cancelled')->sum('amount_ksh')),
                'paid' => Finance::ksh($invoices->sum(fn (Invoice $i) => $i->status === 'cancelled' ? 0 : $i->paidKsh())),
                'owing' => Finance::ksh($invoices->sum(fn (Invoice $i) => $i->status === 'cancelled' ? 0 : $i->balanceKsh())),
                'open' => $invoices->where('status', 'open')->count(),
            ],
            'invoices' => $invoices->map(fn (Invoice $i) => $this->invoice($i))->values(),
        ]);
    }

    /** GET /api/me/leave — the seven balances and everything this account has asked for. */
    public function leave(): JsonResponse
    {
        $worker = $this->worker();

        return response()->json([
            'balances' => collect(Leave::balances($worker))->map(fn (array $b) => $b + [
                'note' => Leave::TYPES[$b['type']]['note'] ?? '',
                'entitled_label' => Leave::format($b['entitled']),
                'left_label' => Leave::format($b['left']),
            ])->values(),
            'types' => collect(Leave::TYPES)->map(fn (array $t) => ['name' => $t['name'], 'note' => $t['note']]),
            'history' => LeaveRequest::forUser($worker->id)
                ->map(fn (LeaveRequest $l) => $this->leaveRow($l))->values(),
            'joined' => Leave::joinedOn($worker)->format('d M Y'),
            'months' => Leave::monthsWorked($worker, now()->year),
        ]);
    }

    /** POST /api/me/leave — the same writer the portal page uses, days counted from the dates. */
    public function applyLeave(Request $request): JsonResponse
    {
        $worker = $this->worker();
        $leave = Leave::record($worker, $request);

        return response()->json([
            'message' => Leave::name($leave->type).' for '.$leave->periodLabel().' ('
                .Leave::format($leave->days).' days) is with Human Resources.',
            'leave' => $this->leaveRow($leave->fresh()),
        ], 201);
    }

    /** POST /api/me/leave/{leave}/cancel */
    public function cancelLeave(LeaveRequest $leave): JsonResponse
    {
        $this->worker();
        abort_unless($leave->user_id === auth()->id(), 403, 'That is not your leave application.');
        abort_unless($leave->status === 'pending', 422,
            'Human Resources has already answered this one — ask the desk if the plan has changed.');

        $leave->forceFill(['status' => 'cancelled'])->save();

        return response()->json(['message' => 'Application withdrawn.']);
    }

    // ---- guards and serialisers ------------------------------------------

    /** Only an E-Fundi holds a post; a CTP trainee has no balance to book. */
    private function worker(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'fundi', 403,
            'Leave is booked by the people who work in the programme.');

        return $user;
    }

    private function invoice(Invoice $i): array
    {
        $cancelled = $i->status === 'cancelled';

        return [
            'id' => $i->id,
            'reference' => $i->reference,
            'title' => $i->title,
            'details' => $i->details ?: $i->title,
            'issued_on' => $i->issued_on?->format('d M Y'),
            'due_on' => $i->due_on?->format('d M Y') ?: 'on receipt',
            'overdue' => $i->status === 'open' && $i->isOverdue(),
            'status' => $i->status,
            'status_label' => $i->statusLabel(),
            'amount' => $i->amountLabel(),
            'paid' => $i->paidLabel(),
            'balance' => $cancelled ? '—' : $i->balanceLabel(),
            'note' => $i->note,
            'withdrawn_by' => $cancelled ? ($i->canceller?->name ?: 'the office') : null,
            'cancel_reason' => $cancelled ? $i->cancel_reason : null,
            'payments' => $i->payments->whereNull('voided_at')
                ->map(fn (InvoicePayment $p) => [
                    'on' => $p->happened_on->format('d M Y'),
                    'amount' => $p->amountLabel(),
                    'method' => $p->methodLabel(),
                    'reference' => $p->reference ?: '—',
                ])->values(),
        ];
    }

    private function leaveRow(LeaveRequest $l): array
    {
        return [
            'id' => $l->id,
            'type' => $l->type,
            'type_label' => $l->typeLabel(),
            'starts_on' => $l->starts_on->toDateString(),
            'ends_on' => $l->ends_on->toDateString(),
            'period' => $l->periodLabel(),
            'applied_on' => $l->created_at?->format('d M Y'),
            'decided_at' => $l->decided_at?->format('d M Y'),
            'days' => Leave::format((float) $l->days),
            'status' => $l->status,
            'status_label' => $l->statusLabel(),
            'cover_for' => $l->cover_for,
            'reason' => $l->reason,
            'decision_note' => $l->decision_note,
            'decided_by' => $l->decidedBy?->name,
            'can_cancel' => $l->status === 'pending',
        ];
    }
}
