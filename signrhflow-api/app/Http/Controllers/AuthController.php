<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $login = (string) $validated['login'];
        $password = (string) $validated['password'];

        $user = User::query()
            ->where('email', $login)
            ->orWhere('name', $login)
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Credenciais invalidas.',
            ], 422);
        }

        $plainToken = Str::random(80);

        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Nao autenticado.',
            ], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenId = $request->attributes->get('api_token_id');

        if (is_int($tokenId) || ctype_digit((string) $tokenId)) {
            ApiToken::query()->whereKey((int) $tokenId)->delete();
        }

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
