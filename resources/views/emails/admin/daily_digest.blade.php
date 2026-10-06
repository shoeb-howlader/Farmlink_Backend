@extends('emails.layouts.base')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">
            📊 {{ ucfirst($frequency ?? 'daily') }} Operations Digest
        </h2>
        <span class="badge badge-info">{{ $reportDate ?? now()->toFormattedDateString() }}</span>
    </div>

    <p class="lead">
        Hello {{ $admin->name ?? 'Admin' }},
    </p>

    <p>
        Here is your {{ $frequency ?? 'daily' }} operational health and activity summary for FarmLink.
    </p>

    <!-- Key Metrics Grid -->
    <table class="metric-grid" role="presentation" border="0" cellpadding="0" cellspacing="10">
        <tr>
            <td class="metric-cell" width="50%">
                <div class="metric-value">৳{{ number_format($salesTotal ?? 0, 2) }}</div>
                <div class="metric-label">Sales Revenue ({{ $ordersCount ?? 0 }} Orders)</div>
            </td>
            <td class="metric-cell" width="50%">
                <div class="metric-value" style="color: {{ ($lowStockCount ?? 0) > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ $lowStockCount ?? 0 }}
                </div>
                <div class="metric-label">Low Stock Products</div>
            </td>
        </tr>
        <tr>
            <td class="metric-cell" width="50%">
                <div class="metric-value" style="color: {{ ($pendingRequestsCount ?? 0) > 0 ? '#f59e0b' : '#16a34a' }};">
                    {{ $pendingRequestsCount ?? 0 }}
                </div>
                <div class="metric-label">Pending Service Requests</div>
            </td>
            <td class="metric-cell" width="50%">
                <div class="metric-value" style="color: {{ ($overdueFollowUpsCount ?? 0) > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ $overdueFollowUpsCount ?? 0 }}
                </div>
                <div class="metric-label">Overdue Follow-ups</div>
            </td>
        </tr>
    </table>

    <!-- Low Stock Highlights if any -->
    @if (!empty($lowStockItems) && count($lowStockItems) > 0)
        <div style="margin-top: 24px;">
            <h3 style="font-size: 15px; font-weight: 700; color: #b91c1c; margin-bottom: 8px;">
                ⚠️ Low Stock Alert (Action Required)
            </h3>
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th style="text-align: right;">Remaining Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($lowStockItems, 0, 5) as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td>{{ $item['category'] ?? 'Supplies' }}</td>
                            <td style="text-align: right; font-weight: 700; color: #dc2626;">{{ $item['stock'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div style="text-align: center; margin: 32px 0 16px;">
        <a href="{{ $dashboardUrl ?? url('/admin/dashboard') }}" class="btn" style="color: #ffffff !important;">
            Go to Admin Dashboard
        </a>
    </div>
@endsection

@section('footer_reason')
    You received this email because you are a registered administrator on FarmLink.
@endsection
