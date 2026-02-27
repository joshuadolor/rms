<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OwnerFeedback;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperadminController extends Controller
{
    /**
     * Dashboard stats: restaurants_count, users_count, paid_users_count.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'restaurants_count' => Restaurant::query()->count(),
                'users_count' => User::query()->count(),
                'paid_users_count' => User::query()->where('is_paid', true)->count(),
            ],
        ]);
    }

    /**
     * List all users (admin list payload: uuid, name, email, email_verified_at, is_paid, is_active, etc.).
     */
    public function users(): JsonResponse
    {
        $users = User::query()->orderBy('created_at')->get();

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->adminUserPayload($u))->all(),
        ]);
    }

    /**
     * Update a user (is_paid, is_active). Superadmin cannot change their own is_active or is_superadmin.
     *
     * @param string $user User UUID from route
     */
    public function updateUser(Request $request, string $user): JsonResponse
    {
        $target = User::query()->where('uuid', $user)->first();

        if ($target === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $current = $request->user();
        $isSelf = $target->id === $current->id;

        $rules = [
            'is_paid' => ['sometimes', 'boolean'],
            'is_active' => $isSelf ? ['prohibited'] : ['sometimes', 'boolean'],
        ];
        $validated = $request->validate($rules);

        if (array_key_exists('is_paid', $validated)) {
            $target->is_paid = $validated['is_paid'];
        }
        if (array_key_exists('is_active', $validated)) {
            $target->is_active = (bool) $validated['is_active'];
        }
        $target->save();

        return response()->json([
            'message' => 'User updated.',
            'data' => $this->adminUserPayload($target),
        ]);
    }

    /**
     * List all restaurants (read-only; same payload shape as owner list).
     */
    public function restaurants(): JsonResponse
    {
        $restaurants = Restaurant::query()->with('languages')->orderBy('name')->get();

        return response()->json([
            'data' => $restaurants->map(fn (Restaurant $r) => $this->restaurantPayload($r))->all(),
        ]);
    }

    /**
     * List all owner feedbacks (feature requests), newest first. Each item includes submitter and restaurant.
     */
    public function ownerFeedbacks(): JsonResponse
    {
        $feedbacks = OwnerFeedback::query()
            ->with(['user', 'restaurant:id,uuid,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $feedbacks->map(fn (OwnerFeedback $f) => $this->ownerFeedbackAdminPayload($f))->all(),
        ]);
    }

    /**
     * Update owner feedback status (e.g. mark as reviewed). Path param is feedback uuid.
     *
     * @param string $feedback OwnerFeedback UUID from route
     */
    public function updateOwnerFeedback(Request $request, string $feedback): JsonResponse
    {
        $model = OwnerFeedback::query()->where('uuid', $feedback)->first();

        if ($model === null) {
            return response()->json(['message' => 'Feedback not found.'], 404);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['pending', 'reviewed'])],
        ]);

        if (array_key_exists('status', $validated)) {
            $model->status = $validated['status'];
            $model->save();
        }

        $model->load(['user', 'restaurant:id,uuid,name']);

        return response()->json([
            'message' => 'Feedback updated.',
            'data' => $this->ownerFeedbackAdminPayload($model),
        ]);
    }

    /**
     * Owner feedback payload for superadmin list/update. Never include internal id.
     *
     * @return array<string, mixed>
     */
    private function ownerFeedbackAdminPayload(OwnerFeedback $feedback): array
    {
        $payload = [
            'uuid' => $feedback->uuid,
            'title' => $feedback->title,
            'message' => $feedback->message,
            'status' => $feedback->status,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'submitter' => null,
            'restaurant' => null,
        ];

        if ($feedback->relationLoaded('user') && $feedback->user) {
            $payload['submitter'] = [
                'uuid' => $feedback->user->uuid,
                'name' => $feedback->user->name,
                'email' => $feedback->user->email,
            ];
        }

        if ($feedback->relationLoaded('restaurant') && $feedback->restaurant) {
            $payload['restaurant'] = [
                'uuid' => $feedback->restaurant->uuid,
                'name' => $feedback->restaurant->name,
            ];
        }

        return $payload;
    }

    /**
     * Admin user payload. Never include internal id.
     *
     * @return array<string, mixed>
     */
    private function adminUserPayload(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'pending_email' => $user->pending_email ?? null,
            'is_paid' => (bool) ($user->is_paid ?? false),
            'is_active' => (bool) ($user->is_active ?? true),
            'is_superadmin' => (bool) ($user->is_superadmin ?? false),
        ];
    }

    /**
     * Restaurant payload (same shape as owner list). Never include internal id.
     *
     * @return array<string, mixed>
     */
    private function restaurantPayload(Restaurant $restaurant): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $scheme = request()->getScheme();
        $restaurantDomain = config('app.restaurant_domain', 'localhost');
        $publicUrl = $scheme . '://' . $restaurant->slug . '.' . $restaurantDomain;

        $restaurant->loadMissing('languages');

        return [
            'uuid' => $restaurant->uuid,
            'name' => $restaurant->name,
            'tagline' => $restaurant->tagline,
            'primary_color' => $restaurant->primary_color,
            'slug' => $restaurant->slug,
            'template' => $restaurant->template ?? 'default',
            'year_established' => $restaurant->year_established !== null ? (int) $restaurant->year_established : null,
            'public_url' => $publicUrl,
            'address' => $restaurant->address,
            'latitude' => $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
            'longitude' => $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
            'phone' => $restaurant->phone,
            'email' => $restaurant->email,
            'website' => $restaurant->website,
            'social_links' => $restaurant->social_links ?? (object) [],
            'default_locale' => $restaurant->default_locale ?? 'en',
            'currency' => $restaurant->currency ?? 'USD',
            'operating_hours' => $restaurant->operating_hours,
            'languages' => $restaurant->languages->pluck('locale')->values()->all(),
            'logo_url' => $restaurant->logo_path
                ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/logo'
                : null,
            'banner_url' => $restaurant->banner_path
                ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/banner'
                : null,
            'created_at' => $restaurant->created_at?->toIso8601String(),
            'updated_at' => $restaurant->updated_at?->toIso8601String(),
        ];
    }
}
