<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CtpRegistration;
use App\Models\Setting;
use App\Support\Finance;
use App\Support\MailCheck;
use Illuminate\Http\Request;

/**
 * Module 12  — the programme switches. Only the keys the code already reads are offered, and changing one is
 * an executive's action, the same rule as issuing a certificate or releasing money. Page wording and branding stay
 * in the content editor, which is Module 1's screen.
 */
class ProgrammeController extends Controller
{
    public function index()
    {
        $rows = collect(Setting::EDITABLE)->map(fn ($meta, $key) => [
            'key' => $key,
            'label' => $meta['label'],
            'type' => $meta['type'],
            'read' => $meta['read'],
            'value' => Setting::get($key, ''),
        ])->values();

        return view('admin.me-gis.settings', [
            'rows' => $rows,
            'canEdit' => Finance::isExecutive(),
            'mobilisation' => CtpRegistration::MOBILISATION_TIERS,
            'mail' => MailCheck::settings(),
        ]);
    }

    /**
     * The office cannot run a command on shared hosting, so this posts one message through the settings in .env
     * and puts the mail server's own answer on the screen.
     */
    public function mailCheck(Request $request)
    {
        abort_unless($request->user()->role === 'superadmin', 403,
            'Only a super admin can test what the programme sends through.');

        $result = MailCheck::deliver($request->user()->email);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function update(Request $request)
    {
        abort_unless(Finance::isExecutive(), 403,
            'Programme settings are changed by a programme executive. Staff can see the current values.');

        $data = $request->validate(
            collect(Setting::EDITABLE)->mapWithKeys(fn ($meta, $key) => [$key => match ($meta['type']) {
                'money' => 'required|numeric|min:0|max:10000000',
                default => 'required|string|max:190',
            }])->all(),
            [],
            collect(Setting::EDITABLE)->mapWithKeys(fn ($meta, $key) => [$key => $meta['label']])->all()
        );

        foreach (Setting::EDITABLE as $key => $meta) {
            $value = trim((string) $data[$key]);
            $current = (string) Setting::get($key, '');

            if ($value !== $current) {
                Setting::set($key, $meta['type'] === 'money' ? (string) (float) $value : $value);
            }
        }

        return back()->with('status', 'Programme settings saved. Any fee already paid keeps the amount that was charged at the time — a change here only applies to applications from now on.');
    }
}
