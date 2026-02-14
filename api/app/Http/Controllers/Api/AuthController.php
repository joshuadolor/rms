<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\LoginUser;
use App\Application\Auth\LogoutUser;
use App\Application\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
        private readonly LogoutUser $logoutUser
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerUser->handle($request->validated());

        return response()->json([
            'message' => __('Registered. Please verify your email using the link we sent you.'),
            'user' => $this->userPayload($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginUser->handle($request->validated());

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $this->userPayload($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutUser->handle($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Revoke all of the authenticated user's tokens (logout everywhere).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices successfully.']);
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
     * @return array{uuid: string, name: string, email: string, email_verified_at: \Illuminate\Support\Carbon|null, pending_email: string|null}
     */
    private function userPayload($user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'pending_email' => $user->pending_email ?? null,
        ];
    }
}
