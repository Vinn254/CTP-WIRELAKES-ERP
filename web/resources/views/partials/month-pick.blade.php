@php
    $pickMonth = $anyMonth ?? false;
    $pickYear = $anyYear ?? false;
@endphp
<div class="field">
    <label>Month</label>
    <select name="month">
        @if ($pickMonth)<option value="">Every month</option>@endif
        @foreach (range(1, 12) as $number)
            @php $value = sprintf('%02d', $number); @endphp
            <option value="{{ $value }}" @selected((int) $month === $number)>
                {{ \Illuminate\Support\Carbon::create(null, $number)->format('F') }}
            </option>
        @endforeach
    </select>
</div>
<div class="field">
    <label>Year</label>
    <select name="year">
        @if ($pickYear)<option value="">Every year</option>@endif
        @foreach ($years as $option)
            <option value="{{ $option }}" @selected((string) $year === (string) $option)>{{ $option }}</option>
        @endforeach
    </select>
</div>
