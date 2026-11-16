<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListHomeGalleries;
use App\Http\Controllers\Controller;
use App\Http\Resources\HomeGalleryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeGalleryController extends Controller
{
    /**
     * List the home page galleries.
     *
     * Returns every active gallery in display order, without products. Load a gallery's products from
     * `GET /v1/products` using the filters in its `href` and `per_page` set to its `limit`. Not paginated.
     */
    public function index(ListHomeGalleries $listHomeGalleries): AnonymousResourceCollection
    {
        return HomeGalleryResource::collection($listHomeGalleries->handle());
    }
}
