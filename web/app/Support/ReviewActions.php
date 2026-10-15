<?php

namespace App\Support;

use App\Models\AccountInvite;
use App\Models\CtpRegistration;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The application decisions that more than one department repeats — Module 1 reviews every applicant,
 * Module 2 runs the CTP lifecycle. One implementation so the two screens cannot drift apart.
 */
class ReviewActions
{
    /** The fee form fields, validated the same way wherever the form is shown. */
    public static function feeFrom(Request $request): array
    {
        return $request->validate([
            'fee_status' => 'required|in:paid,waived',
            'fee_method' => 'nullable|required_if:fee_status,paid|in:mpesa,bank,cash,other',
            'fee_amount' => 'nullable|numeric|min:0|max:999999',
            'fee_reference' => 'nullable|string|max:60',
        ], [], ['fee_method' => 'payment method', 'fee_amount' => 'amount', 'fee_reference' => 'reference']);
    }

    /** Record a membership fee paid by M-Pesa bill, bank transfer or cash — or waive it. */
    public static function recordFee(CtpRegistration|FundiRegistration $member, array $data): string
    {
        $member->update([
            'fee_status' => $data['fee_status'],
            'fee_method' => $data['fee_status'] === 'paid' ? $data['fee_method'] : null,
            'fee_amount' => $data['fee_amount'] ?? $member::feeAmount(),
            'fee_reference' => $data['fee_reference'] ?? null,
            'fee_paid_at' => now(),
            // The application only reaches the review queue once the fee is settled.
            'status' => $member->status === 'pending_payment' ? 'pending_review' : $member->status,
        ]);

        if ($data['fee_status'] === 'paid') {
            static::postMembershipLine($member);
        }

        return $data['fee_status'] === 'paid'
            ? 'Fee of KSh '.number_format((float) $member->fee_amount).' recorded — the application is now in the review queue, and the money is on the ledger.'
            : 'Fee waived — the application is now in the review queue.';
    }

    /**
     * Money a member pays is money in (§14.3). One line per member, posted from the application itself,
     * so the ledger is never typed twice; a fee taken back later is answered with an opposite line.
     */
    private static function postMembershipLine(CtpRegistration|FundiRegistration $member): void
    {
        $who = $member instanceof CtpRegistration ? 'ctp' : 'fundi';
        if (FinanceEntry::existsFor($who, (int) $member->id, 'membership_fee')) {
            return;
        }

        $code = $member->ctp_code ?? $member->ef_code ?? 'no ID yet';

        FinanceEntry::post('membership_fee', 'Membership fee — '.$member->full_name.' ('.$code.')',
            (float) $member->fee_amount, [
                'method' => $member->fee_method ?: 'other',
                'happened_on' => ($member->fee_paid_at ?? now())->format('Y-m-d'),
                $who.'_id' => $member->id,
                'source_type' => get_class($member),
                'source_id' => $member->id,
                'note' => 'One-time membership fee recorded on the application'
                    .($member->fee_reference ? ' under '.$member->fee_reference : '').'.',
            ]);
    }

    /** Approve an applicant: issue the member ID once and open their portal account. */
    public static function approve(Model $record, string $codeColumn, bool $account): string
    {
        // A reversal is allowed: an applicant rejected in error can be approved later.
        abort_unless(in_array($record->status, ['pending_review', 'rejected'], true), 422,
            'Only applications waiting for review (or already rejected) can be approved.');

        DB::transaction(function () use ($record, $codeColumn, $account) {
            $model = get_class($record);
            $record->status = 'active';
            $record->rejection_reason = null;
            // An ID is issued once and never re-issued, even if the record is later reopened.
            $record->{$codeColumn} ??= $model::nextCode();
            if ($record instanceof CtpRegistration) {
                $record->approved_by = auth()->id();
                $record->approved_at = now();
            }
            $record->save();

            if ($account) {
                AccountInvite::issueFor($record, auth()->id());
            }
        });

        return 'Approved — '.$record->{$codeColumn}.' issued.'
            .($account ? ' Copy the activation link below and send it so they can set a password.' : '');
    }

    public static function reject(Model $record, string $reason): void
    {
        abort_unless(in_array($record->status, ['pending_payment', 'pending_review'], true), 422,
            'Only applications that have not been approved can be rejected.');

        $record->update(['status' => 'rejected', 'rejection_reason' => $reason]);
    }

    /** Move a CTP who finished the programme into the fundi pool. */
    public static function setPoolTier(CtpRegistration $ctp, ?string $tier): void
    {
        $ctp->update(['fundi_tier' => $tier]);
    }

    /** Replace a stale activation link for an approved member. */
    public static function reissue(Model $record): string
    {
        abort_unless(in_array($record->status, ['active', 'graduated', 'deployed'], true), 422,
            'Only approved members have activation links.');

        AccountInvite::issueFor($record, auth()->id(), true);

        return 'New activation link ready — copy it below and send it.';
    }
}
