<?php

namespace Tests\Unit\Menu;

use App\Application\Menu\UpdateMenu;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class UpdateMenuTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_menu_with_translations_returns_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Lunch', 'is_active' => true, 'sort_order' => 0]);
        $menu->translations()->create(['locale' => 'en', 'name' => 'Lunch', 'description' => null]);
        $menu->translations()->create(['locale' => 'fr', 'name' => 'Déjeuner', 'description' => null]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Lunch Menu', 'description' => '12–3pm'],
                'fr' => ['name' => 'Menu Déjeuner', 'description' => '12h–15h'],
            ],
        ];

        $useCase = app(UpdateMenu::class);
        $updated = $useCase->handle($user, $restaurant->uuid, $menu->uuid, $input);

        $this->assertInstanceOf(Menu::class, $updated);
        $this->assertTrue($updated->relationLoaded('translations'));
        $translationsByLocale = $updated->translations->keyBy('locale');

        $this->assertSame('Lunch Menu', $translationsByLocale->get('en')->name);
        $this->assertSame('12–3pm', $translationsByLocale->get('en')->description);
        $this->assertSame('Menu Déjeuner', $translationsByLocale->get('fr')->name);
        $this->assertSame('12h–15h', $translationsByLocale->get('fr')->description);
    }

    public function test_update_menu_with_translations_persists_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Dinner', 'is_active' => true, 'sort_order' => 0]);
        $menu->translations()->create(['locale' => 'en', 'name' => 'Dinner', 'description' => null]);
        $menu->translations()->create(['locale' => 'fr', 'name' => 'Dîner', 'description' => null]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Evening Menu', 'description' => 'From 6pm'],
                'fr' => ['name' => 'Menu Soir', 'description' => 'À partir de 18h'],
            ],
        ];

        $useCase = app(UpdateMenu::class);
        $useCase->handle($user, $restaurant->uuid, $menu->uuid, $input);

        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->id,
            'locale' => 'en',
            'name' => 'Evening Menu',
            'description' => 'From 6pm',
        ]);
        $this->assertDatabaseHas('menu_translations', [
            'menu_id' => $menu->id,
            'locale' => 'fr',
            'name' => 'Menu Soir',
            'description' => 'À partir de 18h',
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
