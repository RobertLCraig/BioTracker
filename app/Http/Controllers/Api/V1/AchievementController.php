<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AchievementController extends Controller
{
    /**
     * Return all achievements with the user's unlock status on each.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $achievements = Achievement::with(['users' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }])->get()->map(function (Achievement $achievement) use ($userId) {
            // Attach pivot data so the resource can read unlocked_at
            $userAchievement = $achievement->users->first();
            if ($userAchievement) {
                $achievement->setRelation('pivot_data', $userAchievement->pivot);
                // Manually set pivot on resource layer
                $achievement->pivot = $userAchievement->pivot;
            }
            return $achievement;
        });

        return AchievementResource::collection($achievements);
    }
}
