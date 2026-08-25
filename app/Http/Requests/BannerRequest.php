<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:220'],
            'image' => [
                Rule::requiredIf(! $this->route('banner')),
                'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:min_width=1000,min_height=300',
            ],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng tải ảnh banner.',
            'image.dimensions' => 'Ảnh banner cần rộng ít nhất 1000px và cao ít nhất 300px.',
            'image.max' => 'Ảnh banner không được vượt quá 5MB.',
        ];
    }
}
