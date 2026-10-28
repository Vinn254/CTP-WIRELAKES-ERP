<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Cohort;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\Procurement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module 11 — the procurement register. One row per purchase request, walking the eight steps from
 * whoever raised it to the payment the Finance Officer recorded. Every step names its actor and refuses to run out
 * of order, so a request cannot be paid before it is received or ordered without a quotation on file.
 */
class ProcurementController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('f', 'all');
        $budget = $request->query('budget', 'all');
        $search = trim((string) $request->query('q'));

        $base = fn () => PurchaseRequest::query();

        $rows = PurchaseRequest::ordered()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($budget !== 'all', fn ($q) => $q->where('budget_category', $budget))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('item_name', 'like', "%{$search}%")
                ->orWhere('justification', 'like', "%{$search}%")))
            ->with(['project:id,title', 'cohort:id,reference', 'raiser:id,name', 'ctp:id,full_name,ctp_code',
                'fundi:id,full_name,ef_code', 'orders:id,purchase_request_id,reference,status'])
            ->paginate(25)->withQueryString();

        $open = fn () => $base()->whereNotIn('status', ['paid', 'rejected']);

        return view('admin.finance.procurement', [
            'requests' => $rows,
            'status' => $status,
            'budget' => $budget,
            'search' => $search,
            'filters' => ['all' => 'Every request'] + PurchaseRequest::STATUSES,
            'counts' => ['all' => $base()->count()] + collect(PurchaseRequest::STATUSES)
                ->mapWithKeys(fn ($label, $key) => [$key => $base()->where('status', $key)->count()])->all(),
            'totals' => [
                'raised' => (float) $open()->sum('estimated_cost_ksh'),
                'awaiting' => $base()->where('status', 'submitted')->count(),
                'to_order' => $base()->where('status', 'quoted')->count(),
                'unpaid' => (float) $base()->whereIn('status', ['received', 'matched'])->sum('estimated_cost_ksh'),
            ],
            'categories' => BudgetLine::CATEGORIES,
            'units' => InventoryItem::UNITS,
            'projects' => Project::orderByDesc('id')->limit(200)->get(['id', 'title', 'reference']),
            'cohorts' => Cohort::orderByDesc('id')->limit(200)->get(['id', 'reference']),
            'items' => InventoryItem::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
        ]);
    }

    /** Step 1 in the office — the same form a CTP or an E-Fundi uses in their portal, minus the member link. */
    public function store(Request $request)
    {
        $pr = PurchaseRequest::create([
            ...PurchaseRequest::dataFrom($request),
            'reference' => PurchaseRequest::nextReference(),
            'raised_by_user' => auth()->id(),
            'status' => 'submitted',
        ]);

        return back()->with('status', $pr->reference.' was raised for '.$pr->item_name.' ('.$pr->quantityLabel()
            .'), charged to '.$pr->budgetLabel().'. It waits for the Programme Manager\'s decision.');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load([
            'project.hirer', 'cohort.training:id,name,code', 'item',
            'raiser:id,name', 'ctp:id,full_name,ctp_code', 'fundi:id,full_name,ef_code', 'decider:id,name',
            'quotations.supplier:id,name,status,supplies', 'quotations.adder:id,name',
            'orders.supplier', 'orders.raiser:id,name', 'orders.receiver:id,name', 'orders.matcher:id,name',
        ]);

        return view('admin.finance.request', [
            'pr' => $purchaseRequest,
            'suppliers' => Supplier::where('status', 'approved')->orderBy('name')->get(),
            'best' => $purchaseRequest->quotations->sortBy('amount_ksh')->first(),
            'categories' => BudgetLine::CATEGORIES,
        ]);
    }

    /** Step 2 — the Programme Manager approves or rejects with a reason. */
    public function decide(Request $request, PurchaseRequest $purchaseRequest)
    {
        $data = $request->validate([
            'approve' => 'required|in:yes,no',
            'note' => 'required_if:approve,no|nullable|string|max:1000|min:10',
        ], [
            'note.required_if' => 'A request is rejected on a reason the person who raised it can read.',
            'note.min' => 'One line on why the programme is not buying it.',
        ], ['note' => 'Reason']);

        $approve = $data['approve'] === 'yes';

        Procurement::decide($purchaseRequest, $approve, $data['note'] ?? null);

        return back()->with('status', $purchaseRequest->reference.' was '
            .($approve ? 'approved' : 'rejected with the reason recorded').'. '.$purchaseRequest->statusLabel()
            .($approve ? ' — the Procurement Officer now gets quotations from approved suppliers.' : '.'));
    }

    public function quote(Request $request, PurchaseRequest $purchaseRequest)
    {
        $quotation = Procurement::quote($purchaseRequest, PurchaseQuotation::dataFrom($request));

        return back()->with('status', $quotation->label().' is on file for '.$purchaseRequest->reference
            .' ('.$purchaseRequest->quotations()->count().' quotation(s) so far).');
    }

    /** A price that was typed in wrong comes off the request; the supplier quotes again. Money already paid is paperwork. */
    public function dropQuotation(PurchaseQuotation $quotation)
    {
        $pr = $quotation->request;
        abort_if($pr->status === 'paid', 422,
            $pr->reference.' has been paid — its quotations are part of the payment record now.');

        $quotation->delete();

        if ($pr->status === 'quoted' && $pr->quotations()->doesntExist()) {
            $pr->update(['status' => 'approved']);
        }

        return back()->with('status', 'That quotation was taken off '.$pr->reference
            .'. A wrong price is removed and the supplier quotes again — the request itself stays.');
    }

    public function selectQuotation(PurchaseQuotation $quotation)
    {
        Procurement::select($quotation);

        return back()->with('status', $quotation->label().' is the price of record for '
            .$quotation->request->reference.'.');
    }

    /** Step 4 — the purchase order goes out to one supplier for the approved quantity. */
    public function raiseOrder(Request $request, PurchaseRequest $purchaseRequest)
    {
        $order = Procurement::raiseOrder($purchaseRequest, PurchaseOrder::issueDataFrom($request));

        return back()->with('status', $order->reference.' was written for '
            .($order->supplier->name ?? 'the supplier').' at '.$order->amountLabel()
            .'. Sending it to the supplier is the next step.');
    }

    public function issueOrder(PurchaseOrder $order)
    {
        Procurement::issue($order);

        return back()->with('status', $order->reference.' was sent to '.($order->supplier->name ?? 'the supplier')
            .'. The storekeeper receives the goods against a delivery note.');
    }

    /** Step 6 — the storekeeper signs the delivery note and the quantity goes on the stock card. */
    public function receiveOrder(Request $request, PurchaseOrder $order)
    {
        $data = PurchaseOrder::receiveDataFrom($request);
        Procurement::receive($order, $data);

        $pr = $order->request->fresh()->load('item');

        return back()->with('status', $order->reference.' was received: '.$data['received_qty'].' of '
            .rtrim(rtrim(number_format((float) $order->quantity, 2), '0'), '.').' '.$pr->unit
            .' signed in on delivery note '.$data['delivery_note_ref']
            .($pr->item->exists ? '. Stock card '.$pr->item->sku.' now holds '.$pr->item->quantityLabel() : '')
            .'. The order is matched to the supplier invoice next.');
    }

    public function matchOrder(Request $request, PurchaseOrder $order)
    {
        Procurement::match($order, PurchaseOrder::matchDataFrom($request));

        return back()->with('status', $order->reference.' matched: order '.$order->amountLabel()
            .', delivery note '.$order->delivery_note_ref.', invoice '.$order->invoice_ref
            .' at '.$order->invoiceLabel().($order->paperworkMatches()
                ? ' — the three agree, so the payment can be made.'
                : ' — the invoice does not equal the order amount; the Finance Officer should query it before paying.'));
    }

    /** Step 8 — the Finance Officer pays, and the ledger carries the line. */
    public function payOrder(Request $request, PurchaseOrder $order)
    {
        $data = $request->validate([
            'method' => 'required|in:mpesa,bank,cheque,cash',
            'paid_on' => 'required|date',
        ], ['paid_on.required' => 'A payment has a date — that is what the ledger line is filed under.'], [
            'method' => 'Paid by', 'paid_on' => 'Date paid',
        ]);

        Procurement::pay($order, $data);

        $method = FinanceEntry::METHODS[$data['method']];
        $when = Carbon::parse($data['paid_on'])->format('d M Y');

        return back()->with('status', $order->reference.' was paid '.$order->amountLabel().' to '
            .($order->supplier->name ?? 'the supplier').' by '.$method.' on '.$when
            .'. The ledger carries the expense against '.$order->request->budgetLabel().'.');
    }
}
