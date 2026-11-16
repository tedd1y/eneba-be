<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListSideMenuItems;
use App\Http\Controllers\Controller;
use App\Http\Resources\SideMenuItemResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SideMenuController extends Controller
{
    /**
     * List the side menu.
     *
     * Returns every active side menu item in display order. The menu is short, so the list is not paginated.
     */
    public function index(ListSideMenuItems $listSideMenuItems): AnonymousResourceCollection
    {
        return SideMenuItemResource::collection($listSideMenuItems->handle());
    }
}
