<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    public function __invoke(MetricsService $metrics): JsonResponse
    {
        return response()->json(
            Cache::remember('metrics:snapshot', 5, fn () => $metrics->snapshot())
        );
    }
}
