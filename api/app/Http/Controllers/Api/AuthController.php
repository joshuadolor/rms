<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\LoginUser;
use App\Application\Auth\LogoutUser;
use App\Application\Auth\RegisterUser;
use App\Exceptions\DeactivatedUserException;
use App\Exceptions\InvalidRefreshTokenException;
use App\Exceptions\UnverifiedEmailException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Services\Auth\RefreshTokenCookie;
use App\Services\Auth\RefreshTokenService;
use App\Support\MailLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
        private readonly LogoutUser $logoutUser,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RefreshTokenCookie $refreshTokenCookie
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $locale = MailLocale::resolve($request);
        App::setLocale($locale);

        $result = $this->registerUser->handle($request->validated());

        return response()->json([
            'message' => __('Registered. Please verify your email using the link we sent you.'),
            'user' => $this->userPayload($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginUser->handle($request->validated());
        $refreshToken = $this->refreshTokenService->issueForUser($result['user']);

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $this->userPayload($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ])->cookie($this->refreshTokenCookie->make($refreshToken));
    }

    /**
     * Refresh access token using the HttpOnly refresh cookie (no Bearer required).
     */
    public function refresh(Request $request): JsonResponse
    {
        $plain = (string) ($request->cookie($this->refreshTokenCookie->name()) ?? '');

        try {
            $result = $this->refreshTokenService->rotateAndIssueAccessToken($plain);
        } catch (InvalidRefreshTokenException $e) {
            return response()
                ->json(['message' => $e->getMessage()], 401)
                ->cookie($this->refreshTokenCookie->forget());
        } catch (UnverifiedEmailException|DeactivatedUserException $e) {
            return response()
                ->json(['message' => $e->getMessage()], 403)
                ->cookie($this->refreshTokenCookie->forget());
        }

        return response()->json([
            'message' => 'Token refreshed successfully.',
            'user' => $this->userPayload($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ])->cookie($this->refreshTokenCookie->make($result['refresh_token']));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUser->handle($request->user());
        $this->refreshTokenService->revokeByPlainToken(
            $request->cookie($this->refreshTokenCookie->name())
        );

        return response()
            ->json(['message' => 'Logged out successfully.'])
            ->cookie($this->refreshTokenCookie->forget());
    }

    /**
     * Revoke all of the authenticated user's tokens (logout everywhere).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $this->refreshTokenService->revokeAllForUser($user);

        return response()
            ->json(['message' => 'Logged out from all devices successfully.'])
            ->cookie($this->refreshTokenCookie->forget());
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Public user payload for API responses. Never include internal id.
     *
     * @param \App\Models\User $user
     * @return array{uuid: string, name: string, email: string, email_verified_at: \Illuminate\Support\Carbon|null, pending_email: string|null, is_paid: bool, is_superadmin: bool, is_active: bool}
     */
    private function userPayload($user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'pending_email' => $user->pending_email ?? null,
            'is_paid' => (bool) ($user->is_paid ?? false),
            'is_superadmin' => (bool) ($user->is_superadmin ?? false),
            'is_active' => (bool) ($user->is_active ?? true),
        ];
    }
}
