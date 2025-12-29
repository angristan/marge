<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show settings page.
     */
    public function index(): Response
    {
        $settings = UpdateSettings::getAll();

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // General
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_url' => ['nullable', 'url', 'max:1024'],

            // Moderation
            'moderation_mode' => ['nullable', 'string', 'in:none,all,unverified'],
            'require_author' => ['nullable', 'boolean'],
            'require_email' => ['nullable', 'boolean'],

            // Limits
            'max_depth' => ['nullable', 'integer', 'min:1', 'max:10'],
            'edit_window_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],

            // Spam
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:100'],
            'spam_min_time_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'max_links' => ['nullable', 'integer', 'min:0', 'max:50'],
            'blocked_words' => ['nullable', 'string', 'max:10000'],
            'blocked_ips' => ['nullable', 'string', 'max:10000'],

            // CORS
            'allowed_origins' => ['nullable', 'string', 'max:2000'],

            // Appearance
            'custom_css' => ['nullable', 'string', 'max:50000'],

            // Email
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Convert booleans to strings
        if (isset($validated['require_author'])) {
            $validated['require_author'] = $validated['require_author'] ? 'true' : 'false';
        }
        if (isset($validated['require_email'])) {
            $validated['require_email'] = $validated['require_email'] ? 'true' : 'false';
        }

        UpdateSettings::run($validated);

        return back()->with('success', 'Settings updated.');
    }
}
