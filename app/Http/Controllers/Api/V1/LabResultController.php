<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabResultRequest;
use App\Http\Resources\LabResultResource;
use App\Models\LabResult;
use App\Models\LabTestDefinition;
use App\Services\AuditService;
use App\Services\Lab\LabResultParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LabResultController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LabResult::with('testDefinition')->orderByDesc('sampled_at');

        if ($request->filled('test_key')) {
            $query->where('test_key', $request->input('test_key'));
        }
        if ($request->filled('abnormal')) {
            $query->where('abnormal_flag', $request->input('abnormal'));
        }
        if ($request->filled('panel')) {
            $query->where('lab_panel_id', $request->input('panel'));
        }
        if ($request->filled('from')) {
            $query->where('sampled_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('sampled_at', '<=', $request->input('to'));
        }

        return LabResultResource::collection($query->paginate(50));
    }

    public function store(StoreLabResultRequest $request): JsonResponse
    {
        $data = $request->validated();

        $definition = LabTestDefinition::resolveForImport(
            null,
            null,
            $data['test_name'],
            $data['unit'] ?? null,
        );

        $data['test_definition_id'] = $definition->id;
        $data['test_key'] = $definition->slug;   // canonical grouping key (see design §5)

        $result = LabResult::create($data);
        AuditService::log('create', $result, null, $data);

        return response()->json(new LabResultResource($result->load('testDefinition')), 201);
    }

    public function show(LabResult $labResult): LabResultResource
    {
        AuditService::log('view', $labResult);

        return new LabResultResource($labResult->load(['testDefinition', 'labPanel']));
    }

    public function update(StoreLabResultRequest $request, LabResult $labResult): LabResultResource
    {
        $old = $labResult->toArray();
        $labResult->update($request->validated());
        AuditService::log('update', $labResult, $old, $labResult->toArray());

        return new LabResultResource($labResult->load('testDefinition'));
    }

    public function destroy(LabResult $labResult): JsonResponse
    {
        $labResult->delete();
        AuditService::log('delete', $labResult);

        return response()->json(['message' => 'Lab result deleted.']);
    }

    /**
     * Trend data for charting.
     * With ?test_key= → the numeric series for one analyte (+ range band).
     * Without → the list of available series (for a picker).
     */
    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'test_key' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if (! $request->filled('test_key')) {
            $series = LabResult::query()
                ->join('lab_test_definitions as d', 'd.slug', '=', 'lab_results.test_key')
                ->selectRaw('lab_results.test_key, d.name, d.category, d.default_unit as unit, '
                    . 'count(*) as count, max(lab_results.sampled_at) as latest_at')
                ->groupBy('lab_results.test_key', 'd.name', 'd.category', 'd.default_unit')
                ->orderBy('d.category')
                ->orderBy('d.name')
                ->get();

            return response()->json(['data' => ['series' => $series]]);
        }

        $key = $request->input('test_key');

        $points = LabResult::query()
            ->where('test_key', $key)
            ->when($request->filled('from'), fn ($q) => $q->where('sampled_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('sampled_at', '<=', $request->input('to')))
            ->orderBy('sampled_at')
            ->get()
            ->map(fn (LabResult $r) => [
                'sampled_at' => $r->sampled_at?->toIso8601String(),
                // Cast decimals to real numbers so the chart client gets 0.8, not "0.8000".
                'value' => $r->value_numeric !== null ? (float) $r->value_numeric : null,
                'value_text' => $r->value_text,
                'comparator' => $r->value_comparator,
                'unit' => $r->unit,
                'range_low' => $r->range_low !== null ? (float) $r->range_low : null,
                'range_high' => $r->range_high !== null ? (float) $r->range_high : null,
                'flag' => $r->abnormal_flag?->value,
                'status' => $r->status?->value,
            ]);

        $definition = LabTestDefinition::where('slug', $key)->first();

        return response()->json(['data' => [
            'test_key' => $key,
            'name' => $definition?->name,
            'category' => $definition?->category,
            'unit' => $definition?->default_unit,
            'points' => $points,
        ]]);
    }

    /**
     * Paste-from-PKB preview (fallback path). Parses the pasted "Latest" table
     * text and returns candidate rows for review — never persists. Confirmed
     * rows are submitted individually via POST /lab-results.
     */
    public function parse(Request $request, LabResultParser $parser): JsonResponse
    {
        $request->validate(['text' => 'required|string']);

        $rows = $parser->parse($request->input('text'));

        return response()->json([
            'data' => $rows,
            'count' => count($rows),
            'needs_review' => collect($rows)->contains(fn ($r) => $r['needs_review']),
            'note' => 'Best-effort parse of pasted text. Review, then submit rows to POST /lab-results.',
        ]);
    }
}
