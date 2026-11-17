<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestMagicLinkRequest extends FormRequest
{
    /**
     * A path on the storefront: starts with one `/` (not `//` or `/\`, which browsers treat as another host)
     * and contains no whitespace or control characters.
     */
    public const RELATIVE_PATH_PATTERN = '#^/(?![/\\\\])[^\s\x00-\x1F\x7F]*$#D';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Compare email addresses case-insensitively.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
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
             * Where to send the sign-in link. An unknown address gets an account when the link is first used.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255'],
            /**
             * Storefront path to open after signing in, returned by `POST /auth/magic-link/verify`. Must be a path
             * on the storefront (starting with a single `/`); other URLs are rejected. Defaults to `/`.
             *
             * @example /cart
             */
            'redirect' => ['nullable', 'string', 'max:2048', $this->storefrontPathRule()],
        ];
    }

    /**
     * Accept only paths on the storefront, so a sign-in link cannot be used to send users to another site.
     */
    private function storefrontPathRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match(self::RELATIVE_PATH_PATTERN, $value) !== 1) {
                $fail(__('The :attribute must be a path on the storefront, starting with a single /.'));
            }
        };
    }
}
