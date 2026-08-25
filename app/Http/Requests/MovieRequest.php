<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $movie = $this->route('movie');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('movies')->ignore($movie?->id)],
            'original_title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'poster' => ['nullable', 'url', 'max:2048'],
            'trailer_url' => ['nullable', 'url', 'max:2048'],
            'genre' => ['required', 'string', 'max:150'],
            'director' => ['required', 'string', 'max:150'],
            'actors' => ['nullable', 'string', 'max:1000'],
            'duration' => ['required', 'integer', 'min:30', 'max:400'],
            'age_rating' => ['required', 'in:P,K,T13,T16,T18'],
            'release_date' => ['nullable', 'date'],
            'country' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:now_showing,upcoming,stopped'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
