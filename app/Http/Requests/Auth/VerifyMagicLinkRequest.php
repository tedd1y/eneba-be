<?php

namespace App\Http\Requests\Auth;

use App\Models\MagicLoginToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyMagicLinkRequest extends FormRequest
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
             * The `token` query parameter from the emailed link.
             */
            'token' => ['required', 'string', 'size:'.MagicLoginToken::TOKEN_LENGTH],
        ];
    }
}
