<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVitalLogRequest;
use App\Http\Resources\VitalLogResource;
use App\Models\VitalLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VitalLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VitalLog::orderByDesc('logged_at');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('from')) {
            $query->where('logged_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('logged_at', '<=', $request->input('to'));
        }

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        return VitalLogResource::collection($query->paginate(20));
    }

    public function store(StoreVitalLogRequest $request): JsonResponse
    {
        $log = VitalLog::create($request->validated());

        return response()->json(new VitalLogResource($log), 201);
    }

    public function show(VitalLog $vitalLog): VitalLogResource
    {
        return new VitalLogResource($vitalLog);
    }

    public function update(StoreVitalLogRequest $request, VitalLog $vitalLog): VitalLogResource
    {
        $vitalLog->update($request->validated());

        return new VitalLogResource($vitalLog);
    }

    public function destroy(VitalLog $vitalLog): JsonResponse
    {
        $vitalLog->delete();

        return response()->json(['message' => 'Vital log deleted.']);
    }
}
