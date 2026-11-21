<?php

namespace Tests\Feature;

use App\Mail\GmailApiTransport;
use App\Models\GmailConnection;
use App\Models\User;
use App\Services\GmailApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class GmailApiTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret', 'services.gmail.redirect_uri' => 'https://cinemastar.example/admin/email/gmail/callback']);
        Http::preventStrayRequests();
    }

    private function connected(): GmailConnection
    {
        return GmailConnection::create(['provider' => 'gmail', 'email' => 'sender@gmail.com', 'client_id' => 'test-client', 'refresh_token' => 'private-refresh']);
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'private-access', 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => 'private-refresh', 'scope' => 'openid email '.GmailApiService::SEND_SCOPE]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'sender@gmail.com', 'email_verified' => true]),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-message-id']),
        ]);
    }

    public function test_only_admin_can_start_or_view_gmail_connection(): void
    {
        $this->get(route('admin.email.index'))->assertRedirect(route('login'));
        foreach (['customer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.email.index'))->assertForbidden();
            $this->post(route('admin.email.gmail.connect'))->assertForbidden();
            $this->get(route('admin.email.gmail.callback'))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_authorization_requests_send_scope_and_pkce_without_client_secret(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->post(route('admin.email.gmail.connect'))->assertRedirect();
        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('openid email '.GmailApiService::SEND_SCOPE, $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertArrayNotHasKey('client_secret', $query);
        $stored = session('gmail_oauth');
        $this->assertSame(hash('sha256', $query['state']), $stored['state']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $stored['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
    }

    public function test_bad_expired_or_wrong_user_state_never_reaches_google(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([['state' => hash('sha256', 'correct'), 'user_id' => $admin->id, 'expires' => time() + 60], ['state' => hash('sha256', 'wrong'), 'user_id' => $admin->id, 'expires' => time() - 1], ['state' => hash('sha256', 'wrong'), 'user_id' => $admin->id + 1, 'expires' => time() + 60]] as $state) {
            $this->actingAs($admin)->withSession(['gmail_oauth' => $state])->get(route('admin.email.gmail.callback', ['state' => 'wrong', 'code' => 'not-real']))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_callback_encrypts_refresh_token_and_cannot_be_replayed(): void
    {
        $this->fakeGoogle();
        $admin = User::factory()->create(['role' => 'admin']);
        $url = route('admin.email.gmail.callback', ['state' => 'one-time', 'code' => 'auth-code']);
        $this->actingAs($admin)->withSession(['gmail_oauth' => ['state' => hash('sha256', 'one-time'), 'verifier' => str_repeat('v', 64), 'user_id' => $admin->id, 'expires' => time() + 60]])
            ->get($url)->assertRedirect(route('admin.email.index'))->assertSessionHas('success');
        $connection = GmailConnection::firstOrFail();
        $this->assertSame('private-refresh', $connection->refresh_token);
        $this->assertNotSame('private-refresh', DB::table('gmail_connections')->value('refresh_token'));
        $this->assertArrayNotHasKey('refresh_token', $connection->toArray());
        $this->get($url)->assertForbidden();
        Http::assertSentCount(2);
        $this->get(route('admin.email.index'))->assertOk()->assertDontSee('private-refresh')->assertDontSee('test-secret');
    }

    public function test_missing_send_scope_does_not_replace_existing_connection(): void
    {
        $this->connected();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'token_type' => 'Bearer', 'refresh_token' => 'new-refresh', 'scope' => 'openid email'])]);
        try {
            app(GmailApiService::class)->connect('code', str_repeat('v', 64), User::factory()->create()->id);
            $this->fail('Missing send permission must be rejected.');
        } catch (RuntimeException) {
            $this->assertSame('private-refresh', GmailConnection::firstOrFail()->refresh_token);
        }
        Http::assertSentCount(1);
    }

    public function test_transport_preserves_mime_qr_and_uses_the_authorized_sender(): void
    {
        $this->fakeGoogle();
        $this->connected();
        $email = (new Email)->from('wrong@example.com')->to('customer@example.com')->bcc('archive@example.com')->subject('CinemaStar receipt')->html('<p>Ticket QR</p><img src="cid:qr">')->embed('qr-binary', 'qr', 'image/png');
        $sent = (new GmailApiTransport(app(GmailApiService::class)))->send($email);
        $this->assertSame('gmail-message-id', $sent->getMessageId());
        $this->assertSame('sender@gmail.com', $sent->getEnvelope()->getSender()->getAddress());
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));
            $this->assertStringContainsString('sender@gmail.com', $mime);
            $this->assertStringNotContainsString('wrong@example.com', $mime);
            $this->assertStringContainsString('image/png', $mime);
            $this->assertStringContainsString('Content-ID:', $mime);
            $this->assertStringContainsString('Bcc: archive@example.com', $mime);

            return $request->hasHeader('Authorization', 'Bearer private-access');
        });
        $cached = Cache::get('gmail-api-access:'.hash('sha256', 'test-client|private-refresh'));
        $this->assertIsString($cached);
        $this->assertNotSame('private-access', $cached);
    }

    public function test_failed_refresh_exposes_no_google_response_or_secrets(): void
    {
        $connection = $this->connected();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private-response'], 400)]);
        try {
            app(GmailApiService::class)->send('mime', $connection);
            $this->fail('The request must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        Http::assertSentCount(1);
    }

    public function test_unauthorized_send_is_not_retried_and_cached_access_is_cleared(): void
    {
        $connection = $this->connected();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'token_type' => 'Bearer', 'expires_in' => 3600]), 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['error' => ['message' => 'private-response']], 401)]);
        try {
            app(GmailApiService::class)->send('mime', $connection);
            $this->fail('The request must fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private-response', $exception->getMessage());
        }
        Http::assertSentCount(2);
        $this->assertNull(Cache::get('gmail-api-access:'.hash('sha256', 'test-client|private-refresh')));
    }

    public function test_laravel_paid_receipt_is_sent_through_registered_gmail_transport(): void
    {
        $this->fakeGoogle();
        $this->connected();
        config(['mail.default' => 'gmail_api']);
        Mail::purge();
        $booking = $this->bookingFor();
        $booking->update(['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.bookings.receipt-email', $booking))
            ->assertRedirect()->assertSessionHas('success');
        Http::assertSent(function ($request) use ($booking) {
            if (! str_ends_with($request->url(), '/messages/send')) {
                return false;
            }
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));

            return str_contains($mime, 'image/png')
                && str_contains($mime, $booking->code)
                && str_contains($mime, 'multipart/alternative')
                && str_contains($mime, 'text/plain')
                && str_contains($mime, 'text/html');
        });
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }
}
