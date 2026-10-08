<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAYMENT_FAILED = 'payment_failed';
    case PAID_NEEDS_REVIEW = 'paid_needs_review';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case DISPATCHED = 'dispatched';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Get all string values of the enum.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
