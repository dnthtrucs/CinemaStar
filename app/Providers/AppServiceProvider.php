<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
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

        Carbon::setLocale('vi');
        Paginator::useBootstrapFive();
    }
}
