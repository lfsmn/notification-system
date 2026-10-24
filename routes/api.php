<?php

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/batch', [NotificationController::class, 'storeBatch']);
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->whereUuid('notification');
    Route::get('batches/{batch}', [NotificationController::class, 'showBatch'])->whereUuid('batch');
});
