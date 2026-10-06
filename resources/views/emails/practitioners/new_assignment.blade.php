@extends('emails.layouts.base')

@section('content')
    @php
        $urgencyColors = [
            'emergency' => 'badge-danger',
            'high' => 'badge-warning',
            'normal' => 'badge-info',
            'low' => 'badge-info',
        ];
        $urgencyBadge = $urgencyColors[strtolower($serviceRequest->urgency ?? 'normal')] ?? 'badge-info';
    @endphp

    <div style="margin-bottom: 20px;">
        <span class="badge {{ $urgencyBadge }}">{{ strtoupper($serviceRequest->urgency ?? 'NORMAL') }} URGENCY</span>
        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 6px; margin-bottom: 0;">
            New Service Request Assignment (#SR-{{ $serviceRequest->id }})
        </h2>
    </div>

    <p class="lead">
        Hello {{ $practitioner->name }},
    </p>

    <p>
        A new farm service request has been assigned to you. Here are the key details for your review and planning:
    </p>

    <table class="info-table">
        <tr>
            <td style="width: 35%; font-weight: 600; color: #64748b;">Service Request ID</td>
            <td><strong>#SR-{{ $serviceRequest->id }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Service Type</td>
            <td>{{ ucwords(str_replace('_', ' ', $serviceRequest->type ?? 'Consultation')) }}</td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Farm Name</td>
            <td><strong>{{ $serviceRequest->farm?->farm_name ?? 'Farm' }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Farm Location</td>
            <td>
                {{ $serviceRequest->farm?->address ?: $serviceRequest->farm?->farm_address }},
                {{ $serviceRequest->farm?->district }}
            </td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Farmer Name</td>
            <td>{{ $serviceRequest->farmer?->name ?? 'Farmer' }}</td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Farmer Contact Phone</td>
            <td><a href="tel:{{ $serviceRequest->farmer?->phone }}" style="color: #16a34a; font-weight: 600;">{{ $serviceRequest->farmer?->phone }}</a></td>
        </tr>
        <tr>
            <td style="font-weight: 600; color: #64748b;">Assigned At</td>
            <td>{{ now()->format('M d, Y h:i A') }}</td>
        </tr>
    </table>

    <!-- Description / Symptoms -->
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;">
        <h4 style="margin: 0 0 6px; font-size: 13px; color: #475569; text-transform: uppercase;">Problem Description & Symptoms</h4>
        <p style="margin: 0; font-size: 14px; color: #1e293b; font-style: italic;">
            "{{ $serviceRequest->description ?: 'No additional notes provided by farmer.' }}"
        </p>
    </div>

    <div style="text-align: center; margin: 30px 0 10px;">
        <a href="{{ $portalUrl ?? url('/my/service-requests/' . $serviceRequest->id) }}" class="btn" style="color: #ffffff !important;">
            Open in Practitioner Portal
        </a>
    </div>
@endsection

@section('footer_reason')
    You received this notification because you are an active practitioner on FarmLink.
@endsection
