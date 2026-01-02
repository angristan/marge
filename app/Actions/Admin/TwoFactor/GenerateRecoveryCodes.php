<?php

declare(strict_types=1);

namespace App\Actions\Admin\TwoFactor;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateRecoveryCodes
{
    use AsAction;

    /**
     * Generate 8 recovery codes and store them hashed.
     *
     * @return array<string> Plain recovery codes (shown once)
     */
    public function handle(): array
    {
        $codes = [];
        $hashedCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(4).'-'.Str::random(4));
            $codes[] = $code;
            $hashedCodes[] = Hash::make($code);
        }

        Setting::setValue('totp_recovery_codes', json_encode($hashedCodes), encrypted: true);

        return $codes;
    }
}
