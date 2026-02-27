<?php

namespace App\Http\Controllers\Api;

use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRestaurantContactRequest;
use App\Http\Requests\Api\UpdateRestaurantContactRequest;
use App\Models\RestaurantContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Owner CRUD for restaurant Contact & links (contact numbers and social/links).
 * Restaurant and contact identified by uuid. No internal id in any response.
 */
class RestaurantContactController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant
    ) {}

    /**
     * List all contacts for the restaurant (owner only).
     */
    public function index(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $contacts = $restaurantModel->contacts;

        return response()->json([
            'data' => $contacts->map(fn (RestaurantContact $c) => $this->contactPayload($c)),
        ]);
    }

    /**
     * Show one contact (owner only).
     */
    public function show(Request $request, string $restaurant, string $contact): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $contactModel = RestaurantContact::query()
            ->where('uuid', $contact)
            ->where('restaurant_id', $restaurantModel->id)
            ->first();

        if ($contactModel === null) {
            return response()->json(['message' => __('Contact not found.')], 404);
        }

        return response()->json([
            'data' => $this->contactPayload($contactModel),
        ]);
    }

    /**
     * Create a contact (owner only).
     */
    public function store(StoreRestaurantContactRequest $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $validated = $request->validated();
        $type = $validated['type'];
        $value = $validated['value'] ?? null;
        $contact = $restaurantModel->contacts()->create([
            'type' => $type,
            'value' => $value,
            'number' => $value !== null && in_array($type, RestaurantContact::TYPES_PHONE, true) ? $value : null,
            'label' => $validated['label'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => __('Contact created.'),
            'data' => $this->contactPayload($contact),
        ], 201);
    }

    /**
     * Update a contact (owner only).
     */
    public function update(UpdateRestaurantContactRequest $request, string $restaurant, string $contact): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $contactModel = RestaurantContact::query()
            ->where('uuid', $contact)
            ->where('restaurant_id', $restaurantModel->id)
            ->first();

        if ($contactModel === null) {
            return response()->json(['message' => __('Contact not found.')], 404);
        }

        $validated = $request->validated();
        $contactModel->fill($validated);
        if (array_key_exists('value', $validated) || array_key_exists('type', $validated)) {
            $type = $contactModel->type;
            $value = $contactModel->value ?? $contactModel->number;
            $contactModel->number = $value !== null && in_array($type, RestaurantContact::TYPES_PHONE, true) ? $value : null;
        }
        $contactModel->save();

        return response()->json([
            'message' => __('Contact updated.'),
            'data' => $this->contactPayload($contactModel),
        ]);
    }

    /**
     * Delete a contact (owner only).
     */
    public function destroy(Request $request, string $restaurant, string $contact): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $contactModel = RestaurantContact::query()
            ->where('uuid', $contact)
            ->where('restaurant_id', $restaurantModel->id)
            ->first();

        if ($contactModel === null) {
            return response()->json(['message' => __('Contact not found.')], 404);
        }

        $contactModel->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(RestaurantContact $contact): array
    {
        $value = $contact->getEffectiveValue();
        $isPhone = in_array($contact->type, RestaurantContact::TYPES_PHONE, true);

        return [
            'uuid' => $contact->uuid,
            'type' => $contact->type,
            'value' => $value,
            'number' => $isPhone ? $value : null,
            'label' => $contact->label,
            'is_active' => (bool) $contact->is_active,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}
