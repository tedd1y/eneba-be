<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of active products.
     */
    public function index(ListProductsRequest $request, ListProducts $listProducts): AnonymousResourceCollection
    {
        return ProductResource::collection($listProducts->handle($request->validated()));
    }

    /**
     * Display the specified product, including its description, genres and operating systems.
     */
    public function show(Product $product): ProductDetailResource
    {
        abort_unless($product->is_active, 404);

        return new ProductDetailResource($product->load(Product::detailRelations()));
    }
}
