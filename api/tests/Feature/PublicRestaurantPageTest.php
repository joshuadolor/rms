<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('public')]
class PublicRestaurantPageTest extends TestCase
{
    private const RESTAURANT_DOMAIN = 'rms.local';

    private function createRestaurantWithSlug(string $slug, string $template = 'template-1'): Restaurant
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = 'Public Test Restaurant';
        $r->slug = $slug;
        $r->tagline = 'Best food in town';
        $r->template = $template;
        $r->default_locale = 'en';
        $r->save();
        $r->languages()->create(['locale' => 'en']);

        return $r;
    }

    /** Request GET / on the restaurant subdomain (e.g. http://my-place.localhost/). */
    private function getOnSubdomain(string $slug, string $path = '/'): \Illuminate\Testing\TestResponse
    {
        $host = $slug . '.' . self::RESTAURANT_DOMAIN;
        $uri = 'http://' . $host . ($path === '/' ? '/' : $path);

        return $this->get($uri);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.restaurant_domain' => self::RESTAURANT_DOMAIN]);
    }

    public function test_public_restaurant_page_returns_html_for_valid_slug(): void
    {
        $restaurant = $this->createRestaurantWithSlug('my-place');

        $response = $this->getOnSubdomain('my-place');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee($restaurant->name, false)
            ->assertSee('my-place', false)
            ->assertSee('<title>', false)
            ->assertSee('og:title', false)
            ->assertSee('rms-hero', false);
    }

    public function test_public_restaurant_page_returns_404_for_unknown_slug(): void
    {
        $response = $this->getOnSubdomain('nonexistent-slug-12345');

        $response->assertNotFound();
    }

    public function test_public_restaurant_page_uses_template_2_layout(): void
    {
        $this->createRestaurantWithSlug('template2-place', 'template-2');

        $response = $this->getOnSubdomain('template2-place');

        $response->assertOk()
            ->assertSee('rms-template-2', false);
    }

    public function test_public_restaurant_page_template_1_shows_template_1_layout(): void
    {
        $this->createRestaurantWithSlug('template1-place', 'template-1');

        $response = $this->getOnSubdomain('template1-place');

        $response->assertOk()
            ->assertSee('rms-template-1', false);
    }

    public function test_public_restaurant_page_includes_canonical_and_meta(): void
    {
        $this->createRestaurantWithSlug('canonical-test');

        $response = $this->getOnSubdomain('canonical-test');

        $response->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('canonical-test', false)
            ->assertSee('og:url', false)
            ->assertSee('og:description', false);
    }

    public function test_public_restaurant_page_via_path_returns_blade_template(): void
    {
        $this->createRestaurantWithSlug('path-test', 'template-1');

        $response = $this->get('/r/path-test');

        $response->assertOk()
            ->assertSee('rms-template-1', false)
            ->assertSee('Template 1', false)
            ->assertSee('path-test', false);
    }
}
