@extends('emails.layouts.base')

@section('content')
    <div style="margin-bottom: 20px;">
        <span class="badge badge-success">Completed &bull; Report Ready</span>
        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 6px; margin-bottom: 0;">
            Visit Report & Prescription (#SR-{{ $serviceRequest->id }})
        </h2>
    </div>

    <p class="lead">
        Hello {{ $farmer->name ?? 'Farmer' }},
    </p>

    <p>
        Your service request for <strong>{{ $serviceRequest->farm?->farm_name ?? 'your farm' }}</strong> has been completed.
        {{ $isVet ? 'Dr.' : 'Consultant' }} <strong>{{ $practitioner->name }}</strong> has concluded the field assessment and filed your official consultation report.
    </p>

    <table class="info-table">
        <tr>
            <td style="width: 35%; font-weight: 600; color: #64748b;">Service Request ID</td>
            <td><strong>#SR-{{ $serviceRequest->id }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Farm</td>
            <td>{{ $serviceRequest->farm?->farm_name }} ({{ $serviceRequest->farm?->district }})</td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Specialist</td>
            <td>{{ $practitioner->name }} ({{ $isVet ? 'Veterinary Doctor' : 'Fisheries Consultant' }})</td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Completed At</td>
            <td>{{ $serviceRequest->completed_at ? $serviceRequest->completed_at->format('M d, Y h:i A') : now()->format('M d, Y') }}</td>
        </tr>
        @if ($record->diagnosis ?? false)
            <tr>
                <td style="font-weight: 600; color: #64748b;">Primary Diagnosis</td>
                <td>{{ $record->diagnosis }}</td>
            </tr>
        @endif
        @if ($record->next_follow_up ?? false)
            <tr>
                <td style="font-weight: 600; color: #64748b;">Recommended Follow-up</td>
                <td style="color: #b45309; font-weight: 600;">{{ \Carbon\Carbon::parse($record->next_follow_up)->format('M d, Y') }}</td>
            </tr>
        @endif
    </table>

    <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 16px; margin: 20px 0;">
        <p style="margin: 0; font-size: 13px; color: #065f46;">
            📎 <strong>Official Document Attached:</strong> Your complete Visit Report & Prescription document (PDF) is attached to this email. You can view or print it anytime for your records.
        </p>
    </div>

    <div style="text-align: center; margin: 30px 0 10px;">
        <a href="{{ $viewUrl ?? url('/dashboard') }}" class="btn" style="color: #ffffff !important;">
            View in FarmLink Portal
        </a>
    </div>
@endsection

@section('footer_reason')
    You received this document email because an email address was configured on your farmer profile.
@endsection
