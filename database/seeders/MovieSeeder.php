<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Movie;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $movies = [
            [
                'title' => 'Avengers: Endgame',
                'original_title' => 'Avengers: Endgame',
                'description' => 'Phim siêu anh hùng bom tấn của Marvel.',
                'poster' => 'https://image.tmdb.org/t/p/w500/or06FN3Dka5tukK1e9sl16pB3iy.jpg',
                'duration' => 181,
                'genre' => 'Hành động, Khoa học viễn tưởng',
                'director' => 'Anthony Russo, Joe Russo',
                'actors' => 'Robert Downey Jr., Chris Evans, Mark Ruffalo',
                'age_rating' => 'T13',
                'release_date' => now()->subDays(20)->toDateString(),
                'country' => 'Mỹ',
                'language' => 'Tiếng Anh',
                'status' => 'now_showing',
                'is_featured' => true,
            ],
            [
                'title' => 'Spider-Man: No Way Home',
                'original_title' => 'Spider-Man: No Way Home',
                'description' => 'Người Nhện đối đầu với kẻ thù từ đa vũ trụ.',
                'poster' => 'https://image.tmdb.org/t/p/w500/1g0dhYtq4irTY1GPXvft6k4YLjm.jpg',
                'duration' => 148,
                'genre' => 'Hành động, Phiêu lưu',
                'director' => 'Jon Watts',
                'actors' => 'Tom Holland, Zendaya, Benedict Cumberbatch',
                'age_rating' => 'T13',
                'release_date' => now()->subDays(10)->toDateString(),
                'country' => 'Mỹ',
                'language' => 'Tiếng Anh',
                'status' => 'now_showing',
                'is_featured' => true,
            ],
            [
                'title' => 'The Batman',
                'original_title' => 'The Batman',
                'description' => 'Batman khám phá tội ác của Gotham.',
                'poster' => 'https://image.tmdb.org/t/p/w500/74xTEgt7R36Fpooo50r9T25onhq.jpg',
                'duration' => 176,
                'genre' => 'Hành động, Trinh thám',
                'director' => 'Matt Reeves',
                'actors' => 'Robert Pattinson, Zoë Kravitz, Paul Dano',
                'age_rating' => 'T16',
                'release_date' => now()->subDays(5)->toDateString(),
                'country' => 'Mỹ',
                'language' => 'Tiếng Anh',
                'status' => 'now_showing',
                'is_featured' => false,
            ],
            [
                'title' => 'Fast & Furious 9',
                'original_title' => 'F9',
                'description' => 'Cuộc đua tốc độ đầy kịch tính.',
                'poster' => 'https://image.tmdb.org/t/p/w500/bOFaAXmWWXC3Rbv4u4uM9ZSzRXP.jpg',
                'duration' => 143,
                'genre' => 'Hành động',
                'director' => 'Justin Lin',
                'actors' => 'Vin Diesel, Michelle Rodriguez, Tyrese Gibson',
                'age_rating' => 'T16',
                'release_date' => now()->addDays(15)->toDateString(),
                'country' => 'Mỹ',
                'language' => 'Tiếng Anh',
                'status' => 'upcoming',
                'is_featured' => false,
            ],
            [
                'title' => 'Jurassic World: Dominion',
                'original_title' => 'Jurassic World Dominion',
                'description' => 'Thế giới khủng long đầy hiểm nguy.',
                'poster' => 'https://image.tmdb.org/t/p/w500/kAVRgw7GgK1CfYEJq8ME6EvRIgU.jpg',
                'duration' => 147,
                'genre' => 'Phiêu lưu, Khoa học viễn tưởng',
                'director' => 'Colin Trevorrow',
                'actors' => 'Chris Pratt, Bryce Dallas Howard, Laura Dern',
                'age_rating' => 'T13',
                'release_date' => now()->addDays(25)->toDateString(),
                'country' => 'Mỹ',
                'language' => 'Tiếng Anh',
                'status' => 'upcoming',
                'is_featured' => false,
            ],
        ];

        foreach ($movies as $movie) {
            $movie['slug'] = Str::slug($movie['title']);
            Movie::updateOrCreate(['slug' => $movie['slug']], $movie);
        }
    }
}
