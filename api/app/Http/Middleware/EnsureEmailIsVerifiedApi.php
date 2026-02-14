<?php

namespace App\Http\Middleware;

use App\Exceptions\UnverifiedEmailException;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For API routes: require verified email. Returns 403 + JSON directly (never HTML/blank)
 * when unverified. The resource and route exist; access is refused due to account state.
 */
class EnsureEmailIsVerifiedApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => UnverifiedEmailException::MESSAGE], 403);
        }

        return $next($request);
    }
}
