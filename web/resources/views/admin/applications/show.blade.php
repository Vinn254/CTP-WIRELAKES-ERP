@extends('layouts.admin')
@section('title', $application->name)

@section('content')
@include('partials.errors')
<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $application->name }}
            <span class="status {{ $application->status === 'received' ? 'pending_review' : ($application->status === 'taken' ? 'active' : ($application->status === 'closed' ? 'closed' : '')) }}">
                {{ $application->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $application->reference }}</span>
            <span>{{ $application->areaLabel() }}</span>
            <span>{{ $application->category ?: 'No service listed' }}</span>
            @if ($application->town)<span>{{ $application->town }}</span>@endif
            <span>Asked on {{ $application->created_at->format('d M Y') }}</span>
        </p>
    </div>
    <div class="ah-contact">
        <a class="btn btn-sm btn-outline" href="{{ route($application->area) }}#apply" target="_blank">
            The page they used</a>
    </div>
</div>

<div class="card">
    <div class="section-head"><h3>What they sent</h3></div>
    <table class="data">
        <tbody>
            <tr>
                <td>Phone</td><td><a href="tel:{{ $application->phone }}">{{ $application->phone }}</a></td>
                <td>WhatsApp</td><td>{{ $application->whatsapp ?: 'not given' }}</td>
            </tr>
            <tr>
                <td>Email</td><td colspan="3"><a href="mailto:{{ $application->email }}">{{ $application->email }}</a></td>
            </tr>
            <tr>
                <td>Number to call</td>
                <td>{{ $application->callNumber() }}</td>
                <td>Town</td>
                <td>{{ $application->town ?: 'not given' }}</td>
            </tr>
            @if ($application->details)
                <tr><td>What they need</td><td colspan="3">{{ $application->details }}</td></tr>
            @endif
            @if ($application->review_note)
                <tr><td>Our note</td><td colspan="3">{{ $application->review_note }}</td></tr>
            @endif
            <tr>
                <td>Read by</td>
                <td>{{ $application->reviewer->name ?? 'nobody yet' }}</td>
                <td>Contacted on</td>
                <td>{{ $application->contacted_on?->format('d M Y') ?: 'not yet' }}</td>
            </tr>
        </tbody>
    </table>
    <p class="hint">The person was told by email that we would answer within
        {{ \App\Models\ProgrammeApplication::TURNAROUND }}. Record the call back here.</p>
</div>

@if ($answering)
<div class="card">
    <div class="section-head"><h3>Answer this request</h3></div>
    <form method="POST" action="{{ route('admin.applications.review', $application) }}" class="fee-form"
          data-confirm="Save this answer?" data-title="Answer request">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label>Where this request is</label>
                <select name="status">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($application->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Contacted on</label>
                <input type="date" name="contacted_on" value="{{ $application->contacted_on?->format('Y-m-d') }}">
                <div class="hint">Fill it in when you called, WhatsApp-ed or emailed them.</div>
            </div>
            <div class="field full">
                <label>What was said</label>
                <textarea name="review_note" rows="4" maxlength="1000"
                          placeholder="Who you spoke to, what was agreed, what happens next">{{ $application->review_note }}</textarea>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Save</button>
    </form>
</div>
@else
<div class="card">
    <div class="section-head"><h3>Website &amp; Membership answers this</h3></div>
    <p class="hint">This desk reads the requests on its own area page as its own copy. Call the person on the number above and
        the desk that keeps the register writes down what was said.</p>
</div>
@endif

<p style="margin-top:18px;"><a class="back" href="{{ route('admin.applications.index') }}">Back to all requests</a></p>
@endsection
