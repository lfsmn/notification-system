<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;

class NotificationService
{
    public function create(array $data): Notification
    {
        $data['status'] = isset($data['scheduled_at']) ? NotificationStatus::Pending : NotificationStatus::Queued;

        $key = $data['idempotency_key'] ?? null;

        $notification = $key === null ? Notification::create($data) : Notification::createOrFirst(['idempotency_key' => $key], $data);

        if ($notification->wasRecentlyCreated && $notification->status === NotificationStatus::Queued) {
            //kuyruğa eklenecek
        }

        return $notification;
    }
}
