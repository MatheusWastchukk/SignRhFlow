<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Rules\ValidCpf;
use App\Services\AutentiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SigningController extends Controller
{
    #[OA\Get(
        path: '/api/signing/{token}/context',
        operationId: 'signingContext',
        summary: 'Carrega contexto da assinatura por token',
        tags: ['Signing'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contexto da assinatura carregado',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'contract',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PENDING', 'SIGNED', 'REJECTED']),
                                new OA\Property(property: 'delivery_method', type: 'string', enum: ['EMAIL', 'WHATSAPP']),
                                new OA\Property(property: 'file_path', type: 'string'),
                                new OA\Property(property: 'pdf_url', type: 'string'),
                                new OA\Property(property: 'autentique_signing_url', type: 'string', nullable: true),
                                new OA\Property(property: 'signing_token_expires_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'signer_data_collected_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'signed_at', type: 'string', format: 'date-time', nullable: true),
                            ]
                        ),
                        new OA\Property(
                            property: 'employee',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', nullable: true),
                                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                                new OA\Property(property: 'cpf', type: 'string', nullable: true),
                                new OA\Property(property: 'phone', type: 'string', nullable: true),
                            ]
                        ),
                        new OA\Property(
                            property: 'signer',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', nullable: true),
                                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                                new OA\Property(property: 'cpf', type: 'string', nullable: true),
                                new OA\Property(property: 'phone', type: 'string', nullable: true),
                                new OA\Property(property: 'phone_country', type: 'string', enum: ['BR', 'US', 'PT'], nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Link de assinatura invalido'),
            new OA\Response(response: 410, description: 'Link de assinatura expirado'),
        ]
    )]
    public function context(string $token, AutentiqueService $autentiqueService): JsonResponse
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

        $signingUrl = $contract->autentique_signing_url;

        if (
            $signingUrl === null
            && is_string($contract->autentique_document_id)
            && $contract->autentique_document_id !== ''
        ) {
            $resolvedSigningUrl = $autentiqueService->resolveSigningUrlByDocumentId(
                $contract->autentique_document_id,
                $contract->employee?->email
            );
            if ($resolvedSigningUrl !== null) {
                $contract->forceFill([
                    'autentique_signing_url' => $resolvedSigningUrl,
                ])->save();
                $signingUrl = $resolvedSigningUrl;
            }
        }

        if (is_string($contract->autentique_document_id) && $contract->autentique_document_id !== '') {
            $autentiqueService->applyDocumentProgressToContract($contract);
            $contract->refresh();
        }

        return response()->json([
            'contract' => [
                'id' => $contract->id,
                'status' => $contract->status,
                'delivery_method' => $contract->delivery_method,
                'file_path' => $contract->file_path,
                'pdf_url' => route('contracts.pdf.inline', ['contract' => $contract->id]),
                'autentique_signing_url' => $signingUrl,
                'signing_token_expires_at' => $contract->signing_token_expires_at,
                'signer_data_collected_at' => $contract->signer_data_collected_at,
                'signed_at' => $contract->signed_at,
            ],
            'employee' => [
                'name' => $contract->employee?->name,
                'email' => $contract->employee?->email,
                'cpf' => $contract->employee?->cpf,
                'phone' => $contract->employee?->phone,
            ],
            'signer' => [
                'name' => $contract->signer_name,
                'email' => $contract->signer_email,
                'cpf' => $contract->signer_cpf,
                'phone' => $contract->signer_phone,
                'phone_country' => $contract->signer_phone_country,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/signing/{token}/signer-data',
        operationId: 'saveSignerData',
        summary: 'Salva dados do signatario',
        tags: ['Signing'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'cpf', 'phone', 'phone_country'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Joao Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao.silva@example.com'),
                    new OA\Property(property: 'cpf', type: 'string', example: '52998224725'),
                    new OA\Property(property: 'phone', type: 'string', example: '+5511999999999'),
                    new OA\Property(property: 'phone_country', type: 'string', enum: ['BR', 'US', 'PT']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Dados salvos'),
            new OA\Response(response: 404, description: 'Link de assinatura invalido'),
            new OA\Response(response: 410, description: 'Link de assinatura expirado'),
            new OA\Response(
                response: 422,
                description: 'Erro de validacao',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
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
            'cpf' => ['required', 'string', new ValidCpf],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'phone_country' => ['required', 'string', Rule::in(['BR', 'US', 'PT'])],
        ]);

        $contract->loadMissing('employee');
        $employee = $contract->employee;
        if ($employee === null) {
            return response()->json([
                'message' => 'Contrato sem colaborador vinculado.',
            ], 422);
        }

        $mismatches = $this->employeeDataMismatches($validated, $employee->toArray());
        if ($mismatches !== []) {
            $errorBag = [];
            foreach ($mismatches as $field => $message) {
                $errorBag[$field] = [$message];
            }

            return response()->json([
                'message' => 'Os dados informados nao conferem com o contrato.',
                'errors' => $errorBag,
            ], 422);
        }

        $contract->forceFill([
            'signer_name' => $validated['name'],
            'signer_email' => $validated['email'],
            'signer_cpf' => preg_replace('/\D/', '', (string) $validated['cpf']),
            'signer_phone' => $validated['phone'],
            'signer_phone_country' => $validated['phone_country'],
            'signer_data_collected_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Dados do signatario salvos com sucesso.',
        ]);
    }

    #[OA\Post(
        path: '/api/signing/{token}/sign',
        operationId: 'signContractByToken',
        summary: 'Confirma assinatura digitada',
        tags: ['Signing'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['signed_name'],
                properties: [
                    new OA\Property(property: 'signed_name', type: 'string', example: 'Joao Silva'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Assinatura confirmada'),
            new OA\Response(response: 404, description: 'Link de assinatura invalido'),
            new OA\Response(response: 410, description: 'Link de assinatura expirado'),
            new OA\Response(
                response: 422,
                description: 'Erro de validacao',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function sign(Request $request, string $token): JsonResponse
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
            'signed_name' => ['required', 'string', 'max:255'],
        ]);

        $contract->forceFill([
            'signer_name' => $validated['signed_name'],
        ])->save();

        return response()->json([
            'message' => 'Assinatura confirmada com sucesso.',
        ]);
    }

    #[OA\Post(
        path: '/api/signing/{token}/finalize',
        operationId: 'finalizeContractSigning',
        summary: 'Finaliza assinatura e define canal de recebimento',
        tags: ['Signing'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delivery_method'],
                properties: [
                    new OA\Property(property: 'signed_name', type: 'string', example: 'Joao Silva', nullable: true),
                    new OA\Property(property: 'delivery_method', type: 'string', enum: ['EMAIL', 'WHATSAPP']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Assinatura finalizada',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'signed_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'delivery_method', type: 'string', enum: ['EMAIL', 'WHATSAPP']),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Link de assinatura invalido'),
            new OA\Response(response: 410, description: 'Link de assinatura expirado'),
            new OA\Response(
                response: 422,
                description: 'Erro de validacao',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function finalize(Request $request, string $token, AutentiqueService $autentiqueService): JsonResponse
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
            'signed_name' => ['nullable', 'string', 'max:255'],
            'delivery_method' => ['required', 'string', Rule::in([Contract::DELIVERY_EMAIL, Contract::DELIVERY_WHATSAPP])],
        ]);

        $nextStatus = in_array($contract->status, [Contract::STATUS_SIGNED, Contract::STATUS_REJECTED], true)
            ? $contract->status
            : Contract::STATUS_PENDING;

        $contract->forceFill([
            'status' => $nextStatus,
            'signer_name' => $validated['signed_name'] ?? $contract->signer_name,
            'delivery_method' => $validated['delivery_method'],
        ])->save();

        if (is_string($contract->autentique_document_id) && $contract->autentique_document_id !== '') {
            $autentiqueService->applyDocumentProgressToContract($contract);
            $contract->refresh();
        }

        $message = $contract->status === Contract::STATUS_SIGNED
            ? 'Assinatura confirmada na Autentique. Contrato atualizado.'
            : ($contract->status === Contract::STATUS_REJECTED
                ? 'Documento marcado como recusado na Autentique.'
                : 'Solicitacao registrada. O status sera atualizado quando a Autentique concluir a assinatura ou via webhook.');

        return response()->json([
            'message' => $message,
            'signed_at' => $contract->signed_at,
            'delivery_method' => $contract->delivery_method,
            'status' => $contract->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signerData
     * @param  array<string, mixed>  $employeeData
     */
    private function employeeDataMismatches(array $signerData, array $employeeData): array
    {
        $sameName = $this->normalizeText((string) $signerData['name']) === $this->normalizeText((string) $employeeData['name']);
        $sameEmail = $this->normalizeText((string) $signerData['email']) === $this->normalizeText((string) $employeeData['email']);
        $sameCpf = preg_replace('/\D/', '', (string) $signerData['cpf']) === preg_replace('/\D/', '', (string) $employeeData['cpf']);
        $samePhone = $this->phoneMatches((string) $signerData['phone'], (string) $employeeData['phone']);

        $mismatches = [];
        if (! $sameName) {
            $mismatches['name'] = 'Nome nao confere com o colaborador do contrato.';
        }
        if (! $sameEmail) {
            $mismatches['email'] = 'Email nao confere com o colaborador do contrato.';
        }
        if (! $sameCpf) {
            $mismatches['cpf'] = 'CPF nao confere com o colaborador do contrato.';
        }
        if (! $samePhone) {
            $mismatches['phone'] = 'Telefone nao confere com o colaborador do contrato.';
        }

        return $mismatches;
    }

    private function normalizeText(string $value): string
    {
        $singleSpaced = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return mb_strtolower($singleSpaced);
    }

    private function phoneMatches(string $inputPhone, string $employeePhone): bool
    {
        $inputDigits = ltrim(preg_replace('/\D/', '', $inputPhone) ?? '', '0');
        $employeeDigits = ltrim(preg_replace('/\D/', '', $employeePhone) ?? '', '0');

        if ($inputDigits === '' || $employeeDigits === '') {
            return false;
        }

        if ($inputDigits === $employeeDigits) {
            return true;
        }

        return (strlen($inputDigits) >= 8 && str_ends_with($inputDigits, $employeeDigits))
            || (strlen($employeeDigits) >= 8 && str_ends_with($employeeDigits, $inputDigits));
    }
}
