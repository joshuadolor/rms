<?php

namespace App\Http\Requests\Api;

use App\Rules\OperatingHoursRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:9', 'regex:/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3}([0-9A-Fa-f]{2})?)?$/'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'url', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.instagram' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.twitter' => ['nullable', 'string', 'url', 'max:500'],
            'social_links.linkedin' => ['nullable', 'string', 'url', 'max:500'],
            'default_locale' => ['nullable', 'string', 'max:10', Rule::in(config('locales.supported', ['en', 'nl', 'ru']))],
            'operating_hours' => ['nullable', 'array', new OperatingHoursRule()],
            'template' => ['nullable', 'string', 'max:50', Rule::in(config('templates.ids', \App\Models\Restaurant::TEMPLATES))],
            'year_established' => ['nullable', 'integer', 'min:1800', 'max:' . ((int) date('Y') + 1)],
        ];
    }
}
