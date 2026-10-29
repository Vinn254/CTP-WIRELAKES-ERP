@php
    $brandName = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
    $logo = content('site', 'branding', 'logo');
    $logo = $logo ? (str_starts_with($logo, 'http') ? $logo : url($logo)) : asset('logo.png');

    $ink = '#1d2b23';
    $muted = '#5b6b5f';
    $line = '#dce6dc';
    $green = '#066722';
    $place = collect([$ctp->ward, $ctp->sub_county, $ctp->county])->filter()->join(', ');
    $issued = $seats->map(fn ($e) => $e->certification?->approved_on)->filter()->max();
    $cell = 'padding:7px 10px;border-bottom:1px solid '.$line.';font-size:12px;';
    $head = 'padding:7px 10px;border-bottom:2px solid '.$line.';font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:'.$muted.';text-align:left;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transcript {{ $ctp->ctp_code ?: $ctp->paperName() }}</title>
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
        .sheet { max-width: 780px; margin: 22px auto 40px; }
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
    <b>Transcript — {{ $ctp->paperName() }}{{ $ctp->ctp_code ? ' ('.$ctp->ctp_code.')' : '' }}</b>
    <button type="button" onclick="window.print()">Print / save as PDF</button>
    <a href="{{ download_href(url()->current()) }}">Download</a>
    <a class="ghost" href="{{ route('admin.students.show', $ctp) }}">Back to the record</a>
</div>

