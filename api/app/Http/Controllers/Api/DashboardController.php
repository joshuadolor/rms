<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner dashboard stats: restaurants, menu items (catalog), feedbacks (total, approved, rejected).
 */
class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/stats — counts for the authenticated owner.
     * Bearer + verified. Returns restaurants_count, menu_items_count, feedbacks_total, feedbacks_approved, feedbacks_rejected.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantIds = Restaurant::query()->where('user_id', $user->id)->pluck('id');

        $restaurantsCount = $restaurantIds->count();

        $menuItemsCount = MenuItem::query()
            ->where('user_id', $user->id)
            ->whereNull('restaurant_id')
            ->count();

        $feedbacksQuery = Feedback::query()->whereIn('restaurant_id', $restaurantIds);
        $feedbacksTotal = (clone $feedbacksQuery)->count();
        $feedbacksApproved = (clone $feedbacksQuery)->where('is_approved', true)->count();
        $feedbacksRejected = (clone $feedbacksQuery)->where('is_approved', false)->count();

        return response()->json([
            'data' => [
                'restaurants_count' => $restaurantsCount,
                'menu_items_count' => $menuItemsCount,
                'feedbacks_total' => $feedbacksTotal,
                'feedbacks_approved' => $feedbacksApproved,
                'feedbacks_rejected' => $feedbacksRejected,
            ],
        ]);
    }
}
