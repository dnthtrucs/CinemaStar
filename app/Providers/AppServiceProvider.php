<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\Cinema;
use App\Models\Concession;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\Voucher;
use App\Observers\CatalogAuditObserver;
use App\Services\GmailApiService;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        Mail::extend('gmail_api', fn () => new GmailApiTransport(app(GmailApiService::class)));

        foreach ([Movie::class, Cinema::class, Room::class, Showtime::class, Voucher::class, Concession::class] as $model) {
            $model::observe(CatalogAuditObserver::class);
        }
        Carbon::setLocale('vi');
        Paginator::useBootstrapFive();
    }
}
