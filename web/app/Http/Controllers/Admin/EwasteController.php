<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\EWasteCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Module 10 — the e-waste register (§13.3). The field registers a collection, an E-Fundi forwards it and this
 * screen is where the programme office weighs it in. Sorting, testing and the repair / refurbish / recycle /
 * disposal route are the next steps on the same row — a line is never deleted or duplicated.
 */
class EwasteController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('f', 'all');
        $item = $request->query('item', 'all');
        $county = $request->query('county', 'all');
        $search = trim((string) $request->query('q'));

        $base = fn () => EWasteCollection::query();

        $rows = EWasteCollection::ordered()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($item !== 'all', fn ($q) => $q->where('item_type', $item))
            ->when($county !== 'all', fn ($q) => $q->where('county', $county))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('ctp', fn ($c) => $c->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('fundi', fn ($f) => $f->where('full_name', 'like', "%{$search}%")));
            })
            ->with(['community:id,name', 'ctp:id,full_name,ctp_code', 'fundi:id,full_name,ef_code',
                'forwarder:id,full_name,ef_code', 'receiver:id,name'])
            ->paginate(25)
            ->withQueryString();

        return view('admin.ewaste.index', [
            'collections' => $rows,
            'filters' => ['all' => 'Everything', ...EWasteCollection::STATUSES],
            'status' => $status,
            'item' => $item,
            'county' => $county,
            'search' => $search,
            'counts' => [
                'all' => $base()->count(),
                'submitted' => $base()->where('status', 'submitted')->count(),
                'forwarded' => $base()->where('status', 'forwarded')->count(),
                'received' => $base()->where('status', 'received')->count(),
            ],
            'totals' => [
                'items' => (int) $base()->sum('quantity'),
                'weighed' => round((float) $base()->sum('weighed_weight_kg'), 1),
                'estimated' => round((float) $base()->whereNull('weighed_weight_kg')->sum('estimated_weight_kg'), 1),
                'hazardous' => $base()->where('condition', 'hazardous')->count(),
            ],
            'itemTypes' => EWasteCollection::ITEM_TYPES,
            'conditions' => EWasteCollection::CONDITIONS,
            'counties' => County::orderBy('name')->pluck('name'),
        ]);
    }

    /** The collection point: the actual weight replaces the field estimate and the line becomes programme stock. */
    public function receive(Request $request, EWasteCollection $collection)
    {
        abort_if($collection->status === 'received', 422,
            $collection->reference.' was already weighed in on '.($collection->received_on ? $collection->received_on->format('d M Y') : 'the register')
            .' at '.$collection->weighed_weight_kg.' kg — the weight on the register stays. Change it on the same line if it was wrong.');

        $data = $request->validate([
            'weighed_weight_kg' => 'required|numeric|min:0.01|max:1000000',
            'condition' => 'required|in:'.implode(',', array_keys(EWasteCollection::CONDITIONS)),
            'note' => 'nullable|string|max:1000',
        ], [], ['weighed_weight_kg' => 'Weight on the scale', 'condition' => 'Condition']);

        $collection->update([
            ...$data,
            'status' => 'received',
            'received_by_user' => auth()->id(),
            'received_on' => now(),
        ]);

        return back()->with('status', $collection->reference.' weighed in at '.$data['weighed_weight_kg'].' kg as '
            .EWasteCollection::CONDITIONS[$data['condition']].'. '
            .($collection->estimated_weight_kg !== null
                ? 'The field estimated '.$collection->estimated_weight_kg.' kg — the estimate stays on the line.'
                : 'No weight was estimated in the field.').' Registered by '.$collection->collectorLabel().'.'
            .($data['condition'] === 'hazardous' ? ' Hazardous material goes to a certified disposal partner.' : ''));
    }

    public function photo(EWasteCollection $collection)
    {
        abort_unless($collection->photo_path && Storage::disk('local')->exists($collection->photo_path), 404,
            'No photo was registered with that collection.');

        return Storage::disk('local')->response($collection->photo_path, $collection->photo_name ?: $collection->reference.'.jpg');
    }
}
