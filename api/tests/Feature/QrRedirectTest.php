<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('public')]
class QrRedirectTest extends TestCase
{
    private const RESTAURANT_DOMAIN = 'rms.local';

    private function createRestaurantWithSlug(string $slug): Restaurant
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = 'QR Test Restaurant';
        $r->slug = $slug;
        $r->tagline = null;
        $r->template = 'template-1';
        $r->default_locale = 'en';
        $r->save();
        $r->languages()->create(['locale' => 'en']);

        return $r;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.restaurant_domain' => self::RESTAURANT_DOMAIN]);
    }

    public function test_qr_redirect_redirects_to_subdomain_for_valid_uuid(): void
    {
        $restaurant = $this->createRestaurantWithSlug('pizza');

        $response = $this->get('/page/r/' . $restaurant->uuid);

        $response->assertRedirect();
        $response->assertStatus(302);
        $target = 'http://pizza.' . self::RESTAURANT_DOMAIN . '/';
        $response->assertRedirect($target);
    }

    public function test_qr_redirect_returns_404_for_unknown_uuid(): void
    {
        $response = $this->get('/page/r/550e8400-e29b-41d4-a716-446655440000');

        $response->assertNotFound();
    }
}
