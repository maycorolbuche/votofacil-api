<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Helpers\Cron;

class CronMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Cron::init();
        return $next($request);
    }
}
