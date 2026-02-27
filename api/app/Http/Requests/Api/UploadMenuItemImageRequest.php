<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadMenuItemImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same validation as restaurant logo: image only, jpeg/png/gif/webp, max 2MB.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = 2048; // 2MB

        return [
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:' . $maxKb],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('The image must not be greater than 2MB.'),
        ];
    }
}
