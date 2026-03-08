<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return $this->unauthorizedResponse();
        }

        $token = ApiToken::query()
            ->where('token_hash', hash('sha256', $bearerToken))
            ->with('user')
            ->first();

        if ($token === null || $token->user === null) {
            return $this->unauthorizedResponse();
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return $this->unauthorizedResponse();
        }

        $token->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($token->user);
        $request->attributes->set('api_token_id', $token->id);

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Nao autenticado.',
        ], 401);
    }
}
