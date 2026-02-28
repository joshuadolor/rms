<?php

namespace Tests\Feature;

use App\Contracts\TranslationServiceInterface;
use App\Exceptions\TranslationException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('translate')]
class TranslateTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_translate_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'en',
            'to_locale' => 'es',
        ]);

        $response->assertUnauthorized();
    }

    public function test_translate_returns_503_when_service_not_available(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'en',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_translate_returns_422_when_validation_fails(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => '',
            'from_locale' => 'en',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['text']);

        $response2 = $this->postJson('/api/translate', [
            'from_locale' => 'en',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response2->assertUnprocessable()
            ->assertJsonValidationErrors(['text']);

        $response3 = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response3->assertUnprocessable()
            ->assertJsonValidationErrors(['from_locale']);
    }

    public function test_translate_returns_200_with_translated_text_when_service_available(): void
    {
        $this->bindFakeTranslationService(['en', 'es']);

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'en',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('translated_text', 'Translated: Hello');
    }

    public function test_translate_returns_422_when_from_locale_not_supported(): void
    {
        $this->bindFakeTranslationService(['en', 'es']); // no 'xx'

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'xx',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from_locale']);
    }

    public function test_translate_returns_422_when_to_locale_not_supported(): void
    {
        // Use a code not in service list nor in config('locales.supported')
        $this->bindFakeTranslationService(['en', 'es']);

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'en',
            'to_locale' => 'xx',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_locale']);
    }

    public function test_translate_languages_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/translate/languages');

        $response->assertUnauthorized();
    }

    public function test_translate_languages_returns_503_when_service_not_available(): void
    {
        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/translate/languages', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_translate_languages_returns_200_with_data_when_service_available(): void
    {
        $this->bindFakeTranslationService(['en', 'es'], [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'es', 'name' => 'Spanish'],
        ]);

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/translate/languages', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.0.name', 'English')
            ->assertJsonPath('data.1.code', 'es')
            ->assertJsonPath('data.1.name', 'Spanish');
    }

    public function test_translate_languages_returns_502_when_service_throws(): void
    {
        $this->app->bind(TranslationServiceInterface::class, function (): TranslationServiceInterface {
            return new class implements TranslationServiceInterface
            {
                public function translate(string $text, string $fromLocale, string $toLocale): string
                {
                    return $text;
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function getSupportedLanguages(): array
                {
                    throw new TranslationException('LibreTranslate languages error: connection refused');
                }
            };
        });

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->getJson('/api/translate/languages', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'LibreTranslate languages error: connection refused');
    }

    public function test_translate_returns_502_when_service_throws(): void
    {
        $this->app->bind(TranslationServiceInterface::class, function (): TranslationServiceInterface {
            return new class implements TranslationServiceInterface
            {
                public function translate(string $text, string $fromLocale, string $toLocale): string
                {
                    throw new TranslationException('LibreTranslate error: connection refused');
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function getSupportedLanguages(): array
                {
                    return [
                        ['code' => 'en', 'name' => 'English'],
                        ['code' => 'es', 'name' => 'Spanish'],
                    ];
                }
            };
        });

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello',
            'from_locale' => 'en',
            'to_locale' => 'es',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'LibreTranslate error: connection refused');
    }

    public function test_translate_returns_200_with_original_text_when_language_not_supported(): void
    {
        $this->app->bind(TranslationServiceInterface::class, function (): TranslationServiceInterface {
            return new class implements TranslationServiceInterface
            {
                public function translate(string $text, string $fromLocale, string $toLocale): string
                {
                    throw new TranslationException('LibreTranslate error: ar is not supported');
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function getSupportedLanguages(): array
                {
                    return [['code' => 'en', 'name' => 'English'], ['code' => 'es', 'name' => 'Spanish']];
                }
            };
        });

        $user = $this->createVerifiedUser();
        $token = $user->createToken('auth')->plainTextToken;

        $response = $this->postJson('/api/translate', [
            'text' => 'Hello world',
            'from_locale' => 'en',
            'to_locale' => 'ar',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('translated_text', 'Hello world')
            ->assertJsonPath('fallback', true);
    }

    /**
     * @param  array<string>  $codes
     * @param  array<int, array{code: string, name: string}>  $list
     */
    private function bindFakeTranslationService(array $codes = ['en', 'es'], ?array $list = null): void
    {
        $list = $list ?? array_map(fn (string $c) => ['code' => $c, 'name' => $c], $codes);

        $this->app->bind(TranslationServiceInterface::class, function () use ($codes, $list): TranslationServiceInterface {
            return new class($codes, $list) implements TranslationServiceInterface
            {
                public function __construct(
                    private readonly array $codes,
                    private readonly array $list
                ) {}

                public function translate(string $text, string $fromLocale, string $toLocale): string
                {
                    return 'Translated: ' . $text;
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function getSupportedLanguages(): array
                {
                    return $this->list;
                }
            };
        });
    }
}
