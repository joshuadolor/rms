<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerFeedbackRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:65535'],
            'title' => ['nullable', 'string', 'max:255'],
            'restaurant' => ['nullable', 'string', 'uuid', Rule::exists('restaurants', 'uuid')],
        ];
    }
}
