<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMetricsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('signrhflow.metrics_token');

        if ($expected === '') {
            abort(404);
        }

        $token = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($expected, $token)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
