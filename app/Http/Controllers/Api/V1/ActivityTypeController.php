<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityTypeResource;
use App\Models\ActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityTypeController extends Controller
{
    /**
     * List system activity types plus the current user's custom types.
     */
    public function index(): AnonymousResourceCollection
    {
        $types = ActivityType::forUser()->get();

        return ActivityTypeResource::collection($types);
    }

    /**
     * Create a custom activity type for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'required|string|max:100|unique:activity_types,slug',
            'icon'           => 'nullable|string|max:10',
            'points_per_log' => 'nullable|integer|min:1|max:100',
        ]);

        $type = ActivityType::create([
            ...$validated,
            'is_system' => false,
            'user_id'   => $request->user()->id,
        ]);

        return response()->json(new ActivityTypeResource($type), 201);
    }
}
