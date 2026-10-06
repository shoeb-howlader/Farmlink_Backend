@extends('emails.layouts.base')

@section('content')
    <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">
        Password Reset Request
    </h2>

    <p class="lead">
        Hello {{ $user->name ?? 'there' }},
    </p>

    <p>
        We received a request to reset the password for your FarmLink account associated with <strong>{{ $user->email }}</strong>.
    </p>

    @if (!empty($resetUrl))
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" class="btn" style="color: #ffffff !important;">
                Reset Account Password
            </a>
        </div>
        <p style="font-size: 13px; color: #64748b; text-align: center;">
            Or copy and paste this link into your browser:<br>
            <a href="{{ $resetUrl }}" style="color: #16a34a; word-break: break-all;">{{ $resetUrl }}</a>
        </p>
    @endif

    @if (!empty($otp))
        <div class="code-box">
            <div style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 6px;">
                Alternative 6-Digit Verification Code
            </div>
            <div class="code-number">{{ $otp }}</div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 6px;">
                Valid for 10 minutes. Enter this code on the verification screen.
            </div>
        </div>
    @endif

    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; margin-top: 24px;">
        <p style="font-size: 13px; color: #92400e; margin: 0;">
            <strong>Security Notice:</strong> If you did not request this password reset, no further action is needed. Your account remains safe and secure.
        </p>
    </div>
@endsection

@section('footer_reason')
    You received this email because a password reset was requested for your FarmLink account.
@endsection
