<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, true)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();
        if (! in_array($user->role, ['admin', 'superadmin', 'ctp', 'fundi'], true)) {
            Auth::logout();

            return back()->withErrors(['email' => 'This portal is for programme staff and members only.']);
        }
        if (! $user->active) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account has been deactivated. Contact a super admin.']);
        }

        return redirect()->intended(in_array($user->role, ['ctp', 'fundi'], true)
            ? route('portal.dashboard')
            : route('admin.departments'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Signed out.');
    }
}
