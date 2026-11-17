<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** @example Jane Doe */
            'name' => $this->name,
            /**
             * @format email
             */
            'email' => $this->email,
            /**
             * When the email address was verified, or null while unverified.
             *
             * @format date-time
             */
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
}
