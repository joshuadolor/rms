<?php

namespace App\Http\Requests\Api;

use App\Models\RestaurantContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Backward compat: accept "number" as value for phone-like entries
        if (! $this->filled('value') && $this->filled('number')) {
            $this->merge(['value' => $this->input('number')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $isLinkType = $type && RestaurantContact::isLinkType($type);

        return [
            'type' => ['required', 'string', 'max:50', Rule::in(RestaurantContact::TYPES)],
            'value' => $isLinkType
                ? ['required', 'string', 'max:500', 'url']
                : ['required', 'string', 'max:500'],
            'number' => ['nullable', 'string', 'max:500'], // accepted for backward compat, merged into value
            'label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
