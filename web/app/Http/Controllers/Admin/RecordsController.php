<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\Partner;
use App\Support\SafeUrl;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordsController extends Controller
{
    private function storeLogo(Request $request, Partner $partner): void
    {
        $dir = public_path('uploads/partners');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $request->file('logo');
        $name = Str::slug($partner->name).'-'.substr(bin2hex(random_bytes(3)), 0, 6).'.'.Uploads::imageExtension($file);
        $file->move($dir, $name);
        $partner->logo_path = $name;
    }

    public function partners()
    {
        return view('admin.records.partners', [
            'wall' => Partner::where('is_listed', true)->orderBy('tier')->orderBy('name')->get(),
            'requests' => Partner::where('is_listed', false)->latest()->get(),
        ]);
    }

    /** Staff post a partner straight onto the public wall. */
    public function storePartner(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:partners,name',
            'tier' => 'nullable|in:platinum,gold,silver',
            'caption' => 'nullable|string|max:180',
            'website' => SafeUrl::rule(),
            'logo' => 'required|image|mimes:'.Uploads::IMAGE_MIMES.'|max:2048',
        ], [], ['logo' => 'partner logo']);

        $partner = Partner::create([
            'name' => $data['name'],
            'tier' => $data['tier'] ?? null,
            'caption' => $data['caption'] ?? null,
            'website' => $data['website'] ?? null,
            'contact_email' => null,
            'source' => 'admin',
            'is_listed' => true,
        ]);

        $this->storeLogo($request, $partner);
        $partner->save();

        return back()->with('status', $partner->name.' is now on the public partner wall.');
    }

    public function updatePartner(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'tier' => 'nullable|in:platinum,gold,silver',
            'caption' => 'nullable|string|max:180',
            'website' => SafeUrl::rule(),
            'logo' => 'nullable|image|mimes:'.Uploads::IMAGE_MIMES.'|max:2048',
        ]);

        $partner->fill(collect($data)->except('logo')->all());
        if ($request->hasFile('logo')) {
            $this->storeLogo($request, $partner);
        }
        $partner->save();

        return back()->with('status', $partner->name.' updated.');
    }

    public function togglePartner(Partner $partner)
    {
        $partner->update(['is_listed' => ! $partner->is_listed]);

        return back()->with('status', $partner->name.($partner->is_listed ? ' is now listed on the public wall.' : ' is hidden from the public wall.'));
    }

    public function deletePartner(Partner $partner)
    {
        $name = $partner->name;
        $partner->delete();

        return back()->with('status', $name.' removed.');
    }

    public function donations()
    {
        return view('admin.records.donations', [
            'donations' => Donation::latest()->paginate(25),
            'total' => (float) Donation::sum('amount'),
            'monthTotal' => (float) Donation::where('created_at', '>=', now()->startOfMonth())->sum('amount'),
        ]);
    }

    /** The office records a gift that reached the programme outside the public Donate page. */
    public function storeDonation(Request $request)
    {
        $data = $request->validate([
            'donor_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:mpesa,card,bank',
            'frequency' => 'required|in:one_time,monthly,yearly',
            'impact_label' => 'nullable|string|max:190',
            'message' => 'nullable|string|max:1000',
        ]);
        $data['anonymous'] = $request->boolean('anonymous');

        Donation::create($data);

        return back()->with('status', 'Donation recorded in the register.');
    }

    public function messages()
    {
        return view('admin.records.messages', ['messages' => ContactMessage::latest()->paginate(25)]);
    }
}
