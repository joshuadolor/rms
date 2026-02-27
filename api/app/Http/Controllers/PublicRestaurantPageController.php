<?php

namespace App\Http\Controllers;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the public restaurant page as full Blade HTML (no Vue). Meta tags and content (hero, menu, about, reviews)
 * come from resources/views/generic-templates/template-1 or template-2. Routes: subdomain GET {slug}.RESTAURANT_DOMAIN/
 * or path GET /r/{slug}.
 */
class PublicRestaurantPageController extends Controller
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Show the public restaurant page by slug. 404 if not found.
     * Renders Blade with meta tags and full page content from generic-templates.
     */
    public function show(Request $request, string $slug): View|Response
    {
        $restaurant = $this->restaurantRepository->findBySlug($slug);
        if ($restaurant === null) {
            abort(404, __('Restaurant not found.'));
        }

        $locale = $request->input('locale') ?? $restaurant->default_locale ?? 'en';
        $locale = strtolower($locale);
        $installedLocales = $restaurant->languages()->pluck('locale')->all();
        if (! in_array($locale, $installedLocales, true)) {
            $locale = $restaurant->default_locale ?? 'en';
        }

        $scheme = $request->getScheme();
        $restaurantDomain = config('app.restaurant_domain', 'localhost');
        // Canonical: when on path-based /r/{slug} use full URL; when on subdomain use subdomain root
        $canonicalUrl = str_starts_with($request->path(), 'r/')
            ? $request->url()
            : $scheme . '://' . $request->getHttpHost() . '/';
        $publicUrlSubdomain = $scheme . '://' . $restaurant->slug . '.' . $restaurantDomain . '/';
        $baseUrl = rtrim(config('app.url'), '/');

        $logoUrl = $restaurant->logo_path
            ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/logo'
            : null;
        $bannerUrl = $restaurant->banner_path
            ? $baseUrl . '/api/restaurants/' . $restaurant->uuid . '/banner'
            : null;

        $translation = $restaurant->translations()->where('locale', $locale)->first();
        $description = $translation?->description ?? $restaurant->tagline ?? null;

        $metaTitle = $restaurant->name . ($restaurant->tagline ? ' – ' . $restaurant->tagline : '');
        $metaDescription = $description ?: $restaurant->name;
        $ogImage = $bannerUrl ?: $logoUrl;

        $template = $this->resolveTemplate($restaurant->template ?? 'template-1');
        $menuGroups = $this->buildMenuGroupsForLocale($restaurant, $locale);
        $feedbacks = $this->buildFeedbacks($restaurant);

        return view('public.restaurant', [
            'restaurant' => $restaurant,
            'template' => $template,
            'locale' => $locale,
            'canonicalUrl' => $canonicalUrl,
            'publicUrlSubdomain' => $publicUrlSubdomain,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'ogImage' => $ogImage,
            'logoUrl' => $logoUrl,
            'bannerUrl' => $bannerUrl,
            'description' => $description,
            'menuGroups' => $menuGroups,
            'feedbacks' => $feedbacks,
            'primaryColor' => $restaurant->primary_color ?? '#2563eb',
        ]);
    }

    /** Map legacy or invalid template to a valid one; valid: template-1, template-2. */
    private function resolveTemplate(?string $template): string
    {
        $map = ['default' => 'template-1', 'minimal' => 'template-2'];
        $resolved = $map[$template] ?? $template;
        $ids = config('templates.ids', \App\Models\Restaurant::TEMPLATES);

        return in_array($resolved, $ids, true) ? $resolved : 'template-1';
    }

    /**
     * Menu items grouped by category for Blade (categories sorted; uncategorized last).
     *
     * @return array<int, array{category_name: string, category_uuid: string|null, items: array}>
     */
    private function buildMenuGroupsForLocale(\App\Models\Restaurant $restaurant, string $locale): array
    {
        $items = $restaurant->menuItems()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('category_id')
                    ->orWhereHas('category', fn ($c) => $c->where('is_active', true));
            })
            ->with(['translations', 'sourceMenuItem.translations', 'menuItemTags', 'category.translations'])
            ->orderBy('sort_order')->orderBy('id')->get();

        $byCategory = [];
        foreach ($items as $item) {
            $effective = $item->getEffectiveTranslations();
            $t = $effective[$locale] ?? reset($effective) ?: ['name' => '', 'description' => null];
            $tags = $item->menuItemTags->map(fn ($tag) => $tag->toTagPayload())->values()->all();

            $catUuid = null;
            $catName = __('Menu');
            $catSort = 9999;
            if ($item->category_id !== null && $item->relationLoaded('category') && $item->category) {
                $catUuid = $item->category->uuid;
                $catSort = (int) ($item->category->sort_order ?? 0);
                $catTrans = $item->category->translations->firstWhere('locale', $locale)
                    ?? $item->category->translations->first();
                $catName = $catTrans?->name ?? __('Menu');
            }

            $key = $catUuid ?? 'uncategorized';
            if (! isset($byCategory[$key])) {
                $byCategory[$key] = ['category_uuid' => $catUuid, 'category_name' => $catName, 'category_sort_order' => $catSort, 'items' => []];
            }
            $byCategory[$key]['items'][] = [
                'uuid' => $item->uuid,
                'name' => $t['name'] ?? '',
                'description' => $t['description'] ?? null,
                'price' => $item->getEffectivePrice(),
                'is_available' => (bool) ($item->is_available ?? true),
                'tags' => $tags,
            ];
        }
        uasort($byCategory, fn ($a, $b) => $a['category_sort_order'] <=> $b['category_sort_order']);

        return array_values($byCategory);
    }

    /** @return list<array{uuid: string, rating: int, text: string|null, name: string|null, created_at: string|null}> */
    private function buildFeedbacks(\App\Models\Restaurant $restaurant): array
    {
        return $restaurant->feedbacks()
            ->approved()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($f) => [
                'uuid' => $f->uuid,
                'rating' => (int) $f->rating,
                'text' => $f->text,
                'name' => $f->name,
                'created_at' => $f->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
