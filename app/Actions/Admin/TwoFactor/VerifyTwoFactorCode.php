<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use PragmaRX\Google2FA\Google2FA;

class VerifyTwoFactorCode
{
    use AsAction;

    /**
     * Verify a TOTP code or recovery code.
     */
    public function handle(string $code): bool
    {
        // Normalize code (remove dashes/spaces for TOTP)
        $normalizedCode = preg_replace('/[^A-Za-z0-9]/', '', $code);

        // Try TOTP code first (6 digits)
        if (strlen($normalizedCode) === 6 && ctype_digit($normalizedCode)) {
            $secret = Setting::getValue('totp_secret');

            if ($secret) {
                $google2fa = new Google2FA;
                // Allow 1 window (30 seconds) before/after for clock skew
                if ($google2fa->verifyKey($secret, $normalizedCode, 1)) {
                    return true;
                }
            }
        }

        // Try recovery code (format: XXXX-XXXX)
        return $this->verifyRecoveryCode($code);
    }

    private function verifyRecoveryCode(string $code): bool
    {
        $hashedCodesJson = Setting::getValue('totp_recovery_codes');

        if (! $hashedCodesJson) {
            return false;
        }

        $hashedCodes = json_decode($hashedCodesJson, true);

        if (! is_array($hashedCodes)) {
            return false;
        }

        // Normalize: uppercase and ensure dash format
        $normalizedCode = Str::upper(trim($code));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($normalizedCode, $hashedCode)) {
                // Remove used recovery code
                unset($hashedCodes[$index]);
                Setting::setValue(
                    'totp_recovery_codes',
                    json_encode(array_values($hashedCodes)),
                    encrypted: true
                );

                return true;
            }
        }

        return false;
    }
}
