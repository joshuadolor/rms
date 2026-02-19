<?php

namespace Tests\Unit\Menu;

use App\Application\Menu\CreateMenu;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class CreateMenuTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_menu_with_translations_returns_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);

        $input = [
            'is_active' => true,
            'translations' => [
                'en' => ['name' => 'Lunch Menu', 'description' => 'Served 12–3pm'],
                'fr' => ['name' => 'Menu Déjeuner', 'description' => 'Servi 12h–15h'],
            ],
        ];

        $useCase = app(CreateMenu::class);
        $menu = $useCase->handle($user, $restaurant->uuid, $input);

        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertTrue($menu->relationLoaded('translations'));
        $translationsByLocale = $menu->translations->keyBy('locale');

        $this->assertSame('Lunch Menu', $translationsByLocale->get('en')->name);
        $this->assertSame('Served 12–3pm', $translationsByLocale->get('en')->description);
        $this->assertSame('Menu Déjeuner', $translationsByLocale->get('fr')->name);
        $this->assertSame('Servi 12h–15h', $translationsByLocale->get('fr')->description);
    }

    public function test_create_menu_with_translations_persists_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);

        $input = [
            'translations' => [
                'en' => ['name' => 'Dinner Menu', 'description' => 'Evening only'],
                'fr' => ['name' => 'Menu Dîner', 'description' => 'Soir uniquement'],
            ],
        ];

        $useCase = app(CreateMenu::class);
        $menu = $useCase->handle($user, $restaurant->uuid, $input);

        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->id,
            'locale' => 'en',
            'name' => 'Dinner Menu',
            'description' => 'Evening only',
        ]);
        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->id,
            'locale' => 'fr',
            'name' => 'Menu Dîner',
            'description' => 'Soir uniquement',
        ]);
    }

    private function createRestaurantWithEnAndFr(User $user): Restaurant
    {
        $restaurant = new Restaurant;
        $restaurant->user_id = $user->id;
        $restaurant->name = 'Test Restaurant';
        $restaurant->slug = 'test-restaurant-' . uniqid();
        $restaurant->default_locale = 'en';
        $restaurant->save();
        $restaurant->languages()->create(['locale' => 'en']);
        $restaurant->languages()->create(['locale' => 'fr']);

        return $restaurant;
    }
}
