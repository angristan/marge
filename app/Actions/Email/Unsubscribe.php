<?php

declare(strict_types=1);

namespace App\Actions\Email;

use App\Models\NotificationSubscription;
use Lorisleiva\Actions\Concerns\AsAction;

class Unsubscribe
{
    use AsAction;

    public function handle(string $token, bool $all = false): bool
    {
        $subscription = NotificationSubscription::where('unsubscribe_token', $token)->first();

        if (! $subscription) {
            return false;
        }

        if ($all) {
            // Unsubscribe from all notifications for this email
            NotificationSubscription::where('email', $subscription->email)
                ->whereNull('unsubscribed_at')
                ->update(['unsubscribed_at' => now()]);

            return true;
        }

        if ($subscription->unsubscribed_at) {
            // Already unsubscribed
            return true;
        }

        $subscription->update(['unsubscribed_at' => now()]);

        return true;
    }
}
