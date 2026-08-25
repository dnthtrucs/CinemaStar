<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@cinema.test'], [
            'name' => 'Quản trị viên',
            'phone' => '0900000001',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'customer@cinema.test'], [
            'name' => 'Khách hàng Demo',
            'phone' => '0900000002',
            'password' => Hash::make('User@123'),
            'role' => 'customer',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->call([
            MovieSeeder::class,
            CinemaSeeder::class,
            RoomSeeder::class,
            ShowtimeSeeder::class,
        ]);
    }
}
