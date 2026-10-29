<?php

namespace App\Support;

use App\Mail\PurchaseOrderEmail;
use App\Mail\QuotationEmail;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\Mail;

/**
 * Module 11 — the procurement path in one place. The office screens all call these, so a purchase request
 * cannot be paid before it is received, and goods cannot reach a shelf without the purchase order that brought
 * them. Every step leaves its mark on the same request row: nothing is deleted and nothing is raised twice.
 */
class Procurement
{
    /** Which inventory shelf a budget category restocks, so a received order files itself. */
    public const BUDGET_TO_STOCK = [
        'construction' => 'construction',
        'electrical' => 'construction',
        'ict' => 'technology',
        'networking' => 'technology',
        'robotics' => 'technology',
        'ai' => 'technology',
        'training' => 'office_training',
        'operations' => 'office_training',
        'ewaste' => 'ewaste',
    ];

    /** The budget a stock card is charged to when materials go out of the store. */
    public const STOCK_TO_BUDGET = [
        'technology' => 'ict',
        'construction' => 'construction',
        'ewaste' => 'ewaste',
        'office_training' => 'training',
    ];

    public static function stockToBudget(string $category): string
    {
        return self::STOCK_TO_BUDGET[$category] ?? 'operations';
    }

    public static function budgetToStock(string $category): string
    {
        return self::BUDGET_TO_STOCK[$category] ?? 'office_training';
    }

    /** Step 2 — the Programme Manager approves or rejects what was raised. */
    public static function decide(PurchaseRequest $request, bool $approve, ?string $note): PurchaseRequest
    {
        $request->mustBe('submitted');

        $request->update([
            'status' => $approve ? 'approved' : 'rejected',
            'decided_by_user' => auth()->id(),
            'decided_on' => now(),
            'decision_note' => $approve ? $note : null,
            'rejection_reason' => $approve ? null : $note,
        ]);

        return $request;
    }

    /** Step 3 — a quotation from an approved supplier. The first one moves the request onto the quotations step. */
    public static function quote(PurchaseRequest $request, array $data): PurchaseQuotation
    {
        $request->mustBe('approved', 'quoted');

        $supplier = Supplier::findOrFail($data['supplier_id']);
        abort_unless($supplier->status === 'approved', 422,
            $supplier->name.' is not on the approved supplier list, so their price cannot be used as a quotation.');

        $quotation = $request->quotations()->create([
            'supplier_id' => $supplier->id,
            'amount_ksh' => $data['amount_ksh'],
            'quotation_ref' => $data['quotation_ref'] ?? null,
            'quoted_on' => $data['quoted_on'],
            'note' => $data['note'] ?? null,
            'added_by_user' => auth()->id(),
        ]);

        if ($request->status === 'approved') {
            $request->update(['status' => 'quoted']);
        }

        return $quotation;
    }

    /** The quote bought against. Selecting one clears the others so a request has one price of record. */
    public static function select(PurchaseQuotation $quotation): void
    {
        $quotation->request->mustBe('quoted');

        $quotation->request->quotations()->update(['is_selected' => false]);
        $quotation->update(['is_selected' => true]);
    }

    /** Step 4 — the purchase order is written from the selected quotation. */
    public static function raiseOrder(PurchaseRequest $request, array $data): PurchaseOrder
    {
        $request->mustBe('quoted', 'ordered');

        $order = PurchaseOrder::create([
            'reference' => PurchaseOrder::nextReference(),
            'purchase_request_id' => $request->id,
            'supplier_id' => $data['supplier_id'],
            'quantity' => $data['quantity'],
            'unit_cost_ksh' => $data['unit_cost_ksh'],
            'amount_ksh' => round((float) $data['quantity'] * (float) $data['unit_cost_ksh'], 2),
            'note' => $data['note'] ?? null,
            'raised_by_user' => auth()->id(),
            'status' => 'draft',
        ]);

        return $order;
    }

    public static function issue(PurchaseOrder $order): PurchaseOrder
    {
        $order->mustBe('draft');

        $order->update(['status' => 'issued', 'issued_on' => now()]);
        $order->request->update(['status' => 'ordered']);

        return $order;
    }

