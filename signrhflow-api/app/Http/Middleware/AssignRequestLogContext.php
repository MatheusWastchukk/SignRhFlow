<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::shareContext([
            'request_id' => Str::uuid()->toString(),
            'http_method' => $request->method(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
