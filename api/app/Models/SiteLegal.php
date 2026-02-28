<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLegal extends Model
{
    protected $table = 'site_legal';

    protected $fillable = [
        'terms_of_service_en',
        'privacy_policy_en',
        'terms_of_service_es',
        'privacy_policy_es',
        'terms_of_service_ar',
        'privacy_policy_ar',
    ];

    protected $casts = [
        'terms_of_service_en' => 'string',
        'privacy_policy_en' => 'string',
        'terms_of_service_es' => 'string',
        'privacy_policy_es' => 'string',
        'terms_of_service_ar' => 'string',
        'privacy_policy_ar' => 'string',
    ];

    /**
     * Supported locales for legal content (order: default first).
     */
    public static function supportedLocales(): array
    {
        return config('legal.supported_locales', ['en', 'es', 'ar']);
    }

    /**
     * Resolve locale to a supported one; fallback to default (en).
     */
    public static function resolveLocale(?string $locale): string
    {
        $supported = self::supportedLocales();
        if ($locale !== null && in_array($locale, $supported, true)) {
            return $locale;
        }

        return $supported[0] ?? 'en';
    }

    /**
     * Get Terms of Service content for the given locale (fallback to default locale when empty).
     */
    public function getTermsForLocale(string $locale): string
    {
        $locale = self::resolveLocale($locale);
        $key = "terms_of_service_{$locale}";
        $value = $this->{$key} ?? '';

        if ($value !== '' && $value !== null) {
            return (string) $value;
        }

        $defaultLocale = self::supportedLocales()[0] ?? 'en';
        if ($locale === $defaultLocale) {
            return '';
        }

        $defaultKey = "terms_of_service_{$defaultLocale}";

        return (string) ($this->{$defaultKey} ?? '');
    }

    /**
     * Get Privacy Policy content for the given locale (fallback to default locale when empty).
     */
    public function getPrivacyForLocale(string $locale): string
    {
        $locale = self::resolveLocale($locale);
        $key = "privacy_policy_{$locale}";
        $value = $this->{$key} ?? '';

        if ($value !== '' && $value !== null) {
            return (string) $value;
        }

        $defaultLocale = self::supportedLocales()[0] ?? 'en';
        if ($locale === $defaultLocale) {
            return '';
        }

        $defaultKey = "privacy_policy_{$defaultLocale}";

        return (string) ($this->{$defaultKey} ?? '');
    }

    /**
     * Get the singleton row (create if missing).
     */
    public static function instance(): self
    {
        $row = self::query()->first();
        if ($row === null) {
            $row = self::query()->create([
                'terms_of_service_en' => '',
                'privacy_policy_en' => '',
                'terms_of_service_es' => '',
                'privacy_policy_es' => '',
                'terms_of_service_ar' => '',
                'privacy_policy_ar' => '',
            ]);
        }

        return $row;
    }

    /**
     * Return all locales' terms and privacy as [locale => [terms_of_service => ..., privacy_policy => ...]].
     * Uses raw stored values (no default-locale fallback) so superadmin sees exactly what is saved per locale.
     */
    public function toLocalePayload(): array
    {
        $payload = [];
        foreach (self::supportedLocales() as $locale) {
            $payload[$locale] = [
                'terms_of_service' => (string) ($this->{"terms_of_service_{$locale}"} ?? ''),
                'privacy_policy' => (string) ($this->{"privacy_policy_{$locale}"} ?? ''),
            ];
        }

        return $payload;
    }
}
