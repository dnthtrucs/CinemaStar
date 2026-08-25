<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Cinema::all() as $cinema) {
            foreach (['Phòng 01' => '2D', 'Phòng 02' => '3D'] as $name => $type) {
                $room = Room::updateOrCreate(
                    ['cinema_id' => $cinema->id, 'name' => $name],
                    ['type' => $type, 'rows' => 8, 'seats_per_row' => 10, 'total_seats' => 80, 'is_active' => true]
                );

                if ($room->seats()->doesntExist()) {
                    for ($rowIndex = 0; $rowIndex < 8; $rowIndex++) {
                        for ($number = 1; $number <= 10; $number++) {
                            $isVip = $rowIndex >= 6;
                            $room->seats()->create([
                                'row' => chr(65 + $rowIndex),
                                'number' => $number,
                                'type' => $isVip ? 'vip' : 'standard',
                                'price_surcharge' => $isVip ? 30000 : 0,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
