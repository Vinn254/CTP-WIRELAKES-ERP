<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\Partner;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home', [
            'partners' => Partner::where('is_listed', true)->orderBy('name')->get(),
        ]);
    }

    public function about()
    {
        return view('pages.about');
    }

    public function whatWeDo()
    {
        return view('pages.what-we-do');
    }

    public function faq()
    {
        return view('pages.faq');
    }

    public function partners()
    {
        return view('pages.partners', [
            'partners' => Partner::where('is_listed', true)->orderBy('tier')->orderBy('name')->get(),
        ]);
    }

    public function storePartner(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'website' => 'nullable|url|max:200',
            'contact_email' => 'required|email|max:120',
            'tier' => 'nullable|in:platinum,gold,silver',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:1024',
        ]);

        if (isset($data['logo'])) {
            unset($data['logo']);
        }

        $partner = Partner::create($data + ['is_listed' => false]);

        if ($request->hasFile('logo')) {
            $partner->logo_path = $request->file('logo')->store('registrations/partner/'.$partner->id, 'local');
            $partner->save();
        }

        return back()->with('status', 'Thank you! Our partnership team will review your submission and get back to you.');
    }

    public function donate()
    {
        return view('pages.donate');
    }

    public function storeDonation(Request $request)
    {
        $data = $request->validate([
            'donor_name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:mpesa,card,bank',
            'frequency' => 'required|in:one_time,monthly,yearly',
            'message' => 'nullable|string|max:1000',
            'anonymous' => 'boolean',
        ]);

        Donation::create($data);

        return back()->with('status', 'Asante! Your pledge has been recorded. Secure payment (M-Pesa / card / bank) is being switched on in the next phase — we will contact you to complete it.');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function storeContact(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:120',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string|max:2000',
        ]);

        ContactMessage::create($request->all());

        return back()->with('status', 'Message received — we will get back to you shortly.');
    }
}
