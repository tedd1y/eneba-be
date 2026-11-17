<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    /**
     * Display the signed-in user.
     *
     * Uses the Sanctum session cookie from `POST /api/auth/magic-link/verify`. Returns 401 when not signed in.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
