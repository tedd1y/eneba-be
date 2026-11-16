<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * Maximum number of distinct products in one order.
     */
    public const MAX_ITEMS = 40;

    /**
     * Maximum quantity of a single product in one order.
     */
    public const MAX_QUANTITY_PER_ITEM = 10;

    /**
     * Maximum number of keys in one order, across all items.
     */
    public const MAX_TOTAL_QUANTITY = 40;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /**
             * Where the keys and receipt are sent.
             *
             * @example gamer@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255'],
            /** Products to buy, 1–40 distinct products and at most 40 keys in total. Prices are always taken from the catalog. */
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            /**
             * ID of an active product. Each product may appear only once; use `quantity` for more keys.
             *
             * @example 12
             */
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            /**
             * Number of keys of this product, 1–10.
             *
             * @example 1
             */
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY_PER_ITEM],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateTotalQuantity($validator),
            fn (Validator $validator) => $this->validateProductsAreAvailable($validator),
        ];
    }

    /**
     * Reject orders with more than the allowed number of keys in total.
     */
    private function validateTotalQuantity(Validator $validator): void
    {
        $totalQuantity = collect($this->itemsInput())
            ->sum(fn (array $item) => is_numeric($item['quantity'] ?? null) ? (int) $item['quantity'] : 0);

        if ($totalQuantity > self::MAX_TOTAL_QUANTITY) {
            $validator->errors()->add('items', 'An order may contain at most '.self::MAX_TOTAL_QUANTITY.' keys in total.');
        }
    }

    /**
     * Check every product exists and is active with a single query, instead of one `exists` query per item.
     */
    private function validateProductsAreAvailable(Validator $validator): void
    {
        $productIds = collect($this->itemsInput())
            ->map(fn (array $item, int|string $index) => filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT))
            ->filter(fn (int|false $productId, int|string $index) => $productId !== false && ! $validator->errors()->has("items.{$index}.product_id"));

        if ($productIds->isEmpty()) {
            return;
        }

        $availableIds = Product::query()->active()->whereKey($productIds->unique()->values()->all())->pluck('id')->all();

        foreach ($productIds->diff($availableIds) as $index => $productId) {
            $validator->errors()->add("items.{$index}.product_id", 'The selected product is not available.');
        }
    }

    /**
     * The submitted items that are arrays, keyed by their position.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function itemsInput(): array
    {
        $items = $this->input('items');

        return is_array($items) ? array_filter($items, 'is_array') : [];
    }
}
