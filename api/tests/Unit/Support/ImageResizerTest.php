<?php

namespace Tests\Unit\Support;

use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('restaurant')]
class ImageResizerTest extends TestCase
{
    public function test_resize_to_fit_returns_content_for_small_image_unchanged(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

        $content = ImageResizer::resizeToFit($file, 300, 300);

        $this->assertNotEmpty($content);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertSame(100, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function test_resize_to_fit_scales_down_when_exceeding_max_dimensions(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 600, 200);

        $content = ImageResizer::resizeToFit($file, 300, 300);

        $this->assertNotEmpty($content);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(300, $info[0]);
        $this->assertLessThanOrEqual(300, $info[1]);
        // Proportional: 600x200 -> 300x100
        $this->assertSame(300, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function test_resize_to_fit_throws_for_invalid_image(): void
    {
        $file = UploadedFile::fake()->create('not-an-image.txt', 100, 'text/plain');

        $this->expectException(\InvalidArgumentException::class);

        ImageResizer::resizeToFit($file, 300, 300);
    }

    public function test_resize_to_square_returns_square_image(): void
    {
        $file = UploadedFile::fake()->image('item.jpg', 800, 400);

        $content = ImageResizer::resizeToSquare($file, 512);

        $this->assertNotEmpty($content);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);
    }

    public function test_resize_to_square_small_image_upscales_to_target(): void
    {
        $file = UploadedFile::fake()->image('item.jpg', 100, 100);

        $content = ImageResizer::resizeToSquare($file, 512);

        $this->assertNotEmpty($content);
        $info = getimagesizefromstring($content);
        $this->assertNotFalse($info);
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);
    }
}
