<?php

namespace Tests\Feature;

use App\Http\Requests\MovieRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MovieValidationTest extends TestCase
{
    public function test_invalid_description_and_links_have_readable_vietnamese_errors(): void
    {
        $request = new MovieRequest;
        $data = [
            'title' => 'Phim mới', 'description' => 'Ngắn', 'poster' => 'poster.jpg',
            'trailer_url' => 'trailer', 'genre' => 'Drama', 'director' => 'Test',
            'duration' => 120, 'age_rating' => 'P', 'country' => 'VN',
            'language' => 'VN', 'status' => 'upcoming', 'auto_schedule' => 0,
        ];
        $validate = fn ($values) => Validator::make($values, $request->rules(), $request->messages(), $request->attributes());
        $errors = $validate($data)->errors();
        $this->assertSame('Mô tả phải có ít nhất 20 ký tự.', $errors->first('description'));
        $this->assertStringContainsString('URL poster phải là đường dẫn đầy đủ', $errors->first('poster'));
        $this->assertStringContainsString('URL trailer phải là đường dẫn đầy đủ', $errors->first('trailer_url'));
        $data['description'] = 'Đây là mô tả phim đầy đủ để thêm phim mới.';
        $data['poster'] = '';
        $data['trailer_url'] = 'https://www.youtube.com/watch?v=example';
        $this->assertFalse($validate($data)->fails());
    }
}
