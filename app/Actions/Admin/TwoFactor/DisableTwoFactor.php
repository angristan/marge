<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class DisableTwoFactor
{
    use AsAction;

    /**
     * Disable 2FA after verifying the current code.
     *
     * @return array{success: bool, message?: string}
     */
    public function handle(string $code): array
    {
        if (! VerifyTwoFactorCode::run($code)) {
            return ['success' => false, 'message' => 'Invalid 2FA code.'];
        }

        Setting::setValue('totp_secret', null);
        Setting::setValue('totp_enabled', 'false');
        Setting::setValue('totp_recovery_codes', null);
        Setting::setValue('totp_confirmed_at', null);

        return ['success' => true];
    }
}
