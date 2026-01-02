<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AuthenticateAdmin;
use App\Actions\Admin\TwoFactor\VerifyTwoFactorCode;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (AuthenticateAdmin::run($validated['username'], $validated['password'])) {
            $request->session()->regenerate();

            // Check if 2FA is enabled
            if (Setting::getValue('totp_enabled', 'false') === 'true') {
                $request->session()->put('admin_password_verified', true);
                $request->session()->put('admin_password_verified_at', now()->timestamp);

                return redirect()->route('admin.login.2fa');
            }

            $request->session()->put('admin_authenticated', true);

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'username' => 'Invalid credentials.',
        ])->onlyInput('username');
    }

    /**
     * Show two-factor authentication challenge.
     */
    public function showTwoFactorChallenge(Request $request): Response|RedirectResponse
    {
        // Must have verified password first
        if (! $request->session()->get('admin_password_verified')) {
            return redirect()->route('admin.login');
        }

        // Expire after 5 minutes
        $verifiedAt = $request->session()->get('admin_password_verified_at', 0);
        if (now()->timestamp - $verifiedAt > 300) {
            $request->session()->forget(['admin_password_verified', 'admin_password_verified_at']);

            return redirect()->route('admin.login')->withErrors([
                'username' => 'Session expired. Please login again.',
            ]);
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify two-factor authentication code.
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        // Must have verified password first
        if (! $request->session()->get('admin_password_verified')) {
            return redirect()->route('admin.login');
        }

        // Expire after 5 minutes
        $verifiedAt = $request->session()->get('admin_password_verified_at', 0);
        if (now()->timestamp - $verifiedAt > 300) {
            $request->session()->forget(['admin_password_verified', 'admin_password_verified_at']);

            return redirect()->route('admin.login')->withErrors([
                'username' => 'Session expired. Please login again.',
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! VerifyTwoFactorCode::run($validated['code'])) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        // Clear intermediate state and fully authenticate
        $request->session()->forget(['admin_password_verified', 'admin_password_verified_at']);
        $request->session()->put('admin_authenticated', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'admin_authenticated',
            'admin_password_verified',
            'admin_password_verified_at',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
