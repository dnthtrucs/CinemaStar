<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'cinema_id' => ['required', 'exists:cinemas,id'],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('rooms')->where(fn ($query) => $query->where('cinema_id', $this->integer('cinema_id')))->ignore($room?->id),
            ],
            'type' => ['required', 'in:2D,3D,IMAX'],
            'rows' => ['required', 'integer', 'min:3', 'max:20'],
            'seats_per_row' => ['required', 'integer', 'min:5', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
