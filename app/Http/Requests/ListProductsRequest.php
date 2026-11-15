<?php

namespace App\Http\Requests;

use App\Enums\Platform;
use App\Enums\ProductSort;
use App\Enums\ProductType;
use App\Enums\Region;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept a single value (`?platform=steam`) as well as a list (`?platform[]=steam&platform[]=gog`).
     */
    protected function prepareForValidation(): void
    {
        foreach (['platform', 'region', 'type'] as $key) {
            if (is_string($this->query($key))) {
                $this->merge([$key => [$this->query($key)]]);
            }
        }
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
             * Case-insensitive match against part of the product title.
             *
             * @example elden ring
             */
            'search' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'array'],
            /** Only return products for any of these platforms. A single value is also accepted. Repeat the parameter to match several (`platform[]=a&platform[]=b`). */
            'platform.*' => [Rule::enum(Platform::class)],
            'region' => ['nullable', 'array'],
            /** Only return products for any of these regions. A single value is also accepted. Repeat the parameter to match several (`region[]=a&region[]=b`). */
            'region.*' => [Rule::enum(Region::class)],
            'type' => ['nullable', 'array'],
            /** Only return products of any of these types. A single value is also accepted. Repeat the parameter to match several (`type[]=a&type[]=b`). */
            'type.*' => [Rule::enum(ProductType::class)],
            /**
             * Minimum price in cents (inclusive).
             *
             * @example 999
             */
            'min_price' => ['nullable', 'integer', 'min:0'],
            /**
             * Maximum price in cents (inclusive). Must not be lower than `min_price`.
             *
             * @example 4999
             */
            'max_price' => ['nullable', 'integer', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            /**
             * Sort order. Prefix with `-` for descending, e.g. `-price` is most expensive first and `-created_at` newest first.
             *
             * @default title
             */
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            /**
             * Number of products per page.
             *
             * @default 20
             */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            /**
             * Page number to return.
             *
             * @default 1
             */
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
