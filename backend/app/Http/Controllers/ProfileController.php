<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return (new CurrentUserResource($request->user()))->response()->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:254', Rule::unique(User::class)->ignore($user)],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35', Rule::in([config('app.locale'), config('app.fallback_locale')])],
        ]);
        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return (new CurrentUserResource($user))->response()->header('Cache-Control', 'no-store');
    }
}
