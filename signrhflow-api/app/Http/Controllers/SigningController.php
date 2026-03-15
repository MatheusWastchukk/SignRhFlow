<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Rules\ValidCpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'pdf_url' => route('contracts.pdf.inline', ['contract' => $contract->id]),
                'signing_token_expires_at' => $contract->signing_token_expires_at,
                'signer_data_collected_at' => $contract->signer_data_collected_at,
            ],
            'employee' => [
                'name' => $contract->employee?->name,
            ],
            'signer' => [
                'name' => $contract->signer_name,
                'email' => $contract->signer_email,
                'cpf' => $contract->signer_cpf,
            ],
        ]);
    }

    public function signerData(Request $request, string $token): JsonResponse
    {
        $contract = Contract::query()
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'cpf' => ['required', 'string', new ValidCpf()],
        ]);

        $contract->forceFill([
            'signer_name' => $validated['name'],
            'signer_email' => $validated['email'],
            'signer_cpf' => preg_replace('/\D/', '', (string) $validated['cpf']),
            'signer_data_collected_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Dados do signatario salvos com sucesso.',
        ]);
    }
}
