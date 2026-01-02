<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Lorisleiva\Actions\Concerns\AsAction;
use PragmaRX\Google2FA\Google2FA;

class EnableTwoFactor
{
    use AsAction;

    /**
     * Enable 2FA after verifying the code.
     *
     * @return array{success: bool, message?: string, recovery_codes?: array<string>}
     */
    public function handle(string $code): array
    {
        $secret = Setting::getValue('totp_secret');

        if (! $secret) {
            return ['success' => false, 'message' => 'No 2FA secret found. Generate one first.'];
        }

        $google2fa = new Google2FA;

        if (! $google2fa->verifyKey($secret, $code, 1)) {
            return ['success' => false, 'message' => 'Invalid verification code.'];
        }

        // Generate recovery codes
        $recoveryCodes = GenerateRecoveryCodes::run();

        Setting::setValue('totp_enabled', 'true');
        Setting::setValue('totp_confirmed_at', now()->toIso8601String());

        return [
            'success' => true,
            'recovery_codes' => $recoveryCodes,
        ];
    }
}
