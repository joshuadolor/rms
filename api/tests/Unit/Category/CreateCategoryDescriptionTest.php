<?php

namespace Tests\Unit\Category;

use App\Application\Category\CreateCategory;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class CreateCategoryDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_category_with_translations_returns_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'is_active' => true, 'sort_order' => 0]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Starters', 'description' => 'Light bites to begin'],
                'fr' => ['name' => 'Entrées', 'description' => 'Bouchées pour commencer'],
            ],
        ];

        $useCase = app(CreateCategory::class);
        $category = $useCase->handle($user, $restaurant->uuid, $menu->uuid, $input);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertTrue($category->relationLoaded('translations'));
        $translationsByLocale = $category->translations->keyBy('locale');

        $this->assertSame('Starters', $translationsByLocale->get('en')->name);
        $this->assertSame('Light bites to begin', $translationsByLocale->get('en')->description);
        $this->assertSame('Entrées', $translationsByLocale->get('fr')->name);
        $this->assertSame('Bouchées pour commencer', $translationsByLocale->get('fr')->description);
    }

    public function test_create_category_with_translations_persists_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'is_active' => true, 'sort_order' => 0]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Mains', 'description' => 'Main courses'],
                'fr' => ['name' => 'Plats', 'description' => 'Plats principaux'],
            ],
        ];

        $useCase = app(CreateCategory::class);
        $category = $useCase->handle($user, $restaurant->uuid, $menu->uuid, $input);

        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Mains',
            'description' => 'Main courses',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'fr',
            'name' => 'Plats',
            'description' => 'Plats principaux',
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
