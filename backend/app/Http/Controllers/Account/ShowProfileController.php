<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return (new CurrentUserResource($request->user()))->response()->header('Cache-Control', 'no-store');
    }
}
