{{-- The class hours the Registrar named this trainer on, read from the programme week. --}}
@foreach ($handed as $row)
    <div class="card">
        <div class="section-head">
            <h3>Classes the Registrar has handed you</h3>
            <span class="tag">{{ $row['training'] }}</span>
            <span class="tag">{{ $row['classes'] }} {{ Str::plural('class', $row['classes']) }}</span>
            <span class="tag gold">{{ $row['hours'] }} h a week</span>
        </div>
        <p class="hint">The Registrar’s programme week, approved
            {{ $row['week']->reviewed_at ? $row['week']->reviewed_at->format('d M Y') : 'by the office' }}.
            Your name is on the hours below.</p>
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Day</th><th>Time</th><th>Class</th><th>Unit</th><th>Lecturer</th><th>Room</th></tr>
                </thead>
                <tbody>
                    @foreach ($row['slots'] as $slot)
                        <tr>
                            <td>{{ $slot->dayLabel() }}</td>
                            <td>{{ $slot->timeLabel() }}</td>
                            <td>{{ $slot->kindLabel() }}</td>
                            <td>{{ $slot->module?->title ?: '—' }}</td>
                            <td>{{ $slot->lecturerLabel() }}</td>
                            <td>{{ $slot->room ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach
