<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceEmail;
use App\Models\CtpRegistration;
use App\Models\Donation;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Support\Finance;
use App\Support\Invoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * The invoice desk inside Finance. Staff raise a demand for money on a donor, a CTP, an E-Fundi or anyone else,
 * send the document or hand over a copy, and record what comes in — each payment putting its own income line on
 * the money register so the two can never disagree.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('f'), array_keys(Invoice::STATUSES), true) ? $request->query('f') : 'all';
        $who = in_array($request->query('who'), array_keys(Invoice::BILLED_TO), true) ? $request->query('who') : 'all';
        $search = trim((string) $request->query('q'));

        $base = fn () => Invoice::query();

        $invoices = Invoice::with(['ctp:id,full_name,ctp_code', 'fundi:id,full_name,ef_code', 'project:id,title',
            'payments', 'raiser:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($who !== 'all', fn ($q) => $q->where('billed_to', $who))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('party_name', 'like', "%{$search}%")))
            ->orderByRaw("case when status = 'open' and due_on is not null and due_on < curdate() then 0
                when status = 'open' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.finance.invoices', [
            'invoices' => $invoices,
            'status' => $status,
            'who' => $who,
            'search' => $search,
            'statuses' => Invoice::STATUSES,
            'billedTo' => Invoice::BILLED_TO,
            'methods' => FinanceEntry::METHODS,
            'totals' => [
                'billed' => (float) $base()->where('status', '!=', 'cancelled')->sum('amount_ksh'),
                'outstanding' => $base()->where('status', 'open')->get()->sum(fn (Invoice $i) => $i->balanceKsh()),
                'overdue' => $base()->where('status', 'open')->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString())->count(),
                'count' => $base()->count(),
            ],
            'counts' => [
                'all' => $base()->count(),
                'open' => $base()->where('status', 'open')->count(),
                'settled' => $base()->where('status', 'settled')->count(),
                'cancelled' => $base()->where('status', 'cancelled')->count(),
            ],
            'ctps' => CtpRegistration::whereIn('status', ['pending_payment', 'pending_review', 'active', 'graduated', 'deployed'])
                ->orderBy('full_name')->get(['id', 'full_name', 'ctp_code', 'email', 'phone']),
            'fundis' => FundiRegistration::orderBy('full_name')->get(['id', 'full_name', 'ef_code', 'email', 'phone']),
            'donations' => Donation::whereNotNull('email')->orderByDesc('id')->limit(200)
                ->get(['id', 'donor_name', 'email', 'phone', 'amount', 'impact_label']),
            'projects' => Project::orderByDesc('id')->limit(200)->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        $invoice = Invoices::raise($request);

        return redirect()->route('admin.invoices.show', $invoice)->with('status',
            $invoice->reference.' raised for '.$invoice->memberName().' at '.$invoice->amountLabel()
            .'. Record the payment here when the money arrives — it posts its own line on the money register.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['ctp:id,full_name,ctp_code,email,phone', 'fundi:id,full_name,ef_code,email,phone',
            'project:id,title', 'donation:id,donor_name,amount,impact_label', 'payments.recorder:id,name',
            'raiser:id,name', 'canceller:id,name']);

        return view('admin.finance.invoice', [
            'invoice' => $invoice,
            'methods' => FinanceEntry::METHODS,
            'ledger' => FinanceEntry::where('source_type', InvoicePayment::class)
                ->whereIn('source_id', $invoice->payments->pluck('id'))->orderByDesc('happened_on')->get(),
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $payment = Invoices::pay($request, $invoice);

        return back()->with('status', $payment->amountLabel().' received on '.$invoice->reference.' — '
            .Finance::ksh($invoice->balanceKsh()).' still outstanding'
            .($invoice->status === 'settled' ? '. The invoice is settled.' : '.'));
    }

    public function voidPayment(Request $request, InvoicePayment $payment)
    {
        Invoices::void($request, $payment);

        return back()->with('status', 'The payment of '.$payment->amountLabel().' on '
            .($payment->invoice->reference ?? 'an invoice').' is voided and an opposite line went on the register.');
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        Invoices::cancel($request, $invoice);

        return back()->with('status', $invoice->reference.' is cancelled. It stays on the desk with its reason — '
            .'an executive reopens it if that was wrong.');
    }

    public function reopen(Invoice $invoice)
    {
        Invoices::reopen($invoice);

        return back()->with('status', $invoice->reference.' is open again at '.$invoice->balanceLabel().' outstanding.');
    }

    /** The document itself: opened in the browser to read, printed or saved as a PDF from there. */
    public function document(Invoice $invoice)
    {
        $invoice->load(['ctp:id,full_name,ctp_code', 'fundi:id,full_name,ef_code', 'project:id,title',
            'payments', 'raiser:id,name']);

        return view('admin.finance.invoice-document', ['invoice' => $invoice]);
    }

    public function email(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'to' => 'required|email|max:150',
        ], ['to.required' => 'Give the address the invoice goes to.']);

        abort_if($invoice->status === 'cancelled', 422, 'A cancelled invoice is not sent out.');

        Mail::to($data['to'])->send(new InvoiceEmail($invoice));

        $invoice->update(['emailed_at' => now()]);

        return back()->with('status', 'The invoice went to '.$data['to'].'. Its register lines and payments are '
            .'unchanged — sending a copy is not a money action.');
    }
}
