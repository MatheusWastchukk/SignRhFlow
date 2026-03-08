<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\JsonResponse;

class SigningController extends Controller
{
    public function context(string $token): JsonResponse
    {
        $contract = Contract::query()
            ->with('employee')
            ->where('signing_token', $token)
            ->first();

        if ($contract === null) {
            return response()->json([
                'message' => 'Link de assinatura invalido.',
            ], 404);
        }

        if ($contract->signing_token_expires_at !== null && $contract->signing_token_expires_at->isPast()) {
            return response()->json([
                'message' => 'Link de assinatura expirado.',
            ], 410);
        }

        return response()->json([
            'contract' => [
                'id' => $contract->id,
                'status' => $contract->status,
                'delivery_method' => $contract->delivery_method,
                'file_path' => $contract->file_path,
                'pdf_url' => route('contracts.pdf', ['contract' => $contract->id]),
                'signing_token_expires_at' => $contract->signing_token_expires_at,
            ],
            'employee' => [
                'name' => $contract->employee?->name,
            ],
        ]);
    }
}
