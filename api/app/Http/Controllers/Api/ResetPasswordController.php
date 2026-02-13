<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\ResetPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request, ResetPassword $resetPassword): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $message = $resetPassword->handle($request->only(
            'token',
            'email',
            'password',
            'password_confirmation'
        ));

        return response()->json(['message' => $message]);
    }
}
