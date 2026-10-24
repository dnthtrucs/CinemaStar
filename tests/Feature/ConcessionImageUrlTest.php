<?php

namespace Tests\Feature;

use App\Models\Concession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionImageUrlTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_customer_and_admin_images_use_the_public_disk_url_configuration(): void
    {
        config(['filesystems.disks.public.url' => 'https://images.example.test/cinema-public']);
        Storage::forgetDisk('public');
        [$showtime] = $this->showtimeWithSeats();
        $combo = Concession::create(['name' => 'Combo ảnh', 'price' => 45000, 'image_path' => 'concessions/popcorn.jpg', 'is_active' => true]);
        $url = 'https://images.example.test/cinema-public/concessions/popcorn.jpg';

        $this->get(route('showtimes.show', $showtime))->assertOk()->assertSee('src="'.$url.'"', false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.concessions.edit', $combo))->assertOk()->assertSee('src="'.$url.'"', false);
        $this->assertSame($url, $combo->imageUrl());
        $this->assertNull((new Concession)->imageUrl());
    }
}
