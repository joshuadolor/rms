<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3}([0-9A-Fa-f]{2})?)?$/'],
            'default_locale' => ['sometimes', 'string', 'max:10', Rule::in(config('locales.supported', ['en', 'nl', 'ru']))],
            // Slug is set once on create and cannot be changed (subdomain URL stability).
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.instagram' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.twitter' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.linkedin' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }
}
