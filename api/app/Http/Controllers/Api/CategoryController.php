<?php

namespace App\Http\Controllers\Api;

use App\Application\Category\CreateCategory;
use App\Application\Category\DeleteCategory;
use App\Application\Category\GetCategory;
use App\Application\Category\ListCategories;
use App\Application\Category\ReorderCategories;
use App\Application\Category\UpdateCategory;
use App\Application\Category\UploadCategoryImage;
use App\Application\Category\DeleteCategoryImage;
use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadMenuItemImageRequest;
use App\Models\Category;
use App\Rules\OperatingHoursRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListCategories $listCategories,
        private readonly GetCategory $getCategory,
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly DeleteCategory $deleteCategory,
        private readonly ReorderCategories $reorderCategories,
        private readonly UploadCategoryImage $uploadCategoryImage,
        private readonly DeleteCategoryImage $deleteCategoryImage
    ) {}

    /**
     * List categories for a menu.
     */
    public function index(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $categories = $this->listCategories->handle($request->user(), $restaurant, $menu);
        if ($categories === null) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json([
            'data' => $categories->map(fn (Category $c) => $this->categoryPayload($c, $restaurant, $menu, $defaultLocale)),
        ]);
    }

    /**
     * Show a single category.
     */
    public function show(Request $request, string $restaurant, string $menu, string $category): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $categoryModel = $this->getCategory->handle($request->user(), $restaurant, $menu, $category);
        if ($categoryModel === null) {
            return response()->json(['message' => __('Category not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json(['data' => $this->categoryPayload($categoryModel, $restaurant, $menu, $defaultLocale)]);
    }

    /**
     * Create a category (with translations for restaurant locales).
     */
    public function store(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $validated = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'availability' => ['nullable', 'array', new OperatingHoursRule()],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:65535'],
        ]);

        $translations = $validated['translations'] ?? [];
        $installedLocales = $restaurantModel->languages()->pluck('locale')->all();
        $invalidLocales = array_diff(array_keys($translations), $installedLocales);
        if ($invalidLocales !== []) {
            return response()->json([
                'message' => __('One or more translation locales are not installed for this restaurant.'),
                'errors' => ['translations' => [__('Uninstalled locale(s): :locales', ['locales' => implode(', ', array_values($invalidLocales))])]],
            ], 422);
        }

        try {
            $category = $this->createCategory->handle($request->user(), $restaurant, $menu, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($category === null) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        $defaultLocale = $restaurantModel->default_locale ?? 'en';
        return response()->json([
            'message' => __('Category created.'),
            'data' => $this->categoryPayload($category, $restaurant, $menu, $defaultLocale),
        ], 201);
    }

    /**
     * Update a category.
     */
    public function update(Request $request, string $restaurant, string $menu, string $category): JsonResponse|Response
    {
        $validated = $request->validate([
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'availability' => ['sometimes', 'nullable', 'array', new OperatingHoursRule()],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:65535'],
        ]);

        try {
            $categoryModel = $this->updateCategory->handle($request->user(), $restaurant, $menu, $category, $validated);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($categoryModel === null) {
            return response()->json(['message' => __('Category not found.')], 404);
        }

        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        $defaultLocale = $restaurantModel?->default_locale ?? 'en';
        return response()->json([
            'message' => __('Category updated.'),
            'data' => $this->categoryPayload($categoryModel, $restaurant, $menu, $defaultLocale),
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy(Request $request, string $restaurant, string $menu, string $category): JsonResponse|Response
    {
        try {
            $ok = $this->deleteCategory->handle($request->user(), $restaurant, $menu, $category);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if (! $ok) {
            return response()->json(['message' => __('Category not found.')], 404);
        }

        return response()->noContent();
    }

    /**
     * Reorder categories. Body: { "order": ["uuid1", "uuid2", ...] }
     */
    public function reorder(Request $request, string $restaurant, string $menu): JsonResponse|Response
    {
        $restaurantModel = $this->getRestaurant->handle($request->user(), $restaurant);
        if ($restaurantModel === null) {
            return response()->json(['message' => __('Restaurant not found.')], 404);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'uuid'],
        ]);

        $ok = $this->reorderCategories->handle($request->user(), $restaurant, $menu, $request->input('order'));
        if (! $ok) {
            return response()->json(['message' => __('Menu not found.')], 404);
        }

        return response()->json(['message' => __('Order updated.')]);
    }

    /**
     * Upload category image (restaurant context). Multipart: file.
     */
    public function uploadImage(UploadMenuItemImageRequest $request, string $restaurant, string $menu, string $category): JsonResponse|Response
    {
        $categoryModel = $this->getCategory->handle($request->user(), $restaurant, $menu, $category);
        if ($categoryModel === null) {
            return response()->json(['message' => __('Category not found.')], 404);
        }
        $categoryModel->load('menu.restaurant');

        try {
            $categoryModel = $this->uploadCategoryImage->handle($request->user(), $categoryModel, $request->file('file'));
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $defaultLocale = $categoryModel->menu->restaurant->default_locale ?? 'en';
        return response()->json([
            'message' => __('Image updated.'),
            'data' => $this->categoryPayload($categoryModel, $restaurant, $menu, $defaultLocale),
        ]);
    }

    /**
     * Serve category image (public; for <img> src).
     */
    public function serveImage(string $restaurant, string $menu, string $category): StreamedResponse|JsonResponse
    {
        $categoryModel = Category::query()
            ->where('uuid', $category)
            ->whereHas('menu', fn ($q) => $q->where('uuid', $menu)->whereHas('restaurant', fn ($r) => $r->where('uuid', $restaurant)))
            ->first();
        if ($categoryModel === null || ! $categoryModel->image_path) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($categoryModel->image_path)) {
            return response()->json(['message' => __('File not found.')], 404);
        }

        return $disk->response($categoryModel->image_path, null, [
            'Content-Type' => $disk->mimeType($categoryModel->image_path),
        ]);
    }

    /**
     * Delete category image.
     */
    public function deleteImage(Request $request, string $restaurant, string $menu, string $category): JsonResponse|Response
    {
        $categoryModel = $this->getCategory->handle($request->user(), $restaurant, $menu, $category);
        if ($categoryModel === null) {
            return response()->json(['message' => __('Category not found.')], 404);
        }
        $categoryModel->load('menu.restaurant');

        try {
            $categoryModel = $this->deleteCategoryImage->handle($request->user(), $categoryModel);
        } catch (\App\Exceptions\ForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $defaultLocale = $categoryModel->menu->restaurant->default_locale ?? 'en';
        return response()->json([
            'message' => __('Image removed.'),
            'data' => $this->categoryPayload($categoryModel, $restaurant, $menu, $defaultLocale),
        ]);
    }

    /**
     * Build category payload. When defaultLocale is provided, blank name/description for any locale
     * are filled from the default locale or first non-empty value so changing default language
     * does not leave blank display values.
     *
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category, string $restaurantUuid, string $menuUuid, ?string $defaultLocale = null): array
    {
        $translations = [];
        foreach ($category->translations as $t) {
            $translations[$t->locale] = [
                'name' => $t->name ?? '',
                'description' => $t->description ?? '',
            ];
        }

        if ($defaultLocale !== null) {
            $fallbackName = $this->fallbackTranslationValue($translations, 'name', $defaultLocale);
            $fallbackDescription = $this->fallbackTranslationValue($translations, 'description', $defaultLocale);
            foreach ($translations as $locale => $vals) {
                if (trim((string) $vals['name']) === '') {
                    $translations[$locale]['name'] = $fallbackName;
                }
                if (trim((string) $vals['description']) === '') {
                    $translations[$locale]['description'] = $fallbackDescription;
                }
            }
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $imageUrl = $category->image_path
            ? $baseUrl . '/api/restaurants/' . $restaurantUuid . '/menus/' . $menuUuid . '/categories/' . $category->uuid . '/image'
            : null;

        return [
            'uuid' => $category->uuid,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'availability' => $category->availability,
            'image_url' => $imageUrl,
            'translations' => $translations,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }

    /**
     * First try default locale, then first non-empty value across locales.
     */
    private function fallbackTranslationValue(array $translations, string $key, string $defaultLocale): string
    {
        $v = $translations[$defaultLocale][$key] ?? null;
        if ($v !== null && trim((string) $v) !== '') {
            return (string) $v;
        }
        foreach ($translations as $vals) {
            $v = $vals[$key] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return (string) $v;
            }
        }
        return '';
    }
}
