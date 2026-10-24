<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConcessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Vui lòng nhập tên combo.', 'price.required' => 'Vui lòng nhập giá combo.',
            'price.min' => 'Giá combo không được âm.', 'image.image' => 'Vui lòng tải ảnh hợp lệ.', 'image.max' => 'Ảnh không được vượt quá 5MB.'];
    }
}
