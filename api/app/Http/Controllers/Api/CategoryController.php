<?php

namespace App\Http\Controllers\Api;

use App\Application\Category\CreateCategory;
use App\Application\Category\DeleteCategory;
use App\Application\Category\GetCategory;
use App\Application\Category\ListCategories;
use App\Application\Category\ReorderCategories;
use App\Application\Category\UpdateCategory;
use App\Application\Restaurant\GetRestaurant;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly GetRestaurant $getRestaurant,
        private readonly ListCategories $listCategories,
        private readonly GetCategory $getCategory,
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly DeleteCategory $deleteCategory,
        private readonly ReorderCategories $reorderCategories
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

        return response()->json([
            'data' => $categories->map(fn (Category $c) => $this->categoryPayload($c)),
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

        return response()->json(['data' => $this->categoryPayload($categoryModel)]);
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

        return response()->json([
            'message' => __('Category created.'),
            'data' => $this->categoryPayload($category),
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

        return response()->json([
            'message' => __('Category updated.'),
            'data' => $this->categoryPayload($categoryModel),
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
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category): array
    {
        $translations = [];
        foreach ($category->translations as $t) {
            $translations[$t->locale] = [
                'name' => $t->name,
                'description' => $t->description,
            ];
        }

        return [
            'uuid' => $category->uuid,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'translations' => $translations,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
