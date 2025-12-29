<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthenticateAdmin
{
    use AsAction;

    /**
     * Authenticate admin credentials.
     * Returns true if credentials are valid, false otherwise.
     */
    public function handle(string $username, string $password): bool
    {
        $storedUsername = Setting::getValue('admin_username');
        $storedPassword = Setting::getValue('admin_password');

        if ($storedUsername === null || $storedPassword === null) {
            return false;
        }

        if ($username !== $storedUsername) {
            return false;
        }

        return Hash::check($password, $storedPassword);
    }

    /**
     * Check if admin is set up.
     */
    public static function isSetup(): bool
    {
        return Setting::getValue('admin_username') !== null
            && Setting::getValue('admin_password') !== null;
    }
}
