<?php

namespace App\Http\Requests;

use App\Enums\ProductSort;
use App\Models\Country;
use App\Models\Genre;
use App\Models\OperatingSystem;
use App\Models\Platform;
use App\Models\ProductType;
use App\Models\Region;
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
     * Maximum number of IDs accepted in one request, matching the largest page size.
     */
    public const MAX_IDS = 100;

    /**
     * Accept a single value (`?platform=steam`) as well as a list (`?platform[]=steam&platform[]=gog`),
     * and country codes in any case (`?country=LT`).
     */
    protected function prepareForValidation(): void
    {
        foreach (['ids', 'platform', 'region', 'type', 'genre', 'os'] as $key) {
            if (is_string($this->query($key))) {
                $this->merge([$key => [$this->query($key)]]);
            }
        }

        if (is_string($this->query('country'))) {
            $this->merge(['country' => strtolower($this->query('country'))]);
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
            'ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            /**
             * Only return products with these IDs. Inactive and unknown IDs are left out rather than rejected. Up to 100 IDs; set `per_page` high enough to get them all on one page.
             *
             * @example [12, 31]
             */
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'platform' => ['nullable', 'array'],
            /** Only return products for any of these platform slugs (see `/v1/products/filters`). A single value is also accepted. */
            'platform.*' => ['string', Rule::exists(Platform::class, 'slug')],
            'region' => ['nullable', 'array'],
            /** Only return products for any of these region slugs. A single value is also accepted. */
            'region.*' => ['string', Rule::exists(Region::class, 'slug')],
            /**
             * Only return products that can be activated in this country (the product's region includes it). ISO 3166-1 alpha-2 code, any case.
             *
             * @example lt
             */
            'country' => ['nullable', 'string', Rule::exists(Country::class, 'slug')],
            'type' => ['nullable', 'array'],
            /** Only return products of any of these product type slugs. A single value is also accepted. */
            'type.*' => ['string', Rule::exists(ProductType::class, 'slug')],
            'genre' => ['nullable', 'array'],
            /** Only return products in any of these genre slugs. A single value is also accepted. */
            'genre.*' => ['string', Rule::exists(Genre::class, 'slug')],
            'os' => ['nullable', 'array'],
            /** Only return products that run on any of these operating system slugs. A single value is also accepted. */
            'os.*' => ['string', Rule::exists(OperatingSystem::class, 'slug')],
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
