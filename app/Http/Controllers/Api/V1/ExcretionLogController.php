<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExcretionLogRequest;
use App\Http\Requests\UpdateExcretionLogRequest;
use App\Http\Resources\ExcretionLogResource;
use App\Models\ExcretionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExcretionLogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $logs = ExcretionLog::orderByDesc('logged_at')->paginate(20);

        return ExcretionLogResource::collection($logs);
    }

    public function store(StoreExcretionLogRequest $request): JsonResponse
    {
        $log = ExcretionLog::create($request->safe()->except('photos'));

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $log->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return response()->json(new ExcretionLogResource($log), 201);
    }

    public function show(ExcretionLog $excretionLog): ExcretionLogResource
    {
        return new ExcretionLogResource($excretionLog);
    }

    public function update(UpdateExcretionLogRequest $request, ExcretionLog $excretionLog): ExcretionLogResource
    {
        $excretionLog->update($request->validated());

        return new ExcretionLogResource($excretionLog);
    }

    public function destroy(ExcretionLog $excretionLog): JsonResponse
    {
        $excretionLog->delete();

        return response()->json(['message' => 'Excretion log deleted.']);
    }
}
