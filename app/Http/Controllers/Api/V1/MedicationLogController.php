<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicationLogRequest;
use App\Http\Resources\MedicationLogResource;
use App\Models\MedicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicationLogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $logs = MedicationLog::with('medication')
            ->orderByDesc('taken_at')
            ->paginate(20);

        return MedicationLogResource::collection($logs);
    }

    public function store(StoreMedicationLogRequest $request): JsonResponse
    {
        $log = MedicationLog::create($request->validated());

        $log->load('medication');

        return response()->json(new MedicationLogResource($log), 201);
    }

    public function show(MedicationLog $medicationLog): MedicationLogResource
    {
        return new MedicationLogResource($medicationLog->load('medication'));
    }

    public function update(StoreMedicationLogRequest $request, MedicationLog $medicationLog): MedicationLogResource
    {
        $medicationLog->update($request->validated());

        return new MedicationLogResource($medicationLog->load('medication'));
    }

    public function destroy(MedicationLog $medicationLog): JsonResponse
    {
        $medicationLog->delete();

        return response()->json(['message' => 'Medication log deleted.']);
    }
}
