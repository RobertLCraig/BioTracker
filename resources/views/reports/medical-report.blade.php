<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BioTracker Health Report — {{ $user->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 20px; }
        h1 { font-size: 20px; color: #1a56db; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #374151; margin-top: 20px; margin-bottom: 6px; border-bottom: 2px solid #1a56db; padding-bottom: 4px; }
        h3 { font-size: 12px; color: #6b7280; margin-top: 12px; margin-bottom: 4px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #1a56db; color: #fff; padding: 5px 8px; text-align: left; font-size: 10px; }
        td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 9px; font-weight: bold; }
        .badge-bronze { background: #fef3c7; color: #92400e; }
        .badge-silver { background: #f3f4f6; color: #374151; }
        .badge-gold   { background: #fef9c3; color: #713f12; }
        .badge-blood  { background: #fee2e2; color: #991b1b; }
        .page-break   { page-break-after: always; }
        .footer       { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .bristol-table td { text-align: center; }
    </style>
</head>
<body>

<div class="footer">
    BioTracker Health Report — {{ $user->name }} — Generated {{ now()->format('d M Y H:i') }} — CONFIDENTIAL
</div>

{{-- Cover / Header --}}
<h1>BioTracker Health Report</h1>
<div class="meta">
    <strong>Patient:</strong> {{ $user->name }} &nbsp;|&nbsp;
    <strong>Email:</strong> {{ $user->email }} &nbsp;|&nbsp;
    <strong>Period:</strong> {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} &nbsp;|&nbsp;
    <strong>Generated:</strong> {{ now()->format('d M Y H:i') }}
</div>

{{-- Summary Stats --}}
<h2>Summary</h2>
<table>
    <tr>
        <td><strong>Activity logs</strong></td><td>{{ $activityLogs->count() }}</td>
        <td><strong>Excretion logs</strong></td><td>{{ $excretionLogs->count() }}</td>
    </tr>
    <tr>
        <td><strong>Symptom logs</strong></td><td>{{ $symptomLogs->count() }}</td>
        <td><strong>Medication doses</strong></td><td>{{ $medicationLogs->count() }}</td>
    </tr>
    <tr>
        <td><strong>Vital readings</strong></td><td>{{ $vitalLogs->count() }}</td>
        <td><strong>Total calories</strong></td>
        <td>{{ number_format($activityLogs->sum('calories')) }} kcal</td>
    </tr>
</table>

{{-- Activity Logs --}}
@if($activityLogs->isNotEmpty() && (!$types || in_array('activity', $types)))
<h2>Activity Log</h2>
<table>
    <thead>
        <tr>
            <th>Date/Time</th><th>Type</th><th>Duration</th><th>Quantity</th><th>Calories</th><th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($activityLogs as $log)
        <tr>
            <td>{{ $log->logged_at->format('d M Y H:i') }}</td>
            <td>{{ $log->activityType?->name ?? '–' }}</td>
            <td>{{ $log->duration_minutes ? $log->duration_minutes . ' min' : '–' }}</td>
            <td>{{ $log->quantity ? $log->quantity . ' ' . $log->unit : '–' }}</td>
            <td>{{ $log->calories ?? '–' }}</td>
            <td>{{ $log->notes ?? '–' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Vital Signs --}}
@if($vitalLogs->isNotEmpty() && (!$types || in_array('vitals', $types)))
<h2>Vital Signs</h2>
<table>
    <thead>
        <tr>
            <th>Date/Time</th><th>Type</th><th>Value</th><th>Unit</th><th>Source</th><th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($vitalLogs as $log)
        <tr>
            <td>{{ $log->logged_at->format('d M Y H:i') }}</td>
            <td>{{ str_replace('_', ' ', ucfirst($log->type?->value ?? '')) }}</td>
            <td>{{ $log->value }}{{ $log->secondary_value ? ' / ' . $log->secondary_value : '' }}</td>
            <td>{{ $log->unit }}</td>
            <td>{{ $log->source }}</td>
            <td>{{ $log->notes ?? '–' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Symptom Log --}}
@if($symptomLogs->isNotEmpty() && (!$types || in_array('symptoms', $types)))
<h2>Symptom Log</h2>
<table>
    <thead>
        <tr>
            <th>Date/Time</th><th>Symptom</th><th>Severity</th><th>Body Area</th><th>Duration</th><th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($symptomLogs as $log)
        <tr>
            <td>{{ $log->logged_at->format('d M Y H:i') }}</td>
            <td>{{ $log->symptom }}</td>
            <td>{{ $log->severity }}/10</td>
            <td>{{ $log->body_area ?? '–' }}</td>
            <td>{{ $log->duration_minutes ? $log->duration_minutes . ' min' : '–' }}</td>
            <td>{{ $log->notes ?? '–' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Medication Timeline --}}
@if($medicationLogs->isNotEmpty() && (!$types || in_array('medications', $types)))
<h2>Medication Log</h2>
<table>
    <thead>
        <tr>
            <th>Date/Time</th><th>Medication</th><th>Dosage Taken</th><th>Notes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($medicationLogs as $log)
        <tr>
            <td>{{ $log->taken_at->format('d M Y H:i') }}</td>
            <td>{{ $log->medication?->name ?? '–' }}</td>
            <td>{{ $log->dosage_taken ?? '–' }}</td>
            <td>{{ $log->notes ?? '–' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Excretion Log --}}
@if($excretionLogs->isNotEmpty() && (!$types || in_array('excretion', $types)))
<h2>Excretion Log</h2>
<table>
    <thead>
        <tr>
            <th>Date/Time</th><th>Type</th><th>Size</th><th>Bristol</th><th>Colour</th><th>Blood</th><th>Urgency</th><th>Pain</th>
        </tr>
    </thead>
    <tbody>
        @foreach($excretionLogs as $log)
        <tr>
            <td>{{ $log->logged_at->format('d M Y H:i') }}</td>
            <td>{{ ucfirst($log->type?->value ?? '') }}</td>
            <td>{{ ucfirst($log->size?->value ?? '–') }}</td>
            <td>{{ $log->consistency?->value ?? '–' }}</td>
            <td>{{ $log->colour ?? '–' }}</td>
            <td>
                @if($log->has_blood)
                    <span class="badge badge-blood">{{ strtoupper($log->blood_amount?->value ?? 'yes') }}</span>
                @else
                    None
                @endif
            </td>
            <td>{{ $log->urgency ?? '–' }}/5</td>
            <td>{{ $log->pain_level ?? '–' }}/10</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Bristol Stool Scale Reference --}}
<h3>Bristol Stool Scale Reference</h3>
<table class="bristol-table" style="width:60%">
    <thead><tr><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td>1</td><td>Separate hard lumps (severe constipation)</td></tr>
        <tr><td>2</td><td>Lumpy sausage (mild constipation)</td></tr>
        <tr><td>3</td><td>Sausage with cracks (normal)</td></tr>
        <tr><td>4</td><td>Smooth soft sausage (ideal)</td></tr>
        <tr><td>5</td><td>Soft blobs (lacking fibre)</td></tr>
        <tr><td>6</td><td>Fluffy, mushy pieces (mild diarrhoea)</td></tr>
        <tr><td>7</td><td>Watery, entirely liquid (severe diarrhoea)</td></tr>
    </tbody>
</table>
@endif

<p style="margin-top:30px; color:#6b7280; font-size:10px;">
    This report was generated by BioTracker and is intended for personal health tracking purposes only.
    Please consult a qualified healthcare professional for medical advice.
</p>

</body>
</html>
