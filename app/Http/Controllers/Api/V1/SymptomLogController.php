<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSymptomLogRequest;
use App\Http\Resources\SymptomLogResource;
use App\Models\SymptomLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SymptomLogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $logs = SymptomLog::orderByDesc('logged_at')->paginate(20);

        return SymptomLogResource::collection($logs);
    }

    public function store(StoreSymptomLogRequest $request): JsonResponse
    {
        $log = SymptomLog::create($request->safe()->except('photos'));

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $log->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return response()->json(new SymptomLogResource($log), 201);
    }

    public function show(SymptomLog $symptomLog): SymptomLogResource
    {
        return new SymptomLogResource($symptomLog);
    }

    public function update(StoreSymptomLogRequest $request, SymptomLog $symptomLog): SymptomLogResource
    {
        $symptomLog->update($request->safe()->except('photos'));

        return new SymptomLogResource($symptomLog);
    }

    public function destroy(SymptomLog $symptomLog): JsonResponse
    {
        $symptomLog->delete();

        return response()->json(['message' => 'Symptom log deleted.']);
    }
}
