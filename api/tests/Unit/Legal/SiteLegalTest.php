<?php

namespace Tests\Unit\Legal;

use App\Models\SiteLegal;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('legal')]
class SiteLegalTest extends TestCase
{
    /**
     * resolveLocale: valid locale returns that locale.
     */
    public function test_resolve_locale_returns_locale_when_valid(): void
    {
        $this->assertSame('en', SiteLegal::resolveLocale('en'));
        $this->assertSame('es', SiteLegal::resolveLocale('es'));
        $this->assertSame('ar', SiteLegal::resolveLocale('ar'));
    }

    /**
     * resolveLocale: null falls back to default (en).
     */
    public function test_resolve_locale_returns_en_when_null(): void
    {
        $this->assertSame('en', SiteLegal::resolveLocale(null));
    }

    /**
     * resolveLocale: invalid locale falls back to default (en).
     */
    public function test_resolve_locale_returns_en_when_invalid(): void
    {
        $this->assertSame('en', SiteLegal::resolveLocale('fr'));
        $this->assertSame('en', SiteLegal::resolveLocale('de'));
        $this->assertSame('en', SiteLegal::resolveLocale(''));
    }

    /**
     * getTermsForLocale: returns content for requested locale when present.
     */
    public function test_get_terms_for_locale_returns_content_for_locale_when_present(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => 'Terms EN',
            'privacy_policy_en' => '',
            'terms_of_service_es' => 'Terms ES',
            'privacy_policy_es' => '',
            'terms_of_service_ar' => 'Terms AR',
            'privacy_policy_ar' => '',
        ]);

        $this->assertSame('Terms EN', $legal->getTermsForLocale('en'));
        $this->assertSame('Terms ES', $legal->getTermsForLocale('es'));
        $this->assertSame('Terms AR', $legal->getTermsForLocale('ar'));
    }

    /**
     * getTermsForLocale: when locale content is empty, fallback to default locale (en).
     */
    public function test_get_terms_for_locale_fallbacks_to_en_when_locale_empty(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => 'Default terms',
            'privacy_policy_en' => '',
            'terms_of_service_es' => '',
            'privacy_policy_es' => '',
            'terms_of_service_ar' => '',
            'privacy_policy_ar' => '',
        ]);

        $this->assertSame('Default terms', $legal->getTermsForLocale('es'));
        $this->assertSame('Default terms', $legal->getTermsForLocale('ar'));
    }

    /**
     * getTermsForLocale: when default locale (en) is empty, returns empty string.
     */
    public function test_get_terms_for_locale_returns_empty_when_default_empty(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => '',
            'privacy_policy_en' => '',
            'terms_of_service_es' => '',
            'privacy_policy_es' => '',
            'terms_of_service_ar' => '',
            'privacy_policy_ar' => '',
        ]);

        $this->assertSame('', $legal->getTermsForLocale('en'));
        $this->assertSame('', $legal->getTermsForLocale('es'));
    }

    /**
     * getPrivacyForLocale: returns content for requested locale when present.
     */
    public function test_get_privacy_for_locale_returns_content_for_locale_when_present(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => '',
            'privacy_policy_en' => 'Privacy EN',
            'terms_of_service_es' => '',
            'privacy_policy_es' => 'Privacy ES',
            'terms_of_service_ar' => '',
            'privacy_policy_ar' => 'Privacy AR',
        ]);

        $this->assertSame('Privacy EN', $legal->getPrivacyForLocale('en'));
        $this->assertSame('Privacy ES', $legal->getPrivacyForLocale('es'));
        $this->assertSame('Privacy AR', $legal->getPrivacyForLocale('ar'));
    }

    /**
     * getPrivacyForLocale: when locale content is empty, fallback to default locale (en).
     */
    public function test_get_privacy_for_locale_fallbacks_to_en_when_locale_empty(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => '',
            'privacy_policy_en' => 'Default privacy',
            'terms_of_service_es' => '',
            'privacy_policy_es' => '',
            'terms_of_service_ar' => '',
            'privacy_policy_ar' => '',
        ]);

        $this->assertSame('Default privacy', $legal->getPrivacyForLocale('es'));
        $this->assertSame('Default privacy', $legal->getPrivacyForLocale('ar'));
    }

    /**
     * toLocalePayload: returns raw per-locale terms_of_service and privacy_policy for superadmin.
     */
    public function test_to_locale_payload_returns_raw_per_locale_terms_and_privacy(): void
    {
        $legal = new SiteLegal([
            'terms_of_service_en' => 'T EN',
            'privacy_policy_en' => 'P EN',
            'terms_of_service_es' => 'T ES',
            'privacy_policy_es' => '',
            'terms_of_service_ar' => '',
            'privacy_policy_ar' => 'P AR',
        ]);

        $payload = $legal->toLocalePayload();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('en', $payload);
        $this->assertArrayHasKey('es', $payload);
        $this->assertArrayHasKey('ar', $payload);

        $this->assertSame(['terms_of_service' => 'T EN', 'privacy_policy' => 'P EN'], $payload['en']);
        $this->assertSame(['terms_of_service' => 'T ES', 'privacy_policy' => ''], $payload['es']);
        $this->assertSame(['terms_of_service' => '', 'privacy_policy' => 'P AR'], $payload['ar']);
    }
}
