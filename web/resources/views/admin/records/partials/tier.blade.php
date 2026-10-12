{{-- Tier select. Expects: $selected --}}
<select name="tier">
    <option value="">No tier</option>
    @foreach (['platinum' => 'Platinum', 'gold' => 'Gold', 'silver' => 'Silver'] as $k => $lbl)
        <option value="{{ $k }}" @selected(($selected ?? null) === $k)>{{ $lbl }}</option>
    @endforeach
</select>
