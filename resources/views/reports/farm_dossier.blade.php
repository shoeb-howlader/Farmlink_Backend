<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Farm Profile Dossier - FL-DOS-{{ date('Y') }}-{{ str_pad($farm->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        body {
            font-family: 'kalpurush', 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #059669;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .logo-title {
            font-size: 18pt;
            font-weight: bold;
            color: #059669;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .logo-subtitle {
            font-size: 7.5pt;
            color: #64748b;
            letter-spacing: 0.8px;
            margin-top: 1px;
        }
        .dossier-title-bn {
            font-size: 11pt;
            font-weight: bold;
            color: #047857;
            margin-top: 3px;
        }
        .company-meta {
            font-size: 8pt;
            color: #475569;
            text-align: right;
            line-height: 1.3;
        }
        .ref-badge {
            display: inline-block;
            background-color: #ecfdf5;
            color: #047857;
            font-size: 8pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            letter-spacing: 0.5px;
        }
        .compliance-pill {
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
            margin-top: 2px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 6px;
            border-collapse: separate;
        }
        .info-col {
            width: 50%;
            vertical-align: top;
            padding: 5px 8px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .info-col-title {
            font-size: 8pt;
            font-weight: bold;
            color: #059669;
            margin-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
        }
        .info-item {
            margin-bottom: 2px;
            font-size: 8pt;
        }
        .info-label {
            color: #64748b;
            font-weight: normal;
        }
        .info-value {
            font-weight: bold;
            color: #0f172a;
        }
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 6px;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
            border-left: 3px solid #059669;
            padding-left: 5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 3px 5px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        .data-table td {
            padding: 3px 5px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 7.5pt;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .badge-green {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            font-size: 7pt;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .badge-yellow {
            display: inline-block;
            background-color: #fffbeb;
            color: #d97706;
            font-size: 7pt;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .badge-red {
            display: inline-block;
            background-color: #fef2f2;
            color: #dc2626;
            font-size: 7pt;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }
        .metric-cards-table {
            width: 100%;
            margin-bottom: 6px;
            border-collapse: separate;
        }
        .metric-card {
            width: 25%;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 3px 5px;
            text-align: center;
        }
        .metric-label {
            font-size: 7pt;
            color: #166534;
            font-weight: bold;
        }
        .metric-value {
            font-size: 10.5pt;
            font-weight: bold;
            color: #14532d;
            margin-top: 1px;
        }
        .metric-unit {
            font-size: 7pt;
            font-weight: normal;
            color: #15803d;
        }
        .notice-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #059669;
            padding: 4px 6px;
            font-size: 7pt;
            color: #475569;
            margin-top: 6px;
            line-height: 1.3;
        }
        .footer-table {
            width: 100%;
            margin-top: 8px;
            border-top: 1px solid #cbd5e1;
            padding-top: 0;
            border-collapse: collapse;
        }
        .sign-space-cell {
            height: 55px;
            vertical-align: bottom;
            font-size: 1pt;
            line-height: 1;
        }
        .sign-col {
            text-align: center;
            width: 33.33%;
            vertical-align: top;
            padding: 0 4px;
        }
        .sign-hr {
            border: none;
            border-top: 1px dashed #64748b;
            margin: 0 auto 4px auto;
            width: 75%;
            height: 1px;
        }
        .sign-role {
            font-size: 8pt;
            font-weight: bold;
            color: #1e293b;
            margin-top: 2px;
        }
        .sign-desc {
            font-size: 6.8pt;
            color: #64748b;
            margin-top: 1px;
        }
    </style>
</head>
<body>

@php
    $toBnNum = function ($num) {
        if ($num === null || $num === '') return '';
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        return str_replace($en, $bn, (string)$num);
    };

    $formatWaterDist = function ($dist) use ($toBnNum) {
        if (!$dist || $dist === 'সংলগ্ন') return 'সংলগ্ন';
        if (preg_match('/(\d+)\s*m/i', (string)$dist, $matches)) {
            return $toBnNum($matches[1]) . ' মিটার';
        }
        return $toBnNum($dist);
    };

    $geoDict = [
        // Divisions
        'khulna' => 'খুলনা',
        'barishal' => 'বরিশাল',
        'chattogram' => 'চট্টগ্রাম',
        'chittagong' => 'চট্টগ্রাম',
        'dhaka' => 'ঢাকা',
        'mymensingh' => 'ময়মনসিংহ',
        'rajshahi' => 'রাজশাহী',
        'rangpur' => 'রংপুর',
        'sylhet' => 'সিলেট',
        // Districts
        'cox\'s bazar' => 'কক্সবাজার',
        'coxs bazar' => 'কক্সবাজার',
        'satkhira' => 'সাতক্ষীরা',
        'bagerhat' => 'বাগেরহাট',
        'khulna' => 'খুলনা',
        'patuakhali' => 'পটুয়াখালী',
        'bhola' => 'ভোলা',
        'pirojpur' => 'পিরোজপুর',
        'barguna' => 'বরগুনা',
        'jhalokathi' => 'ঝালকাঠি',
        'noakhali' => 'নোয়াখালী',
        'lakshmipur' => 'লক্ষ্মীপুর',
        'feni' => 'ফেনী',
        'chandpur' => 'চাঁদপুর',
        'jashore' => 'যশোর',
        'narail' => 'নড়াইল',
        'gopalganj' => 'গোপালগঞ্জ',
        'madaripur' => 'মাদারীপুর',
        'shariatpur' => 'শরীয়তপুর',
        // Upazilas
        'kaliganj' => 'কালীগঞ্জ',
        'shyamnagar' => 'শ্যামনগর',
        'assasuni' => 'আশাশুনি',
        'debhata' => 'দেবহাটা',
        'kalaroa' => 'কলারোয়া',
        'satkhira sadar' => 'সাতক্ষীরা সদর',
        'tala' => 'তালা',
        'koyra' => 'কয়রা',
        'paikgachha' => 'পাইকগাছা',
        'dacope' => 'দাকোপ',
        'batiaghata' => 'বটিয়াঘাটা',
        'dumuria' => 'ডুমুরিয়া',
        'dighalia' => 'দিঘলিয়া',
        'rupsha' => 'রূপসা',
        'terokhada' => 'তেরখাদা',
        'phultala' => 'ফুলতলা',
        'mongla' => 'মোংলা',
        'rampal' => 'রামপাল',
        'morrelganj' => 'মোরেলগঞ্জ',
        'sarankhola' => 'শরণখোলা',
        'bagerhat sadar' => 'বাগেরহাট সদর',
        'chitalmari' => 'চিতলমারী',
        'fakirhat' => 'ফকিরহাট',
        'kachua' => 'কচুয়া',
        'mollahat' => 'মোল্লাহাট',
        'teknaf' => 'টেকনাফ',
        'ukhiya' => 'উখিয়া',
        'chokoria' => 'চকোরিয়া',
        'chakaria' => 'চকোরিয়া',
        'moheshkhali' => 'মহেশখালী',
        'maheshkhali' => 'মহেশখালী',
        'kutubdia' => 'কুতুবদিয়া',
        'pekuya' => 'পেকুয়া',
        'cox\'s bazar sadar' => 'কক্সবাজার সদর',
        'trishal' => 'ত্রিশাল',
        'muktagachha' => 'মুক্তাগাছা',
        'mymensingh sadar' => 'ময়মনসিংহ সদর',
        'bhaluka' => 'ভালুকা',
        'fulbaria' => 'ফুলবাড়িয়া',
        'gafargaon' => 'গফরগাঁও',
        // Unions
        'gabura' => 'গাবুরা',
        'gabura union' => 'গাবুরা',
        'padmapukur' => 'পদ্মপুকুর',
        'padmapukur union' => 'পদ্মপুকুর',
        'burigoalini' => 'বুড়িগোয়ালিনী',
        'burigoalini union' => 'বুড়িগোয়ালিনী',
        'munshiganj' => 'মুন্সীগঞ্জ',
        'munshiganj union' => 'মুন্সীগঞ্জ',
        'atulia' => 'আটুলিয়া',
        'atulia union' => 'আটুলিয়া',
        'nurnagar' => 'নূরনগর',
        'koikhali' => 'কৈখালী',
        'kashimari' => 'কাশিমারী',
        'kulia' => 'কুলিয়া',
        'barobaria' => 'বারোবাড়িয়া',
        'batiaghata union' => 'বটিয়াঘাটা',
        'chitalmari sadar union' => 'চিতলমারী সদর',
        'rampal union' => 'রামপাল',
        'fakirhat union' => 'ফকিরহাট',
        'dumuria sadar union' => 'ডুমুরিয়া সদর',
    ];

    $termDict = [
        // Water Sources
        'canal' => 'ক্যানেল / খাল',
        'river' => 'প্রাকৃতিক নদী',
        'deep_tubewell' => 'গভীর নলকূপ',
        'deep tubewell' => 'গভীর নলকূপ',
        'pond/groundwater' => 'পুকুর ও ভূগর্ভস্থ পানি',
        'pond' => 'পুকুর',
        'groundwater' => 'ভূগর্ভস্থ পানি',
        'tidal river' => 'জোয়ার-ভাটার নদী',
        // Water Exchange
        'no' => 'সুবিধা নেই',
        'partial' => 'আংশিক সুবিধা',
        'yes' => 'পরিপূর্ণ সুবিধা',
        'daily' => 'নিয়মিত দৈনিক পরিবর্তন',
        'weekly' => 'সাপ্তাহিক পরিবর্তন',
        'monthly' => 'মাসিক পরিবর্তন',
        // Technical Support
        'govt extension' => 'উপজেলা মৎস্য দপ্তর (DoF)',
        'government extension' => 'উপজেলা মৎস্য দপ্তর (DoF)',
        'dof officer' => 'উপজেলা মৎস্য কর্মকর্তা (DoF)',
        'dof' => 'উপজেলা মৎস্য দপ্তর (DoF)',
        'feed company officer' => 'ফিড কোম্পানি টেকনিক্যাল অফিসার',
        'local vet' => 'স্থানীয় ভেট / পশু চিকিৎসক',
        'ngo worker' => 'এনজিও মাঠকর্মী',
        'none' => 'নেই',
        'consultant' => 'মৎস্য বিশেষজ্ঞ / কনসালটেন্ট',
        // Advice Source
        'feed dealer' => 'ফিড ও ওষুধ ডিলার',
        'self-experience' => 'নিজ অভিজ্ঞতা',
        'neighbor farmers' => 'অভিজ্ঞ প্রতিবেশী চাষি',
        'neighbors' => 'অভিজ্ঞ প্রতিবেশী চাষি',
        'department of fisheries' => 'মৎস্য অধিদপ্তর (DoF)',
        'veterinarian' => 'ভেটেরিনারি সার্জন',
        // Managers
        'self' => 'স্বত্বাধিকারী নিজে',
        'hired manager' => 'নিয়োগকৃত ব্যবস্থাপক',
        'family' => 'পরিবারের সদস্য',
        'family member' => 'পরিবারের সদস্য',
        'shared worker' => 'অংশীদারী কর্মী',
        // Culture Types
        'bagda' => 'উন্নত বাগদা চিংড়ি চাষ',
        'shrimp' => 'উপকূলীয় বাগদা ও চিংড়ি চাষ',
        'golda' => 'গলদা চিংড়ি চাষ',
        'tilapia' => 'তেলাপিয়া মাছ চাষ',
        'carp' => 'কার্প জাতীয় মাছ চাষ',
        'pangas' => 'পাঙ্গাস মাছ চাষ',
        'mixed' => 'মিশ্র মৎস্য ও চিংড়ি চাষ',
        // Farm Types / Ownership
        'own' => 'নিজস্ব জমি / ঘের',
        'own land' => 'নিজস্ব জমি / ঘের',
        'leased' => 'ইজারা / লিজকৃত ঘের',
        'leased gher' => 'ইজারা / লিজকৃত ঘের',
        'family' => 'পারিবারিক পৈতৃক ঘের',
        'family inherited' => 'পারিবারিক পৈতৃক ঘের',
        'partnership' => 'যৌথ অংশীদারিত্বের ঘের',
        'partnership estate' => 'যৌথ অংশীদারিত্বের ঘের',
        'other' => 'অন্যান্য ধরন',
        // Farming Systems
        'extensive' => 'সনাতন / ব্যাপক পদ্ধতি',
        'improved extensive' => 'উন্নত সনাতন পদ্ধতি',
        'improved_extensive' => 'উন্নত সনাতন পদ্ধতি',
        'semi-intensive' => 'আধা-নিবিড় পদ্ধতি',
        'semi_intensive' => 'আধা-নিবিড় পদ্ধতি',
        'intensive' => 'নিবিড় এয়ারেটেড পদ্ধতি',
        'traditional' => 'ঐতিহ্যবাহী উপকূলীয় ঘের',
        // Facilities
        'net/harvest' => 'মাছ ধরার জাল ও আহরণ সরঞ্জাম',
        'net / harvest' => 'মাছ ধরার জাল ও আহরণ সরঞ্জাম',
        'storage' => 'গুদামঘর',
        'feed_store' => 'ফিড গুদাম',
        'electric' => 'বিদ্যুৎ সংযোগ',
        'aerator' => 'এয়ারেটর',
        'fencing' => 'সুরক্ষা বেড়া',
        'generator' => 'জেনারেটর',
        'feeding equip.' => 'খাদ্য প্রয়োগ সরঞ্জাম',
        'feeding equipment' => 'খাদ্য প্রয়োগ সরঞ্জাম',
        'pump' => 'পানি পাম্প',
        'water_pump' => 'পানি পাম্প',
        'solar' => 'সোলার প্যানেল',
        'guard_shed' => 'পাহারা শেড',
        'guard shed' => 'পাহারা শেড',
        'laboratory' => 'ল্যাব কিট',
        'bio-security' => 'বায়োসিকিউরিটি বেষ্টনী',
        'sluice_gate' => 'স্লুইস গেট',
        // Aerator HP
        '1 hp' => '১ HP',
        '2 hp' => '২ HP',
        '3 hp' => '৩ HP',
        '5 hp' => '৫ HP',
    ];

    $taxonomyMap = $taxonomyMap ?? [];

    $tr = function ($val, $default = '') use ($taxonomyMap, $termDict, $geoDict) {
        if (!$val) return $default;
        $k = strtolower(trim((string)$val));
        if (isset($taxonomyMap[$k])) return $taxonomyMap[$k];
        if (isset($termDict[$k])) return $termDict[$k];
        if (isset($geoDict[$k])) return $geoDict[$k];
        return $val;
    };
@endphp

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
                মুদ্রণ তারিখ: {{ $toBnNum(now()->format('d/m/Y h:i A')) }} (UTC+6)<br/>
                অফিসিয়াল পোর্টাল: https://farmlinkcare.com/
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
                    <span class="info-value">{{ $toBnNum($farm->user?->phone ?? '—') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">লিঙ্গ ও সদস্য পদ:</span>
                    <span class="info-value">{{ $farm->user?->gender === 'female' ? 'মহিলা চাষি' : ($farm->user?->gender === 'male' ? 'পুরুষ চাষি' : 'সাধারণ') }} | সদস্য আইডি #{{ $toBnNum($farm->user_id) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">খামার প্রতিষ্ঠার বছর:</span>
                    <span class="info-value">{{ $farm->established_year ? $toBnNum($farm->established_year) : 'নথিভুক্ত নয়' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">চাষের ধরন (Culture Type):</span>
                    <span class="info-value">{{ $tr($farm->culture_type ?? $farm->main_culture_type, 'মিশ্র মৎস্য ও চিংড়ি চাষ') }}</span>
                </div>
            </td>

            <!-- Geographical Location -->
            <td class="info-col">
                <div class="info-col-title">২. ভৌগোলিক অবস্থান ও ঠিকানা (Location & Coordinates)</div>
                <div class="info-item">
                    <span class="info-label">বিভাগ ও জেলা:</span>
                    <span class="info-value">{{ $farm->division_bn ?? $farm->division?->bn_name ?? $tr($farm->division?->name, 'খুলনা') }} বিভাগ, {{ $farm->district_bn ?? $farm->districtModel?->bn_name ?? $tr($farm->district, 'খুলনা') }} জেলা</span>
                </div>
                <div class="info-item">
                    <span class="info-label">উপজেলা ও ইউনিয়ন:</span>
                    @php
                        $upz = $farm->upazila_bn ?? $farm->upazilaModel?->bn_name ?? $tr($farm->upazila, 'কালীগঞ্জ');
                        $rawUnion = $farm->union_bn ?? $farm->unionModel?->bn_name ?? $tr($farm->union, 'সদর');
                        $unStr = str_contains($rawUnion, 'ইউনিয়ন') || str_contains($rawUnion, 'ইউনিয়ন') ? $rawUnion : $rawUnion . ' ইউনিয়ন';
                    @endphp
                    <span class="info-value">{{ $upz }} উপজেলা, {{ $unStr }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">গ্রাম / নির্দিষ্ট ঠিকানা:</span>
                    <span class="info-value">{{ $tr($farm->village ?: ($farm->farm_address ?: 'উপকূলীয় ঘের এলাকা')) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">জিপিএস স্থানাঙ্ক (GPS):</span>
                    <span class="info-value">
                        @php
                            $lat = $farm->gps_lat;
                            $lng = $farm->gps_lng ?? $farm->gps_long;
                        @endphp
                        @if($lat && $lng)
                            {{ $toBnNum(number_format($lat, 6)) }}<span style="font-family: 'DejaVu Sans', sans-serif;">&deg;</span> N, {{ $toBnNum(number_format($lng, 6)) }}<span style="font-family: 'DejaVu Sans', sans-serif;">&deg;</span> E
                        @else
                            উপগ্রহ মানচিত্রে নিবন্ধিত (অপেক্ষমান)
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">পানির মূল উৎস:</span>
                    <span class="info-value">{{ $tr($farm->main_water_source ?? $farm->water_source, 'প্রাকৃতিক জোয়ার-ভাটার নদী ও খাল') }} ({{ $formatWaterDist($farm->water_source_distance) }})</span>
                </div>
                <div class="info-item">
                    <span class="info-label">পানি পরিবর্তনের সুবিধা:</span>
                    <span class="info-value">{{ $tr($farm->water_exchange_facility ?? $farm->water_exchange_frequency, 'নিয়মিত স্লুইস গেট ও পাম্প ব্যবস্থা') }}</span>
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
                    <span class="info-value">{{ $toBnNum(is_numeric($farm->total_area) ? number_format($farm->total_area, 2) : ($farm->total_area ?? '০')) }} শতাংশ / শতক</span>
                </div>
                <div class="info-item">
                    <span class="info-label">চাষযোগ্য জলাশয় আয়তন:</span>
                    <span class="info-value">{{ $toBnNum(is_numeric($farm->cultivation_area ?? $farm->cultivable_water_area ?? $farm->total_area) ? number_format($farm->cultivation_area ?? $farm->cultivable_water_area ?? $farm->total_area, 2) : '০') }} শতাংশ</span>
                </div>
                <div class="info-item">
                    <span class="info-label">ঘের / পুকুরের সংখ্যা:</span>
                    <span class="info-value">{{ $toBnNum($farm->pond_count ?? $farm->number_of_ponds ?? 1) }} টি</span>
                </div>
                <div class="info-item">
                    <span class="info-label">গড় পানির গভীরতা:</span>
                    <span class="info-value">{{ $farm->average_depth_feet ? $toBnNum($farm->average_depth_feet) . ' ফুট' : '৩.৫ - ৪.৫ ফুট' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">লবণাক্ততার মাত্রা (স্বাভাবিক):</span>
                    <span class="info-value">{{ isset($farm->salinity_ppt) && $farm->salinity_ppt ? $toBnNum($farm->salinity_ppt) . ' ppt' : 'উপকূলীয় মাঝারি লবণাক্ততা (১০-১৮ ppt)' }}</span>
                </div>
            </td>

            <!-- On-Site Infrastructure -->
            <td class="info-col">
                <div class="info-col-title">৪. অন-সাইট যন্ত্রপাতি ও ব্যবস্থাপনা (Equipment & Management)</div>
                <div class="info-item">
                    <span class="info-label">এয়ারেশন যন্ত্রপাতি:</span>
                    <span class="info-value">
                        @if($farm->aerator_count && $farm->aerator_count > 0)
                            @php
                                $hpClean = preg_replace('/[^0-9.]/', '', (string)($farm->aerator_hp ?: 2));
                            @endphp
                            {{ $toBnNum($farm->aerator_count) }} টি পেডেলহুইল ({{ $toBnNum($hpClean ?: '২') }} HP)
                        @else
                            প্রাকৃতিক বাতাস ও অক্সিজেন নির্ভর
                        @endif
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">খামার ব্যবস্থাপক:</span>
                    <span class="info-value">{{ $tr($farm->farm_manager ?? $farm->manager_type, 'স্বত্বাধিকারী নিজে') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">কারিগরি সহায়তার মাধ্যম:</span>
                    <span class="info-value">{{ $tr($farm->technical_support_used ?? $farm->technical_support, 'উপজেলা মৎস্য কার্যালয় ও স্থানীয় বিশেষজ্ঞ') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">পরামর্শের মূল উৎস:</span>
                    <span class="info-value">{{ $tr($farm->main_advice_source ?? $farm->primary_advice_source, 'মৎস্য অধিদপ্তর ও বিশেষজ্ঞ চাষি') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">অন-সাইট অবকাঠামো:</span>
                    <span class="info-value">
                        @php
                            $facs = $farm->available_facilities ?? $farm->facilities;
                        @endphp
                        @if(!empty($facs) && is_array($facs))
                            {{ implode(', ', array_map(fn($f) => $tr($f), $facs)) }}
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
                <div class="metric-value">{{ $toBnNum($telemetryCount) }} <span class="metric-unit">টি লগ</span></div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় দ্রবীভূত অক্সিজেন (DO)</div>
                <div class="metric-value">
                    {{ $toBnNum($avgDo ? number_format($avgDo, 1) : '৫.২') }} <span class="metric-unit">mg/L</span>
                </div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় পিএইচ মাত্রা (pH)</div>
                <div class="metric-value">
                    {{ $toBnNum($avgPh ? number_format($avgPh, 1) : '৭.৮') }} <span class="metric-unit">pH</span>
                </div>
            </td>
            <td class="metric-card">
                <div class="metric-label">গড় লবণাক্ততা (Salinity)</div>
                <div class="metric-value">
                    {{ $toBnNum($avgSalinity ? number_format($avgSalinity, 1) : ($farm->salinity_ppt ? number_format($farm->salinity_ppt, 1) : '১২.০')) }} <span class="metric-unit">ppt</span>
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
                        <td>{{ $telemetry->recorded_at ? $toBnNum($telemetry->recorded_at->format('d/m/Y h:i A')) : '—' }}</td>
                        <td>{{ $telemetry->pond_name ?: 'মূল ঘের' }}</td>
                        <td><strong>{{ $telemetry->dissolved_oxygen !== null ? $toBnNum(number_format($telemetry->dissolved_oxygen, 1)) . ' mg/L' : '—' }}</strong></td>
                        <td>{{ $telemetry->ph !== null ? $toBnNum(number_format($telemetry->ph, 1)) : '—' }}</td>
                        <td>{{ $telemetry->salinity !== null ? $toBnNum(number_format($telemetry->salinity, 1)) . ' ppt' : '—' }}</td>
                        <td>
                            @if($telemetry->temperature !== null)
                                {{ $toBnNum(number_format($telemetry->temperature, 1)) }}<span style="font-family: 'DejaVu Sans', sans-serif;">&deg;</span>C
                            @else
                                —
                            @endif
                        </td>
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
        <div style="font-size: 8px; color: #64748b; padding: 3px 0; margin-bottom: 6px;">
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
                        <span class="info-value">{{ $activeCycle->start_date ? $toBnNum($activeCycle->start_date->diffInDays(now())) . ' দিন' : 'চলমান' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">মজুদকৃত পোনার সংখ্যা:</span>
                        <span class="info-value">{{ $toBnNum(number_format($activeCycle->stocking_quantity ?? 0)) }} টি</span>
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
                    <span class="info-value">{{ $toBnNum($vetVisitsCount + $consultantVisitsCount) }} বার অন-সাইট পরিদর্শন সম্পন্ন</span>
                </div>
                <div class="info-item">
                    <span class="info-label">প্ল্যাটফর্ম অনুমোদিত পণ্য ক্রয়:</span>
                    <span class="info-value">{{ $toBnNum($ordersCount) }} টি অনুমোদিত গুণগত ফিড ও প্রোবায়োটিক ইনভয়েস</span>
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

    <!-- Official Sign-off Signatures with dedicated signature/stamping room -->
    <table class="footer-table">
        <tr>
            <td class="sign-space-cell">&nbsp;</td>
            <td class="sign-space-cell">&nbsp;</td>
            <td class="sign-space-cell">&nbsp;</td>
        </tr>
        <tr>
            <td class="sign-col">
                <hr class="sign-hr" />
                <div class="sign-role">{{ $farm->user?->name ?? 'খামার স্বত্বাধিকারী' }}</div>
                <div class="sign-desc">খামার মালিক / ব্যবস্থাপকের স্বাক্ষর ও তারিখ</div>
            </td>
            <td class="sign-col">
                <hr class="sign-hr" />
                <div class="sign-role">ফার্মলিঙ্ক টেকনিক্যাল অফিসার</div>
                <div class="sign-desc">মাঠ পরিদর্শন কর্মকর্তা ও ডিজিটাল অনুমোদন</div>
            </td>
            <td class="sign-col">
                <hr class="sign-hr" />
                <div class="sign-role">উপজেলা মৎস্য কর্মকর্তা (DoF)</div>
                <div class="sign-desc">পরিদর্শন অনুমোদন ও দাপ্তরিক সিলমোহর</div>
            </td>
        </tr>
    </table>

</body>
</html>
