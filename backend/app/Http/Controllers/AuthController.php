<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ], ['password.regex' => __('The password must contain no null bytes and must not exceed 72 bytes.')]);
        $user = User::create($data);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new CurrentUserResource($user))->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function login(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ]);
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();

        return (new CurrentUserResource(Auth::guard('web')->user()))->response()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
    }
}
