<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class DashboardExportTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_dashboard_download_link_uses_its_thirty_day_vietnam_period(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-10-24 00:30:00', 'Asia/Ho_Chi_Minh'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.reports.revenue', ['from' => '2026-09-25', 'to' => '2026-10-24']));
    }

    public static function storageTimezones(): array
    {
        return ['UTC' => ['UTC'], 'Vietnam' => ['Asia/Ho_Chi_Minh']];
    }

    #[DataProvider('storageTimezones')]
    public function test_dashboard_csv_uses_the_same_period_and_successful_paid_revenue_in_each_storage_timezone(string $timezone): void
    {
        config(['app.timezone' => $timezone]);
        $at = CarbonImmutable::parse('2026-10-24 00:30:00', 'Asia/Ho_Chi_Minh');
        $this->travelTo($at);
        [$showtime] = $this->showtimeWithSeats();
        $customer = User::factory()->create();
        $start = $at->startOfDay()->subDays(29);
        $included = [
            $this->receipt($showtime, $customer, 10000, $start),
            $this->receipt($showtime, $customer, 20000, $at->startOfDay()->subSecond()),
            $this->receipt($showtime, $customer, 30000, $at->startOfDay()),
            $this->receipt($showtime, $customer, 40000, $at),
        ];
        $excluded = [
            $this->receipt($showtime, $customer, 900000, $start->subSecond()),
            $this->receipt($showtime, $customer, 900000, $at->addSecond()),
            $this->receipt($showtime, $customer, 900000, $at, 'refunded'),
            $this->receipt($showtime, $customer, 900000, $at, 'unpaid'),
            $this->receipt($showtime, $customer, 900000, $at, paymentStatus: 'failed'),
            $this->receipt($showtime, $customer, 900000, $at, paymentStatus: 'pending'),
        ];
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.reports.revenue', ['from' => '2026-09-25', 'to' => '2026-10-24']))
            ->assertOk()->assertDownload('doanh-thu.csv');
        $rows = $this->csvRows($response->streamedContent());
        $codes = array_column($rows, 0);

        $this->assertCount(4, $rows);
        foreach ($included as $booking) {
            $this->assertContains($booking->code, $codes);
        }
        foreach ($excluded as $booking) {
            $this->assertNotContains($booking->code, $codes);
        }
        $this->assertSame(100000, array_sum(array_map('intval', array_column($rows, 3))));
        $this->assertSame(array_sum(array_column(app(ReportService::class)->dailyRevenue($at), 'revenue')),
            array_sum(array_map('intval', array_column($rows, 3))));
    }

    public function test_report_without_date_filters_keeps_the_existing_one_month_default(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $at = CarbonImmutable::parse('2026-10-24 00:30:00', 'Asia/Ho_Chi_Minh');
        $this->travelTo($at);
        [$showtime] = $this->showtimeWithSeats();
        $customer = User::factory()->create();
        $included = $this->receipt($showtime, $customer, 10000, $at->subMonth()->startOfDay());
        $excluded = $this->receipt($showtime, $customer, 900000, $at->subMonth()->startOfDay()->subSecond());
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.reports.revenue'))->assertOk();
        $rows = $this->csvRows($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertSame($included->code, $rows[0][0]);
        $this->assertNotContains($excluded->code, array_column($rows, 0));
    }

    public function test_report_rejects_invalid_or_reversed_calendar_dates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('admin.reports.revenue', ['from' => 'bad-date', 'to' => '2026-13-50']))
            ->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('admin.reports.revenue', ['from' => '2026-10-24', 'to' => '2026-10-23']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    private function receipt(Showtime $showtime, User $customer, int $amount, CarbonImmutable $at, string $bookingStatus = 'paid', string $paymentStatus = 'success'): Booking
    {
        $booking = Booking::create([
            'user_id' => $customer->id, 'showtime_id' => $showtime->id,
            'code' => 'BK'.Str::upper(Str::random(16)), 'quantity' => 1,
            'subtotal' => $amount, 'discount' => 0, 'total_price' => $amount,
            'status' => $bookingStatus === 'unpaid' ? 'pending' : 'confirmed', 'payment_status' => $bookingStatus,
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'provider' => 'sepay', 'request_id' => (string) Str::uuid(),
            'transaction_id' => (string) Str::uuid(), 'amount' => $amount, 'status' => $paymentStatus,
            'paid_at' => $at->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        return $booking;
    }

    private function csvRows(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        fgetcsv($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
