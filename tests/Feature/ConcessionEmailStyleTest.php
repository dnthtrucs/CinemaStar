<?php

namespace Tests\Feature;

use App\Models\Concession;
use App\Models\User;
use App\Notifications\PaymentReceiptNotification;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class ConcessionEmailStyleTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    public function test_receipt_combo_details_use_an_inline_styled_email_table_row(): void
    {
        [$showtime, $seats] = $this->showtimeWithSeats();
        $combo = Concession::create(['name' => 'Combo email riêng', 'price' => 45000, 'is_active' => true]);
        $booking = app(BookingService::class)->create(User::factory()->create(), $showtime, [$seats[0]->id], concessions: [$combo->id => 2]);

        $html = (new PaymentReceiptNotification($booking))->toMail($booking->user)->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $row = (new \DOMXPath($document))->query('//tr[td/i[normalize-space(.)="Concessions"]]')->item(0);

        $this->assertNotNull($row, 'The bilingual concessions label must be part of an email table row.');
        $fragment = $document->saveHTML($row);
        $this->assertStringContainsString('Bắp nước', $row->textContent);
        $this->assertStringContainsString('Combo email riêng', $row->textContent);
        $this->assertStringContainsString('× 2', $row->textContent);
        $this->assertStringContainsString('45.000', $row->textContent);
        $this->assertStringContainsString('90.000', $row->textContent);
        $this->assertStringContainsString('style=', $fragment);
        $this->assertStringNotContainsString('fw-bold', $fragment);
        $this->assertStringNotContainsString('list-unstyled', $fragment);
        $this->assertStringNotContainsString('class=', $fragment);
    }
}
