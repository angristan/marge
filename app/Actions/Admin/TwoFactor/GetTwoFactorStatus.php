<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTwoFactorStatus
{
    use AsAction;

    /**
     * Get the current 2FA status.
     *
     * @return array{enabled: bool, confirmed_at: string|null, recovery_codes_remaining: int}
     */
    public function handle(): array
    {
        $enabled = Setting::getValue('totp_enabled', 'false') === 'true';
        $confirmedAt = Setting::getValue('totp_confirmed_at');
        $recoveryCodesJson = Setting::getValue('totp_recovery_codes');

        $recoveryCodesRemaining = 0;
        if ($recoveryCodesJson) {
            $recoveryCodes = json_decode($recoveryCodesJson, true);
            $recoveryCodesRemaining = is_array($recoveryCodes) ? count($recoveryCodes) : 0;
        }

        return [
            'enabled' => $enabled,
            'confirmed_at' => $confirmedAt,
            'recovery_codes_remaining' => $recoveryCodesRemaining,
        ];
    }
}
