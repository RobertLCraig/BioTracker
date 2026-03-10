<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    /**
     * Return the authenticated user's dashboard snapshot.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->analytics->dashboard($request->user());

        return response()->json(['data' => $data]);
    }

    /**
     * Return trend data for charting.
     * Query params: period (7d|30d|90d), type (optional: calories|water_ml|exercise|sleep|logs|points)
     */
    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d',
            'type'   => 'nullable|in:calories,water_ml,exercise,sleep,logs,points',
        ]);

        $data = $this->analytics->trends(
            $request->user(),
            $request->input('period', '30d'),
            $request->input('type'),
        );

        return response()->json(['data' => $data]);
    }
}
