@php
    /** A report or a statement: sections of columns and rows, printed the same way on every page. @var array $report */
@endphp
@foreach ($report['sections'] as $section)
    <div class="card">
        <h2 class="section-head">{{ $section['heading'] }}</h2>
        @if ($section['note'])<p class="help">{{ $section['note'] }}</p>@endif
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr>@foreach ($section['columns'] as $col)<th>{{ $col }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($section['columns']) }}" class="empty-cell">
                            Nothing recorded against this yet.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach
