<?php

declare(strict_types=1);

namespace App\Actions\Telegram;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SetupTelegramWebhook
{
    use AsAction;

    private const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    /**
     * Setup the Telegram webhook with the current app URL.
     *
     * @return array{success: bool, message: string}
     */
    public function handle(): array
    {
        $botToken = Setting::getValue('telegram_bot_token');

        if (! $botToken) {
            return ['success' => false, 'message' => 'Bot token not configured'];
        }

        // Generate webhook secret if not exists
        $webhookSecret = Setting::getValue('telegram_webhook_secret');
        if (! $webhookSecret) {
            $webhookSecret = Str::random(64);
            Setting::setValue('telegram_webhook_secret', $webhookSecret, true);
        }

        $webhookUrl = rtrim(config('app.url'), '/').'/api/telegram/webhook';

        try {
            $response = Http::post(self::TELEGRAM_API_BASE.$botToken.'/setWebhook', [
                'url' => $webhookUrl,
                'secret_token' => $webhookSecret,
                'allowed_updates' => ['message', 'message_reaction'],
            ]);

            if ($response->successful() && $response->json('ok')) {
                return ['success' => true, 'message' => 'Webhook configured successfully'];
            }

            return [
                'success' => false,
                'message' => $response->json('description', 'Unknown error'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get current webhook info from Telegram.
     *
     * @return array{configured: bool, url: string|null, error: string|null}
     */
    public function getInfo(): array
    {
        $botToken = Setting::getValue('telegram_bot_token');

        if (! $botToken) {
            return ['configured' => false, 'url' => null, 'error' => null];
        }

        try {
            $response = Http::timeout(5)->get(self::TELEGRAM_API_BASE.$botToken.'/getWebhookInfo');

            if ($response->successful() && $response->json('ok')) {
                $url = $response->json('result.url');

                return [
                    'configured' => ! empty($url),
                    'url' => $url ?: null,
                    'error' => null,
                ];
            }

            return [
                'configured' => false,
                'url' => null,
                'error' => $response->json('description', 'Unknown error'),
            ];
        } catch (\Exception $e) {
            return [
                'configured' => false,
                'url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove the Telegram webhook.
     *
     * @return array{success: bool, message: string}
     */
    public function remove(): array
    {
        $botToken = Setting::getValue('telegram_bot_token');

        if (! $botToken) {
            return ['success' => false, 'message' => 'Bot token not configured'];
        }

        try {
            $response = Http::post(self::TELEGRAM_API_BASE.$botToken.'/deleteWebhook');

            if ($response->successful() && $response->json('ok')) {
                // Clear the webhook secret
                Setting::setValue('telegram_webhook_secret', null);

                return ['success' => true, 'message' => 'Webhook removed'];
            }

            return [
                'success' => false,
                'message' => $response->json('description', 'Unknown error'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
