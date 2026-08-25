<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'movie_id' => ['required', 'exists:movies,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'base_price' => ['required', 'integer', 'min:10000', 'max:2000000'],
            'format' => ['required', 'in:2D,3D,IMAX'],
            'language' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:scheduled,cancelled'],
        ];
    }
}
