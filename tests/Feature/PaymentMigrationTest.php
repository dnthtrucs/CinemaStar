<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class PaymentMigrationTest extends TestCase
{
    use CreatesCinemaBookings, DatabaseMigrations;

    public function test_payment_migrations_preserve_existing_rows_and_reject_ambiguous_transactions(): void
    {
        $restrict = require database_path('migrations/2026_10_23_000001_restrict_payment_booking_deletion.php');
        $unique = require database_path('migrations/2026_10_23_000002_add_unique_provider_transaction_to_payments.php');
        $unique->down();
        $restrict->down();
        $this->assertSame('cascade', collect(Schema::getForeignKeys('payments'))->firstWhere('name', 'payments_booking_id_foreign')['on_delete']);

        $booking = $this->bookingFor();
        $data = ['booking_id' => $booking->id, 'provider' => 'sepay', 'amount' => 80000, 'status' => 'pending'];
        Payment::create($data + ['request_id' => 'EXISTING-A', 'transaction_id' => 'BANK-A']);
        $duplicate = Payment::create($data + ['request_id' => 'EXISTING-B', 'transaction_id' => 'BANK-A']);
        Payment::create($data + ['request_id' => 'NULL-A']);
        Payment::create($data + ['request_id' => 'NULL-B']);
        Payment::create(array_replace($data, ['provider' => 'vnpay', 'request_id' => 'OTHER-PROVIDER', 'transaction_id' => 'BANK-A']));
        $before = Payment::orderBy('id')->get()->toArray();
        $restrict->up();
        try {
            $unique->up();
            $this->fail('Duplicate bank transactions must stop migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate (provider, transaction_id)', $exception->getMessage());
            $this->assertStringContainsString('sepay / BANK-A', $exception->getMessage());
        }
        $this->assertSame($before, Payment::orderBy('id')->get()->toArray());
        $this->assertFalse(Schema::hasIndex('payments', 'payments_provider_transaction_unique'));

        // Reconcile only this synthetic fixture, as an operator must do before retrying a deploy.
        $duplicate->update(['transaction_id' => 'BANK-B']);
        $before = Payment::orderBy('id')->get()->toArray();
        $unique->up();
        $this->assertSame($before, Payment::orderBy('id')->get()->toArray());
        $this->assertTrue(Schema::hasIndex('payments', 'payments_provider_transaction_unique'));
        $this->assertSame('restrict', collect(Schema::getForeignKeys('payments'))->firstWhere('name', 'payments_booking_id_foreign')['on_delete']);
    }
}
