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

    /**
     * code => "Label — description". The office can reword a grade on the CMS, but a grade the programme's code
     * carries is always on the form — otherwise a grade added in the app would be missing from the public page
     * until somebody remembered to edit the site content.
     */
    private function fundiTiers(): array
    {
        $typed = collect(content_rows('membership', 'fundi_tiers'))
            ->filter(fn ($r) => filled($r['code'] ?? null))
            ->mapWithKeys(fn ($r) => [trim($r['code']) => trim(($r['label'] ?? $r['code'])
                .(! empty($r['desc']) ? ' — '.$r['desc'] : ''))]);

        return collect(FundiRegistration::TIERS)->merge($typed)->all();
    }

    public function apply()
    {
        return view('register.apply');
    }

    public function ctpForm(Request $request)
    {
        return view('register.ctp', $this->formData() + [
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'refPrefill' => Str::upper(trim((string) $request->query('ref'))),
        ]);
    }

    public function storeCtp(Request $request)
    {
        $data = $request->validate(CtpRegistration::applicationRules(), [], CtpRegistration::applicationLabels());

        $ctp = CtpRegistration::startApplication($data);
        $ctp->storeFiles($request);

        return redirect()->route('register.success', ['type' => 'ctp', 'reference' => $ctp->reference]);
    }

    public function fundiForm(Request $request)
    {
        return view('register.fundi', $this->formData() + [
            'tiers' => $this->fundiTiers(),
            'specialisations' => FundiRegistration::SPECIALISATIONS,
            'trainings' => \App\Models\Training::active()->get(['id', 'name', 'code']),
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
            // The county comes from the list the applicant served, so it is never typed and never blocks them.
            'county' => 'nullable|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'mobiliser_code' => 'nullable|string|max:20',
            'specialisations' => 'required|array|min:1',
            'specialisations.*' => 'in:'.implode(',', FundiRegistration::SPECIALISATIONS),
            'service_counties' => 'required|array|min:1',
            'service_counties.*' => 'string|max:100',
            // A Trainer teaches a field of the programme's own list. It is not held to their trade skills —
            // somebody who trains CTPs in Mathematics may well also wire houses.
            'training_field_ids' => 'required_if:tier,trainer|nullable|array|min:1|max:12',
            'training_field_ids.*' => 'integer|exists:trainings,id',
            // A junior or an attaché fresh from training may have nobody to name yet, so the referee is optional.
            'referee_name' => 'nullable|string|max:150',
            'referee_phone' => 'nullable|string|max:20',
            'referee_role' => 'nullable|string|max:150',
            'id_scan' => 'required|file|mimes:jpg,jpeg,png,pdf|max:3072',
            'passport_photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'cv' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
        ], [], [
            'national_id' => 'National ID', 'id_scan' => 'National ID scan',
            'sub_county' => 'Sub-county', 'mobiliser_code' => 'Mobilising CTP code',
            'cv' => 'CV', 'training_field_ids' => 'Field of training',
            'passport_photo' => 'Profile photo',
        ]);

        $ref = $this->reference('EF');
        // The same person cannot sit on both registers under different cases or spellings.
        \App\Support\Accounts::guard($data);
        $files = ['id_scan', 'cv', 'passport_photo'];
        $record = collect($data)->except(array_merge($files, ['mobiliser_code']))->all();
        $record['mobilised_by_ctp'] = CtpRegistration::resolveReferrer($data['mobiliser_code'] ?? null, $data)?->id;
        $record['reference'] = $ref;
        // Nothing gates an application on payment — it goes straight to the review queue.
        $record['status'] = 'pending_review';
        $record['specialisations'] = array_values(array_unique($data['specialisations']));
        $record['service_counties'] = array_values(array_unique($data['service_counties']));
        $record['county'] = ($data['county'] ?? null) ?: ($record['service_counties'][0] ?? null);
        $record['training_field_ids'] = array_values(array_unique(array_map('intval', $data['training_field_ids'] ?? []))) ?: null;

        $fundi = FundiRegistration::create($record);

        $dir = 'registrations/fundi/'.$ref;
        $store = fn ($f) => $f->store($dir, 'local');
        if ($request->hasFile('passport_photo')) {
            $fundi->passport_photo_path = $store($request->file('passport_photo'));
        }
        if ($request->hasFile('id_scan')) {
            $fundi->id_scan_path = $store($request->file('id_scan'));
        }
        if ($request->hasFile('cv')) {
            $fundi->cv_path = $store($request->file('cv'));
        }
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
