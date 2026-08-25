<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Create or reset the demo accounts without changing booking data.
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

        User::updateOrCreate(['email' => 'staff@cinema.test'], [
            'name' => 'Nhân viên CinemaStar',
            'phone' => '0900000003',
            'password' => Hash::make('Staff@123'),
            'role' => 'staff',
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
    }
}
