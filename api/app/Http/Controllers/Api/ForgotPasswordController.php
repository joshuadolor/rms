<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\ForgotPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request, ForgotPassword $forgotPassword): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $message = $forgotPassword->handle($request->only('email'));

        return response()->json(['message' => $message]);
    }
}
