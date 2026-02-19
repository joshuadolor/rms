<?php

namespace App\Http\Controllers\Api;

use App\Application\Restaurant\CreateRestaurant;
use App\Application\Restaurant\DeleteRestaurant;
use App\Application\Restaurant\GetRestaurant;
use App\Application\Restaurant\ListRestaurants;
use App\Application\Restaurant\UpdateRestaurant;
use App\Application\Restaurant\UploadRestaurantBanner;
use App\Application\Restaurant\UploadRestaurantLogo;
use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRestaurantRequest;
use App\Http\Requests\Api\UpdateRestaurantRequest;
use App\Http\Requests\Api\UploadRestaurantMediaRequest;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly ListRestaurants $listRestaurants,
        private readonly GetRestaurant $getRestaurant,
        private readonly CreateRestaurant $createRestaurant,
        private readonly UpdateRestaurant $updateRestaurant,
        private readonly DeleteRestaurant $deleteRestaurant,
        private readonly UploadRestaurantLogo $uploadLogo,
        private readonly UploadRestaurantBanner $uploadBanner,
        private readonly RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * List restaurants for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $paginator = $this->listRestaurants->handle($request->user(), $perPage);

        $items = collect($paginator->items())->map(fn (Restaurant $r) => $this->restaurantPayload($r));

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show a single restaurant (owner only).
     *
     * @param string $restaurant UUID from route
     */
    public function show(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);

        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        return response()->json(['data' => $this->restaurantPayload($restaurantModel)]);
    }

    /**
     * Create a restaurant.
     */
    public function store(StoreRestaurantRequest $request): JsonResponse|Response
    {
        try {
            $restaurant = $this->createRestaurant->handle($request->user(), $request->validated());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Restaurant created.'),
            'data' => $this->restaurantPayload($restaurant),
        ], 201);
    }

    /**
     * Update a restaurant.
     *
     * @param string $restaurant UUID from route
     */
    public function update(UpdateRestaurantRequest $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->restaurantRepository->findByUuid($restaurant);

        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        try {
            $restaurantModel = $this->updateRestaurant->handle($request->user(), $restaurantModel, $request->validated());
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => __('Restaurant updated.'),
            'data' => $this->restaurantPayload($restaurantModel),
        ]);
    }

    /**
     * Delete a restaurant.
     *
     * @param string $restaurant UUID from route
     */
    public function destroy(Request $request, string $restaurant): JsonResponse|Response
    {
        $restaurantModel = $this->restaurantRepository->findByUuid($restaurant);

        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        try {
            $this->deleteRestaurant->handle($request->user(), $restaurantModel);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->noContent();
    }

    /**
     * Upload logo (multipart: file).
     */
    public function uploadLogo(UploadRestaurantMediaRequest $request, string $uuid): JsonResponse|Response
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);

        if ($restaurant === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        try {
            $restaurant = $this->uploadLogo->handle($request->user(), $restaurant, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Logo updated.'),
            'data' => $this->restaurantPayload($restaurant),
        ]);
    }

    /**
     * Upload banner (multipart: file).
     */
    public function uploadBanner(UploadRestaurantMediaRequest $request, string $uuid): JsonResponse|Response
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);

        if ($restaurant === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        try {
            $restaurant = $this->uploadBanner->handle($request->user(), $restaurant, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => __('Banner updated.'),
            'data' => $this->restaurantPayload($restaurant),
        ]);
    }

    /**
     * Serve logo file (public; for <img> src).
     *
     * @return StreamedResponse|JsonResponse
     */
    public function serveLogo(string $uuid): StreamedResponse|JsonResponse
    {
        return $this->serveMedia($uuid, 'logo_path');
    }

    /**
     * Serve banner file (public; for <img> src).
     *
     * @return StreamedResponse|JsonResponse
     */
    public function serveBanner(string $uuid): StreamedResponse|JsonResponse
    {
        return $this->serveMedia($uuid, 'banner_path');
    }

    /**
     * @return StreamedResponse|JsonResponse
     */
    private function serveMedia(string $uuid, string $pathKey): StreamedResponse|JsonResponse
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);

        if ($restaurant === null) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        $path = $pathKey === 'logo_path' ? $restaurant->logo_path : $restaurant->banner_path;

        if (! $path || ! Storage::disk(config('filesystems.default'))->exists($path)) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        /** @var \Illuminate\Contracts\Filesystem\Filesystem $disk */
        $disk = Storage::disk(config('filesystems.default'));

        return $disk->response($path, null, [
            'Content-Type' => $disk->mimeType($path),
        ]);
    }

    /**
     * API payload for a restaurant. Never include internal id.
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
