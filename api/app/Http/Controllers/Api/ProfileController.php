<?php

namespace App\Http\Controllers\Api;

use App\Application\Profile\ChangePassword;
use App\Application\Profile\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateProfile $updateProfile,
        private readonly ChangePassword $changePassword
    ) {}

    /**
     * Update profile: name (immediate) and/or email (pending until new email is verified).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $result = $this->updateProfile->handle($request->user(), $request->validated());

        return response()->json([
            'message' => $result['message'],
            'user' => $this->userPayload($result['user']),
        ]);
    }

    /**
     * Change password. Requires current password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->changePassword->handle($request->user(), $request->validated('password'));

        return response()->json([
            'message' => __('Password updated successfully.'),
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
