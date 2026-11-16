<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'number',
    'email',
    'status',
    'subtotal',
    'cashback',
    'currency',
    'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const NUMBER_PREFIX = 'O-';

    public const NUMBER_LENGTH = 8;

    /**
     * Characters used in order numbers; leaves out 0, O, 1 and I so numbers are easy to read out.
     */
    private const NUMBER_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::Pending->value,
        'cashback' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'cashback' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * Resolve order numbers case-insensitively, so `o-abcd2345` finds `O-ABCD2345`.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return parent::resolveRouteBinding(is_string($value) ? Str::upper($value) : $value, $field);
    }

    /**
     * Generate a random order number such as `O-7KD2XQ9M`. Uniqueness is checked by the caller.
     */
    public static function generateNumber(): string
    {
        $number = '';

        for ($i = 0; $i < self::NUMBER_LENGTH; $i++) {
            $number .= self::NUMBER_ALPHABET[random_int(0, strlen(self::NUMBER_ALPHABET) - 1)];
        }

        return self::NUMBER_PREFIX.$number;
    }

    /**
     * Products bought in this order, as snapshotted at checkout.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->chaperone()->orderBy('id');
    }
}
