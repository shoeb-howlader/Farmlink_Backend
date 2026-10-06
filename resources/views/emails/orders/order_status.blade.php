@extends('emails.layouts.base')

@section('content')
    @php
        $statusColors = [
            'pending' => 'badge-warning',
            'confirmed' => 'badge-success',
            'dispatched' => 'badge-info',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
            'returned' => 'badge-warning',
        ];
        $statusLabels = [
            'pending' => 'Order Received',
            'confirmed' => 'Order Confirmed',
            'dispatched' => 'Dispatched for Delivery',
            'delivered' => 'Successfully Delivered',
            'cancelled' => 'Order Cancelled',
            'returned' => 'Order Returned',
        ];
        $badgeClass = $statusColors[$order->status] ?? 'badge-info';
        $statusTitle = $statusLabels[$order->status] ?? ucfirst($order->status);
    @endphp

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <span class="badge {{ $badgeClass }}">{{ $statusTitle }}</span>
            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 6px; margin-bottom: 0;">
                Order #{{ $order->id }} ({{ $order->invoice_number ?? 'INV-' . $order->id }})
            </h2>
        </div>
    </div>

    <p class="lead">
        Hello {{ $order->recipient_name ?? $order->user?->name ?? 'Farmer' }},
    </p>

    <p>
        @if ($order->status === 'confirmed')
            Great news! Your order has been confirmed by FarmLink and is currently being prepared for dispatch.
        @elseif ($order->status === 'dispatched')
            Your order is on the way! It has been dispatched for delivery to your specified location.
        @elseif ($order->status === 'delivered')
            Your order has been delivered. Thank you for choosing FarmLink for your farming essentials!
        @elseif ($order->status === 'cancelled')
            Your order #{{ $order->id }} has been marked as cancelled. If this was unexpected, please contact our support team.
        @elseif ($order->status === 'returned')
            Your order #{{ $order->id }} has been marked as returned and inventory has been restocked. If you have questions regarding return credit or replacements, please contact our support desk.
        @else
            Thank you for placing your order with FarmLink. We have received your order and are reviewing it.
        @endif
    </p>

    <!-- Order Items Summary -->
    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-top: 24px; margin-bottom: 8px;">
        Order Summary
    </h3>
    <table class="info-table">
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @php
                    $itemPrice = $item->price_at_purchase ?? $item->product?->price ?? 0;
                    $itemSubtotal = $itemPrice * ($item->quantity ?? 1);
                @endphp
                <tr>
                    <td>
                        <strong>{{ $item->product?->name ?? 'Product' }}</strong>
                        @if ($item->variant)
                            <div style="font-size: 12px; color: #64748b;">{{ $item->variant->variant_label }}</div>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">৳{{ number_format($itemPrice, 2) }}</td>
                    <td style="text-align: right; font-weight: 600;">৳{{ number_format($itemSubtotal, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" style="text-align: right; font-weight: 700; padding-top: 14px;">Total Amount:</td>
                <td style="text-align: right; font-weight: 800; font-size: 16px; color: #16a34a; padding-top: 14px;">
                    ৳{{ number_format($order->total, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Delivery & Invoice Attachment Note -->
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;">
        <h4 style="margin: 0 0 6px; font-size: 13px; color: #475569; text-transform: uppercase;">Delivery Details</h4>
        <p style="margin: 0; font-size: 14px; color: #1e293b;">
            <strong>Recipient:</strong> {{ $order->recipient_name }} ({{ $order->recipient_phone }})<br>
            @if ($order->delivery_address)
                <strong>Address:</strong> {{ $order->delivery_address }}<br>
            @endif
            @if ($order->district)
                <strong>District:</strong> {{ $order->district }}
            @endif
        </p>
    </div>

    @if ($hasAttachment ?? false)
        <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 16px; margin-top: 20px;">
            <p style="margin: 0; font-size: 13px; color: #065f46;">
                📎 <strong>Invoice Attached:</strong> Your official FarmLink tax invoice PDF is attached to this email for your records.
            </p>
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0 10px;">
        <a href="{{ $orderUrl ?? url('/orders/' . $order->id) }}" class="btn" style="color: #ffffff !important;">
            View Order in FarmLink Portal
        </a>
    </div>
@endsection

@section('footer_reason')
    You received this supplementary notification because you provided an email address on your FarmLink account.
@endsection
