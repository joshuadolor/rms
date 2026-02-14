<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    /**
     * Verify email via signed URL (id, hash, expires, signature).
     * Link in verification email points here.
     */
    public function verify(Request $request): JsonResponse
    {
        $id = $request->route('id');
        $hash = $request->route('hash');

        if (! $id || ! $hash) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        $user = User::query()->find((int) $id);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('Email already verified.'),
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
            ]);
        }

        // Signature is validated by the 'signed' route middleware before we get here.

        $user->forceFill(['email_verified_at' => $user->freshTimestamp()])->save();

        event(new Verified($user));

        return response()->json([
            'message' => __('Email verified successfully. You can now log in.'),
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
        ]);
    }

    /**
     * Resend verification email.
     * Authenticated: use current user. Guest: require email in body, generic message to avoid enumeration.
     */
    public function resend(Request $request): JsonResponse
    {
        $genericMessage = __('If that email exists and is unverified, we have sent a new verification link.');

        if ($request->user()) {
            $user = $request->user();
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => __('Email already verified.'),
                ]);
            }
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => __('Verification link sent. Please check your email.'),
            ]);
        }

        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()->where('email', $request->string('email'))->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => $genericMessage]);
    }
}
