<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Concession;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ApplicationPagesTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_public_customer_and_payment_pages_render_with_real_relations(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $customer = User::factory()->create();
        $service = app(BookingService::class);
        $paid = $service->create($customer, $showtime, [$seats[0]->id]);
        $paid->update(['status' => 'confirmed', 'payment_status' => 'paid', 'paid_at' => now()]);
        $pending = $service->create($customer, $showtime, [$seats[1]->id]);
        $payment = Payment::create([
            'booking_id' => $pending->id, 'provider' => 'momo', 'request_id' => (string) Str::uuid(),
            'amount' => $pending->total_price, 'status' => 'pending',
        ]);
        foreach (['home' => [], 'movies.index' => [], 'movies.show' => [$showtime->movie],
            'cinemas.index' => [], 'cinemas.show' => [$showtime->room->cinema],
            'showtimes.show' => [$showtime], 'support.index' => [], 'login' => [], 'register' => [], 'password.request' => []] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk();
        }
        $this->get($paid->verificationUrl())->assertOk();
        $this->actingAs($customer);
        foreach (['dashboard' => [], 'profile.edit' => [], 'bookings.index' => [], 'bookings.show' => [$paid],
            'bookings.print' => [$paid], 'refunds.create' => [$paid], 'payments.show' => [$pending],
            'payments.simulate' => [$payment]] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk();
        }
        config(['cinema.sepay.bank_code' => 'TESTBANK', 'cinema.sepay.account_number' => '123456789', 'cinema.sepay.api_key' => 'test-key']);
        $payment->update(['provider' => 'sepay']);
        $this->get(route('payments.sepay.show', $payment))->assertOk();
        $this->getJson(route('payments.sepay.status', $payment))->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_admin_and_staff_management_pages_render_with_nonempty_data(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id]);
        $banner = Banner::create(['title' => 'Banner test', 'image_path' => 'test.jpg', 'is_active' => true]);
        $combo = Concession::create(['name' => 'Combo test', 'price' => 25000, 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['dashboard', 'activity-logs.index', 'users.index', 'bookings.index', 'vouchers.index',
            'refunds.index', 'manual-refunds.index', 'reviews.index', 'tickets.index', 'showtimes.bulk.create',
            'reports.excel', 'reports.pdf'] as $page) {
            $this->get(route('admin.'.$page))->assertOk();
        }
        foreach (['movies' => $showtime->movie, 'cinemas' => $showtime->room->cinema, 'rooms' => $showtime->room,
            'showtimes' => $showtime, 'banners' => $banner, 'concessions' => $combo] as $resource => $model) {
            $this->get(route('admin.'.$resource.'.index'))->assertOk();
            $this->get(route('admin.'.$resource.'.create'))->assertOk();
            $this->get(route('admin.'.$resource.'.edit', $model))->assertOk();
        }
        $this->get(route('admin.bookings.show', $booking))->assertOk();
        $this->get(route('admin.tickets.index', ['code' => $booking->code]))->assertOk()->assertSee($booking->code);
        $this->get(route('admin.reports.revenue'))->assertOk()->assertDownload('doanh-thu.csv');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('staff.tickets.index', ['code' => $booking->code]))->assertOk()->assertSee($booking->code);
        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
