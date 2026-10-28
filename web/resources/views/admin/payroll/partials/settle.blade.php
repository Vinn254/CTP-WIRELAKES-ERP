@php
    use App\Models\PayrollLine;

    if (! ($paying ?? false) || $line->status !== 'unpaid') {
        return;
    }

    $name = 'settle-'.$line->id;
    $today = now()->toDateString();
@endphp
@include('partials.sheet-start', ['name' => $name, 'title' => 'Pay '.$line->name.' — '.$run->periodLabel()])
<form method="POST" action="{{ route('admin.staffpay.pay', $line) }}" class="fee-form"
      data-confirm="Pay {{ $line->name }} {{ $line->netLabel() }} and post it to the register?" data-title="Pay staff">
    @csrf
    <input type="hidden" name="_sheet" value="{{ $name }}">
    <p class="sheet-note"><b>{{ $line->netLabel() }}</b> net — {{ \App\Support\Finance::ksh((float) $line->gross_ksh) }}
        gross less {{ \App\Support\Finance::ksh((float) $line->deductions_ksh) }} deductions.</p>
    <div class="form-grid-2">
        <div class="field">
            <label>How they are paid <span class="req">*</span></label>
            <select name="method" required>
                @foreach (PayrollLine::METHODS as $key => $label)
                    <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Date paid <span class="req">*</span></label>
            <input type="date" name="paid_on" max="{{ $today }}" required value="{{ old('paid_on', $today) }}">
        </div>
    </div>
    <div class="form-grid-2">
        <div class="field">
            <label>Account or phone number</label>
            <input type="text" name="payee_account" maxlength="120"
                   value="{{ old('payee_account', $line->record->bank_account) }}" placeholder="A count number, not an account name">
        </div>
        <div class="field">
            <label>Payment reference</label>
            <input type="text" name="provider_ref" maxlength="60" value="{{ old('provider_ref') }}"
                   placeholder="e.g. M-Pesa confirmation code">
        </div>
    </div>
    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit">Pay {{ $line->netLabel() }}</button>
    </div>
</form>
<p class="hint">This posts its own line to the money register and signs the person off for the month. A line already
    paid cannot be paid again.</p>
@include('partials.sheet-end')
