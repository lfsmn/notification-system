<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use App\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Context;

class NotificationProviderClient
{
    public function send(Notification $notification): string
    {
       try {
            $response = Http::timeout(config('services.notification_provider.timeout'))
                ->acceptJson()
                ->withHeaders(array_filter([
                    'Idempotency-Key' => $notification->id,
                    'X-Correlation-ID' => Context::get('correlation_id'),
                ]))
                ->post(config('services.notification_provider.url'), [
                    'to' => $notification->recipient,
                    'channel' => $notification->channel->value,
                    'content' => $notification->content,
                ]);
        } catch (ConnectionException $e) {
            throw new ProviderException('Provider connection failed: '.$e->getMessage(), retryable: true);
        }

        if ($response->status() === 202) {
            return (string) $response->json('messageId');
        }

        $retryable = $response->status() === 429 || $response->serverError();

        throw new ProviderException("Provider returned HTTP {$response->status()}", $retryable);
    
    }
}