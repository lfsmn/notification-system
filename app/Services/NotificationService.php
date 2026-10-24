<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Jobs\SendNotification;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{

    public function create(array $data): Notification
    {
        $notification = $this->store($data);
        $this->dispatchIfNeeded($notification);

        return $notification;
    }


     public function createBatch(array $items): array
    {
        [$batch, $notifications] = DB::transaction(function () use ($items) {
            $batch = NotificationBatch::create(['total_count' => count($items)]);

            $notifications = array_map(fn (array $item) => $this->store([...$item, 'batch_id' => $batch->id]), $items);

            return [$batch, $notifications];
        });

        foreach ($notifications as $notification) {
            $this->dispatchIfNeeded($notification);
        }

        return [$batch, $notifications];
    }

    private function store(array $data): Notification
    {
        $data['status'] = isset($data['scheduled_at']) ? NotificationStatus::Pending : NotificationStatus::Queued;
        $key = $data['idempotency_key'] ?? null;

        return $key === null ? Notification::create($data) : Notification::createOrFirst(['idempotency_key' => $key], $data);
    }

    private function dispatchIfNeeded(Notification $notification): void
    {
        if ($notification->wasRecentlyCreated && $notification->status === NotificationStatus::Queued) {
            SendNotification::dispatch($notification->id)->onQueue($notification->priority->value);
        }
    }

    public function search(array $filters): LengthAwarePaginator
    {
        return Notification::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($filters['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function cancel(Notification $notification): bool
    {
        $cancellable = array_filter(
            NotificationStatus::cases(),
            fn (NotificationStatus $status) => $status->isCancellable(),
        );

        $updated = Notification::where('id', $notification->id)
            ->whereIn('status', $cancellable)
            ->update(['status' => NotificationStatus::Cancelled]);

        return $updated === 1;
    }

}
