<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityLogRequest;
use App\Http\Requests\UpdateActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $logs = ActivityLog::with('activityType')
            ->orderByDesc('logged_at')
            ->paginate(20);

        return ActivityLogResource::collection($logs);
    }

    public function store(StoreActivityLogRequest $request): JsonResponse
    {
        $log = ActivityLog::create($request->safe()->except('photos'));

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $log->addMedia($photo)->toMediaCollection('photos');
            }
        }

        $log->load('activityType');

        return response()->json(new ActivityLogResource($log), 201);
    }

    public function show(ActivityLog $activityLog): ActivityLogResource
    {
        return new ActivityLogResource($activityLog->load('activityType'));
    }

    public function update(UpdateActivityLogRequest $request, ActivityLog $activityLog): ActivityLogResource
    {
        $activityLog->update($request->validated());

        return new ActivityLogResource($activityLog->load('activityType'));
    }

    public function destroy(ActivityLog $activityLog): JsonResponse
    {
        $activityLog->delete();

        return response()->json(['message' => 'Activity log deleted.']);
    }
}
