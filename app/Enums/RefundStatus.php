<?php

namespace App\Enums;

enum RefundStatus: string
{
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REJECTED = 'rejected';

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
