<?php

namespace App\Http\Controllers\Api;

use App\Application\MenuItemTag\ListMenuItemTags;
use App\Http\Controllers\Controller;
use App\Models\MenuItemTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuItemTagController extends Controller
{
    private const TAG_MANAGEMENT_DISABLED_MESSAGE = 'Custom menu item tags are not available. Use the default tags.';

    public function __construct(
        private readonly ListMenuItemTags $listMenuItemTags
    ) {}

    /**
     * List tags: default (system) tags only.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $this->listMenuItemTags->handle($request->user());

        return response()->json([
            'data' => $tags->map(fn (MenuItemTag $tag) => $tag->toTagPayload()),
        ]);
    }

    /**
     * Create tag disabled. Returns 403 so existing frontends receive a clear message.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => __(self::TAG_MANAGEMENT_DISABLED_MESSAGE)], 403);
    }

    /**
     * Update tag disabled. Returns 403 so existing frontends receive a clear message.
     */
    public function update(Request $request, string $tag): JsonResponse
    {
        return response()->json(['message' => __(self::TAG_MANAGEMENT_DISABLED_MESSAGE)], 403);
    }

    /**
     * Delete tag disabled. Returns 403 so existing frontends receive a clear message.
     */
    public function destroy(Request $request, string $tag): JsonResponse
    {
        return response()->json(['message' => __(self::TAG_MANAGEMENT_DISABLED_MESSAGE)], 403);
    }
}
