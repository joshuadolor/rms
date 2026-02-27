<?php

namespace App\Http\Requests\Api;

use App\Models\RestaurantContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('value') && $this->filled('number')) {
            $this->merge(['value' => $this->input('number')]);
        }
    }

    /**
     * Resolve type from request or existing contact (so value is validated against link/phone when only value is sent).
     */
    private function resolveTypeForValidation(): ?string
    {
        if ($this->filled('type')) {
            return $this->input('type');
        }
        $restaurantUuid = $this->route('restaurant');
        $contactUuid = $this->route('contact');
        if (! is_string($restaurantUuid) || ! is_string($contactUuid)) {
            return null;
        }
        $contact = RestaurantContact::query()
            ->where('uuid', $contactUuid)
            ->whereHas('restaurant', fn ($q) => $q->where('uuid', $restaurantUuid))
            ->first();

        return $contact?->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->resolveTypeForValidation();
        $isLinkType = $type && RestaurantContact::isLinkType($type);

        $valueRules = ['sometimes', 'string', 'max:500'];
        if ($isLinkType) {
            $valueRules[] = 'url';
        }

        return [
            'type' => ['sometimes', 'string', 'max:50', Rule::in(RestaurantContact::TYPES)],
            'value' => $valueRules,
            'number' => ['nullable', 'string', 'max:500'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
