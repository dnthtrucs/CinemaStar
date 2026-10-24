<?php

namespace Database\Seeders;

use App\Models\Concession;
use Illuminate\Database\Seeder;

class ConcessionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Bắp ngọt', 45000, 'Một phần bắp ngọt thơm giòn.'], ['Nước ngọt', 30000, 'Một ly nước ngọt mát lạnh.'],
            ['Combo một người', 65000, 'Một bắp và một nước.'], ['Combo hai người', 110000, 'Một bắp lớn và hai nước.']] as $index => [$name, $price, $description]) {
            Concession::firstOrCreate(['name' => $name], ['price' => $price, 'description' => $description, 'is_active' => true, 'sort_order' => $index]);
        }
    }
}
