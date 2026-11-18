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

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'required_if' => 'Vui lòng nhập :attribute khi bật tự động theo ngày.',
            'string' => ':attribute phải là văn bản.',
            'description.min' => ':attribute phải có ít nhất :min ký tự.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'url' => ':attribute phải là đường dẫn đầy đủ, bắt đầu bằng http:// hoặc https:// (không phải tên phim hay đường dẫn trên máy).',
            'integer' => ':attribute phải là số nguyên.',
            'duration.min' => ':attribute phải từ :min trở lên.',
            'duration.max' => ':attribute không được vượt quá :max.',
            'date' => ':attribute phải là ngày hợp lệ.',
            'after_or_equal' => ':attribute phải bằng hoặc sau ngày khởi chiếu.',
            'unique' => ':attribute đã được sử dụng, vui lòng chọn giá trị khác.',
            'in' => ':attribute không hợp lệ.',
            'boolean' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Tên phim', 'slug' => 'Slug', 'original_title' => 'Tên gốc',
            'description' => 'Mô tả', 'poster' => 'URL poster', 'trailer_url' => 'URL trailer',
            'genre' => 'Thể loại', 'director' => 'Đạo diễn', 'actors' => 'Diễn viên',
            'duration' => 'Thời lượng', 'age_rating' => 'Phân loại tuổi',
            'release_date' => 'Ngày khởi chiếu', 'end_date' => 'Ngày kết thúc chiếu',
            'country' => 'Quốc gia', 'language' => 'Ngôn ngữ', 'status' => 'Trạng thái',
            'auto_schedule' => 'Tự động theo ngày', 'is_featured' => 'Phim nổi bật',
        ];
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
            'duration' => ['nullable', 'integer', 'min:30', 'max:400'],
            'age_rating' => ['required', 'in:NO,P,K,T13,T16,T18'],
            'release_date' => ['nullable', 'required_if:auto_schedule,1', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
            'auto_schedule' => ['nullable', 'boolean'],
            'country' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:now_showing,upcoming,stopped'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
