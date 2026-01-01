<?php

declare(strict_types=1);

use App\Actions\Telegram\SetupTelegramWebhook;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sets up webhook successfully', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    Setting::setValue('telegram_bot_token', 'test-token', true);

    $result = SetupTelegramWebhook::run();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Webhook configured successfully');
    expect(Setting::getValue('telegram_webhook_secret'))->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'setWebhook')
            && str_contains($request['url'], '/api/telegram/webhook')
            && in_array('message', $request['allowed_updates'])
            && in_array('message_reaction', $request['allowed_updates']);
    });
});

it('fails without bot token', function (): void {
    $result = SetupTelegramWebhook::run();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Bot token not configured');
});

it('returns error message from telegram api', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => false,
            'description' => 'Bad Request: invalid token',
        ]),
    ]);

    Setting::setValue('telegram_bot_token', 'invalid-token', true);

    $result = SetupTelegramWebhook::run();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Bad Request: invalid token');
});

it('removes webhook successfully', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_webhook_secret', 'old-secret', true);

    $result = SetupTelegramWebhook::make()->remove();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Webhook removed');
    expect(Setting::getValue('telegram_webhook_secret'))->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'deleteWebhook');
    });
});

it('fails to remove webhook without bot token', function (): void {
    $result = SetupTelegramWebhook::make()->remove();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Bot token not configured');
});

it('reuses existing webhook secret on subsequent setup', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_webhook_secret', 'existing-secret', true);

    SetupTelegramWebhook::run();

    expect(Setting::getValue('telegram_webhook_secret'))->toBe('existing-secret');
});

it('gets webhook info when configured', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://example.com/api/telegram/webhook',
                'has_custom_certificate' => false,
                'pending_update_count' => 0,
            ],
        ]),
    ]);

    Setting::setValue('telegram_bot_token', 'test-token', true);

    $info = SetupTelegramWebhook::make()->getInfo();

    expect($info['configured'])->toBeTrue();
    expect($info['url'])->toBe('https://example.com/api/telegram/webhook');
    expect($info['error'])->toBeNull();
});

it('gets webhook info when not configured', function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => [
                'url' => '',
                'has_custom_certificate' => false,
                'pending_update_count' => 0,
            ],
        ]),
    ]);

    Setting::setValue('telegram_bot_token', 'test-token', true);

    $info = SetupTelegramWebhook::make()->getInfo();

    expect($info['configured'])->toBeFalse();
    expect($info['url'])->toBeNull();
    expect($info['error'])->toBeNull();
});

it('returns not configured when no bot token', function (): void {
    $info = SetupTelegramWebhook::make()->getInfo();

    expect($info['configured'])->toBeFalse();
    expect($info['url'])->toBeNull();
    expect($info['error'])->toBeNull();
});
