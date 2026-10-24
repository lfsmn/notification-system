<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\NotificationProviderClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Queueable;
    
    public function __construct(public string $notificationId)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(NotificationProviderClient $provider): void
    {
        $claimed = Notification::where('id', $this->notificationId)
            ->where('status', NotificationStatus::Queued)
            ->update(['status' => NotificationStatus::Processing]);

        if ($claimed === 0) {
            Log::info('Notification skipped', ['notification_id' => $this->notificationId]);

            return;
        }

        $notification = Notification::findOrFail($this->notificationId);

        $notification->provider_message_id = $provider->send($notification);
        
        $notification->status = NotificationStatus::Sent;
        $notification->sent_at = now();
        $notification->save();


        Log::info('Notification sent', ['notification_id' => $notification->id]);
    }
}
