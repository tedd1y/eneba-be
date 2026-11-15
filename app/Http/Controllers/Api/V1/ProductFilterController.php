<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\GetProductFilterOptions;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductFilterOptionsResource;

class ProductFilterController extends Controller
{
    /**
     * List product filter options with counts.
     *
     * Returns every platform, region and product type with the number of active products
     * matching each, plus the min/max price of active products in cents.
     */
    public function index(GetProductFilterOptions $getFilterOptions): ProductFilterOptionsResource
    {
        return new ProductFilterOptionsResource($getFilterOptions->handle());
    }
}
