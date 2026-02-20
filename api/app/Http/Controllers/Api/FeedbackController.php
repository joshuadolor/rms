<?php

namespace App\Http\Controllers\Api;

use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Owner management of restaurant feedbacks: list, approve/reject (PATCH), delete.
 */
class FeedbackController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant
    ) {}

    /**
     * List all feedbacks for the restaurant (owner only).
     */
    public function index(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $feedbacks = $restaurantModel->feedbacks;

        return response()->json([
            'data' => $feedbacks->map(fn (Feedback $f) => $this->feedbackPayload($f)),
        ]);
    }

    /**
     * Update feedback (approve or reject). Owner only.
     */
    public function update(Request $request, string $restaurant, string $feedback): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $feedbackModel = Feedback::query()
            ->where('uuid', $feedback)
            ->where('restaurant_id', $restaurantModel->id)
            ->first();

        if ($feedbackModel === null) {
            return response()->json(['message' => __('Feedback not found.')], 404);
        }

        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $feedbackModel->is_approved = $validated['is_approved'];
        $feedbackModel->save();

        return response()->json([
            'message' => __('Feedback updated.'),
            'data' => $this->feedbackPayload($feedbackModel),
        ]);
    }

    /**
     * Delete feedback (owner only).
     */
    public function destroy(Request $request, string $restaurant, string $feedback): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $feedbackModel = Feedback::query()
            ->where('uuid', $feedback)
            ->where('restaurant_id', $restaurantModel->id)
            ->first();

        if ($feedbackModel === null) {
            return response()->json(['message' => __('Feedback not found.')], 404);
        }

        $feedbackModel->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackPayload(Feedback $feedback): array
    {
        return [
            'uuid' => $feedback->uuid,
            'rating' => $feedback->rating,
            'text' => $feedback->text,
            'name' => $feedback->name,
            'is_approved' => $feedback->is_approved,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ];
    }
}
