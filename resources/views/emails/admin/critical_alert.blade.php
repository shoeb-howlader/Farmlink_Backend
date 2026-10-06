@extends('emails.layouts.base')

@section('content')
    <div style="margin-bottom: 20px;">
        <span class="badge badge-danger">CRITICAL ALERT</span>
        <h2 style="font-size: 20px; font-weight: 700; color: #b91c1c; margin-top: 8px; margin-bottom: 4px;">
            {{ $title ?? 'System Failure Notification' }}
        </h2>
        <span style="font-size: 13px; color: #64748b;">Triggered at: {{ now()->toDateTimeString() }}</span>
    </div>

    <p class="lead" style="color: #991b1b;">
        {{ $summary ?? 'A critical failure occurred in backend background processing that requires attention.' }}
    </p>

    <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 20px 0;">
        <div style="font-size: 12px; font-weight: 700; color: #991b1b; text-transform: uppercase; margin-bottom: 6px;">
            Error Details
        </div>
        <pre style="margin: 0; font-size: 13px; color: #7f1d1d; white-space: pre-wrap; word-break: break-all; font-family: monospace;">{{ $errorMessage }}</pre>
    </div>

    @if (!empty($context))
        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 8px;">
            Context Metadata
        </h3>
        <table class="info-table">
            @foreach ($context as $key => $value)
                <tr>
                    <td style="width: 35%; font-weight: 600; color: #64748b;">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                    <td style="font-family: monospace; font-size: 13px;">{{ is_array($value) ? json_encode($value) : $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="text-align: center; margin: 30px 0 10px;">
        <a href="{{ $actionUrl ?? url('/admin/dashboard') }}" class="btn" style="background-color: #b91c1c; color: #ffffff !important;">
            Inspect in Admin Dashboard
        </a>
    </div>
@endsection

@section('footer_reason')
    Critical system alert dispatched immediately to FarmLink technical administrators.
@endsection
