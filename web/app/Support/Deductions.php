<?php

namespace App\Support;

use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The statutory deductions Kenya puts on a paysheet, and the rates Human Resources runs them at.
 *
 * The bands and percentages are kept on one settings row rather than in code, because the Finance Act changes them
 * almost every year and the office — not a developer — is the one that must answer for the figures on a slip. The
 * defaults below are the rates the law carries today; a desk that has already moved edits them on the rates screen.
 *
 * What the rates produce is only ever a working: the office saves the sheet with the figures it wants, and a line
 * can be corrected by hand after the calculation.
 */
class Deductions
{
    public const SETTING = 'payroll_rates';

    /** The four columns the statutory rates write. Anything else on the line is left alone. */
    public const WRITTEN = ['paye_ksh', 'nssf_ksh', 'shif_ksh', 'levy_ksh'];

    public static function defaults(): array
    {
        return [
            // Monthly PAYE bands: tax each slice of taxable pay at its own rate. A blank ceiling is "above that".
            'bands' => [
                ['up_to' => 24000, 'pct' => 10],
                ['up_to' => 32333, 'pct' => 25],
                ['up_to' => 350000, 'pct' => 30],
                ['up_to' => 500000, 'pct' => 32.5],
                ['up_to' => null, 'pct' => 36.25],
            ],
            'relief_ksh' => 2400,
            'nssf_pct' => 6,
            'nssf_tier1' => 8000,
            'nssf_tier2' => 72000,
            'shif_pct' => 7.5,
            'shif_min' => 1500,
            'levy_pct' => 1.5,
        ];
    }

    /** The rates in force, with anything the office has saved laid over the defaults. */
    public static function rates(): array
    {
        $saved = json_decode((string) Setting::get(self::SETTING, ''), true);
        $rates = self::defaults();

        if (! is_array($saved)) {
            return $rates;
        }

        foreach ($rates as $key => $value) {
            if ($key === 'bands') {
                continue;
            }
            if (is_numeric($saved[$key] ?? null)) {
                $rates[$key] = (float) $saved[$key];
            }
        }

        $bands = collect($saved['bands'] ?? [])->filter(fn ($band) => is_array($band))->values();

        $rates['bands'] = $bands->isNotEmpty() ? $bands->all() : $rates['bands'];

        return $rates;
    }

    /** What the screen asks for, and what the programme keeps. */
    public static function save(Request $request, User $actor): array
    {
        abort_unless(Payroll::hr($actor), 403, 'The deduction rates are set by the Human Resources desk.');

        $data = $request->validate([
            'relief_ksh' => 'required|numeric|min:0|max:999999',
            'nssf_pct' => 'required|numeric|min:0|max:100',
            'nssf_tier1' => 'required|numeric|min:0|max:9999999',
            'nssf_tier2' => 'required|numeric|min:0|max:9999999|gte:nssf_tier1',
            'shif_pct' => 'required|numeric|min:0|max:100',
            'shif_min' => 'required|numeric|min:0|max:999999',
            'levy_pct' => 'required|numeric|min:0|max:100',
            'bands' => 'required|array|min:1|max:8',
            'bands.*.up_to' => 'nullable|numeric|min:1|max:99999999',
            'bands.*.pct' => 'required|numeric|min:0|max:100',
        ], [
            'bands.*.pct.required' => 'Every PAYE band needs its percentage — the last band may leave the ceiling blank.',
            'nssf_tier2.gte' => 'The NSSF upper band must sit above the lower band.',
        ], self::labels());

        $bands = collect($data['bands'])
            ->map(fn ($band) => ['up_to' => filled($band['up_to'] ?? null) ? round((float) $band['up_to'], 2) : null,
                'pct' => round((float) ($band['pct'] ?? 0), 2)])
            ->values();

        if ($bands->slice(0, -1)->contains(fn (array $band) => $band['up_to'] === null)) {
            throw ValidationException::withMessages(['bands' =>
                'Only the last PAYE band may be open — every band under it needs a ceiling.']);
        }

        $rates = collect(self::labels())->map(fn ($label, $key) => $key === 'bands' ? $bands->all()
            : round((float) $data[$key], 2))->all();

        Setting::set(self::SETTING, json_encode($rates));
        Audit::record(Setting::firstOrCreate(['key' => self::SETTING], ['value' => '{}']), 'updated',
            $actor->name.' set the payroll deduction rates — PAYE bands, relief, NSSF, SHIF and the housing levy.');

        return $rates;
    }

