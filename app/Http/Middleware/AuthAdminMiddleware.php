<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Helpers\CheckStatus;

class AuthAdminMiddleware
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
        $status = CheckStatus::room();
        if (isset($status['error'])) {
            return response()->json(['error' => $status['error']], 500);
        }

        $request->merge(['auth' => $status]);

        return $next($request);
    }
}
