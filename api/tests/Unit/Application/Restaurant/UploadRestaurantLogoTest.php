<?php

namespace Tests\Unit\Application\Restaurant;

use App\Application\Restaurant\UploadRestaurantLogo;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('restaurant')]
class UploadRestaurantLogoTest extends TestCase
{
    private function createVerifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function createRestaurantForUser(User $user, array $overrides = []): Restaurant
    {
        $r = new Restaurant;
        $r->user_id = $user->id;
        $r->name = $overrides['name'] ?? 'Test Restaurant';
        $r->slug = $overrides['slug'] ?? 'test-restaurant-' . uniqid();
        $r->tagline = $overrides['tagline'] ?? null;
        $r->default_locale = $overrides['default_locale'] ?? 'en';
        $r->save();
        $r->languages()->create(['locale' => $r->default_locale]);

        return $r;
    }

    public function test_upload_logo_stores_resized_content_when_image_exceeds_max(): void
    {
        Storage::fake();
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $file = UploadedFile::fake()->image('logo.jpg', 600, 200);

        $useCase = app(UploadRestaurantLogo::class);
        $useCase->handle($user, $restaurant, $file);

        $path = 'restaurants/' . $restaurant->uuid . '/logo.jpg';
        $this->assertTrue(Storage::exists($path));
        $content = Storage::get($path);
        $this->assertNotEmpty($content);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(300, $info[0]);
        $this->assertLessThanOrEqual(300, $info[1]);
        $this->assertSame(300, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function test_upload_logo_stores_unchanged_content_when_image_within_bounds(): void
    {
        Storage::fake();
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

        $useCase = app(UploadRestaurantLogo::class);
        $useCase->handle($user, $restaurant, $file);

        $path = 'restaurants/' . $restaurant->uuid . '/logo.jpg';
        $this->assertTrue(Storage::exists($path));
        $content = Storage::get($path);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertSame(100, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function test_upload_logo_deletes_old_file_then_stores_new(): void
    {
        Storage::fake();
        $user = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($user);
        // Old logo had different extension (e.g. PNG); new upload is JPEG so new path is logo.jpg.
        $oldPath = 'restaurants/' . $restaurant->uuid . '/logo.png';
        Storage::put($oldPath, 'old-content');
        $restaurant->forceFill(['logo_path' => $oldPath])->save();

        $file = UploadedFile::fake()->image('logo.jpg', 150, 150);
        $useCase = app(UploadRestaurantLogo::class);
        $useCase->handle($user, $restaurant, $file);

        $this->assertFalse(Storage::exists($oldPath), 'Old logo file should be deleted.');
        $newPath = $restaurant->fresh()->logo_path;
        $this->assertTrue(Storage::exists($newPath));
        $content = Storage::get($newPath);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertSame(150, $info[0]);
        $this->assertSame(150, $info[1]);
    }

    public function test_upload_logo_throws_for_non_owner(): void
    {
        Storage::fake();
        $owner = $this->createVerifiedUser();
        $other = $this->createVerifiedUser();
        $restaurant = $this->createRestaurantForUser($owner);
        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

        $useCase = app(UploadRestaurantLogo::class);

        $this->expectException(\App\Exceptions\ForbiddenException::class);
        $this->expectExceptionMessage('You do not own this restaurant.');

        $useCase->handle($other, $restaurant, $file);
    }
}
