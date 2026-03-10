<?php

namespace App\Services\Reports;

use App\Models\ActivityLog;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\VitalLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Generates PDF and CSV health reports for a user over a date range.
 */
class ReportExportService
{
    /**
     * Generate a PDF medical report and return the rendered PDF content.
     *
     * @param  string[]|null  $types  e.g. ['activity', 'vitals', 'symptoms', 'medications', 'excretion']
     */
    public function generatePdf(User $user, Carbon $from, Carbon $to, ?array $types = null): string
    {
        $data = $this->collectData($user, $from, $to, $types);

        $pdf = Pdf::loadView('reports.medical-report', array_merge($data, [
            'user'  => $user,
            'from'  => $from,
            'to'    => $to,
            'types' => $types,
        ]));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate a CSV export and return the raw CSV string.
     */
    public function generateCsv(User $user, Carbon $from, Carbon $to, ?array $types = null): string
    {
        $data = $this->collectData($user, $from, $to, $types);
        $rows = [];

        // Activity logs
        if (! $types || in_array('activity', $types)) {
            $rows[] = ['--- Activity Logs ---'];
            $rows[] = ['Date', 'Type', 'Duration (min)', 'Quantity', 'Unit', 'Calories', 'Notes'];
            foreach ($data['activityLogs'] as $log) {
                $rows[] = [
                    $log->logged_at->toIso8601String(),
                    $log->activityType?->name,
                    $log->duration_minutes,
                    $log->quantity,
                    $log->unit,
                    $log->calories,
                    $log->notes,
                ];
            }
            $rows[] = [];
        }

        // Vital logs
        if (! $types || in_array('vitals', $types)) {
            $rows[] = ['--- Vital Signs ---'];
            $rows[] = ['Date', 'Type', 'Value', 'Secondary Value', 'Unit', 'Source', 'Notes'];
            foreach ($data['vitalLogs'] as $log) {
                $rows[] = [
                    $log->logged_at->toIso8601String(),
                    $log->type?->value,
                    $log->value,
                    $log->secondary_value,
                    $log->unit,
                    $log->source,
                    $log->notes,
                ];
            }
            $rows[] = [];
        }

        // Symptom logs
        if (! $types || in_array('symptoms', $types)) {
            $rows[] = ['--- Symptom Logs ---'];
            $rows[] = ['Date', 'Symptom', 'Severity', 'Body Area', 'Duration (min)', 'Notes'];
            foreach ($data['symptomLogs'] as $log) {
                $rows[] = [
                    $log->logged_at->toIso8601String(),
                    $log->symptom,
                    $log->severity,
                    $log->body_area,
                    $log->duration_minutes,
                    $log->notes,
                ];
            }
            $rows[] = [];
        }

        // Medication logs
        if (! $types || in_array('medications', $types)) {
            $rows[] = ['--- Medication Logs ---'];
            $rows[] = ['Date', 'Medication', 'Dosage Taken', 'Notes'];
            foreach ($data['medicationLogs'] as $log) {
                $rows[] = [
                    $log->taken_at->toIso8601String(),
                    $log->medication?->name,
                    $log->dosage_taken,
                    $log->notes,
                ];
            }
            $rows[] = [];
        }

        // Excretion logs
        if (! $types || in_array('excretion', $types)) {
            $rows[] = ['--- Excretion Logs ---'];
            $rows[] = ['Date', 'Type', 'Size', 'Bristol Scale', 'Colour', 'Has Blood', 'Blood Amount', 'Urgency', 'Pain', 'Notes'];
            foreach ($data['excretionLogs'] as $log) {
                $rows[] = [
                    $log->logged_at->toIso8601String(),
                    $log->type?->value,
                    $log->size?->value,
                    $log->consistency?->value,
                    $log->colour,
                    $log->has_blood ? 'Yes' : 'No',
                    $log->blood_amount?->value,
                    $log->urgency,
                    $log->pain_level,
                    $log->notes,
                ];
            }
        }

        // Build CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Query all requested data types for the report.
     */
    private function collectData(User $user, Carbon $from, Carbon $to, ?array $types): array
    {
        $activityLogs = collect();
        $vitalLogs    = collect();
        $symptomLogs  = collect();
        $medicationLogs = collect();
        $excretionLogs  = collect();

        if (! $types || in_array('activity', $types)) {
            $activityLogs = ActivityLog::withoutGlobalScopes()
                ->with('activityType')
                ->where('user_id', $user->id)
                ->whereBetween('logged_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('logged_at')
                ->get();
        }

        if (! $types || in_array('vitals', $types)) {
            $vitalLogs = VitalLog::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereBetween('logged_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('logged_at')
                ->get();
        }

        if (! $types || in_array('symptoms', $types)) {
            $symptomLogs = SymptomLog::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereBetween('logged_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('logged_at')
                ->get();
        }

        if (! $types || in_array('medications', $types)) {
            $medicationLogs = MedicationLog::withoutGlobalScopes()
                ->with('medication')
                ->where('user_id', $user->id)
                ->whereBetween('taken_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('taken_at')
                ->get();
        }

        if (! $types || in_array('excretion', $types)) {
            $excretionLogs = ExcretionLog::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereBetween('logged_at', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('logged_at')
                ->get();
        }

        return compact('activityLogs', 'vitalLogs', 'symptomLogs', 'medicationLogs', 'excretionLogs');
    }
}
