<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Cohort;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Support\Finance;
use App\Support\Procurement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module 11 — the stock register (§14.5). Cards by category with the quantity on hand, the reorder level, where
 * the item sits and what it cost. Materials drawn for a project or a cohort leave through a movement, which also
 * posts the value on the ledger — that is what makes "materials issued from inventory" a tracked item (§14.3)
 * rather than a shelf that quietly empties.
 */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('cat', 'all');
        $flag = $request->query('f', 'all');
        $search = trim((string) $request->query('q'));

        $items = InventoryItem::with('movements')
            ->when($category !== 'all', fn ($q) => $q->where('category', $category))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->orderBy('category')->orderBy('name')
            ->get();

        $shown = $items->when($flag === 'low', fn ($c) => $c->filter(fn ($i) => $i->isLow()))
            ->when($flag === 'empty', fn ($c) => $c->filter(fn ($i) => $i->isEmpty()));

        return view('admin.finance.inventory', [
            'items' => $shown,
            'category' => $category,
            'flag' => $flag,
            'search' => $search,
            'categories' => InventoryItem::CATEGORIES,
            'units' => InventoryItem::UNITS,
            'totals' => InventoryItem::totals(),
            'low' => $items->filter(fn ($i) => $i->isLow() && ! $i->isEmpty())->count(),
            'empties' => $items->filter(fn ($i) => $i->isEmpty())->count(),
            'requests' => PurchaseRequest::whereIn('status', ['submitted', 'approved', 'quoted', 'ordered'])->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = InventoryItem::dataFrom($request);
        $opening = (float) $request->validate([
            'qty_on_hand' => 'nullable|numeric|min:0|max:1000000',
        ], [], ['qty_on_hand' => 'Opening balance'])['qty_on_hand'];

        $item = InventoryItem::create([...$data, 'sku' => InventoryItem::nextSku($data['category']),
            'qty_on_hand' => 0, 'status' => 'active']);

        if ($opening > 0) {
            $this->record($item, [
                'direction' => 'in',
                'reason' => 'opening',
                'quantity' => $opening,
                'unit_cost_ksh' => (float) $data['unit_cost_ksh'],
                'moved_on' => now()->toDateString(),
                'note' => 'Opening balance the card was opened with.',
            ]);
        }

        return back()->with('status', $item->sku.' — '.$item->name.' is on the register at '
            .($item->location ?: 'no store named').' with '.$item->quantityLabel()
            .'. Reorder level '.$item->reorder_level.' '.$item->unit.'.');
    }

    public function show(Request $request, InventoryItem $item)
    {
        $item->load(['movements' => fn ($q) => $q->with(['project:id,title', 'cohort:id,reference',
            'order:id,reference', 'recorder:id,name'])->orderByDesc('moved_on')->orderByDesc('id')->limit(200),
            'requests' => fn ($q) => $q->with('project:id,title')->latest('id')->limit(20)]);

        return view('admin.finance.item', [
            'item' => $item,
            'reasons' => StockMovement::REASONS,
            'projects' => Project::orderByDesc('id')->limit(200)->get(['id', 'title', 'reference']),
            'cohorts' => Cohort::orderByDesc('id')->limit(200)->get(['id', 'reference']),
            'categories' => InventoryItem::CATEGORIES,
            'units' => InventoryItem::UNITS,
        ]);
    }

    /** A card that has run out is not deleted — it is reordered, so a purchase request is raised from here. */
    public function reorder(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:1000000',
            'justification' => 'required|string|max:1000|min:15',
            'needed_by' => 'nullable|date',
        ], ['quantity.min' => 'Say how much the store needs — that is what the supplier is quoted for.'], [
            'quantity' => 'How much is needed', 'justification' => 'Why', 'needed_by' => 'Needed by',
        ]);

        $pr = PurchaseRequest::create([
            'reference' => PurchaseRequest::nextReference(),
            'item_name' => $item->name,
            'description' => $item->description ?: $item->sku.' at '.$item->placeLabel(),
            'quantity' => (int) round((float) $data['quantity']),
            'unit' => $item->unit,
            'estimated_cost_ksh' => round((float) $data['quantity'] * (float) $item->unit_cost_ksh, 2),
            'budget_category' => Procurement::stockToBudget($item->category),
            'justification' => $data['justification'],
            'needed_by' => $data['needed_by'] ?? null,
            'county' => null,
            'inventory_item_id' => $item->id,
            'raised_by_user' => auth()->id(),
            'status' => 'submitted',
        ]);

        return back()->with('status', $pr->reference.' was raised from stock card '.$item->sku
            .' ('.$item->quantityLabel().' left against a reorder level of '.$item->reorder_level.' '
            .$item->unit.'). It waits for the Programme Manager\'s decision (§14.4 step 2).');
    }

    /**
     * One movement on one card. An issue out to a project or a cohort also posts its value on the ledger, so the
     * materials a project used are on the project's own account (§14.3).
     */
    public function move(Request $request, InventoryItem $item)
    {
        $data = StockMovement::dataFrom($request);

        $this->record($item, $data);

        $value = (float) $data['quantity'] * (float) ($data['unit_cost_ksh'] ?? $item->unit_cost_ksh);

        return back()->with('status', $item->sku.' — '.$data['direction'].' movement of '
            .rtrim(rtrim(number_format((float) $data['quantity'], 2), '0'), '.').' '.$item->unit.' ('
            .StockMovement::REASONS[$data['reason']].') on '
            .Carbon::parse($data['moved_on'])->format('d M Y').'. The card now holds '.$item->refresh()->quantityLabel()
            .($data['direction'] === 'out' && $value > 0 ? ' · '.Finance::ksh($value).' of material went out.' : ''));
    }

    private function record(InventoryItem $item, array $data): StockMovement
    {
        $movement = StockMovement::create([
            'inventory_item_id' => $item->id,
            'direction' => $data['direction'],
            'quantity' => $data['quantity'],
            'unit_cost_ksh' => $data['unit_cost_ksh'] ?? $item->unit_cost_ksh,
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'cohort_id' => $data['cohort_id'] ?? null,
            'moved_on' => $data['moved_on'],
            'recorded_by_user' => auth()->id(),
        ]);

        $item->applyMovement($data['direction'], (float) $data['quantity']);

        if ($data['direction'] === 'out' && $movement->reason !== 'transfer_out') {
            $value = (float) $movement->quantity * (float) $movement->unit_cost_ksh;

            if ($value > 0) {
                FinanceEntry::post('materials_issued', $item->name.' issued — '.
                    rtrim(rtrim(number_format((float) $movement->quantity, 2), '0'), '.').' '.$item->unit, $value, [
                    'budget_category' => Procurement::stockToBudget($item->category),
                    'method' => 'pending',
                    'happened_on' => $movement->moved_on->toDateString(),
                    'project_id' => $movement->project_id,
                    'cohort_id' => $movement->cohort_id,
                    'source_type' => StockMovement::class,
                    'source_id' => $movement->id,
                    'note' => $movement->reasonLabel().' · card '.$item->sku.' at '.$item->placeLabel()
                        .' at '.Finance::ksh((float) $item->unit_cost_ksh).' per '.$item->unit.'.',
                ]);
            }
        }

        return $movement;
    }
}
