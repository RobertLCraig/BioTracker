<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserStreakResource;
use App\Models\UserStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreakController extends Controller
{
    /**
     * Return the authenticated user's current streak data.
     */
    public function show(Request $request): JsonResponse
    {
        $streak = UserStreak::withoutGlobalScopes()
            ->firstOrNew(
                ['user_id' => $request->user()->id],
                ['current_streak' => 0, 'longest_streak' => 0]
            );

        return response()->json(new UserStreakResource($streak));
    }
}
