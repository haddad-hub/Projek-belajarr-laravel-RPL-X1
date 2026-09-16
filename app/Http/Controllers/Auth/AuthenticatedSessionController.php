<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Display the admin login view.
     */
    public function createAdmin(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user()->role !== $request->input('role')) {
            Auth::guard('web')->logout();

            return back()
                ->withInput($request->only('email', 'role'))
                ->withErrors(['role' => 'Role akun tidak sesuai dengan pilihan login.']);
        }

        return redirect()->intended(route('bonjek.launch', absolute: false));
    }

    /**
     * Handle the static Bonjek admin credentials.
     */
    public function adminStore(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($request->input('email') !== 'adminbonjek' || $request->input('password') !== 'admin123') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Username atau sandi admin salah.']);
        }

        $request->session()->regenerate();
        $request->session()->put('bonjek_admin', true);

        return redirect('/Bonjek/dashboard%20yojek.html');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $request->session()->forget('bonjek_admin');

        return redirect('/');
    }
}
