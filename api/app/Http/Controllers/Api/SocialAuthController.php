<?php

namespace App\Http\Controllers\Api;

use App\Application\Auth\SocialLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialLogin $socialLogin
    ) {}

    /**
     * Frontend: complete OAuth with Google, then send access_token or id_token here.
     */
    public function google(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => ['required_without:id_token', 'string'],
            'id_token' => ['required_without:access_token', 'string'],
        ]);

        $token = $request->input('id_token') ?? $request->input('access_token');

        try {
            $socialUser = Socialite::driver('google')->userFromToken($token);
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid or expired Google token.'], 401);
        }

        return $this->socialLoginResponse(
            $this->socialLogin->handle([
                'provider' => 'google',
                'provider_id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'name' => $socialUser->getName(),
            ])
        );
    }

    /**
     * Frontend: complete OAuth with Facebook, then send access_token here.
     */
    public function facebook(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        try {
            $socialUser = Socialite::driver('facebook')->userFromToken($request->input('access_token'));
        } catch (\Throwable) {
            return response()->json(['message' => 'Invalid or expired Facebook token.'], 401);
        }

        return $this->socialLoginResponse(
            $this->socialLogin->handle([
                'provider' => 'facebook',
                'provider_id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'name' => $socialUser->getName() ?: $socialUser->getNickname(),
            ])
        );
    }

    /**
     * Frontend: complete OAuth with Instagram (Meta), then send access_token here.
     */
    public function instagram(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $token = $request->input('access_token');
        $response = Http::get('https://graph.instagram.com/me', [
            'fields' => 'id,username',
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return response()->json(['message' => 'Invalid or expired Instagram token.'], 401);
        }

        $data = $response->json();
        $providerId = (string) $data['id'];
        $username = $data['username'] ?? 'instagram_' . $providerId;

        return $this->socialLoginResponse(
            $this->socialLogin->handle([
                'provider' => 'instagram',
                'provider_id' => $providerId,
                'email' => 'instagram_' . $providerId . '@placeholder.rms.local',
                'name' => $username,
            ])
        );
    }

    /**
     * @param array{user: \App\Models\User, token: string} $result
     */
    private function socialLoginResponse(array $result): JsonResponse
    {
        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => [
                'uuid' => $result['user']->uuid,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'email_verified_at' => $result['user']->email_verified_at,
                'pending_email' => $result['user']->pending_email ?? null,
            ],
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ]);
    }
}
