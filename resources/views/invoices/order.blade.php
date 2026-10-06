<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $order->invoice_number }}</title>
    <style>
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) }}') format('truetype');
            font-weight: 600;
            font-style: normal;
        }
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) }}') format('truetype');
            font-weight: 700;
            font-style: normal;
        }
        @font-face {
            font-family: 'Kalpurush';
            src: url('data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(storage_path("fonts/kalpurush.ttf"))) }}') format('truetype');
            font-weight: 800;
            font-style: normal;
        }
        .currency-symbol {
            font-family: 'Kalpurush', sans-serif !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 1.05em;
            line-height: 1;
        }
        @page {
            margin: 25px 30px;
            size: a4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #10b981;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo-title {
            font-size: 22px;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .logo-subtitle {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .company-meta {
            font-size: 10px;
            color: #475569;
            text-align: right;
        }
        .invoice-title-block {
            margin-bottom: 20px;
        }
        .invoice-badge {
            display: inline-block;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-col {
            width: 50%;
            vertical-align: top;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-right: 10px;
        }
        .info-card.right {
            margin-right: 0;
            margin-left: 10px;
        }
        .card-header {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .info-row {
            margin-bottom: 4px;
        }
        .info-label {
            font-weight: 600;
            color: #475569;
            display: inline-block;
            width: 90px;
        }
        .info-value {
            color: #0f172a;
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .totals-box {
            width: 260px;
            float: right;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f8fafc;
            padding: 10px 14px;
        }
        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
            font-size: 11px;
        }
        .totals-label {
            display: table-cell;
            color: #64748b;
            font-weight: 500;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            color: #0f172a;
            font-weight: 600;
        }
        .totals-grand {
            border-top: 1.5px solid #0f172a;
            padding-top: 6px;
            margin-top: 6px;
            font-size: 13px;
        }
        .totals-grand .totals-label {
            color: #0f172a;
            font-weight: 800;
        }
        .totals-grand .totals-value {
            color: #059669;
            font-weight: 800;
        }
        .notes-box {
            background-color: #fefce8;
            border: 1px solid #fef08a;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 20px;
            font-size: 10px;
            color: #713f12;
            clear: both;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            clear: both;
        }
    </style>
</head>
<body>

    <!-- Header Table -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="vertical-align: middle;">
                @if(\App\Models\Setting::get('site_logo_url'))
                    <img src="{{ \App\Models\Setting::get('site_logo_url') }}" alt="{{ \App\Models\Setting::get('site_name', 'FarmLink') }}" style="max-height: 44px; max-width: 180px; object-fit: contain; margin-bottom: 4px; display: block;" />
                @endif
                <h1 class="logo-title">{{ \App\Models\Setting::get('site_name', 'FarmLink') }}</h1>
                <div class="logo-subtitle">Aquaculture Supply & Advisory Network</div>
            </td>
            <td class="company-meta" style="vertical-align: middle;">
                <strong>{{ \App\Models\Setting::get('site_name', 'FarmLink') }} Operations Depot</strong><br>
                {{ \App\Models\Setting::get('business_hours', 'Khulna & Satkhira Regional Hub, Bangladesh') }}<br>
                Helpline: {{ \App\Models\Setting::get('contact_phone', '+880 1700-000000') }} | Email: {{ \App\Models\Setting::get('contact_email', 'support@farmlink.com.bd') }}
            </td>
        </tr>
    </table>

    <!-- Info Table: Left (Invoice details), Right (Customer & Farm) -->
    <table class="info-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-col">
                <div class="info-card">
                    <div class="card-header">Tax Invoice / Receipt</div>
                    <div class="info-row">
                        <span class="info-label">Invoice No:</span>
                        <span class="info-value" style="font-weight: 800; color: #059669;">{{ $order->invoice_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Ref:</span>
                        <span class="info-value">#ORD-{{ $order->id }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date Issued:</span>
                        <span class="info-value">{{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : date('d M Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Channel:</span>
                        <span class="info-value">{{ $order->channel === 'admin_pos' ? 'Assisted Sale (Admin POS)' : 'Self-Service Online' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Mode:</span>
                        <span class="info-value" style="text-transform: uppercase;">
                            @if($order->payment_mode === 'cash') Cash Payment
                            @elseif($order->payment_mode === 'sslcommerz') Online Payment (SSLCommerz)
                            @else Cash on Delivery (COD)
                            @endif
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="invoice-badge">{{ ucfirst($order->status) }}</span>
                    </div>
                </div>
            </td>
            <td class="info-col">
                <div class="info-card right">
                    <div class="card-header">Billed To (Customer Details)</div>
                    <div class="info-row">
                        <span class="info-label">Farmer Name:</span>
                        <span class="info-value" style="font-weight: 700;">{{ $order->user?->name ?? 'Guest Farmer' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number:</span>
                        <span class="info-value">{{ $order->user?->phone ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">District:</span>
                        <span class="info-value">{{ $order->user?->district ?? '—' }}</span>
                    </div>
                    @if($order->farm)
                    <div class="info-row" style="margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 4px;">
                        <span class="info-label">Target Farm:</span>
                        <span class="info-value" style="color: #047857; font-weight: 700;">{{ $order->farm->farm_name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Farm Location:</span>
                        <span class="info-value">{{ $order->farm->district }}{{ $order->farm->upazila ? ', ' . $order->farm->upazila : '' }}</span>
                    </div>
                    @else
                    <div class="info-row">
                        <span class="info-label">Target Farm:</span>
                        <span class="info-value" style="color: #94a3b8; font-style: italic;">Unspecified / Direct Delivery</span>
                    </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th>Product Description</th>
                <th style="width: 70px;" class="text-center">Category</th>
                <th style="width: 85px;" class="text-right">Unit Price</th>
                <th style="width: 50px;" class="text-center">Qty</th>
                <th style="width: 95px;" class="text-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $index => $item)
            <tr>
                <td class="text-center" style="color: #64748b;">{{ $index + 1 }}</td>
                <td>
                    <strong style="color: #0f172a;">{{ $item->product?->name ?? 'Product #' . $item->product_id }}</strong>
                    @if($item->product?->sku)
                        <div style="font-size: 8.5px; color: #94a3b8; font-family: monospace;">SKU: {{ $item->product->sku }}</div>
                    @endif
                </td>
                <td class="text-center" style="color: #475569; text-transform: capitalize;">
                    {{ $item->product?->category ?? 'Supply' }}
                </td>
                <td class="text-right" style="color: #475569;">
                    <span class="currency-symbol">&#x09F3;</span> {{ number_format((float) $item->price_at_purchase, 2) }}
                </td>
                <td class="text-center" style="font-weight: 700; color: #0f172a;">
                    {{ $item->quantity }}
                </td>
                <td class="text-right" style="font-weight: 700; color: #0f172a;">
                    <span class="currency-symbol">&#x09F3;</span> {{ number_format((float) ($item->price_at_purchase * $item->quantity), 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #94a3b8;">
                    No line items found for this order.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Financial Totals -->
    <div style="width: 100%; margin-bottom: 25px;">
        @php
            $subtotal = $order->subtotal !== null ? (float) $order->subtotal : (float) $order->total;
            $deliveryFee = $order->delivery_fee !== null ? (float) $order->delivery_fee : 0.00;
            $discount = $order->discount_amount !== null ? (float) $order->discount_amount : 0.00;
        @endphp
        <div class="totals-box">
            <div class="totals-row">
                <span class="totals-label">Subtotal:</span>
                <span class="totals-value"><span class="currency-symbol">&#x09F3;</span> {{ number_format($subtotal, 2) }}</span>
            </div>
            @if($discount > 0)
            <div class="totals-row" style="color: #b91c1c;">
                <span class="totals-label" style="color: #b91c1c;">Discount {{ $order->coupon ? '(' . $order->coupon->code . ')' : '' }}:</span>
                <span class="totals-value" style="color: #b91c1c;">- <span class="currency-symbol">&#x09F3;</span> {{ number_format($discount, 2) }}</span>
            </div>
            @endif
            <div class="totals-row">
                <span class="totals-label">Delivery Fee:</span>
                <span class="totals-value">
                    @if($deliveryFee > 0)
                        <span class="currency-symbol">&#x09F3;</span> {{ number_format($deliveryFee, 2) }}
                    @else
                        <span style="color: #059669; font-weight: 700;">FREE</span>
                    @endif
                </span>
            </div>
            <div class="totals-row totals-grand">
                <span class="totals-label">Total Payable:</span>
                <span class="totals-value"><span class="currency-symbol">&#x09F3;</span> {{ number_format((float) $order->total, 2) }}</span>
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Notes if present -->
    @if($order->notes)
    <div class="notes-box">
        <strong>Order Notes / Instructions:</strong><br>
        {{ $order->notes }}
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p style="margin: 0 0 3px 0; font-weight: 600; color: #475569;">
            Thank you for partnering with FarmLink! For inquiries or clinical aquaculture visits, visit farmlink.com.bd or call our helpline.
        </p>
        <p style="margin: 0;">
            This is a verified computer-generated document. Generated on {{ date('d M Y, h:i A') }} • FarmLink Bangladesh System.
        </p>
    </div>

</body>
</html>
