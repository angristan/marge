<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Lorisleiva\Actions\Concerns\AsAction;
use PragmaRX\Google2FA\Google2FA;

class GenerateTwoFactorSecret
{
    use AsAction;

    /**
     * Generate a new TOTP secret and return it with QR code URL.
     *
     * @return array{secret: string, qr_code_url: string}
     */
    public function handle(): array
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        // Store temporarily (not enabled yet)
        Setting::setValue('totp_secret', $secret, encrypted: true);

        $siteName = Setting::getValue('site_name', 'Bulla');
        $adminEmail = Setting::getValue('admin_email', 'admin');

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $siteName,
            $adminEmail,
            $secret
        );

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }
}
