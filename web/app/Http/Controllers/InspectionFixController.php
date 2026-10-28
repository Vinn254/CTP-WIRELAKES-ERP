<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\SiteInspection;
use App\Models\SiteInspectionItem;
use App\Models\WorkOrder;
use App\Support\Construction;
use App\Support\MemberPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Module 8 — the worker's side of an inspection. An item the inspector failed is answered here by the
 * person whose order it sits on: what they did, with the photograph of the rework. The office re-checks it on the
 * construction file; nothing on this screen marks an item passed.
 */
class InspectionFixController extends Controller
{
    private function member(): Model
    {
        $member = MemberPortal::resolve(auth()->user());

        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    public function index()
    {
        $member = $this->member();

        return view('portal.fixes', MemberPortal::board($member) + [
            'fixes' => Construction::fixesFor($member),
            'cleared' => SiteInspectionItem::with(['inspection.project'])
                ->whereIn('inspection_id', $this->inspectionIds($member))
                ->where('result', 'fail')
                ->whereNotNull('rechecked_on')
                ->orderByDesc('rechecked_on')
                ->limit(10)
                ->get(),
        ]);
    }

    public function prove(Request $request, SiteInspectionItem $item)
    {
        $member = $this->member();

        abort_unless(Construction::fixesFor($member)->firstWhere('id', $item->id) !== null, 404,
            'That item is not a failure waiting on you. It may already be re-checked, or it sits on somebody else\'s site.');

        Construction::proveFix($item, $request, $member->id);

        return back()->with('status', 'Proof of the fix on "'.$item->item.'" filed for the inspector to re-check.');
    }

    public function proof(SiteInspectionItem $item)
    {
        $member = $this->member();

        abort_unless(
            SiteInspectionItem::where('id', $item->id)->whereIn('inspection_id', $this->inspectionIds($member))->exists(),
            404,
            'That photograph is not on your site.'
        );

        return Construction::serve($item->proof_path, (string) $item->proof_name, 'fix-'.$item->id);
    }

    private function inspectionIds(Model $member)
    {
        return SiteInspection::whereIn('work_order_id', $this->orderIds($member))->pluck('id');
    }

    private function orderIds(Model $member)
    {
        return WorkOrder::where('assignee_type', $member instanceof CtpRegistration ? 'ctp' : 'fundi')
            ->where('assignee_id', $member->id)
            ->pluck('id');
    }
}
