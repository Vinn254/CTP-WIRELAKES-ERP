<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Http\Request;

/**
 * Module 11 — the approved supplier list. Quotations are only accepted from the names here, so a
 * supplier is added on their details and payment information and taken off the list rather than deleted: the
 * purchase orders they already delivered stay readable.
 */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('f', 'all');
        $search = trim((string) $request->query('q'));

        $suppliers = Supplier::withCount(['quotations', 'purchaseOrders'])
            ->with('adder:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")))
            ->orderBy('status')->orderBy('name')
            ->get();

        return view('admin.finance.suppliers', [
            'suppliers' => $suppliers,
            'status' => $status,
            'search' => $search,
            'filters' => ['all' => 'Everyone'] + Supplier::STATUSES,
            'counts' => [
                'all' => $suppliers->count(),
                'approved' => $suppliers->where('status', 'approved')->count(),
                'inactive' => $suppliers->where('status', 'inactive')->count(),
            ],
            'categories' => InventoryItem::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = Supplier::dataFrom($request);

        $supplier = Supplier::create([...$data, 'code' => Supplier::nextCode(),
            'status' => 'approved', 'added_by_user' => auth()->id()]);

        return back()->with('status', $supplier->code.' — '.$supplier->name.' is on the approved list'
            .' ('.$supplier->suppliesLabel().'). Quotations can now be taken from them.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = Supplier::dataFrom($request);

        $supplier->update($data);

        return back()->with('status', $supplier->code.' — '.$supplier->name
            .' was corrected. Their quotations and orders keep standing as they were.');
    }

    /** Off the list, not deleted — the orders they delivered are still on the register. */
    public function setStatus(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Supplier::STATUSES)),
            'note' => 'nullable|string|max:1000',
        ], [], ['status' => 'List status']);

        $supplier->update(['status' => $data['status'], 'note' => $data['note'] ?? $supplier->note]);

        return back()->with('status', $supplier->name.' is now '.$supplier->statusLabel()
            .($data['status'] === 'approved'
                ? ' — they can be quoted again.'
                : ' — old quotations and purchase orders stay on file, new ones are refused.'));
    }
}
