@extends('layouts.admin')
@section('title', 'Site content & pages')

@section('content')
<a class="back" href="{{ route('admin.dashboard') }}">Back to Departments</a>
<h1>Site content &amp; pages</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 22px;">
    Edit everything visitors see — logo, background photos, hero text, cards, mission &amp; vision, fees.
    You can also add brand-new fields and sections to any page; they appear on the public site once you save.
</p>

<div class="dept-grid">
    @foreach ($pages as $key => $label)
        <div class="dept-card live">
            <span class="badge tag gray">{{ $counts[$key] ?? 0 }} section{{ ($counts[$key] ?? 0) === 1 ? '' : 's' }}</span>
            <div class="mod">{{ strtoupper(str_replace('-', ' ', $key)) }}</div>
            <h3>{{ $label }}</h3>
            @if ($key === 'site')
                <p>Logo, brand colours, background photos, contact details and footer — used on every page.</p>
            @elseif ($key === 'membership')
                <p>Membership fees, CTP tracks and E-Fundi tiers. These drive the public registration forms.</p>
            @elseif ($key === 'home')
                <p>Hero slides, intro, programme cards and the donate strip.</p>
            @else
                <p>All sections and cards shown on this page.</p>
            @endif
            <a class="btn btn-primary btn-sm" href="{{ route('admin.content.edit', $key) }}">Edit content</a>
            @if (! in_array($key, ['site', 'membership'], true))
                <a class="btn btn-ghost btn-sm" href="{{ route($key) }}" target="_blank">View page ↗</a>
            @endif
        </div>
    @endforeach
</div>
@endsection
