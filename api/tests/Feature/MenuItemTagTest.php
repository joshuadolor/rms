<?php

namespace Tests\Feature;

use App\Models\MenuItemTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('menu-item-tags')]
class MenuItemTagTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_list_tags_returns_default_tags_only(): void
    {
        $defaultTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'local_fire_department',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $customTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'My Custom',
            'color' => '#000',
            'icon' => 'star',
            'user_id' => $user->id,
        ]);
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/menu-item-tags', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [['uuid', 'color', 'icon', 'text']]])
            ->assertJsonFragment(['text' => 'Spicy', 'uuid' => $defaultTag->uuid])
            ->assertJsonMissing(['uuid' => $customTag->uuid]);
    }

    public function test_create_tag_returns_403(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/menu-item-tags', [
            'text' => 'My Custom Tag',
            'color' => '#000',
            'icon' => 'star',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Custom menu item tags are not available. Use the default tags.']);
    }

    public function test_update_tag_returns_403(): void
    {
        $defaultTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'fire',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->patchJson('/api/menu-item-tags/' . $defaultTag->uuid, [
            'text' => 'Updated',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Custom menu item tags are not available. Use the default tags.']);
    }

    public function test_delete_tag_returns_403(): void
    {
        $defaultTag = MenuItemTag::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'text' => 'Spicy',
            'color' => '#dc2626',
            'icon' => 'fire',
            'user_id' => null,
        ]);
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->deleteJson('/api/menu-item-tags/' . $defaultTag->uuid, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Custom menu item tags are not available. Use the default tags.']);
        $this->assertDatabaseHas('menu_item_tags', ['id' => $defaultTag->id]);
    }
}
