<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\ForgotPassword;
use App\Http\Controllers\Controller;
use App\Support\MailLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request, ForgotPassword $forgotPassword): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'locale' => MailLocale::validationRule(),
        ]);

        $locale = MailLocale::resolve($request);
        App::setLocale($locale);

        $result = $forgotPassword->handle($request->only('email'));

        if ($result['success']) {
            return response()->json(['message' => $result['message']]);
        }

        return response()->json(['message' => $result['message']], 503);
    }
}
