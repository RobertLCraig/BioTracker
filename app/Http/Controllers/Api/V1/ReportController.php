<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Reports\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(private ReportExportService $reports) {}

    /**
     * Export a health report as PDF or CSV.
     *
     * Params:
     *   from    (date, required)
     *   to      (date, required)
     *   format  pdf|csv (default: pdf)
     *   types[] activity|vitals|symptoms|medications|excretion (default: all)
     */
    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'from'    => 'required|date',
            'to'      => 'required|date|after_or_equal:from',
            'format'  => 'nullable|in:pdf,csv',
            'types'   => 'nullable|array',
            'types.*' => 'in:activity,vitals,symptoms,medications,excretion',
        ]);

        $user   = $request->user();
        $from   = Carbon::parse($validated['from'])->startOfDay();
        $to     = Carbon::parse($validated['to'])->endOfDay();
        $format = $validated['format'] ?? 'pdf';
        $types  = $validated['types'] ?? null;

        AuditService::log('export', $user, null, [
            'format' => $format,
            'from'   => $from->toDateString(),
            'to'     => $to->toDateString(),
            'types'  => $types,
        ]);

        $filename = 'biotracker-report-' . $from->toDateString() . '-' . $to->toDateString();

        if ($format === 'csv') {
            $csv = $this->reports->generateCsv($user, $from, $to, $types);

            return response($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            ]);
        }

        $pdfContent = $this->reports->generatePdf($user, $from, $to, $types);

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }
}