    public static function labels(): array
    {
        return [
            'relief_ksh' => 'Personal relief (per month)',
            'nssf_pct' => 'NSSF per cent',
            'nssf_tier1' => 'NSSF tier 1 up to',
            'nssf_tier2' => 'NSSF tier 2 up to',
            'shif_pct' => 'SHIF per cent',
            'shif_min' => 'SHIF minimum (per month)',
            'levy_pct' => 'Housing levy per cent',
        ] + ['bands' => 'PAYE bands'];
    }

    /** The four statutory figures on a gross pay. */
    public static function statutory(float $gross): array
    {
        $rates = self::rates();

        $nssf = $gross > 0 ? self::nssf($gross, $rates) : 0.0;
        $shif = $gross > 0 ? max(self::round($gross * $rates['shif_pct'] / 100), min((float) $rates['shif_min'], $gross)) : 0.0;
        $levy = self::round($gross * $rates['levy_pct'] / 100);

        return [
            'nssf_ksh' => $nssf,
            'shif_ksh' => $shif,
            'levy_ksh' => $levy,
            'paye_ksh' => self::paye(max($gross - $nssf - $shif, 0), $rates),
        ];
    }

    /** Taxable pay after the NSSF and SHIF deductions, sliced across the bands, less the relief. */
    public static function paye(float $taxable, array $rates): float
    {
        $from = 0.0;
        $tax = 0.0;

        foreach ($rates['bands'] as $band) {
            $ceiling = $band['up_to'] === null ? INF : (float) $band['up_to'];

            if ($taxable > $from) {
                $tax += (min($taxable, $ceiling) - $from) * (float) $band['pct'] / 100;
            }

            $from = $ceiling;
        }

        return self::round(max($tax - (float) $rates['relief_ksh'], 0));
    }

    private static function nssf(float $gross, array $rates): float
    {
        $pct = (float) $rates['nssf_pct'] / 100;
        $tier1 = min($gross, (float) $rates['nssf_tier1']);
        $tier2 = max(min($gross, (float) $rates['nssf_tier2']) - (float) $rates['nssf_tier1'], 0);

        return self::round(($tier1 + $tier2) * $pct);
    }

    /** Work one person's line from the rates, leaving their other deduction as the office typed it. */
    public static function apply(PayrollLine $line): array
    {
        abort_unless(Payroll::hr(), 403, 'Payroll is prepared by the Human Resources desk.');
        $line->run->mustBe('prepared', 'returned');

        abort_if((float) $line->gross_ksh <= 0, 422,
            $line->name.' has no gross pay on this sheet yet — set the basic pay and allowances first.');

        $figures = self::statutory((float) $line->gross_ksh);
        $line->fill($figures)->recompute()->save();

        return $figures;
    }

    /** The whole sheet at once. A month is worked line by line, and every figure stays open to correction. */
    public static function applyRun(PayrollRun $run, User $actor): array
    {
        abort_unless(Payroll::hr($actor), 403, 'Payroll is prepared by the Human Resources desk.');
        $run->mustBe('prepared', 'returned');

        $done = 0;
        $skipped = [];

        foreach ($run->payableLines() as $line) {
            if ((float) $line->gross_ksh <= 0) {
                continue;
            }

            try {
                self::apply($line);
                $done++;
            } catch (HttpException) {
                // A line whose statutory figures would eat the whole of its pay is left as the office typed it.
                $skipped[] = $line->name;
            }
        }

        Audit::record($run, 'updated', $actor->name.' worked out the statutory deductions on the payroll for '
            .$run->periodLabel().' — '.$done.' lines'.($skipped ? ', leaving '.implode(', ', $skipped) : '').'.');

        return [$done, $skipped];
    }

    /** The working behind one person's figures, so a slip can be explained to the person it pays. */
    public static function worked(PayrollLine $line): array
    {
        $rates = self::rates();
        $gross = (float) $line->gross_ksh;
        $taxable = max($gross - (float) $line->nssf_ksh - (float) $line->shif_ksh, 0);

        return [
            'Taxable pay (gross less NSSF and SHIF)' => Finance::ksh($taxable),
            'PAYE bands' => collect($rates['bands'])
                ->map(fn ($b) => ($b['up_to'] === null ? 'above' : 'to '.Finance::ksh((float) $b['up_to'])).
                    ' at '.$b['pct'].'%')->implode(', '),
            'Personal relief' => Finance::ksh((float) $rates['relief_ksh']),
            'NSSF' => $rates['nssf_pct'].'% of the two tiers ('.Finance::ksh((float) $rates['nssf_tier1']).
                ' and '.Finance::ksh((float) $rates['nssf_tier2']).')',
            'SHIF' => $rates['shif_pct'].'% of gross, minimum '.Finance::ksh((float) $rates['shif_min']),
            'Housing levy' => $rates['levy_pct'].'% of gross',
        ];
    }

    private static function round(float $value): float
    {
        return round($value, 2);
    }
}
