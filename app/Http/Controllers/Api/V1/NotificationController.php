<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreNotificationBatchRequest;
use App\Models\Notification;
use App\Enums\NotificationStatus;
use App\Models\NotificationBatch;
use App\Http\Requests\ListNotificationsRequest;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->service->create($request->validated());

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode($notification->wasRecentlyCreated ? 202 : 200);
    }

    public function storeBatch(StoreNotificationBatchRequest $request): JsonResponse
    {
        [$batch, $notifications] = $this->service->createBatch($request->validated('notifications'));

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'total_count' => $batch->total_count,
                'notifications' => NotificationResource::collection($notifications),
            ],
        ], 202);
    }

    public function show(Notification $notification): NotificationResource
    {
        return new NotificationResource($notification);
    }

    public function showBatch(NotificationBatch $batch): JsonResponse
    {
        $counts = $batch->notifications()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusCounts = [];
        foreach (NotificationStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'total_count' => $batch->total_count,
                'status_counts' => $statusCounts,
                'created_at' => $batch->created_at->toIso8601String(),
            ],
        ]);
    }

    public function index(ListNotificationsRequest $request)
    {
        return NotificationResource::collection(
            $this->service->search($request->validated())
        );
    }

    public function cancel(Notification $notification): JsonResponse
    {
        if (!$this->service->cancel($notification)) {
            $notification->refresh();

            return response()->json([
                'message' => "Notification cannot be cancelled in status '{$notification->status->value}'.",
            ], 409);
        }

        return (new NotificationResource($notification->refresh()))->response();
    }
}
