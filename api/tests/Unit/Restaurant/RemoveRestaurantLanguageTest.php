<?php

namespace Tests\Unit\Restaurant;

use App\Application\Restaurant\RemoveRestaurantLanguage;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('restaurant')]
class RemoveRestaurantLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_language_does_not_delete_restaurant_translations(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithTwoLanguages($user);

        $restaurant->translations()->create(['locale' => 'en', 'description' => 'English description']);
        $restaurant->translations()->create(['locale' => 'fr', 'description' => 'Description française']);
        $restaurantTranslationIds = $restaurant->translations()->pluck('id')->all();

        $useCase = app(RemoveRestaurantLanguage::class);
        $useCase->handle($user, $restaurant->uuid, 'fr');

        $this->assertDatabaseCount('restaurant_translations', 2);
        foreach ($restaurantTranslationIds as $id) {
            $this->assertDatabaseHas('restaurant_translations', ['id' => $id]);
        }
    }

    public function test_removing_language_does_not_delete_menu_translations(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithTwoLanguages($user);
        $menu = $restaurant->menus()->create(['name' => 'Lunch', 'is_active' => true, 'sort_order' => 0]);
        $menu->translations()->create(['locale' => 'en', 'name' => 'Lunch', 'description' => 'Lunch menu']);
        $menu->translations()->create(['locale' => 'fr', 'name' => 'Déjeuner', 'description' => 'Menu déjeuner']);
        $menuTranslationIds = $menu->translations()->pluck('id')->all();

        $useCase = app(RemoveRestaurantLanguage::class);
        $useCase->handle($user, $restaurant->uuid, 'fr');

        $this->assertDatabaseCount('menu_translations', 2);
        foreach ($menuTranslationIds as $id) {
            $this->assertDatabaseHas('menu_translations', ['id' => $id]);
        }
    }

    public function test_removing_language_does_not_delete_category_translations(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithTwoLanguages($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'is_active' => true, 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Starters', 'description' => 'Starters section']);
        $category->translations()->create(['locale' => 'fr', 'name' => 'Entrées', 'description' => 'Section entrées']);
        $categoryTranslationIds = $category->translations()->pluck('id')->all();

        $useCase = app(RemoveRestaurantLanguage::class);
        $useCase->handle($user, $restaurant->uuid, 'fr');

        $this->assertDatabaseCount('category_translations', 2);
        foreach ($categoryTranslationIds as $id) {
            $this->assertDatabaseHas('category_translations', ['id' => $id]);
        }
    }

    public function test_removing_language_deletes_only_restaurant_language_row(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithTwoLanguages($user);
        $restaurant->translations()->create(['locale' => 'en', 'description' => 'En']);
        $restaurant->translations()->create(['locale' => 'fr', 'description' => 'Fr']);

        $this->assertDatabaseCount('restaurant_languages', 2);
        $this->assertDatabaseHas('restaurant_languages', ['restaurant_id' => $restaurant->id, 'locale' => 'fr']);

        $useCase = app(RemoveRestaurantLanguage::class);
        $useCase->handle($user, $restaurant->uuid, 'fr');

        $this->assertDatabaseCount('restaurant_languages', 1);
        $this->assertDatabaseMissing('restaurant_languages', ['restaurant_id' => $restaurant->id, 'locale' => 'fr']);
        $this->assertDatabaseHas('restaurant_languages', ['restaurant_id' => $restaurant->id, 'locale' => 'en']);
    }

    private function createRestaurantWithTwoLanguages(User $user): Restaurant
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
