<?php

namespace App\Http\Resources;

use App\Models\SideMenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SideMenuItem
 */
class SideMenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Text shown in the menu.
             *
             * @example Cheap Games
             */
            'label' => $this->label,
            /**
             * Storefront link the item points to, relative to the site root.
             *
             * @example /store/all?types[]=game&sort=price
             */
            'href' => $this->href,
        ];
    }
}
