@extends('layouts.admin')
@section('title', 'Add an asset')

@php
    use App\Support\Technology;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">&larr; Asset register</a></p>
<h1>Add an asset</h1>
<p class="page-lede">Add one device, licence or kit to the register.</p>

@if ($errors->any())
    <div class="card" style="border-left:4px solid var(--danger);">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card">
    <form method="POST" action="{{ route('admin.technology.asset.store') }}" class="fee-form" data-title="Add asset">
        @csrf
        @include('admin.technology.asset-fields', [
            'asset' => $asset,
            'categories' => $categories,
            'communities' => $communities,
            'organisations' => $organisations,
        ])
        <button class="btn btn-sm btn-primary" type="submit">Add to the register</button>
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">Cancel</a>
    </form>
</div>
@endsection
