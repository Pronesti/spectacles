<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ], ['password.regex' => __('The password must contain no null bytes and must not exceed 72 bytes.')])->safe(['name', 'email', 'password']);
        $user = User::create($data);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new CurrentUserResource($user))->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }
}
