<?php

namespace App\Http\Middleware;

use Closure;

class TrimStrings
{
    public function handle($request, Closure $next)
    {
        $input = $request->all();

        // Aplica trim em todos os valores que são strings
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        $request->merge($input);

        return $next($request);
    }
}
