<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotificationProviderClient
{
    public function send(Notification $notification): string
    {
        $response = Http::timeout(config('services.notification_provider.timeout'))
            ->acceptJson()
            ->post(config('services.notification_provider.url'), [
                'to' => $notification->recipient,
                'channel' => $notification->channel->value,
                'content' => $notification->content,
            ]);

        if ($response->status() !== 202) {
            throw new RuntimeException("Provider returned HTTP {$response->status()}");
        }

        return $response->json('messageId');
    }
}