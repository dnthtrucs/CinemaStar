<?php

namespace App\Http\Middleware;

use App\Services\MovieScheduleService;
use Closure;
use Illuminate\Http\Request;

class SyncMovieSchedule
{
    public function handle(Request $request, Closure $next)
    {
        app(MovieScheduleService::class)->sync();

        return $next($request);
    }
}
