<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicationRequest;
use App\Http\Resources\MedicationResource;
use App\Models\Medication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $medications = Medication::orderBy('name')->paginate(50);

        return MedicationResource::collection($medications);
    }

    public function store(StoreMedicationRequest $request): JsonResponse
    {
        $medication = Medication::create($request->validated());

        return response()->json(new MedicationResource($medication), 201);
    }

    public function show(Medication $medication): MedicationResource
    {
        return new MedicationResource($medication);
    }

    public function update(StoreMedicationRequest $request, Medication $medication): MedicationResource
    {
        $medication->update($request->validated());

        return new MedicationResource($medication);
    }

    public function destroy(Medication $medication): JsonResponse
    {
        $medication->delete();

        return response()->json(['message' => 'Medication deleted.']);
    }
}