<div class="sheet">
    <div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid <?= $line ?>;border-radius:12px;overflow:hidden;font-family:Segoe UI,Arial,sans-serif;color:<?= $ink ?>;">
        <div style="background:<?= $green ?>;color:#fff;padding:22px 26px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="middle">
                        <img src="{{ $logo }}" alt="" width="46" height="46"
                             style="display:block;border-radius:8px;background:#fff;padding:4px;">
                    </td>
                    <td valign="middle" style="padding-left:12px;">
                        <div style="font-size:17px;font-weight:700;">{{ $brandName }}</div>
                        <div style="font-size:12px;opacity:.85;">Community Training Programme — training record</div>
                    </td>
                    <td valign="middle" align="right">
                        <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.85;">Transcript</div>
                        <div style="font-size:20px;font-weight:800;">{{ $ctp->ctp_code ?: 'ID not issued yet' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding:22px 26px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td valign="top" width="52%" style="padding-right:18px;">
                        <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?= $muted ?>;">Trainee</div>
                        <div style="font-size:16px;font-weight:700;margin-top:5px;">{{ $ctp->paperName() }}</div>
                        <div style="font-size:12px;color:<?= $muted ?>;">
                            {{ collect([$ctp->gender ? ucfirst($ctp->gender) : null, $ctp->phone, $ctp->email])->filter()->join(' · ') }}
                        </div>
                        @if ($place)<div style="font-size:12px;color:<?= $muted ?>;">{{ $place }}</div>@endif
                    </td>
                    <td valign="top">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="<?= $head ?>">Track</td>
                                <td style="<?= $head ?>">Registered</td>
                                <td style="<?= $head ?>">Courses</td>
                            </tr>
                            <tr>
                                <td style="<?= $cell ?>"><?= $ctp->trackLabel() ?></td>
                                <td style="<?= $cell ?>"><?= $ctp->created_at->format('d M Y') ?></td>
                                <td style="<?= $cell ?>"><?= $seats->count() ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        @if ($seats->isEmpty())
            <div style="padding:20px 26px 26px;font-size:13px;color:<?= $muted ?>;">
                This trainee has not yet sat a course, so there is no curriculum history to record.
            </div>
        @else
            @foreach ($terms as $term)
                <div style="padding:16px 26px 2px;">
                    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?= $green ?>;
                                border-bottom:1px solid <?= $line ?>;padding-bottom:5px;font-weight:800;">
                        {{ $term['label'] }}
                    </div>
                </div>
                @foreach ($term['seats'] as $e)
                    @php
                        $board = $modules[$e->id] ?? ['modules' => collect(), 'marked' => collect()];
                        $rows = $marks[$e->id] ?? [];
                        $att = $e->attendancePct();
                        $cert = $e->certification;
                    @endphp
                    <div style="padding:6px 26px 14px;">
                        <div style="font-size:14px;font-weight:700;">{{ $e->cohort?->courseLabel() ?: 'Cohort removed' }}</div>
                        <div style="font-size:12px;color:<?= $muted ?>;margin:2px 0 8px;">
                            {{ collect([
                                $e->cohort?->reference,
                                $e->cohort?->venue?->name,
                                $e->cohort?->trainer?->personName() ? 'trainer '.$e->cohort->trainer->personName() : null,
                                $e->stageLabel(),
                            ])->filter()->join(' · ') }}
                        </div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border-collapse:collapse;margin-bottom:8px;">
                            <tr>
                                <td style="<?= $head ?>">Attendance</td>
                                <td style="<?= $head ?>">Units signed off</td>
                                <td style="<?= $head ?>">Track</td>
                                <td style="<?= $head ?>">Certificate</td>
                            </tr>
                            <tr>
                                <td style="<?= $cell ?>"><?= $att === null ? 'not marked' : $att.'%' ?></td>
                                <td style="<?= $cell ?>">
                                    <?= $board['modules']->isEmpty() ? '—' : $board['marked']->count().' of '.$board['modules']->count() ?>
                                </td>
                                <td style="<?= $cell ?>"><b><?= $e->trackPct() ?>%</b></td>
                                <td style="<?= $cell ?>">
                                    <?= $cert ? ($cert->certificate_code ?: $cert->statusLabel()) : 'not put forward' ?>
                                </td>
                            </tr>
                        </table>

                        @if ($rows)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="border-collapse:collapse;">
                                <tr>
                                    <td style="<?= $head ?>">Unit</td>
                                    <td style="<?= $head ?>">Day taken</td>
                                    <td style="<?= $head ?>">Type</td>
                                    <td style="<?= $head ?>text-align:right;">Mark</td>
                                </tr>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td style="<?= $cell ?>"><?= $row['unit'] ?></td>
                                        <td style="<?= $cell ?>"><?= $row['on'] ?></td>
                                        <td style="<?= $cell ?>"><?= $row['kind'] ?></td>
                                        <td style="<?= $cell ?>text-align:right;"><?= $row['score'] === null ? '—' : $row['score'] ?></td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <div style="font-size:12px;color:<?= $muted ?>;">No marks approved on the unit sheets for this course yet.</div>
                        @endif
                    </div>
                @endforeach
            @endforeach

            <div style="padding:6px 26px 26px;">
                <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:<?= $green ?>;
                            border-bottom:1px solid <?= $line ?>;padding-bottom:5px;font-weight:800;margin-bottom:6px;">
                    Certificates issued
                </div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="border-collapse:collapse;">
                    <tr>
                        <td style="<?= $head ?>">Course</td>
                        <td style="<?= $head ?>">Period</td>
                        <td style="<?= $head ?>">Certificate</td>
                        <td style="<?= $head ?>">Status</td>
                    </tr>
                    @foreach ($seats->filter(fn ($e) => $e->certification) as $e)
                        <tr>
                            <td style="<?= $cell ?>"><?= $e->cohort?->courseLabel() ?: '—' ?></td>
                            <td style="<?= $cell ?>"><?= $e->cohort?->periodLabel() ?: '—' ?></td>
                            <td style="<?= $cell ?>"><?= $e->certification->certificate_code ?: '—' ?></td>
                            <td style="<?= $cell ?>"><?= $e->certification->statusLabel() ?></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div style="background:#f3f8f2;border-top:1px solid <?= $line ?>;padding:16px 26px;font-size:12px;color:<?= $muted ?>;">
            Issued by the Training department of {{ $brandName }} on {{ now()->format('d M Y') }}.
            @if ($issued) The latest certificate on this record was signed on {{ $issued->format('d M Y') }}.@endif
            Marks are those the Registrar approved on the trainer's unit sheets; a track of 100% with the attendance
            minimum met is what lets a certificate be put forward.
        </div>
    </div>
</div>

<p class="foot">
    Quoting {{ $ctp->ctp_code ?: $ctp->reference }} with this transcript is how the office matches it to the trainee it belongs to.
</p>
</body>
</html>
