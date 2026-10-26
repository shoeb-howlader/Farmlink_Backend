@extends('emails.layouts.base')

@section('content')
    <div style="margin-bottom: 24px;">
        <span class="badge badge-success">New Website Inquiry</span>
        <h2 style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 12px; margin-bottom: 8px;">
            Contact Form Submission
        </h2>
        <p class="lead">
            A new contact inquiry has been submitted through the FarmLink public contact portal.
        </p>
    </div>

    <!-- Sender Details Table -->
    <table class="info-table" role="presentation">
        <tr>
            <th style="width: 30%;">Sender Name</th>
            <td><strong>{{ $name }}</strong></td>
        </tr>
        <tr>
            <th>Email Address</th>
            <td>
                <a href="mailto:{{ $email }}" style="color: #16a34a; font-weight: 600; text-decoration: none;">
                    {{ $email }}
                </a>
            </td>
        </tr>
        <tr>
            <th>Inquiry Subject</th>
            <td><strong>{{ $subject }}</strong></td>
        </tr>
        <tr>
            <th>Submitted At</th>
            <td>{{ $submittedAt }}</td>
        </tr>
        @if(!empty($ipAddress))
        <tr>
            <th>IP Address</th>
            <td style="color: #64748b; font-family: monospace;">{{ $ipAddress }}</td>
        </tr>
        @endif
    </table>

    <!-- Message Content Box -->
    <div style="margin: 28px 0;">
        <h3 style="font-size: 15px; font-weight: 600; color: #475569; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Message Content:
        </h3>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 8px; padding: 20px; font-size: 15px; line-height: 1.7; color: #1e293b; white-space: pre-wrap; word-break: break-word;">{{ $messageText }}</div>
    </div>

    <!-- Action Button to Directly Reply -->
    <div style="text-align: center; margin: 32px 0 16px;">
        <a href="mailto:{{ $email }}?subject=Re:%20{{ rawurlencode($subject) }}" class="btn">
            ✉️ Reply directly to {{ $name }}
        </a>
        <p style="font-size: 13px; color: #64748b; margin-top: 10px;">
            Or click "Reply" in your email software to reach <span style="font-weight: 600;">{{ $email }}</span> directly.
        </p>
    </div>
@endsection

@section('footer_reason')
    You received this notification because a visitor submitted the public contact form on FarmLink.
@endsection
