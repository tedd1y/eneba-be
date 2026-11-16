<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Human-readable name for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether a payment may be attempted: new orders, and orders whose previous payment failed.
     */
    public function canBePaid(): bool
    {
        return match ($this) {
            self::Pending, self::Failed => true,
            self::Paid, self::Completed, self::Cancelled => false,
        };
    }

    /**
     * Whether payment has already been taken.
     */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Completed], true);
    }
}
