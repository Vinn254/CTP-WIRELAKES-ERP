@php
    use App\Models\TimeLog;

    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $logo = content('site', 'branding', 'logo');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : asset('logo.png');

    $ink = '#1d2b23';
    $muted = '#5b6b5f';
    $line = '#dce6dc';
    $green = '#066722';
    $rows = $sheet['logs'];
    $first = $rows->first();
    $sign = 'border-top:1px solid '.$ink.';margin-top:34px;padding-top:5px;font-size:10px;letter-spacing:.1em;';
    $head = 'padding:7px 9px;border:1px solid '.$line.';font-size:9px;letter-spacing:.08em;text-transform:uppercase;'
        .'color:'.$muted.';text-align:left;background:#f3f8f2;';
    $cell = 'padding:7px 9px;border:1px solid '.$line.';font-size:11px;vertical-align:top;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Time log — {{ $worker->name }} {{ $sheet['label'] }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2ef; font-family: Segoe UI, Arial, sans-serif; color: <?= $ink ?>; }
        .bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;
               padding: 12px 16px; background: <?= $green ?>; color: #fff; }
        .bar b { font-size: .95rem; margin-right: auto; }
        .bar a, .bar button { font: inherit; font-size: .85rem; font-weight: 700; color: <?= $green ?>;
                              background: #d8f3c8; border: 0; border-radius: 8px; padding: 8px 14px;
                              text-decoration: none; cursor: pointer; }
        .bar a.ghost { color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, .55); }
        .sheet { max-width: 860px; margin: 22px auto 40px; }
        .sheet > div { box-shadow: 0 12px 34px rgba(14, 40, 22, .13); }
        @media print {
            body { background: #fff; }
            .bar, .foot { display: none !important; }
            .sheet { margin: 0; max-width: none; }
            .sheet > div { box-shadow: none; border: 0 !important; }
            a { text-decoration: none; }
        }
        .foot { text-align: center; font-size: .82rem; color: <?= $muted ?>; padding: 0 16px 30px; }
    </style>
</head>
<body>
<div class="bar">
    <b>Time log — {{ $worker->name }} · {{ $sheet['label'] }}</b>
    <button type="button" onclick="window.print()">Print / save as PDF</button>
    <a href="{{ download_href(url()->current()) }}">Download</a>
    <a class="ghost" href="{{ $back }}">{{ $backLabel }}</a>
</div>

<div class="sheet">
    <div style="background:#fff;border:1px solid <?= $line ?>;border-radius:12px;overflow:hidden;
                font-family:Segoe UI,Arial,sans-serif;color:<?= $ink ?>;">
        <div style="background:<?= $green ?>;color:#fff;padding:20px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle">
                        <img src="{{ $logo }}" alt="" width="46" height="46"
                             style="display:block;border-radius:8px;background:#fff;padding:4px;">
                    </td>
                    <td valign="middle" style="padding-left:12px;">
                        <div style="font-size:17px;font-weight:700;">{{ $brandName }}</div>
                        <div style="font-size:12px;opacity:.85;">Weekly time sheet — tasks accomplished</div>
                    </td>
                    <td valign="middle" align="right">
                        <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">Week of</div>
                        <div style="font-size:19px;font-weight:800;">{{ $sheet['label'] }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding:18px 24px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                    <td style="<?= $head ?>">Name</td>
                    <td style="<?= $head ?>">Department</td>
                    <td style="<?= $head ?>">Initials</td>
                    <td style="<?= $head ?>">Month</td>
                    <td style="<?= $head ?>">Week of month</td>
                    <td style="<?= $head ?>">Days written</td>
                    <td style="<?= $head ?>">Status</td>
                </tr>
                <tr>
                    <td style="<?= $cell ?>"><b>{{ $worker->name }}</b></td>
                    <td style="<?= $cell ?>">{{ $rows->pluck('department')->filter()->first() ?: '—' }}</td>
                    <td style="<?= $cell ?>">{{ $first?->initials ?: TimeLog::initialsOf($worker) }}</td>
                    <td style="<?= $cell ?>">{{ \Illuminate\Support\Carbon::parse($sheet['month'].'-01')->format('F Y') }}</td>
                    <td style="<?= $cell ?>">{{ $sheet['week_no'] }} of {{ count($sheet['weeks']) }}</td>
                    <td style="<?= $cell ?>">{{ $rows->count() }}</td>
                    <td style="<?= $cell ?>"><b>{{ TimeLog::STATUSES[$sheet['status']] ?? $sheet['status'] }}</b></td>
                </tr>
            </table>
        </div>

        <div style="padding:16px 24px 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                    <td style="<?= $head ?>width:88px;">DAY/DATE</td>
                    <td style="<?= $head ?>width:62px;">TIME IN</td>
                    <td style="<?= $head ?>width:62px;">TIME OUT</td>
                    <td style="<?= $head ?>">TASKS ACCOMPLISHED</td>
                    <td style="<?= $head ?>width:52px;">INITIALS</td>
                    <td style="<?= $head ?>width:78px;">DEPT</td>
                    <td style="<?= $head ?>width:96px;">APPROVAL SIGN</td>
                    <td style="<?= $head ?>width:62px;text-align:right;">TOTAL HOURS</td>
                    <td style="<?= $head ?>width:56px;text-align:right;">TOTAL UNITS</td>
                </tr>
                @foreach ($sheet['days'] as $day)
                    @php $row = $day['row']; @endphp
                    <tr>
                        <td style="<?= $cell ?>"><b>{{ $day['day'] }}</b><br>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                        <td style="<?= $cell ?>">{{ $row ? $row->clockIn() : '-' }}</td>
                        <td style="<?= $cell ?>">{{ $row ? $row->clockOut() : '-' }}</td>
                        <td style="<?= $cell ?>">
                            {{ $row && $row->task ? $row->task : '-' }}
                            @if ($row?->training)<br><span style="color:<?= $muted ?>;font-size:10px;">{{ $row->training->name }}</span>@endif
                            @if ($row?->cohort)<br><span style="color:<?= $muted ?>;font-size:10px;">Cohort {{ $row->cohort->reference }}</span>@endif
                        </td>
                        <td style="<?= $cell ?>">{{ $row?->initials ?: '-' }}</td>
                        <td style="<?= $cell ?>">{{ $row?->department ?: '-' }}</td>
                        <td style="<?= $cell ?>">
                            @if ($row?->status === 'approved')
                                <b>{{ $row->approvedBy?->name ?? 'Approved' }}</b>
                                <br><span style="color:<?= $muted ?>;font-size:10px;">{{ $row->approved_at?->format('d/m/Y') }}</span>
                            @elseif ($row?->status === 'submitted')
                                <span style="color:<?= $muted ?>;font-size:10px;">Signed by {{ $row->signedBy?->name ?? $worker->name }}</span>
                            @elseif ($row)
                                &nbsp;
                            @else
                                -
                            @endif
                        </td>
                        <td style="<?= $cell ?>text-align:right;">{{ $row && $row->hours !== null ? number_format($row->hours, 2) : '-' }}</td>
                        <td style="<?= $cell ?>text-align:right;">{{ $row ? (int) $row->units : '-' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="<?= $cell ?>background:#f3f8f2;" colspan="6"><b>TOTALS</b></td>
                    <td style="<?= $cell ?>background:#f3f8f2;">&nbsp;</td>
                    <td style="<?= $cell ?>background:#f3f8f2;text-align:right;"><b>{{ number_format($sheet['hours'], 2) }}</b></td>
                    <td style="<?= $cell ?>background:#f3f8f2;text-align:right;"><b>{{ (int) $sheet['units'] }}</b></td>
                </tr>
            </table>
        </div>

        @if ($note = $rows->pluck('approval_note')->filter()->first())
            <div style="margin:4px 24px 0;padding:10px 14px;background:#fdf1ef;border:1px solid #e9c6bf;border-radius:8px;
                        font-size:12px;color:#7a2e1e;">
                <b>Note from the office:</b> {{ $note }}
            </div>
        @endif

        <div style="padding:20px 24px 26px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="46%" style="padding-right:26px;">
                        <div style="<?= $sign ?>color:<?= $muted ?>;">WORKER&apos;S SIGNATURE — {{ $worker->name }}</div>
                    </td>
                    <td width="26%">
                        <div style="<?= $sign ?>color:<?= $muted ?>;">DATE</div>
                    </td>
                    <td width="28%">
                        <div style="<?= $sign ?>color:<?= $muted ?>;">APPROVED BY (HUMAN RESOURCES)</div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="background:#f3f8f2;border-top:1px solid <?= $line ?>;padding:14px 24px;font-size:11px;color:<?= $muted ?>;">
            An hour for lunch comes off the clock times and a day counts eight hours at most; two units are recorded for
            every hour. {{ $brandName }} keeps this sheet as the proof of work before pay — Human Resources signs the
            week, and the hours go to Finance.
        </div>
    </div>
</div>

<p class="foot">
    Week of {{ $sheet['label'] }} · {{ $worker->name }} · printed {{ now()->format('d M Y') }}
</p>
</body>
</html>
