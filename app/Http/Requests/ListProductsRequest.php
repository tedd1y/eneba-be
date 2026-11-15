<?php

namespace App\Http\Requests;

use App\Enums\Platform;
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
            /** Only return products for this platform. */
            'platform' => ['nullable', Rule::enum(Platform::class)],
            /** Only return products for this region. */
            'region' => ['nullable', Rule::enum(Region::class)],
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
