<?php

declare(strict_types=1);

use App\Actions\Admin\SetupAdmin;
use App\Models\Setting;
use PragmaRX\Google2FA\Google2FA;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    SetupAdmin::run('admin', 'admin@example.com', 'password123', 'Test Site', 'https://example.com');
});

describe('Two-Factor Authentication Setup', function (): void {
    beforeEach(function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);
    });

    it('generates 2FA secret', function (): void {
        $response = $this->postJson('/admin/settings/2fa/setup');

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_code_svg']);

        expect($response->json('secret'))->toHaveLength(32);
        expect($response->json('qr_code_svg'))->toContain('<svg');
    });

    it('stores secret in settings', function (): void {
        $this->postJson('/admin/settings/2fa/setup');

        expect(Setting::getValue('totp_secret'))->not->toBeNull();
    });

    it('enables 2FA with valid code', function (): void {
        // Generate secret
        $response = $this->postJson('/admin/settings/2fa/setup');
        $secret = $response->json('secret');

        // Generate valid TOTP code
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/admin/settings/2fa/enable', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'recovery_codes']);

        expect(Setting::getValue('totp_enabled'))->toBe('true');
        expect(Setting::getValue('totp_secret'))->not->toBeNull();
        expect($response->json('recovery_codes'))->toHaveCount(8);
    });

    it('rejects invalid code when enabling 2FA', function (): void {
        $this->postJson('/admin/settings/2fa/setup');

        $response = $this->postJson('/admin/settings/2fa/enable', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid verification code.']);

        expect(Setting::getValue('totp_enabled', 'false'))->toBe('false');
    });

    it('requires setup before enabling', function (): void {
        $response = $this->postJson('/admin/settings/2fa/enable', [
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'No 2FA secret found. Generate one first.']);
    });
});

describe('Two-Factor Authentication Disable', function (): void {
    beforeEach(function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        // Setup and enable 2FA, capture recovery codes
        $response = $this->postJson('/admin/settings/2fa/setup');
        $secret = $response->json('secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);
        $enableResponse = $this->postJson('/admin/settings/2fa/enable', ['code' => $code]);
        $this->recoveryCodes = $enableResponse->json('recovery_codes');
    });

    it('disables 2FA with valid TOTP code', function (): void {
        $secret = Setting::getValue('totp_secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/admin/settings/2fa/disable', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect(Setting::getValue('totp_enabled', 'false'))->toBe('false');
    });

    it('disables 2FA with valid recovery code', function (): void {
        $recoveryCode = $this->recoveryCodes[0];

        $response = $this->postJson('/admin/settings/2fa/disable', [
            'code' => $recoveryCode,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect(Setting::getValue('totp_enabled', 'false'))->toBe('false');
    });

    it('rejects invalid code when disabling', function (): void {
        $response = $this->postJson('/admin/settings/2fa/disable', [
            'code' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid 2FA code.']);

        expect(Setting::getValue('totp_enabled'))->toBe('true');
    });
});

describe('Recovery Codes', function (): void {
    beforeEach(function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        // Setup and enable 2FA
        $response = $this->postJson('/admin/settings/2fa/setup');
        $secret = $response->json('secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);
        $this->postJson('/admin/settings/2fa/enable', ['code' => $code]);
    });

    it('regenerates recovery codes with valid TOTP', function (): void {
        $secret = Setting::getValue('totp_secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $oldCodes = Setting::getValue('totp_recovery_codes');

        $response = $this->postJson('/admin/settings/2fa/recovery-codes', [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'recovery_codes']);

        expect($response->json('recovery_codes'))->toHaveCount(8);

        $newCodes = Setting::getValue('totp_recovery_codes');
        expect($newCodes)->not->toBe($oldCodes);
    });

    it('rejects invalid code when regenerating', function (): void {
        $response = $this->postJson('/admin/settings/2fa/recovery-codes', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid 2FA code.']);
    });
});

describe('Two-Factor Login Challenge', function (): void {
    beforeEach(function (): void {
        // Setup and enable 2FA while logged in
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/admin/settings/2fa/setup');
        $secret = $response->json('secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);
        $enableResponse = $this->postJson('/admin/settings/2fa/enable', ['code' => $code]);
        $this->recoveryCodes = $enableResponse->json('recovery_codes');

        // Logout
        $this->post('/admin/logout');
    });

    it('redirects to 2FA challenge after password verification', function (): void {
        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/login/2fa');
        expect(session('admin_password_verified'))->toBeTrue();
        expect(session('admin_authenticated'))->toBeNull();
    });

    it('shows 2FA challenge page', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response = $this->get('/admin/login/2fa');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/TwoFactorChallenge'));
    });

    it('completes login with valid TOTP code', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $secret = Setting::getValue('totp_secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->post('/admin/login/2fa', ['code' => $code]);

        $response->assertRedirect('/admin');
        expect(session('admin_authenticated'))->toBeTrue();
        expect(session('admin_password_verified'))->toBeNull();
    });

    it('completes login with recovery code', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $recoveryCode = $this->recoveryCodes[0];

        $response = $this->post('/admin/login/2fa', ['code' => $recoveryCode]);

        $response->assertRedirect('/admin');
        expect(session('admin_authenticated'))->toBeTrue();
    });

    it('marks recovery code as used', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $recoveryCode = $this->recoveryCodes[0];

        $this->post('/admin/login/2fa', ['code' => $recoveryCode]);

        // Try to use same recovery code again
        $this->post('/admin/logout');
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response = $this->post('/admin/login/2fa', ['code' => $recoveryCode]);

        $response->assertSessionHasErrors(['code']);
    });

    it('rejects invalid 2FA code', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response = $this->post('/admin/login/2fa', ['code' => '000000']);

        $response->assertSessionHasErrors(['code']);
        expect(session('admin_authenticated'))->toBeNull();
    });

    it('redirects to login if password not verified', function (): void {
        $response = $this->get('/admin/login/2fa');

        $response->assertRedirect('/admin/login');
    });

    it('expires password verification after 5 minutes', function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        // Simulate 6 minutes passing
        session(['admin_password_verified_at' => now()->subMinutes(6)->timestamp]);

        $response = $this->get('/admin/login/2fa');

        $response->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['username']);
    });
});

describe('2FA Status in Settings', function (): void {
    beforeEach(function (): void {
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);
    });

    it('shows 2FA status in settings', function (): void {
        $response = $this->get('/admin/settings');

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Settings/Index')
                    ->has('twoFactor')
                    ->has('twoFactor.enabled')
                    ->has('twoFactor.recovery_codes_remaining')
            );
    });

    it('shows disabled status when 2FA not enabled', function (): void {
        $response = $this->get('/admin/settings');

        $response->assertInertia(
            fn ($page) => $page
                ->where('twoFactor.enabled', false)
        );
    });

    it('shows enabled status when 2FA is active', function (): void {
        // Enable 2FA
        $response = $this->postJson('/admin/settings/2fa/setup');
        $secret = $response->json('secret');
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);
        $this->postJson('/admin/settings/2fa/enable', ['code' => $code]);

        $response = $this->get('/admin/settings');

        $response->assertInertia(
            fn ($page) => $page
                ->where('twoFactor.enabled', true)
                ->where('twoFactor.recovery_codes_remaining', 8)
        );
    });
});
