<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cinema;
use Illuminate\Support\Str;

class CinemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $cinemas = [
            ['name' => 'Cinema Machinco', 'location' => 'Trần Phú, Mộ Lao, Hà Đông, Hà Nội', 'city' => 'Hà Nội'],
            ['name' => 'Cinema Vincom Bà Triệu', 'location' => '191 Bà Triệu, Hai Bà Trưng, Hà Nội', 'city' => 'Hà Nội'],
            ['name' => 'Cinema Aeon Mall Long Biên', 'location' => '27 Cổ Linh, Long Biên, Hà Nội', 'city' => 'Hà Nội'],
        ];

        foreach ($cinemas as $cinema) {
            $cinema['slug'] = Str::slug($cinema['name']);
            $cinema['phone'] = '1900 0000';
            $cinema['description'] = 'Cụm rạp hiện đại, âm thanh sống động và dịch vụ chuyên nghiệp.';
            $cinema['is_active'] = true;
            Cinema::updateOrCreate(['slug' => $cinema['slug']], $cinema);
        }
    }
}
