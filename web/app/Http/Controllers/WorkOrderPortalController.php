<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\WorkOrder;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderUpdate;
use App\Support\Execution;
use App\Support\MemberPortal;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Module 7 — the worker's own side of the order. Everything here is resolved
 * from the signed-in member's record: an order that is not theirs does not exist. The office does the rest (the
 * materials, the sign-offs, the hirer's inspection and the money) on the delivery desk.
 */
class WorkOrderPortalController extends Controller
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
        $orders = Execution::for($member);

        return view('portal.work', MemberPortal::board($member) + [
            'orders' => $orders,
            'waiting' => $orders->where('status', 'issued')->count(),
            'working' => $orders->whereIn('status', ['accepted', 'in_progress'])->count(),
            'questions' => $orders->where('status', 'disputed')->count(),
            'done' => $orders->whereIn('status', ['confirmed', 'closed', 'cancelled'])->count(),
        ]);
    }

    public function show(WorkOrder $order)
    {
        $this->theirOrder($order);
        $order->load(['project.hirer', 'project.providedMaterials.item', 'milestones.signer',
            'updates.recorder', 'updates.fundi', 'disputes.mediator', 'disputes.decider']);

        return view('portal.workorder', MemberPortal::board($this->member()) + [
            'order' => $order,
            'dispute' => Execution::activeDispute($order),
            'payout' => Execution::payoutFor($order),
            'next' => Execution::nextState($order),
            'percent' => Execution::rollUp($order),
            'window' => WorkOrderDispute::WINDOW_HOURS,
        ]);
    }

    /**  — accept the order, sign it and confirm the day work starts. */
    public function accept(Request $request, WorkOrder $order)
    {
        $this->theirOrder($order);
        Execution::accept($order, $request);

        return back()->with('status', $order->reference.' accepted and signed by '.$order->signature
            .', starting '.$order->start_on->format('d M Y').'. Issue materials against it at the store, then log your days here.');
    }

    /**  — a day on the job: what was done, the percentage, and a photo of it. */
    public function log(Request $request, WorkOrder $order)
    {
        $this->theirOrder($order);
        $order->mustBe('accepted', 'in_progress', 'disputed');

        $data = WorkOrderUpdate::dataFrom($request, ['progress']);

        $fundi = $order->assignee_type === 'fundi' ? FundiRegistration::find($order->assignee_id) : null;
        Execution::log($order, $data, $request, $fundi);

        $day = Carbon::parse($data['entry_date'])->format('d M Y');
        $percent = isset($data['percent']) ? ' — '.$data['percent'].'% complete' : '';

        return back()->with('status', 'Logged for '.$day.$percent
            .'. The office and the supervisor read this log.');
    }

    /**  — the work is done; the completion request goes to the hirer for inspection. */
    public function complete(Request $request, WorkOrder $order)
    {
        $this->theirOrder($order);
        Execution::requestCompletion($order, $request);

        return back()->with('status', 'Completion requested on '.$order->reference
            .'. The office arranges the hirer\'s inspection — if they raise an issue instead, you will see it here and have 48 hours to answer.');
    }

    /**  — the worker's answer to the hirer's issue. */
    public function answer(Request $request, WorkOrder $order, WorkOrderDispute $dispute)
    {
        $this->theirOrder($order);
        abort_unless((int) $dispute->work_order_id === (int) $order->id, 403, 'That issue is not on '.$order->reference.'.');

        $member = $this->member();
        abort_unless($member instanceof FundiRegistration, 403,
            'An attachment order has no dispute answer box — the office settles it with the hirer and notes it on the trainee\'s record.');

        Execution::answerDispute($dispute, $request, $member);

        return back()->with('status', 'Your answer is on the dispute. The Programme Manager mediates and a Director decides.');
    }

    public function photo(WorkOrder $order, WorkOrderUpdate $update)
    {
        $this->theirOrder($order);
        abort_unless((int) $update->work_order_id === (int) $order->id, 403, 'That entry is not on your order.');

        return Execution::serve($update->photo_path, (string) $update->photo_name,
            $order->reference.'-'.$update->id);
    }

    /** The file the hirer sent with their issue — read through the order it belongs to. */
    public function evidence(WorkOrder $order, WorkOrderDispute $dispute)
    {
        $this->theirOrder($order);
        abort_unless((int) $dispute->work_order_id === (int) $order->id, 403, 'That issue is not on your order.');

        return Execution::serve($dispute->issue_path, (string) $dispute->issue_name,
            $order->reference.'-dispute-'.$dispute->id);
    }

    /** The order is read from the member's own record, never from the id in the url. */
    private function theirOrder(WorkOrder $order): void
    {
        abort_unless($order->isHeldBy($this->member()), 404,
            'That work order is not in your name. The delivery desk keeps every order on the project it was awarded for.');
    }
}
