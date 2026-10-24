<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreNotificationBatchRequest;

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
}
