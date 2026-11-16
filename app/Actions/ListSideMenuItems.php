<?php

namespace App\Actions;

use App\Models\SideMenuItem;
use Illuminate\Database\Eloquent\Collection;

class ListSideMenuItems
{
    /**
     * Active side menu items in display order. Not paginated: the menu is a short list rendered in full.
     *
     * @return Collection<int, SideMenuItem>
     */
    public function handle(): Collection
    {
        return SideMenuItem::query()->active()->ordered()->get();
    }
}
