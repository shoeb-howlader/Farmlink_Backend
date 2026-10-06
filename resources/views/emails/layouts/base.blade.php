<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name', 'FarmLink') }}</title>
    <style>
        /* Base Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; }

        /* Container */
        .wrapper { width: 100%; table-layout: fixed; background-color: #f1f5f9; padding: 32px 16px; }
        .main-card { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }

        /* Header */
        .header { background: linear-gradient(135deg, #15803d 0%, #16a34a 100%); padding: 32px 28px; text-align: center; }
        .logo-badge { display: inline-block; background-color: rgba(255, 255, 255, 0.2); border-radius: 12px; padding: 8px 16px; margin-bottom: 8px; }
        .logo-text { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; text-decoration: none; }
        .logo-sub { color: #dcfce7; font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* Content */
        .content { padding: 36px 32px; }
        h1, h2, h3 { color: #0f172a; margin-top: 0; }
        p { margin: 0 0 16px; font-size: 15px; color: #334155; }
        .lead { font-size: 17px; font-weight: 500; color: #0f172a; line-height: 1.5; }

        /* Components */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-warning { background-color: #fef3c7; color: #b45309; }
        .badge-info { background-color: #e0f2fe; color: #0369a1; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; }

        .btn { display: inline-block; background-color: #16a34a; color: #ffffff !important; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 8px; box-shadow: 0 2px 4px rgba(22, 163, 74, 0.3); text-align: center; }
        .btn:hover { background-color: #15803d; }

        .code-box { background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 18px; text-align: center; margin: 24px 0; }
        .code-number { font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #16a34a; font-family: monospace; }

        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        .info-table th { background-color: #f8fafc; text-align: left; padding: 10px 14px; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
        .info-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }

        .metric-grid { width: 100%; border-collapse: separate; border-spacing: 10px; margin: 16px 0; }
        .metric-cell { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center; }
        .metric-value { font-size: 22px; font-weight: 700; color: #16a34a; margin-bottom: 4px; }
        .metric-label { font-size: 12px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Footer */
        .footer { padding: 24px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 13px; color: #64748b; }
        .footer a { color: #16a34a; text-decoration: underline; }
        .footer-note { font-size: 12px; color: #94a3b8; margin-top: 12px; }

        @media only screen and (max-width: 600px) {
            .content { padding: 24px 18px !important; }
            .header { padding: 24px 18px !important; }
            .metric-grid { display: block; }
            .metric-cell { display: block; margin-bottom: 8px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <div class="main-card">
                        <!-- Branded Header -->
                        <div class="header">
                            <div class="logo-badge">
                                <span class="logo-text">🌾 FarmLink</span>
                            </div>
                            <div class="logo-sub">Smart Agriculture & Farm Care Platform</div>
                        </div>

                        <!-- Email Body Content -->
                        <div class="content">
                            @yield('content')
                        </div>

                        <!-- Reusable Branded Footer -->
                        <div class="footer">
                            <p style="margin-bottom: 8px; color: #475569; font-weight: 500;">
                                FarmLink Bangladesh &bull; Connected Farm Care & Supply Chain
                            </p>
                            <p style="margin-bottom: 8px;">
                                Need help? Email our team at <a href="mailto:{{ config('mail.reply_to.address', 'support@farmlink.com') }}">{{ config('mail.reply_to.address', 'support@farmlink.com') }}</a>
                            </p>
                            <div class="footer-note">
                                @yield('footer_reason', 'You received this transactional email because an action was triggered on your FarmLink account.')
                                <br>&copy; {{ date('Y') }} FarmLink. All rights reserved.
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
