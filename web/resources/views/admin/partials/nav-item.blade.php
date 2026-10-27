@php $badge = \App\Support\Departments::badge($item['badge'] ?? null); @endphp
<a href="{{ route(...$item['route']) }}" class="{{ $isActive($item) ? 'active' : '' }}">
    @include('partials.icon', ['n' => $item['icon']]) {{ $item['label'] }}
    @if ($badge)<span class="nav-badge">{{ $badge }}</span>@endif
</a>
