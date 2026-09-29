<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Visit Report - {{ $isVet ? 'VR' : 'CR' }}-{{ str_pad($record->id, 5, '0', STR_PAD_LEFT) }}</title>
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
        .report-badge {
            display: inline-block;
            background-color: #ecfdf5;
            border: 1px solid #10b981;
            color: #047857;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
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
        .content-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 14px;
            font-size: 9.5px;
            line-height: 1.45;
            color: #334155;
            white-space: pre-line;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th {
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
        .data-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .item-name {
            font-weight: 700;
            color: #0f172a;
        }
        .tag-badge {
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
        .type-badge {
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .flag-normal {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .flag-high {
            display: inline-block;
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .flag-low {
            display: inline-block;
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .flag-abnormal {
            display: inline-block;
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 700;
            text-transform: uppercase;
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
            margin-top: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }
        .sign-col {
            text-align: center;
            width: 40%;
        }
        .sign-line {
            border-bottom: 1px dashed #94a3b8;
            margin: 24px auto 4px auto;
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
                    <span class="report-badge">
                        {{ $isVet ? 'Veterinary Clinical Visit & Rx Report' : 'Technical Consultation & Advisory Report' }}
                    </span>
                </div>
            </td>
            <td class="company-meta" style="width: 45%; vertical-align: middle;">
                <strong>FARMLINK PLATFORM BD</strong><br/>
                Digital Aquaculture Diagnostics & Field Operations<br/>
                Coastal Brackish & Inland Freshwater Network<br/>
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
                <div class="info-col-title">Practitioner in Charge</div>
                <div class="info-item">
                    <span class="info-label">Specialist:</span>
                    <span class="info-value">{{ $practitioner->name ?? 'Authorized Practitioner' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Designation:</span>
                    <span class="info-value">{{ $isVet ? 'Veterinary Doctor (DVM)' : 'Aquaculture Consultant' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $practitioner->phone ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Area / Station:</span>
                    <span class="info-value">{{ $practitioner->district ?? ($farm->district ?? 'Regional Field Team') }}</span>
                </div>
            </td>

            <!-- Visit Meta -->
            <td class="info-col">
                <div class="info-col-title">Visit Details</div>
                <div class="info-item">
                    <span class="info-label">Report ID:</span>
                    <span class="info-value">VR-{{ str_pad($record->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Visit Date:</span>
                    <span class="info-value">{{ $record->visit_date ? $record->visit_date->format('d M Y') : now()->format('d M Y') }}</span>
                </div>
                @if($serviceRequest)
                    <div class="info-item">
                        <span class="info-label">Ticket Ref:</span>
                        <span class="info-value">#SR-{{ $serviceRequest->id }} ({{ ucfirst($serviceRequest->urgency) }})</span>
                    </div>
                @endif
                <div class="info-item">
                    <span class="info-label">Next Follow-Up:</span>
                    <span class="info-value" style="color: {{ $record->next_follow_up ? '#b45309' : '#64748b' }};">
                        {{ $record->next_follow_up ? $record->next_follow_up->format('d M Y') : 'As needed' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 1. Clinical Findings / Advisory Observations -->
    <div class="section-title">
        {{ $isVet ? 'Clinical Findings & Examination' : 'Farm Condition & Advisory Assessment' }}
    </div>
    <div class="content-box">{!! nl2br(e($record->findings ?? $record->recommendation ?? 'Assessment completed during on-site visit.')) !!}</div>

    <!-- 2. Test Results / Readings Table (Omit if empty) -->
    @if(isset($testResults) && count($testResults) > 0)
        <div class="section-title">Water Quality & Farm Test Results</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Parameter Tested</th>
                    <th style="width: 20%;">Measured Value</th>
                    <th style="width: 25%;">Standard / Reference Range</th>
                    <th style="width: 15%;">Evaluation Flag</th>
                </tr>
            </thead>
            <tbody>
                @foreach($testResults as $idx => $t)
                    <tr>
                        <td style="color: #64748b;">{{ $idx + 1 }}</td>
                        <td class="item-name">{{ $t->parameter }}</td>
                        <td>
                            <strong>{{ $t->value }}</strong>
                            @if($t->unit) <span style="color: #64748b;">{{ $t->unit }}</span> @endif
                        </td>
                        <td style="color: #475569;">{{ $t->reference_range ?: 'Standard aquaculture range' }}</td>
                        <td>
                            @php $flagLower = strtolower($t->flag ?? 'normal'); @endphp
                            @if($flagLower === 'high')
                                <span class="flag-high">High</span>
                            @elseif($flagLower === 'low')
                                <span class="flag-low">Low</span>
                            @elseif($flagLower === 'abnormal')
                                <span class="flag-abnormal">Abnormal</span>
                            @else
                                <span class="flag-normal">Normal</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 3. Prescriptions or Consultant Recommendations Table (Omit if empty) -->
    @if(isset($prescriptionItems) && count($prescriptionItems) > 0)
        <div class="section-title">
            {{ $isVet ? 'Rx — Prescribed Medicines & Inputs' : 'Structured Recommendations & Advisory Actions' }}
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    @if(!$isVet)
                        <th style="width: 16%;">Category</th>
                        <th style="width: 32%;">Recommended Item / Action</th>
                        <th style="width: 47%;">Reasoning & Operational Instructions</th>
                    @else
                        <th style="width: 30%;">Medicine / Active Compound</th>
                        <th style="width: 15%;">Dosage</th>
                        <th style="width: 15%;">Frequency</th>
                        <th style="width: 12%;">Duration</th>
                        <th style="width: 23%;">Instructions / Administration</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($prescriptionItems as $idx => $item)
                    <tr>
                        <td style="color: #64748b;">{{ $idx + 1 }}</td>
                        @if(!$isVet)
                            <td>
                                <span class="type-badge">{{ str_replace('_', ' ', $item->type ?? 'Other') }}</span>
                            </td>
                            <td>
                                <div class="item-name">{{ $item->medicine_name ?: $item->item_name }}</div>
                                @if($item->product_id)
                                    <div class="tag-badge">&#x2714; Verified Catalog Product</div>
                                @endif
                            </td>
                            <td style="color: #334155;">
                                {{ $item->reasoning ?: $item->instructions ?: 'Implement as advised during consultation.' }}
                            </td>
                        @else
                            <td>
                                <div class="item-name">{{ $item->medicine_name }}</div>
                                @if($item->product_id)
                                    <div class="tag-badge">&#x2714; Verified Catalog Product</div>
                                @endif
                            </td>
                            <td>{{ $item->dosage ?: '—' }}</td>
                            <td>{{ $item->frequency ?: '—' }}</td>
                            <td>{{ $item->duration ?: '—' }}</td>
                            <td style="color: #475569; font-style: italic;">
                                {{ $item->instructions ?: 'Administer according to clinical instructions.' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 4. Treatment Protocol / Management Plan (if any) -->
    @if(!empty($record->treatment))
        <div class="section-title">Treatment Protocol & Management Plan</div>
        <div class="content-box">{!! nl2br(e($record->treatment)) !!}</div>
    @endif

    <!-- 5. Visit Photos & Visual Evidence Appendix (if any) -->
    @if(isset($photos) && count($photos) > 0)
        <div class="section-title">Visit Photos & Visual Evidence Appendix</div>
        <table style="width: 100%; border-collapse: separate; border-spacing: 8px 10px; margin-bottom: 14px;">
            @foreach($photos->chunk(2) as $row)
                <tr>
                    @foreach($row as $photo)
                        @php
                            $fullPath = storage_path('app/public/' . $photo->photo_path);
                            if (!file_exists($fullPath)) {
                                $fullPath = public_path('storage/' . $photo->photo_path);
                            }
                            $imgSrc = null;
                            if (file_exists($fullPath)) {
                                $mime = @mime_content_type($fullPath) ?: 'image/jpeg';
                                $imgSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
                            }
                        @endphp
                        <td style="width: 50%; vertical-align: top; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px; text-align: center;">
                            @if($imgSrc)
                                <div style="max-height: 180px; overflow: hidden; margin-bottom: 4px;">
                                    <img src="{{ $imgSrc }}" style="max-width: 100%; max-height: 180px; border-radius: 3px;" alt="Visit photo" />
                                </div>
                            @else
                                <div style="padding: 20px; color: #94a3b8; font-size: 8.5px;">[Image attached: {{ basename($photo->photo_path) }}]</div>
                            @endif
                            @if(!empty($photo->caption))
                                <div style="font-size: 8.5px; color: #475569; font-style: italic; margin-top: 4px; text-align: left; padding: 0 4px;">
                                    {{ $photo->caption }}
                                </div>
                            @endif
                        </td>
                    @endforeach
                    @if(count($row) === 1)
                        <td style="width: 50%; border: none; background: transparent;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    <!-- 6. Advisory / Biosecurity Notice -->
    <div class="notice-box">
        <strong>Biosecurity & Pond Health Assurance:</strong>
        Maintain optimal aeration, follow feed rationing schedules based on biomass, and ensure clean water exchange. If mortality or abnormal behavior persists, contact the FarmLink specialist helpline immediately.
    </div>

    <!-- Footer Signature / Verification -->
    <table class="footer-table">
        <tr>
            <td style="width: 60%; vertical-align: bottom; font-size: 8.5px; color: #64748b;">
                Generated digitally via FarmLink Aquaculture Operations Platform.<br/>
                Report generated on {{ now()->format('d M Y, h:i A') }} (UTC). Computer-verified digital visit record.
            </td>
            <td class="sign-col" style="vertical-align: bottom;">
                <div class="sign-line"></div>
                <strong style="color: #0f172a; font-size: 9.5px;">{{ $practitioner->name ?? 'Authorized Practitioner' }}</strong><br/>
                <span style="font-size: 8.5px; color: #64748b;">{{ $isVet ? 'Registered Veterinary Doctor' : 'Aquaculture Technical Consultant' }}</span>
            </td>
        </tr>
    </table>

</body>
</html>
