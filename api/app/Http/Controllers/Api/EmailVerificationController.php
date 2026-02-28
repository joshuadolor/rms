<?php

namespace App\Http\Controllers\Api;

use App\Application\EmailVerification\VerifyEmail;
use App\Application\EmailVerification\VerifyNewEmail;
use App\Domain\Auth\Contracts\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Support\MailLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly VerifyEmail $verifyEmail,
        private readonly VerifyNewEmail $verifyNewEmail,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Verify email via signed URL (uuid, hash, expires, signature).
     * Link in verification email points here.
     */
    public function verify(Request $request): JsonResponse
    {
        $uuid = $request->route('uuid');
        $hash = $request->route('hash');

        if (! $uuid || ! $hash) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }

        $result = $this->verifyEmail->handle((string) $uuid, (string) $hash);

        return response()->json([
            'message' => $result['already_verified']
                ? __('Email already verified.')
                : __('Email verified successfully. You can now log in.'),
            'user' => [
                'uuid' => $result['user']->uuid,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'email_verified_at' => $result['user']->email_verified_at,
            ],
        ]);
    }

    /**
     * Resend verification email.
     * Authenticated: use current user. Guest: require email in body, generic message to avoid enumeration.
     * Optional locale (body or Accept-Language) sets the language for the verification email.
     */
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => MailLocale::validationRule(),
        ]);

        $locale = MailLocale::resolve($request);
        App::setLocale($locale);

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

        $user = $this->userRepository->findByEmail($request->string('email')->toString());
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => $genericMessage]);
    }

    /**
     * Verify new email (after profile email change). Signed URL with uuid + hash of pending_email.
     */
    public function verifyNewEmail(Request $request): JsonResponse
    {
        $uuid = $request->route('uuid');
        $hash = $request->route('hash');

        if (! $uuid || ! $hash) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid verification link.')],
            ]);
        }
        $user = $this->verifyNewEmail->handle((string) $uuid, (string) $hash);

        return response()->json([
            'message' => __('Your email has been updated and verified.'),
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }
}