    /**
     * Step 6 — the storekeeper checks quantity and quality, signs the delivery note and the goods go on the shelf.
     * The stock card is the one the request named, or one is opened for the item so nothing arrives uncounted.
     */
    public static function receive(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $order->mustBe('issued');

        $request = $order->request;
        if ($order->received_on) {
            abort(422, $order->reference.' was already received on '.$order->received_on->format('d M Y')
                .' against delivery note '.$order->delivery_note_ref.' — the store never counts the same delivery twice.');
        }

        $item = $request->inventory_item_id
            ? InventoryItem::findOrFail($request->inventory_item_id)
            : static::openStockCard($request, $order);

        $moved = (float) $data['received_qty'];

        $order->update([
            'status' => 'received',
            'received_qty' => $moved,
            'delivery_note_ref' => $data['delivery_note_ref'],
            'receipt_note' => $data['receipt_note'] ?? null,
            'received_by_user' => auth()->id(),
            'received_on' => now(),
        ]);

        $item->applyMovement('in', $moved);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'direction' => 'in',
            'quantity' => $moved,
            'unit_cost_ksh' => (float) $order->unit_cost_ksh,
            'reason' => 'purchase_order',
            'note' => 'Signed in on delivery note '.$data['delivery_note_ref'].' against '.$order->reference
                .' from '.($order->supplier->name ?? 'the supplier')
                .($moved < (float) $order->quantity ? ' — short of the '.$order->quantity.' ordered' : ''),
            'project_id' => $request->project_id,
            'cohort_id' => $request->cohort_id,
            'purchase_order_id' => $order->id,
            'source_type' => PurchaseOrder::class,
            'source_id' => $order->id,
            'moved_on' => now()->toDateString(),
            'recorded_by_user' => auth()->id(),
        ]);

        $request->update(['status' => 'received', 'inventory_item_id' => $item->id]);

        return $order->refresh();
    }

    /** Step 7 — PO, delivery note and invoice lined up before a payment request exists. */
    public static function match(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $order->mustBe('received');
        abort_if(! $order->delivery_note_ref, 422, 'A delivery note number has to be on the order before it can be matched.');

        $order->update([
            'status' => 'matched',
            'invoice_ref' => $data['invoice_ref'],
            'invoice_amount_ksh' => $data['invoice_amount_ksh'],
            'matched_by_user' => auth()->id(),
            'matched_on' => now(),
        ]);

        $order->request->update(['status' => 'matched']);

        return $order;
    }

    /**
     * Step 8 — the Finance Officer pays and the payment is recorded. Money leaving the programme is an executive
     * action, and the ledger line points back at the order so the store, the invoice and the payment are one story.
     */
    public static function pay(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $order->mustBe('matched');
        Finance::requireRelease('Paying '.$order->reference);

        $amount = (float) $order->amount_ksh;

        $order->update(['status' => 'paid', 'paid_on' => $data['paid_on']]);
        $order->request->update(['status' => 'paid']);

        FinanceEntry::post('procurement', $order->reference.' — '.$order->request->item_name.' from '
            .($order->supplier->name ?? 'supplier'), $amount, [
            'budget_category' => $order->request->budget_category,
            'method' => $data['method'],
            'happened_on' => $data['paid_on'],
            'project_id' => $order->request->project_id,
            'cohort_id' => $order->request->cohort_id,
            'source_type' => PurchaseOrder::class,
            'source_id' => $order->id,
            'note' => 'Paid against '.$order->reference.', delivery note '.($order->delivery_note_ref ?? '—')
                .', invoice '.($order->invoice_ref ?? '—')
                .($order->paperworkMatches() ? '' : ' — the invoice did not match the order amount'),
        ]);

        return $order;
    }

    /**
     * A purchase the office made outside the system — bought at the counter and brought in on a receipt. It joins
     * the register as already paid, with the supplier named as typed and one line on the money register; the eight
     * steps are not faked around it. Money leaving the programme is still an executive action.
     */
    public static function recordHand(array $data): PurchaseRequest
    {
        Finance::requireRelease('Recording a purchase that was already paid for');

        $request = PurchaseRequest::create([
            'reference' => PurchaseRequest::nextReference(),
            'item_name' => $data['item_name'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'estimated_cost_ksh' => $data['paid_amount_ksh'],
            'budget_category' => $data['budget_category'],
            'justification' => $data['justification'],
            'county' => $data['county'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'raised_by_user' => $data['raised_by_user'] ?? auth()->id(),
            'status' => 'paid',
            'recorded_by_hand_on' => now(),
            'supplier_label' => $data['supplier_label'],
        ]);

        FinanceEntry::post('procurement', 'Hand purchase — '.$request->item_name.' from '.$request->supplier_label,
            (float) $data['paid_amount_ksh'], [
                'budget_category' => $request->budget_category,
                'method' => $data['method'],
                'happened_on' => $data['paid_on'],
                'project_id' => $request->project_id,
                'note' => 'Recorded by hand: bought outside the system and paid by '
                    .FinanceEntry::METHODS[$data['method']].'. The receipt is on the file.',
                'source_type' => PurchaseRequest::class,
                'source_id' => $request->id,
            ]);

        return $request;
    }

    /**
     * Step 5 by email — the order goes to the supplier's address as the same branded document the office prints.
     * Sending it is the sending step, so a draft order becomes issued here; an order already sent is only sent again.
     */
    public static function emailOrder(PurchaseOrder $order, string $to, ?string $note = null): PurchaseOrder
    {
        Mail::to($to)->send(new PurchaseOrderEmail($order, $note));

        $order->update(['emailed_at' => now(), 'sent_to' => $to, 'sent_note' => $note]);

        if ($order->status === 'draft') {
            static::issue($order);
        }

        return $order;
    }

    /** A quotation sent back to the supplier to confirm the figure and the terms still stand. Not a money action. */
    public static function emailQuotation(PurchaseQuotation $quotation, string $to, ?string $note = null): PurchaseQuotation
    {
        Mail::to($to)->send(new QuotationEmail($quotation, $note));

        $quotation->update(['emailed_at' => now(), 'sent_to' => $to, 'sent_note' => $note]);

        return $quotation;
    }

    private static function openStockCard(PurchaseRequest $request, PurchaseOrder $order): InventoryItem
    {
        $category = static::budgetToStock($request->budget_category);

        return InventoryItem::create([
            'sku' => InventoryItem::nextSku($category),
            'name' => $request->item_name,
            'description' => $request->description ?: 'Received on '.$order->reference.' from '
                .($order->supplier->name ?? 'a supplier'),
            'category' => $category,
            'unit' => $request->unit,
            'qty_on_hand' => 0,
            'reorder_level' => 0,
            'location' => $request->county ? $request->county.' store' : 'Main store',
            'unit_cost_ksh' => (float) $order->unit_cost_ksh,
        ]);
    }
}
