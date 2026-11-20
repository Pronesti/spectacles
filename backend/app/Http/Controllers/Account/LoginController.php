<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
        $credentials = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ])->safe(['email', 'password']);
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();

        return (new CurrentUserResource(Auth::guard('web')->user()))->response()->header('Cache-Control', 'no-store');
    }
}
