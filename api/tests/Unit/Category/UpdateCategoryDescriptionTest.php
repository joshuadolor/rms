<?php

namespace Tests\Unit\Category;

use App\Application\Category\UpdateCategory;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu')]
class UpdateCategoryDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_category_with_translations_returns_name_and_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'is_active' => true, 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Starters', 'description' => null]);
        $category->translations()->create(['locale' => 'fr', 'name' => 'Entrées', 'description' => null]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Starters', 'description' => 'Light bites'],
                'fr' => ['name' => 'Entrées', 'description' => 'Bouchées légères'],
            ],
        ];

        $useCase = app(UpdateCategory::class);
        $updated = $useCase->handle($user, $restaurant->uuid, $menu->uuid, $category->uuid, $input);

        $this->assertInstanceOf(Category::class, $updated);
        $this->assertTrue($updated->relationLoaded('translations'));
        $translationsByLocale = $updated->translations->keyBy('locale');

        $this->assertSame('Starters', $translationsByLocale->get('en')->name);
        $this->assertSame('Light bites', $translationsByLocale->get('en')->description);
        $this->assertSame('Entrées', $translationsByLocale->get('fr')->name);
        $this->assertSame('Bouchées légères', $translationsByLocale->get('fr')->description);
    }

    public function test_update_category_with_translations_persists_description_per_locale(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $restaurant = $this->createRestaurantWithEnAndFr($user);
        $menu = $restaurant->menus()->create(['name' => 'Main', 'is_active' => true, 'sort_order' => 0]);
        $category = $menu->categories()->create(['sort_order' => 0]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Mains', 'description' => null]);
        $category->translations()->create(['locale' => 'fr', 'name' => 'Plats', 'description' => null]);

        $input = [
            'translations' => [
                'en' => ['name' => 'Mains', 'description' => 'Main courses and sides'],
                'fr' => ['name' => 'Plats', 'description' => 'Plats et accompagnements'],
            ],
        ];

        $useCase = app(UpdateCategory::class);
        $useCase->handle($user, $restaurant->uuid, $menu->uuid, $category->uuid, $input);

        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Mains',
            'description' => 'Main courses and sides',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'fr',
            'name' => 'Plats',
            'description' => 'Plats et accompagnements',
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
