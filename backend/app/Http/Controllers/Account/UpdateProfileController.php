<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
        $user = $request->user() ?? throw new AuthenticationException;
        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:254', Rule::unique(User::class)->ignore($user)],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35', Rule::in([config('app.locale'), config('app.fallback_locale')])],
        ])->safe(['name', 'email', 'locale']);
        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return (new CurrentUserResource($user))->response()->header('Cache-Control', 'no-store');
    }
}
