<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_and_checkin_pages_do_not_load_cdns(): void
    {
        $responses = [$this->get('/')->assertOk(), $this->get('/login')->assertOk()];
        $responses[] = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/tickets/check-in')->assertOk();
        foreach ($responses as $response) {
            foreach (['cdn.jsdelivr.net', 'unpkg.com', 'fonts.googleapis.com', 'fonts.gstatic.com'] as $host) {
                $response->assertDontSee($host);
            }
        }
    }
}
