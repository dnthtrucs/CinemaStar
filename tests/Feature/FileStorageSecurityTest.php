<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesCinemaBookings;
use Tests\TestCase;

class FileStorageSecurityTest extends TestCase
{
    use CreatesCinemaBookings, RefreshDatabase;

    private string $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = storage_path('framework/testing/security-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($this->files);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->files);
        parent::tearDown();
    }

    public function test_home_page_roundtrips_its_allowlisted_models_through_file_cache(): void
    {
        $this->assertIsArray(config('cache.serializable_classes'));
        [$showtime] = $this->showtimeWithSeats();
        Banner::create(['title' => 'Ưu đãi kiểm thử', 'image_path' => 'test.jpg', 'is_active' => true, 'sort_order' => 0]);
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->files]);
        Cache::forgetDriver('file');
        $this->get('/')->assertOk()->assertSee($showtime->movie->title)->assertSee($showtime->room->cinema->name);
        $payload = Cache::get('home-page-data:v1');
        preg_match_all('/O:\d+:"([^"]+)"/', serialize($payload), $classes);
        $this->assertSame([], array_values(array_diff(array_unique($classes[1]), config('cache.serializable_classes'))));
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/from `(banners|movies|showtimes|cinemas|rooms)`/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });
        Cache::forgetDriver('file');
        $this->get('/')->assertOk()->assertSee($showtime->movie->title)->assertSee('Ưu đãi kiểm thử');
        $this->assertSame([], $queries, 'Second home request must read the file cache.');
    }

    private function nextRequestFromDisk(): void
    {
        $id = app('session.store')->getId();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        // Redirector giữ tham chiếu session: request mới phải nhận store vừa đọc từ file.
        $this->app->forgetInstance('redirect');
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $id);
    }

    public function test_json_file_sessions_keep_errors_old_input_flash_and_login_across_requests(): void
    {
        $this->assertSame('json', config('session.serialization'));
        config(['session.driver' => 'file', 'session.files' => $this->files]);
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $user = User::factory()->create(['password' => 'password']);
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertRedirect('/login');
        $this->nextRequestFromDisk();
        $this->get('/login')->assertOk()->assertSee('Vui lòng kiểm tra lại:')->assertSee('Email hoặc mật khẩu không chính xác.')->assertSee('value="'.$user->email.'"', false);

        Route::middleware('web')->get('/_session-test/flash', fn () => redirect('/')->with('success', 'Đã lưu kiểm thử')->with('error', 'Lỗi kiểm thử'));
        $this->nextRequestFromDisk();
        $this->get('/_session-test/flash')->assertRedirect('/');
        $this->assertSame('Đã lưu kiểm thử', json_decode(File::get($this->files.'/'.app('session.store')->getId()), true)['success'] ?? null);
        $this->nextRequestFromDisk();
        $this->get('/')->assertOk()->assertSee('Đã lưu kiểm thử')->assertSee('Lỗi kiểm thử');

        $this->nextRequestFromDisk();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->nextRequestFromDisk();
        $this->get('/profile')->assertOk()->assertSee($user->email);
        $this->assertAuthenticatedAs($user);
        $this->assertIsArray(json_decode(File::get($this->files.'/'.app('session.store')->getId()), true));
    }
}
