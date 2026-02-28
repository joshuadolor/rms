<?php

namespace Tests\Feature;

use App\Models\SiteLegal;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('legal')]
class LegalContentTest extends TestCase
{
    /**
     * Public terms: optional locale query; fallback to en when missing.
     */
    public function test_public_terms_returns_content_for_locale(): void
    {
        $legal = SiteLegal::instance();
        $legal->terms_of_service_en = 'Terms EN';
        $legal->terms_of_service_es = 'Terms ES';
        $legal->terms_of_service_ar = 'Terms AR';
        $legal->save();

        $responseEn = $this->getJson('/api/legal/terms');
        $responseEn->assertOk()
            ->assertJsonPath('data.content', 'Terms EN');

        $responseEs = $this->getJson('/api/legal/terms?locale=es');
        $responseEs->assertOk()
            ->assertJsonPath('data.content', 'Terms ES');

        $responseAr = $this->getJson('/api/legal/terms?locale=ar');
        $responseAr->assertOk()
            ->assertJsonPath('data.content', 'Terms AR');
    }

    /**
     * Public terms: missing or invalid locale falls back to en.
     */
    public function test_public_terms_fallbacks_to_en_when_locale_missing_or_invalid(): void
    {
        $legal = SiteLegal::instance();
        $legal->terms_of_service_en = 'Default terms';
        $legal->save();

        $noLocale = $this->getJson('/api/legal/terms');
        $noLocale->assertOk()->assertJsonPath('data.content', 'Default terms');

        $invalidLocale = $this->getJson('/api/legal/terms?locale=fr');
        $invalidLocale->assertOk()->assertJsonPath('data.content', 'Default terms');
    }

    /**
     * Public privacy: optional locale query; fallback to en when missing.
     */
    public function test_public_privacy_returns_content_for_locale(): void
    {
        $legal = SiteLegal::instance();
        $legal->privacy_policy_en = 'Privacy EN';
        $legal->privacy_policy_es = 'Privacy ES';
        $legal->privacy_policy_ar = 'Privacy AR';
        $legal->save();

        $responseEn = $this->getJson('/api/legal/privacy');
        $responseEn->assertOk()
            ->assertJsonPath('data.content', 'Privacy EN');

        $responseEs = $this->getJson('/api/legal/privacy?locale=es');
        $responseEs->assertOk()
            ->assertJsonPath('data.content', 'Privacy ES');

        $responseAr = $this->getJson('/api/legal/privacy?locale=ar');
        $responseAr->assertOk()
            ->assertJsonPath('data.content', 'Privacy AR');
    }

    /**
     * Public privacy: missing or invalid locale falls back to en.
     */
    public function test_public_privacy_fallbacks_to_en_when_locale_missing_or_invalid(): void
    {
        $legal = SiteLegal::instance();
        $legal->privacy_policy_en = 'Default privacy';
        $legal->save();

        $noLocale = $this->getJson('/api/legal/privacy');
        $noLocale->assertOk()->assertJsonPath('data.content', 'Default privacy');

        $invalidLocale = $this->getJson('/api/legal/privacy?locale=de');
        $invalidLocale->assertOk()->assertJsonPath('data.content', 'Default privacy');
    }

    /**
     * Public terms: when locale content is empty, response uses fallback to default (en).
     */
    public function test_public_terms_fallbacks_to_en_content_when_requested_locale_empty(): void
    {
        $legal = SiteLegal::instance();
        $legal->terms_of_service_en = 'Only EN terms';
        $legal->terms_of_service_es = '';
        $legal->terms_of_service_ar = '';
        $legal->save();

        $response = $this->getJson('/api/legal/terms?locale=es');
        $response->assertOk()->assertJsonPath('data.content', 'Only EN terms');
    }
}
