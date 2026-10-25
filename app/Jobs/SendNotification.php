<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\ProviderException;
use App\Models\Notification;
use App\Services\NotificationProviderClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 5;

    private const BASE_DELAY_SECONDS = 30;

    private const BACKOFF_MULTIPLIER = 3;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $notificationId) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationProviderClient $provider): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null || $notification->status !== NotificationStatus::Queued) {
            Log::info('Notification skipped', ['notification_id' => $this->notificationId]);

            return;
        }

        if (! $this->acquireRateLimitSlot($notification)) {
            self::dispatch($notification->id)
                ->onQueue($notification->priority->value)
                ->delay(now()->addSecond());

            return;
        }

        $claimed = Notification::where('id', $this->notificationId)
            ->where('status', NotificationStatus::Queued)
            ->update([
                'status' => NotificationStatus::Processing,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if ($claimed === 0) {
            Log::info('Notification skipped', ['notification_id' => $this->notificationId]);

            return;
        }

        $notification->refresh();

        try {
            $messageId = $provider->send($notification);
        } catch (ProviderException $e) {
            $this->handleFailure($notification, $e);

            return;
        }

        $notification->provider_message_id = $messageId;
        $notification->status = NotificationStatus::Sent;
        $notification->sent_at = now();
        $notification->last_error = null;
        $notification->save();

        Log::info('Notification sent', [
            'notification_id' => $notification->id,
            'attempt' => $notification->attempts,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Notification::where('id', $this->notificationId)
            ->where('status', NotificationStatus::Processing)
            ->update([
                'status' => NotificationStatus::Failed,
                'failed_at' => now(),
                'last_error' => $exception?->getMessage(),
            ]);
    }

    private function handleFailure(Notification $notification, ProviderException $e): void
    {
        $notification->last_error = $e->getMessage();

        if ($e->retryable && $notification->attempts < self::MAX_ATTEMPTS) {
            $delay = $this->backoffSeconds($notification->attempts);

            $notification->status = NotificationStatus::Queued;
            $notification->save();

            self::dispatch($notification->id)
                ->onQueue($notification->priority->value)
                ->delay(now()->addSeconds($delay));

            Log::warning('Notification delivery failed, will retry', [
                'notification_id' => $notification->id,
                'attempt' => $notification->attempts,
                'retry_in_seconds' => $delay,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $notification->status = NotificationStatus::Failed;
        $notification->failed_at = now();
        $notification->save();

        Log::error('Notification delivery failed permanently', [
            'notification_id' => $notification->id,
            'attempt' => $notification->attempts,
            'error' => $e->getMessage(),
        ]);
    }

    private function backoffSeconds(int $attempt): int
    {
        $base = self::BASE_DELAY_SECONDS * (self::BACKOFF_MULTIPLIER ** ($attempt - 1));

        return $base + random_int(0, intdiv($base, 5));
    }

    private function acquireRateLimitSlot(Notification $notification): bool
    {
        $key = 'provider-rate:'.$notification->channel->value;
        $limit = config('services.notification_provider.rate_limit_per_second');

        return RateLimiter::hit($key, 1) <= $limit;
    }
}
