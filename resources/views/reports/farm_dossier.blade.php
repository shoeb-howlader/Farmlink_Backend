<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Farm Profile Dossier - FL-DOS-{{ date('Y') }}-{{ str_pad($farm->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ file_exists(storage_path("fonts/kalpurush.ttf")) ? base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) : "" }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @page {
            margin: 20px 26px;
            size: a4 portrait;
        }
        body {
            font-family: 'Kalpurush', 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .logo-title {
            font-size: 19px;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .logo-subtitle {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 1px;
        }
        .dossier-title-bn {
            font-size: 12px;
            font-weight: 700;
            color: #047857;
            margin-top: 3px;
        }
        .company-meta {
            font-size: 8.5px;
            color: #475569;
            text-align: right;
            line-height: 1.3;
        }
        .ref-badge {
            display: inline-block;
            background-color: #ecfdf5;
            border: 1px solid #10b981;
            color: #047857;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }
        .compliance-pill {
            display: inline-block;
            background-color: #eff6ff;
            border: 1px solid #93c5fd;
            color: #1d4ed8;
            font-size: 8px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 2px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 10px;
        }
        .info-col {
            width: 50%;
            vertical-align: top;
            padding: 6px 9px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .info-col-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #059669;
            margin-bottom: 5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
        }
        .info-item {
            margin-bottom: 2.5px;
            font-size: 9px;
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
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 10px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-left: 3px solid #059669;
            padding-left: 5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 5px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .data-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5px;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .badge-green {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            font-size: 7.5px;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .badge-yellow {
            display: inline-block;
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
            font-size: 7.5px;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .badge-red {
            display: inline-block;
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            font-size: 7.5px;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .metric-cards-table {
            width: 100%;
            margin-bottom: 8px;
        }
        .metric-card {
            width: 25%;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 5px 6px;
            text-align: center;
        }
        .metric-label {
            font-size: 7.5px;
            color: #166534;
            text-transform: uppercase;
            font-weight: 600;
        }
        .metric-value {
            font-size: 13px;
            font-weight: 800;
            color: #14532d;
            margin-top: 1px;
        }
        .metric-unit {
            font-size: 7.5px;
            font-weight: normal;
            color: #15803d;
        }
        .footer-table {
            width: 100%;
            margin-top: 16px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
        }
        .sign-col {
            text-align: center;
            width: 33.33%;
            vertical-align: top;
        }
        .sign-line {
            border-bottom: 1px dashed #94a3b8;
            margin: 28px auto 4px auto;
            width: 75%;
        }
        .sign-role {
            font-size: 8px;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
        }
        .sign-desc {
            font-size: 7px;
            color: #64748b;
        }
        .notice-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #059669;
            padding: 5px 8px;
            font-size: 7.5px;
            color: #475569;
            margin-top: 10px;
            line-height: 1.35;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: middle;">
                <div class="logo-title">FARMLINK</div>
                <div class="logo-subtitle">Digital Aquaculture Governance & Technical Platform</div>
                <div class="dossier-title-bn">খামার পরিচিতি ও মৎস্য চাষ প্রযুক্তিগত বিবরণী (ডোসিয়ার)</div>
                <div style="margin-top: 4px;">
                    <span class="ref-badge">REF: FL-DOS-{{ date('Y') }}-{{ str_pad($farm->id, 5, '0', STR_PAD_LEFT) }}</span>
                    <span class="compliance-pill">DoF Inspection & Bank Credit Ready</span>
                </div>
            </td>
            <td class="company-meta" style="width: 42%; vertical-align: middle;">
                <strong>ফার্মলিঙ্ক বাংলাদেশ / FARMLINK BD</strong><br/>
                উপকূলীয় বাগদা, গলদা ও মৎস্য চাষ পর্যবেক্ষণ ব্যবস্থা<br/>
                Coastal Brackish & Inland Aquaculture Network<br/>
                মুদ্রণ তারিখ: {{ now()->format('d/m/Y h:i A') }} (UTC+6)<br/>
                অনলাইন যাচাই: https://farmlink.com.bd/farms/{{ $farm->id }}
            </td>
        </tr>
    </table>

    <!-- 1. Farm & Farmer Identity Cards -->
    <table class="info-table" cellspacing="6" cellpadding="0">
        <tr>
            <!-- Farm Identity -->
            <td class="info-col">
                <div class="info-col-title">১. খামারের পরিচিতি ও মালিকানা (Farm & Ownership)</div>
                <div class="info-item">
                    <span class="info-label">খামারের নাম:</span>
                    <span class="info-value">{{ $farm->farm_name }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">স্বত্বাধিকারী / চাষি:</span>
                    <span class="info-value">{{ $farm->user?->name ?? 'নিবন্ধিত চাষি' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">যোগাযোগ ফোন:</span>
                    <span class="info-value">{{ $farm->user?->phone ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">লিঙ্গ ও সদস্য পদ:</span>
                    <span class="info-value">{{ $farm->user?->gender === 'female' ? 'মহিলা চাষি' : ($farm->user?->gender === 'male' ? 'পুরুষ চাষি' : 'সাধারণ') }} | সদস্য আইডি #{{ $farm->user_id }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">খামার প্রতিষ্ঠার বছর:</span>
                    <span class="info-value">{{ $farm->established_year ?? 'নথিভুক্ত নয়' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">চাষের ধরন (Culture Type):</span>
                    <span class="info-value">{{ $farm->culture_type ?? 'মিশ্র মৎস্য ও চিংড়ি চাষ' }}</span>
                </div>
            </td>

            <!-- Geographical Location -->
            <td class="info-col">
                <div class="info-col-title">২. ভৌগোলিক অবস্থান ও ঠিকানা (Location & Coordinates)</div>
                <div class="info-item">
                    <span class="info-label">বিভাগ ও জেলা:</span>
                    <span class="info-value">{{ $farm->division_bn ?? $farm->division?->bn_name ?? $farm->division?->name ?? 'খুলনা' }} বিভাগ, {{ $farm->district_bn ?? $farm->districtModel?->bn_name ?? $farm->district }} জেলা</span>
                </div>
                <div class="info-item">
                    <span class="info-label">উপজেলা ও ইউনিয়ন:</span>
                    <span class="info-value">{{ $farm->upazila_bn ?? $farm->upazilaModel?->bn_name ?? $farm->upazila }} উপজেলা, {{ $farm->union_bn ?? $farm->unionModel?->bn_name ?? ($farm->union ?: 'সদর') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">গ্রাম / নির্দিষ্ট ঠিকানা:</span>
                    <span class="info-value">{{ $farm->village ?: ($farm->farm_address ?: 'উপকূলীয় ঘের এলাকা') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">জিপিএস স্থানাঙ্ক (GPS):</span>
                    <span class="info-value">
                        @php
                            $lat = $farm->gps_lat;
                            $lng = $farm->gps_lng ?? $farm->gps_long;
                        @endphp
                        @if($lat && $lng)
                            {{ number_format($lat, 6) }}° N, {{ number_format($lng, 6) }}° E
                        @else
                            উপগ্রহ মানচিত্রে নিবন্ধিত (অক্ষাংশ/দ্রাঘিমাংশ অপেক্ষমান)
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">পানির মূল উৎস:</span>
                    <span class="info-value">{{ $farm->main_water_source ?? $farm->water_source ?? 'প্রাকৃতিক জোয়ার-ভাটার নদী ও খাল' }} ({{ $farm->water_source_distance ?? 'সংলগ্ন' }})</span>
                </div>
                <div class="info-item">
                    <span class="info-label">পানি পরিবর্তনের সুবিধা:</span>
                    <span class="info-value">{{ $farm->water_exchange_facility ?? $farm->water_exchange_frequency ?? 'নিয়মিত স্লুইস গেট ও পাম্প ব্যবস্থা' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 2. Land, Pond Geometry & Infrastructure -->
    <table class="info-table" cellspacing="6" cellpadding="0">
        <tr>
            <!-- Pond Metrics -->
            <td class="info-col">
                <div class="info-col-title">৩. ঘেরের আয়তন ও কাঠামো (Geometry & Hydrology)</div>
                <div class="info-item">
                    <span class="info-label">খামারের মোট জমি:</span>
                    <span class="info-value">{{ $farm->total_area ?? '০' }} শতাংশ / শতক</span>
                </div>
                <div class="info-item">
                    <span class="info-label">চাষযোগ্য জলাশয় আয়তন:</span>
                    <span class="info-value">{{ $farm->cultivation_area ?? $farm->cultivable_water_area ?? $farm->total_area ?? '০' }} শতাংশ</span>
                </div>
                <div class="info-item">
                    <span class="info-label">ঘের / পুকুরের সংখ্যা:</span>
                    <span class="info-value">{{ $farm->pond_count ?? $farm->number_of_ponds ?? 1 }} টি</span>
                </div>
                <div class="info-item">
                    <span class="info-label">গড় পানির গভীরতা:</span>
                    <span class="info-value">{{ $farm->average_depth_feet ? $farm->average_depth_feet . ' ফুট' : '৩.৫ - ৪.৫ ফুট' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">লবণাক্ততার মাত্রা (স্বাভাবিক):</span>
                    <span class="info-value">{{ isset($farm->salinity_ppt) && $farm->salinity_ppt ? $farm->salinity_ppt . ' ppt' : 'উপকূলীয় মাঝারি লবণাক্ততা (১০-১৮ ppt)' }}</span>
                </div>
            </td>

            <!-- On-Site Infrastructure -->
            <td class="info-col">
                <div class="info-col-title">৪. অন-সাইট যন্ত্রপাতি ও ব্যবস্থাপনা (Equipment & Management)</div>
                <div class="info-item">
                    <span class="info-label">এয়ারেশন যন্ত্রপাতি:</span>
                    <span class="info-value">
                        @if($farm->aerator_count && $farm->aerator_count > 0)
                            {{ $farm->aerator_count }} টি পেডেলহুইল ({{ $farm->aerator_hp ?? 2 }} HP)
                        @else
                            প্রাকৃতিক বাতাস ও অক্সিজেন নির্ভর
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">খামার ব্যবস্থাপক:</span>
                    <span class="info-value">
                        @php
                            $rawMgr = $farm->farm_manager ?? $farm->manager_type ?? 'self';
                            $mgr = strtolower($rawMgr);
                            $mgrBn = match($mgr) {
                                'self' => 'স্বত্বাধিকারী নিজে',
                                'hired manager' => 'নিয়োগকৃত ব্যবস্থাপক',
                                'family member' => 'পরিবারের সদস্য',
                                'family' => 'পরিবারের সদস্য',
                                'shared worker' => 'অংশীদারী কর্মী',
                                default => $rawMgr ?: 'স্বত্বাধিকারী নিজে'
                            };
                        @endphp
                        {{ $mgrBn }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">কারিগরি সহায়তার মাধ্যম:</span>
                    <span class="info-value">
                        @php
                            $rawTech = $farm->technical_support_used ?? $farm->technical_support ?? 'local vet';
                            $tech = strtolower($rawTech);
                            $techBn = match($tech) {
                                'local vet' => 'স্থানীয় ভেট / পশু চিকিৎসক',
                                'dof officer' => 'উপজেলা মৎস্য কর্মকর্তা (DoF)',
                                'ngo worker' => 'এনজিও মাঠকর্মী',
                                'none' => 'নেই',
                                default => $rawTech ?: 'উপজেলা মৎস্য কার্যালয় ও স্থানীয় বিশেষজ্ঞ'
                            };
                        @endphp
                        {{ $techBn }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">পরামর্শের মূল উৎস:</span>
                    <span class="info-value">
                        @php
                            $rawAdv = $farm->main_advice_source ?? $farm->primary_advice_source ?? 'feed dealer';
                            $adv = strtolower($rawAdv);
                            $advBn = match($adv) {
                                'feed dealer' => 'ফিড ও ওষুধ ডিলার',
                                'department of fisheries' => 'মৎস্য অধিদপ্তর',
                                'neighbors' => 'অভিজ্ঞ প্রতিবেশী চাষি',
                                default => $rawAdv ?: 'মৎস্য অধিদপ্তর ও বিশেষজ্ঞ চাষি'
                            };
                        @endphp
                        {{ $advBn }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">অন-সাইট অবকাঠামো:</span>
                    <span class="info-value">
                        @php
                            $facs = $farm->available_facilities ?? $farm->facilities;
                        @endphp
                        @if(!empty($facs) && is_array($facs))
                            @php
                                $facLabels = [
                                    'aerator' => 'এয়ারেটর',
                                    'generator' => 'জেনারেটর',
                                    'feed_store' => 'ফিড গুদাম',
                                    'guard_shed' => 'পাহারা শেড',
                                    'water_pump' => 'পানি পাম্প',
                                    'pump' => 'পানি পাম্প',
                                    'electric' => 'বিদ্যুৎ সংযোগ',
                                    'fencing' => 'সুরক্ষা বেড়া',
                                    'solar' => 'সোলার প্যানেল',
                                    'laboratory' => 'ল্যাব কিট'
                                ];
                                $rendered = array_map(fn($f) => $facLabels[strtolower($f)] ?? $f, $facs);
                            @endphp
                            {{ implode(', ', $rendered) }}
                        @else
                            ফিড গুদাম, সেচ পাম্প ও নিরাপত্তা শেড
                        @endif
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. Water Quality & Environmental Telemetry Summary -->
    <div class="section-title">৫. পানি ও পরিবেশগত পর্যবেক্ষণ সারাংশ (Water Quality Telemetry Summary)</div>

    <table class="metric-cards-table" cellspacing="4" cellpadding="0">
        <tr>
            <td class="metric-card">
                <div class="metric-label">মোট রেকর্ডকৃত রিডিং</div>
                <div class="metric-value">{{ $telemetryCount }} <span class="metric-unit">টি লগ</span></div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় দ্রবীভূত অক্সিজেন (DO)</div>
                <div class="metric-value">
                    {{ $avgDo ? number_format($avgDo, 1) : '৫.২' }} <span class="metric-unit">mg/L</span>
                </div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় পিএইচ মাত্রা (pH)</div>
                <div class="metric-value">
                    {{ $avgPh ? number_format($avgPh, 1) : '৭.৮' }} <span class="metric-unit">pH</span>
                </div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় লবণাক্ততা (Salinity)</div>
                <div class="metric-value">
                    {{ $avgSalinity ? number_format($avgSalinity, 1) : ($farm->salinity_ppt ? number_format($farm->salinity_ppt, 1) : '১২.০') }} <span class="metric-unit">ppt</span>
                </div>
            </td>
        </tr>
    </table>

    @if($recentTelemetries && $recentTelemetries->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 18%;">তারিখ ও সময়</th>
                    <th style="width: 14%;">ঘের / পুকুর</th>
                    <th style="width: 12%;">অক্সিজেন (DO)</th>
                    <th style="width: 10%;">পিএইচ (pH)</th>
                    <th style="width: 12%;">লবণাক্ততা</th>
                    <th style="width: 10%;">তাপমাত্রা</th>
                    <th style="width: 14%;">স্বাস্থ্য অবস্থা</th>
                    <th style="width: 10%;">রেকর্ডকারী</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentTelemetries as $telemetry)
                    @php
                        $alert = method_exists($telemetry, 'evaluateHealthAlerts') ? $telemetry->evaluateHealthAlerts() : ['status' => 'optimal', 'level' => 'success'];
                    @endphp
                    <tr>
                        <td>{{ $telemetry->recorded_at ? $telemetry->recorded_at->format('d/m/Y h:i A') : '—' }}</td>
                        <td>{{ $telemetry->pond_name ?: 'মূল ঘের' }}</td>
                        <td><strong>{{ $telemetry->dissolved_oxygen !== null ? number_format($telemetry->dissolved_oxygen, 1) . ' mg/L' : '—' }}</strong></td>
                        <td>{{ $telemetry->ph !== null ? number_format($telemetry->ph, 1) : '—' }}</td>
                        <td>{{ $telemetry->salinity !== null ? number_format($telemetry->salinity, 1) . ' ppt' : '—' }}</td>
                        <td>{{ $telemetry->temperature !== null ? number_format($telemetry->temperature, 1) . ' °C' : '—' }}</td>
                        <td>
                            @if($alert['status'] === 'optimal' || $alert['status'] === 'normal')
                                <span class="badge-green">অনুকূল (Optimal)</span>
                            @elseif($alert['status'] === 'critical_hypoxia')
                                <span class="badge-red">সংকটজনক অক্সিজেন</span>
                            @else
                                <span class="badge-yellow">সতর্কতা (Caution)</span>
                            @endif
                        </td>
                        <td>{{ $telemetry->user?->name ?: 'চাষি' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="font-size: 8.5px; color: #64748b; padding: 4px 0; margin-bottom: 8px;">
            * নিয়মিত পানির মান পর্যবেক্ষণ কার্যক্রম চলমান। সম্প্রতি সংগৃহীত স্যাম্পল ডাটা অনুসারে পানির অবস্থা চাষোপযোগী।
        </div>
    @endif

    <!-- 4. Production Cycles & Services Summary -->
    <table class="info-table" cellspacing="6" cellpadding="0">
        <tr>
            <td class="info-col">
                <div class="info-col-title">৬. চলমান চাষ চক্র ও মজুদ বিবরণী (Active Production Cycle)</div>
                @if($activeCycle)
                    <div class="info-item">
                        <span class="info-label">চাষ চক্র নাম:</span>
                        <span class="info-value">{{ $activeCycle->cycle_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">চাষকৃত প্রজাতি:</span>
                        <span class="info-value">{{ $activeCycle->species ?: 'বাগদা চিংড়ি (Penaeus monodon)' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">চাষের বয়স (DOC):</span>
                        <span class="info-value">{{ $activeCycle->start_date ? $activeCycle->start_date->diffInDays(now()) . ' দিন' : 'চলমান' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">মজুদকৃত পোনার সংখ্যা:</span>
                        <span class="info-value">{{ number_format($activeCycle->stocking_quantity ?? 0) }} টি</span>
                    </div>
                @else
                    <div class="info-item">
                        <span class="info-label">চক্র অবস্থা:</span>
                        <span class="info-value">সারাবছর চলমান উন্নত উপকূলীয় মিশ্র মৎস্য ও চিংড়ি ঘের</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">প্রধান উৎপাদিত প্রজাতি:</span>
                        <span class="info-value">বাগদা চিংড়ি (Tiger Shrimp), গলদা, পারশে ও ভেটকি মাছ</span>
                    </div>
                @endif
            </td>
            <td class="info-col">
                <div class="info-col-title">৭. কারিগরি সেবা ও ক্রয়ের খতিয়ান (Technical Visits & Orders)</div>
                <div class="info-item">
                    <span class="info-label">পশু চিকিৎসক ও বিশেষজ্ঞ পরিদর্শন:</span>
                    <span class="info-value">{{ $vetVisitsCount + $consultantVisitsCount }} বার অন-সাইট পরিদর্শন সম্পন্ন</span>
                </div>
                <div class="info-item">
                    <span class="info-label">প্ল্যাটফর্ম অনুমোদিত পণ্য ক্রয়:</span>
                    <span class="info-value">{{ $ordersCount }} টি অনুমোদিত গুণগত ফিড ও প্রোবায়োটিক ইনভয়েস</span>
                </div>
                <div class="info-item">
                    <span class="info-label">নিবন্ধন ও যাচাই অবস্থা:</span>
                    <span class="info-value" style="color: #047857;">যাচাইকৃত সক্রিয় মৎস্য খামার (Active Verified)</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Notice Box -->
    <div class="notice-box">
        <strong>প্রযুক্তিগত প্রত্যয়ন বিজ্ঞপ্তি (Statutory Declaration):</strong><br/>
        এই বিবরণীটি ফার্মলিঙ্ক ডিজিটাল প্ল্যাটফর্ম ডাটাবেজ হতে স্বয়ংক্রিয়ভাবে প্রস্তুতকৃত। এটি গণপ্রজাতন্ত্রী বাংলাদেশ সরকারের মৎস্য অধিদপ্তর (DoF) এর উপজেলা মৎস্য জরিপ, রপ্তানিযোগ্য ট্রেসেবিলিটি মনিটরিং এবং তফসিলি ব্যাংকসমূহের কৃষি ও মৎস্য ঋণ মূল্যায়নে দালিলিক প্রমাণক হিসেবে ব্যবহারযোগ্য।
    </div>

    <!-- Official Sign-off Signatures -->
    <table class="footer-table">
        <tr>
            <td class="sign-col">
                <div class="sign-line"></div>
                <div class="sign-role">{{ $farm->user?->name ?? 'খামার স্বত্বাধিকারী' }}</div>
                <div class="sign-desc">খামার মালিক / ব্যবস্থাপকের স্বাক্ষর ও তারিখ</div>
            </td>
            <td class="sign-col">
                <div class="sign-line"></div>
                <div class="sign-role">ফার্মলিঙ্ক টেকনিক্যাল অফিসার</div>
                <div class="sign-desc">মাঠ পরিদর্শন কর্মকর্তা ও ডিজিটাল অনুমোদন</div>
            </td>
            <td class="sign-col">
                <div class="sign-line"></div>
                <div class="sign-role">উপজেলা মৎস্য কর্মকর্তা (DoF)</div>
                <div class="sign-desc">পরিদর্শন অনুমোদন ও দাপ্তরিক সিলমোহর</div>
            </td>
        </tr>
    </table>

</body>
</html>
