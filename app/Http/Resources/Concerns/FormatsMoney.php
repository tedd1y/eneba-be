<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Number;

trait FormatsMoney
{
    /**
     * Represent an amount in cents as both raw cents and a formatted string.
     *
     * @return array{amount: int, formatted: string}
     */
    protected function money(int $cents, string $currency): array
    {
        return [
            /**
             * Amount in minor units (cents).
             *
             * @example 4299
             */
            'amount' => (int) $cents,
            /**
             * Amount formatted for display in the given currency.
             *
             * @example €42.99
             */
            'formatted' => (string) Number::currency($cents / 100, in: $currency, locale: 'en'),
        ];
    }
}
