<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    private function reference(string $prefix): string
    {
        return $prefix.'-APP-'.strtoupper(bin2hex(random_bytes(3)));
    }

    /** Per-table rules first, then the programme-wide one-person check — the same address cannot be on both registers. */
    private function uniqueIn(string $table, string $column): array
    {
        return [Rule::unique($table, $column)];
    }

    /** code => "Label — description" from the CMS, falling back to model constants. */
    private function fundiTiers(): array
    {
        $out = [];
        foreach (content_rows('membership', 'fundi_tiers') as $r) {
            if (empty($r['code'])) continue;
            $out[$r['code']] = trim(($r['label'] ?? $r['code']).(! empty($r['desc']) ? ' — '.$r['desc'] : ''));
        }
        return $out ?: FundiRegistration::TIERS;
    }

    private function ctpTracks(): array
    {
        $out = [];
        foreach (content_rows('membership', 'ctp_tracks') as $r) {
            if (empty($r['code'])) continue;
            $out[$r['code']] = ['label' => $r['label'] ?? $r['code'], 'desc' => $r['desc'] ?? ''];
        }
        if ($out) return $out;
        return collect(CtpRegistration::TRACKS)->map(fn ($l) => ['label' => $l, 'desc' => ''])->all();
    }

    public function ctpForm(Request $request)
    {
        return view('register.ctp', $this->formData() + [
            'tracks' => $this->ctpTracks(),
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'refPrefill' => Str::upper(trim((string) $request->query('ref'))),
        ]);
    }

    public function storeCtp(Request $request)
    {
        $rules = CtpRegistration::applicationRules();
        // The tracks on offer are CMS copy, so the form accepts whatever the office has published.
        $rules['track'] = 'required|in:'.implode(',', array_keys($this->ctpTracks()));

        $data = $request->validate($rules, [], CtpRegistration::applicationLabels());

        $ctp = CtpRegistration::startApplication($data);
        $ctp->storeFiles($request);

        return redirect()->route('register.success', ['type' => 'ctp', 'reference' => $ctp->reference]);
    }

    public function fundiForm(Request $request)
    {
        return view('register.fundi', $this->formData() + [
            'tiers' => $this->fundiTiers(),
            'specialisations' => FundiRegistration::SPECIALISATIONS,
            'refPrefill' => Str::upper(trim((string) $request->query('ref'))),
        ]);
    }

    public function storeFundi(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'national_id' => ['required', 'max:20', ...$this->uniqueIn('fundi_registrations', 'national_id')],
            'phone' => ['required', 'max:20', ...$this->uniqueIn('fundi_registrations', 'phone')],
            'email' => ['required', 'email', ...$this->uniqueIn('fundi_registrations', 'email')],
            'dob' => 'required|date|before:'.now()->subYears(16)->toDateString(),
            'gender' => 'required|in:male,female,other',
            'tier' => 'required|in:'.implode(',', array_keys($this->fundiTiers())),
            'county' => 'required|string|max:100',
            'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100',
            'mobiliser_code' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:150',
            'business_reg_no' => 'nullable|string|max:60',
            'years_in_business' => 'nullable|integer|min:0|max:70',
            'specialisations' => 'required|array|min:1',
            'specialisations.*' => 'in:'.implode(',', FundiRegistration::SPECIALISATIONS),
            'service_counties' => 'required|array|min:1',
            'service_counties.*' => 'string|max:100',
            'referee_name' => 'required|string|max:150',
            'referee_phone' => 'required|string|max:20',
            'referee_role' => 'required|string|max:150',
            'id_scan' => 'required|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'passport_photo' => 'required|image|mimes:jpg,jpeg,png|max:1024',
            'business_cert' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'trade_certs' => 'nullable|array|max:6',
            'trade_certs.*' => 'file|mimes:jpg,jpeg,png,pdf|max:3072',
            'portfolio' => 'nullable|array|max:6',
            'portfolio.*' => 'image|mimes:jpg,jpeg,png|max:3072',
        ], [], [
            'national_id' => 'National ID', 'id_scan' => 'National ID scan',
            'sub_county' => 'Sub-county', 'mobiliser_code' => 'Mobilising CTP code',
            'trade_certs.*' => 'Trade certificate', 'portfolio.*' => 'Portfolio photo',
            'passport_photo' => 'Profile photo',
        ]);

        $ref = $this->reference('EF');
        // The same person cannot sit on both registers under different cases or spellings.
        \App\Support\Accounts::guard($data);
        $files = ['id_scan', 'business_cert', 'trade_certs', 'portfolio', 'passport_photo'];
        $record = collect($data)->except(array_merge($files, ['mobiliser_code']))->all();
        $record['mobilised_by_ctp'] = CtpRegistration::resolveReferrer($data['mobiliser_code'] ?? null, $data)?->id;
        $record['reference'] = $ref;
        // Nothing gates an application on payment — it goes straight to the review queue.
        $record['status'] = 'pending_review';
        $record['specialisations'] = array_values(array_unique($data['specialisations']));
        $record['service_counties'] = array_values(array_unique($data['service_counties']));

        $fundi = FundiRegistration::create($record);

        $dir = 'registrations/fundi/'.$ref;
        $store = fn ($f) => $f->store($dir, 'local');
        if ($request->hasFile('passport_photo')) {
            $fundi->passport_photo_path = $store($request->file('passport_photo'));
        }
        if ($request->hasFile('id_scan')) {
            $fundi->id_scan_path = $store($request->file('id_scan'));
        }
        if ($request->hasFile('business_cert')) {
            $fundi->business_cert_path = $store($request->file('business_cert'));
        }
        $fundi->trade_cert_paths = collect($request->file('trade_certs', []))->filter()->map($store)->values()->all();
        $fundi->portfolio_paths = collect($request->file('portfolio', []))->filter()->map($store)->values()->all();
        $fundi->save();

        return redirect()->route('register.success', ['type' => 'fundi', 'reference' => $ref]);
    }

    public function success(string $type, string $reference)
    {
        $record = match ($type) {
            'ctp' => CtpRegistration::where('reference', $reference)->firstOrFail(),
            'fundi' => FundiRegistration::where('reference', $reference)->firstOrFail(),
        };

        return view('register.success', compact('type', 'record'));
    }

    private function formData(): array
    {
        return ['counties' => \App\Models\County::orderBy('name')->pluck('name')];
    }
}
