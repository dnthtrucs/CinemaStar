<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public const GENRES = ['Hành động', 'Hài', 'Tình cảm', 'Kinh dị', 'Hoạt hình', 'Khoa học viễn tưởng', 'Phiêu lưu', 'Tâm lý', 'Gia đình'];

    public function update(Request $request)
    {
        $data = $request->validate(['genres' => ['sometimes', 'array', 'max:9'], 'genres.*' => ['string', 'distinct', Rule::in(self::GENRES)]]);
        $request->user()->update(['favorite_genres' => $data['genres'] ?? []]);

        return back()->with('success', 'Đã lưu sở thích xem phim.');
    }
}
