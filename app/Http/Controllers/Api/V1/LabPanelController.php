<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LabPanelResource;
use App\Models\LabPanel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LabPanelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $panels = LabPanel::with('results.testDefinition')
            ->orderByDesc('collected_at')
            ->paginate(25);

        return LabPanelResource::collection($panels);
    }

    public function show(LabPanel $labPanel): LabPanelResource
    {
        return new LabPanelResource($labPanel->load('results.testDefinition'));
    }
}
