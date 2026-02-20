<?php

namespace App\Http\Controllers\Api;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no auth) feedback submission for a restaurant by slug.
 */
class PublicFeedbackController extends Controller
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Submit feedback for a restaurant. Rate-limited to prevent abuse.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $restaurant = $this->restaurantRepository->findBySlug($slug);
        if ($restaurant === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['required', 'string', 'max:65535'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $feedback = new Feedback;
        $feedback->restaurant_id = $restaurant->id;
        $feedback->rating = (int) $validated['rating'];
        $feedback->text = $validated['text'];
        $feedback->name = $validated['name'];
        $feedback->is_approved = false;
        $feedback->save();

        return response()->json([
            'message' => __('Thank you for your feedback.'),
            'data' => [
                'uuid' => $feedback->uuid,
                'rating' => $feedback->rating,
                'text' => $feedback->text,
                'name' => $feedback->name,
                'is_approved' => $feedback->is_approved,
                'created_at' => $feedback->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
