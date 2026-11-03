<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\LibraryItem;
use App\Models\LibraryPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the library costs a student to keep.
 *
 * Everything on the shelf is read on the platform for nothing — a trainee opens a book, a note or a past paper in
 * the browser as often as they like. Saving it to the phone is what costs: KSh 50 for a past paper, KSh 100 for a
 * book or notes, KSh 500 for a syllabus. The money goes to the programme's own Paybill and the Academic Registrar,
 * who keeps the shelf, confirms it arrived; only then does the download open.
 *
 * A trainer is handed the same shelf to teach from and never pays for it, so the charge falls on a CTP student's
 * account alone.
 */
class Library
{
    public const PAST_PAPER = 50;

    public const READING = 100;

    /** A programme's syllabus is the whole course map, so it carries its own line on the Paybill. */
    public const SYLLABUS = 500;

    public const PURCHASES = [
        'requested' => 'Waiting for the office to confirm',
        'paid' => 'Paid — the download is open',
        'declined' => 'Not confirmed — ask again',
    ];

    /** What one title costs a student to keep. */
    public static function price(LibraryItem $item): int
    {
        return match ($item->kind) {
            'past_paper' => self::PAST_PAPER,
            'syllabus' => self::SYLLABUS,
            default => self::READING,
        };
    }

    public static function priceLabel(int $amount): string
    {
        return 'KSh '.number_format($amount);
    }

    /**
     * The shelf one reader is written for: the titles filed under the programmes they teach or study, and the titles
     * the Registrar filed for every course. Reading of another programme is not on their shelf at all.
     *
     * @param  array<int>  $trainingIds
     */
    public static function shelf(array $trainingIds): Builder
    {
        return LibraryItem::onShelf()
            ->where(fn ($q) => $q->whereIn('training_id', $trainingIds)->orWhereNull('training_id'));
    }

    /**
     * The same rule on a single title, so a file cannot be reached by typing its url any more than the list can.
     *
     * @param  array<int>  $trainingIds
     */
    public static function reads(LibraryItem $item, array $trainingIds): bool
    {
        return ! $item->training_id || in_array((int) $item->training_id, $trainingIds, true);
    }

    /** Does this account pay for reading? A trainer — an E-Fundi or an office trainer — reads and keeps for nothing. */
    public static function charges(?User $user): bool
    {
        return $user !== null && ! Workspace::trainerDesk($user)
            && MemberPortal::resolve($user) instanceof CtpRegistration;
    }

    /** May this account save the title to their phone? A trainer always may; a student only once the money is in. */
    public static function entitled(?CtpRegistration $ctp, LibraryItem $item, ?User $user): bool
    {
        if (! static::charges($user)) {
            return true;
        }

        return static::purchase($ctp, $item)?->isPaid() ?? false;
    }

    /** The student's own row for a title, if they have ever asked for it. */
    public static function purchase(?CtpRegistration $ctp, LibraryItem $item): ?LibraryPurchase
    {
        return $ctp ? LibraryPurchase::where('ctp_id', $ctp->id)
            ->where('library_item_id', $item->id)->first() : null;
    }

    /** Every title this student may already keep, keyed by title, so a shelf is cut in one pass. */
    public static function kept(?CtpRegistration $ctp): Collection
    {
        return $ctp
            ? LibraryPurchase::where('ctp_id', $ctp->id)->get()->keyBy('library_item_id')
            : new Collection;
    }

    /**
     * The student says the money has gone in. The row is written over, never doubled, and the amount is the price on
     * the day it was asked for. Nothing here opens the file — only the desk's confirmation does.
     */
    public static function request(User $asker, CtpRegistration $ctp, LibraryItem $item): LibraryPurchase
    {
        $amount = static::price($item);
        $purchase = static::purchase($ctp, $item) ?: new LibraryPurchase([
            'ctp_id' => $ctp->id, 'library_item_id' => $item->id, 'requested_by_user' => $asker->id,
        ]);

        if ($purchase->isPaid()) {
            return $purchase;
        }

        $purchase->forceFill([
            'ctp_id' => $ctp->id,
            'library_item_id' => $item->id,
            'amount' => $amount,
            'status' => 'requested',
            'decision_note' => null,
            'decided_by_user' => null,
            'decided_at' => null,
            'requested_by_user' => $asker->id,
        ])->save();

        Audit::record($purchase, $purchase->wasRecentlyCreated ? 'created' : 'updated',
            $asker->name.' says they sent '.static::priceLabel($amount).' for '.$item->title.'.');

        return $purchase;
    }

    /** The desk counted the money. The download opens on this title for this student. */
    public static function confirm(LibraryPurchase $purchase, ?string $note, User $by): string
    {
        $purchase->forceFill(['status' => 'paid', 'decision_note' => $note ? trim($note) : null,
            'decided_by_user' => $by->id, 'decided_at' => now()])->save();

        Audit::record($purchase, 'updated', $by->name.' confirmed '
            .static::priceLabel($purchase->amount).' from '.$purchase->personLabel().' for '.$purchase->titleLabel().'.');

        return $purchase->titleLabel().' is paid for — '.$purchase->personLabel().' can save it to their phone.';
    }

    /** The money did not arrive, or not enough of it. The student reads why and can ask again. */
    public static function decline(LibraryPurchase $purchase, ?string $note, User $by): string
    {
        $purchase->forceFill(['status' => 'declined', 'decision_note' => $note ? trim($note) : null,
            'decided_by_user' => $by->id, 'decided_at' => now()])->save();

        Audit::record($purchase, 'updated', $by->name.' declined a library payment from '
            .$purchase->personLabel().' for '.$purchase->titleLabel().($note ? ': '.trim($note) : '.'));

        return 'The payment is declined. '.$purchase->personLabel().' can send it again and ask.';
    }

    /** The desk's own list: what is waiting, and what was answered lately. */
    public static function desk(): array
    {
        return [
            'waiting' => LibraryPurchase::with(['student:id,full_name,reference', 'item:id,title,kind'])
                ->where('status', 'requested')->latest('id')->get(),
            'answered' => LibraryPurchase::with(['student:id,full_name,reference', 'item:id,title,kind',
                'decidedBy:id,name'])->whereIn('status', ['paid', 'declined'])->latest('id')->limit(60)->get(),
        ];
    }
}
