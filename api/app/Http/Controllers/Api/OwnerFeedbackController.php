<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOwnerFeedbackRequest;
use App\Models\OwnerFeedback;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;

/**
 * Owner (authenticated, verified) endpoints for submitting and listing own feature requests / feedback.
 */
class OwnerFeedbackController extends Controller
{
    /**
     * List the current user's own submissions (newest first).
     */
    public function index(): JsonResponse
    {
        $user = request()->user();
        $feedbacks = OwnerFeedback::query()
            ->where('user_id', $user->id)
            ->with('restaurant:id,uuid,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $feedbacks->map(fn (OwnerFeedback $f) => $this->ownerPayload($f))->all(),
        ]);
    }

    /**
     * Create an owner feedback / feature request.
     * 403 if restaurant uuid is sent but not owned by the user.
     */
    public function store(StoreOwnerFeedbackRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $restaurantId = null;
        if (! empty($validated['restaurant'])) {
            $restaurant = Restaurant::query()->where('uuid', $validated['restaurant'])->first();
            if ($restaurant === null || ! $restaurant->isOwnedBy($user)) {
                return response()->json(['message' => __('You do not have access to that restaurant.')], 403);
            }
            $restaurantId = $restaurant->id;
        }

        $feedback = OwnerFeedback::query()->create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurantId,
            'title' => $validated['title'] ?? null,
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        $feedback->load('restaurant:id,uuid,name');

        return response()->json([
            'message' => __('Feedback submitted.'),
            'data' => $this->ownerPayloadWithSubmitterAndRestaurant($feedback, $user),
        ], 201);
    }

    /**
     * Payload for list item (owner view). No internal id.
     *
     * @return array<string, mixed>
     */
    private function ownerPayload(OwnerFeedback $feedback): array
    {
        $payload = [
            'uuid' => $feedback->uuid,
            'title' => $feedback->title,
            'message' => $feedback->message,
            'status' => $feedback->status,
            'created_at' => $feedback->created_at?->toIso8601String(),
        ];
        if ($feedback->relationLoaded('restaurant') && $feedback->restaurant) {
            $payload['restaurant'] = [
                'uuid' => $feedback->restaurant->uuid,
                'name' => $feedback->restaurant->name,
            ];
        } else {
            $payload['restaurant'] = null;
        }

        return $payload;
    }

    /**
     * Payload for create response: same as owner list + optional submitter and restaurant summary. No internal id.
     *
     * @return array<string, mixed>
     */
    private function ownerPayloadWithSubmitterAndRestaurant(OwnerFeedback $feedback, \App\Models\User $submitter): array
    {
        $payload = [
            'uuid' => $feedback->uuid,
            'title' => $feedback->title,
            'message' => $feedback->message,
            'status' => $feedback->status,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'submitter' => [
                'uuid' => $submitter->uuid,
                'name' => $submitter->name,
            ],
        ];
        if ($feedback->restaurant) {
            $payload['restaurant'] = [
                'uuid' => $feedback->restaurant->uuid,
                'name' => $feedback->restaurant->name,
            ];
        } else {
            $payload['restaurant'] = null;
        }

        return $payload;
    }
}
