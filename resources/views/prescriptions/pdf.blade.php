<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Prescription - RX-{{ str_pad($prescription->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ file_exists(storage_path("fonts/kalpurush.ttf")) ? base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) : "" }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @page {
            margin: 25px 30px;
            size: a4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .logo-title {
            font-size: 20px;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .logo-subtitle {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .company-meta {
            font-size: 9px;
            color: #475569;
            text-align: right;
            line-height: 1.35;
        }
        .rx-badge {
            display: inline-block;
            background-color: #ecfdf5;
            border: 1px solid #10b981;
            color: #047857;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 16px;
        }
        .info-col {
            width: 33.33%;
            vertical-align: top;
            padding: 8px 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .info-col-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #059669;
            margin-bottom: 6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .info-item {
            margin-bottom: 3px;
            font-size: 9.5px;
        }
        .info-label {
            color: #64748b;
            font-weight: normal;
        }
        .info-value {
            font-weight: 600;
            color: #0f172a;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 14px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 3px solid #059669;
            padding-left: 6px;
        }
        .clinical-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 14px;
            font-size: 9.5px;
            line-height: 1.45;
            color: #334155;
        }
        .med-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .med-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 7px 8px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .med-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: top;
        }
        .med-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .med-name {
            font-weight: 700;
            color: #0f172a;
        }
        .catalog-tag {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            font-size: 8px;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 600;
            margin-top: 2px;
        }
        .instructions-text {
            color: #475569;
            font-style: italic;
            font-size: 9px;
        }
        .notice-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 8px 10px;
            margin-top: 12px;
            font-size: 9px;
            color: #1e3a8a;
            line-height: 1.4;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }
        .sign-col {
            text-align: center;
            width: 40%;
        }
        .sign-line {
            border-bottom: 1px dashed #94a3b8;
            margin: 30px auto 4px auto;
            width: 70%;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: middle;">
                <div class="logo-title">FARMLINK</div>
                <div class="logo-subtitle">Aquaculture Medical & Advisory Care Network</div>
                <div style="margin-top: 6px;">
                    <span class="rx-badge">OFFICIAL PRESCRIPTION & TREATMENT PLAN</span>
                </div>
            </td>
            <td class="company-meta" style="width: 45%; vertical-align: middle;">
                <strong>FARMLINK PLATFORM BD</strong><br/>
                Digital Aquaculture Diagnostics Center<br/>
                Khulna / Satkhira / Bagerhat Coastal Zones<br/>
                Helpline: +880 1700-000000 | support@farmlink.com
            </td>
        </tr>
    </table>

    <!-- 3-Column Info Cards -->
    <table class="info-table" cellspacing="8" cellpadding="0">
        <tr>
            <!-- Patient / Farm Details -->
            <td class="info-col">
                <div class="info-col-title">Farm & Farmer Info</div>
                <div class="info-item">
                    <span class="info-label">Farmer:</span>
                    <span class="info-value">{{ $farmer->name ?? 'Registered Farmer' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $farmer->phone ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Farm:</span>
                    <span class="info-value">{{ $farm->farm_name ?? 'Farm #' . $record->farm_id }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Location:</span>
                    <span class="info-value">{{ $farm->district ?? 'N/A' }}{{ $farm->upazila ? ', ' . $farm->upazila : '' }}</span>
                </div>
            </td>

            <!-- Practitioner Details -->
            <td class="info-col">
                <div class="info-col-title">Prescribed By</div>
                <div class="info-item">
                    <span class="info-label">Specialist:</span>
                    <span class="info-value">{{ $practitioner->name ?? 'Practitioner' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="info-value">{{ $isVet ? 'Veterinary Doctor (DVM)' : 'Aquaculture Consultant' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $practitioner->phone ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">District:</span>
                    <span class="info-value">{{ $practitioner->district ?? 'Central Panel' }}</span>
                </div>
            </td>

            <!-- Prescription Meta -->
            <td class="info-col">
                <div class="info-col-title">Prescription Details</div>
                <div class="info-item">
                    <span class="info-label">Prescription ID:</span>
                    <span class="info-value">RX-{{ str_pad($prescription->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Visit Date:</span>
                    <span class="info-value">{{ $record->visit_date ? $record->visit_date->format('d M Y') : now()->format('d M Y') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Record Ref:</span>
                    <span class="info-value">#{{ $isVet ? 'VR-' : 'CR-' }}{{ $record->id }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Next Follow-Up:</span>
                    <span class="info-value" style="color: {{ $record->next_follow_up ? '#b45309' : '#64748b' }};">
                        {{ $record->next_follow_up ? $record->next_follow_up->format('d M Y') : 'As needed' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Clinical Findings & Diagnosis -->
    <div class="section-title">Clinical Findings & Diagnosis</div>
    <div class="clinical-box">
        <strong>Diagnosis / Observations:</strong><br/>
        {{ $record->findings ?? $record->recommendation ?? 'General medical assessment conducted.' }}
    </div>

    <!-- Prescribed Medications & Chemicals -->
    <div class="section-title">Rx — Prescribed Medicines & Inputs</div>
    <table class="med-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Medicine / Active Compound</th>
                <th style="width: 15%;">Dosage</th>
                <th style="width: 15%;">Frequency</th>
                <th style="width: 12%;">Duration</th>
                <th style="width: 23%;">Instructions / Administration</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prescription->items as $idx => $item)
                <tr>
                    <td style="color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div class="med-name">{{ $item->medicine_name }}</div>
                        @if($item->product_id)
                            <div class="catalog-tag">&#x2714; Verified Catalog Product</div>
                        @endif
                    </td>
                    <td>{{ $item->dosage ?: '—' }}</td>
                    <td>{{ $item->frequency ?: '—' }}</td>
                    <td>{{ $item->duration ?: '—' }}</td>
                    <td class="instructions-text">{{ $item->instructions ?: 'Administer according to package directions.' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 14px;">
                        No structured items listed. Please refer to general treatment notes below.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Treatment Protocol & Care Advice -->
    @if(!empty($record->treatment) || !empty($record->recommendation))
        <div class="section-title">Treatment Protocol & Management Plan</div>
        <div class="clinical-box">
            {{ $record->treatment ?? $record->recommendation }}
        </div>
    @endif

    <!-- Advisory / Biosecurity Notice -->
    <div class="notice-box">
        <strong>Important Biosecurity & Safety Guidelines:</strong>
        Maintain proper aeration during and after administration of aquatic chemicals. Adhere strictly to the withdrawal period prior to harvest. Store all veterinary products in a cool, dry place out of direct sunlight and reach of children.
    </div>

    <!-- Footer Signature / Verification -->
    <table class="footer-table">
        <tr>
            <td style="width: 60%; vertical-align: bottom; font-size: 8.5px; color: #64748b;">
                Generated digitally via FarmLink Platform on {{ now()->format('d M Y, h:i A') }} (UTC).<br/>
                This is a computer-verified digital medical prescription. Scan or verify via FarmLink.
            </td>
            <td class="sign-col" style="vertical-align: bottom;">
                <div class="sign-line"></div>
                <strong style="color: #0f172a; font-size: 9.5px;">{{ $practitioner->name ?? 'Authorized Practitioner' }}</strong><br/>
                <span style="font-size: 8.5px; color: #64748b;">{{ $isVet ? 'Registered Veterinary Doctor' : 'Senior Aquaculture Consultant' }}</span>
            </td>
        </tr>
    </table>

</body>
</html>
