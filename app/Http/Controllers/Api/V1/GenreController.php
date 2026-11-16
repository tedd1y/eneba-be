<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListGenres;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenreResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GenreController extends Controller
{
    /**
     * List genres.
     *
     * Returns every genre in display order. `value` is the slug the `genre[]` filter of `GET /v1/products` accepts.
     * Not paginated. For per-genre product counts, use `GET /v1/products/filters`.
     */
    public function index(ListGenres $listGenres): AnonymousResourceCollection
    {
        return GenreResource::collection($listGenres->handle());
    }
}
