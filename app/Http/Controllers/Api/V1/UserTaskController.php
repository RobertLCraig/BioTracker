<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserTaskResource;
use App\Models\UserTask;
use App\Models\UserTaskCompletion;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserTaskController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function index(): AnonymousResourceCollection
    {
        $tasks = UserTask::with('completions')->where('is_active', true)->get();

        return UserTaskResource::collection($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'points_reward' => 'nullable|integer|min:1|max:1000',
            'is_recurring'  => 'boolean',
            'frequency'     => 'nullable|in:daily,weekly,monthly',
        ]);

        $task = UserTask::create($validated);

        return response()->json(new UserTaskResource($task), 201);
    }

    public function show(UserTask $userTask): UserTaskResource
    {
        return new UserTaskResource($userTask->load('completions'));
    }

    public function update(Request $request, UserTask $userTask): UserTaskResource
    {
        $validated = $request->validate([
            'title'         => 'sometimes|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'points_reward' => 'nullable|integer|min:1|max:1000',
            'is_recurring'  => 'boolean',
            'frequency'     => 'nullable|in:daily,weekly,monthly',
            'is_active'     => 'boolean',
        ]);

        $userTask->update($validated);

        return new UserTaskResource($userTask);
    }

    public function destroy(UserTask $userTask): JsonResponse
    {
        $userTask->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Mark a task as complete and award points.
     */
    public function complete(Request $request, UserTask $userTask): JsonResponse
    {
        $completion = UserTaskCompletion::create([
            'user_task_id' => $userTask->id,
            'completed_at' => now(),
        ]);

        $point = $this->scoring->award(
            $request->user(),
            $userTask,
            "Completed task: {$userTask->title}"
        );

        return response()->json([
            'message'      => 'Task marked as complete.',
            'points_earned'=> $point->points,
            'completed_at' => $completion->completed_at->toIso8601String(),
        ]);
    }
}
