<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Jobs\SendContractToAutentique;
use App\Models\Contract;
use App\Rules\ValidCpf;
use App\Services\ContractPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    #[OA\Get(
        path: '/api/contracts',
        operationId: 'listContracts',
        summary: 'Lista contratos',
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de contratos',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedContractsResponse')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $contracts = Contract::query()
            ->with('employee')
            ->latest()
            ->paginate(max(1, min($perPage, 100)));

        return response()->json($contracts);
    }

    #[OA\Post(
        path: '/api/contracts',
        operationId: 'storeContract',
        summary: 'Cria contrato',
        tags: ['Contracts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employee_id', 'delivery_method'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'delivery_method', type: 'string', enum: ['EMAIL', 'WHATSAPP']),
                    new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PENDING', 'SIGNED', 'REJECTED']),
                    new OA\Property(property: 'file_path', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Contrato criado',
                content: new OA\JsonContent(ref: '#/components/schemas/ContractResource')
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validacao',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function store(StoreContractRequest $request, ContractPdfService $contractPdfService): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? Contract::STATUS_PENDING;
        $data['file_path'] = $data['file_path'] ?? 'contracts/pending.pdf';

        $contract = Contract::query()->create($data);
        $pdfPath = $contractPdfService->generateAndStore($contract);
        $contract->forceFill([
            'file_path' => $pdfPath,
            'pdf_generated_at' => now(),
        ])->save();
        SendContractToAutentique::dispatch($contract->id);

        return response()->json($contract->load('employee'), 201);
    }

    #[OA\Get(
        path: '/api/contracts/{contract}',
        operationId: 'showContract',
        summary: 'Detalha contrato por ID',
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(
                name: 'contract',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contrato encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ContractResource')
            ),
            new OA\Response(response: 404, description: 'Contrato nao encontrado'),
        ]
    )]
    public function show(Contract $contract): JsonResponse
    {
        return response()->json($contract->load('employee'));
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $employee = $contract->employee()->first();

        if ($employee === null) {
            return response()->json([
                'message' => 'Contrato sem colaborador vinculado.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    Contract::STATUS_DRAFT,
                    Contract::STATUS_PENDING,
                    Contract::STATUS_SIGNED,
                    Contract::STATUS_REJECTED,
                ]),
            ],
            'delivery_method' => [
                'sometimes',
                'string',
                Rule::in([Contract::DELIVERY_EMAIL, Contract::DELIVERY_WHATSAPP]),
            ],
            'employee' => ['required', 'array'],
            'employee.name' => ['required', 'string', 'max:255'],
            'employee.email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employee->id),
            ],
            'employee.phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'employee.cpf' => [
                'required',
                'string',
                new ValidCpf,
                Rule::unique('employees', 'cpf')->ignore($employee->id),
            ],
        ]);

        DB::transaction(function () use ($validated, $contract, $employee): void {
            $employeePayload = $validated['employee'];
            $employeePayload['email'] = mb_strtolower(trim((string) $employeePayload['email']));
            $employeePayload['cpf'] = preg_replace('/\D/', '', (string) $employeePayload['cpf']);
            $employeePayload['phone'] = preg_replace('/(?!^\+)\D/', '', trim((string) $employeePayload['phone'])) ?? '';
            $employee->forceFill($employeePayload)->save();

            $contractPayload = [];
            if (array_key_exists('status', $validated)) {
                $contractPayload['status'] = $validated['status'];
            }
            if (array_key_exists('delivery_method', $validated)) {
                $contractPayload['delivery_method'] = $validated['delivery_method'];
            }
            if ($contractPayload !== []) {
                $contract->forceFill($contractPayload)->save();
            }
        });

        return response()->json($contract->fresh()->load('employee'));
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();

        return response()->json([
            'message' => 'Contrato excluido com sucesso.',
        ]);
    }

    #[OA\Get(
        path: '/api/contracts/{contract}/pdf',
        operationId: 'downloadContractPdf',
        summary: 'Baixa PDF do contrato',
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(
                name: 'contract',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Arquivo PDF do contrato'),
            new OA\Response(response: 404, description: 'PDF nao encontrado'),
        ]
    )]
    public function pdf(Contract $contract): BinaryFileResponse|JsonResponse
    {
        if (! Storage::disk('local')->exists($contract->file_path)) {
            return response()->json([
                'message' => 'PDF do contrato nao encontrado.',
            ], 404);
        }

        return response()->download(
            Storage::disk('local')->path($contract->file_path),
            sprintf('contrato-%s.pdf', $contract->id),
            ['Content-Type' => 'application/pdf']
        );
    }

    #[OA\Get(
        path: '/api/contracts/{contract}/pdf/inline',
        operationId: 'viewContractPdfInline',
        summary: 'Visualiza PDF do contrato inline',
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(
                name: 'contract',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Arquivo PDF inline'),
            new OA\Response(response: 404, description: 'PDF nao encontrado'),
        ]
    )]
    public function pdfInline(Contract $contract): BinaryFileResponse|JsonResponse
    {
        if (! Storage::disk('local')->exists($contract->file_path)) {
            return response()->json([
                'message' => 'PDF do contrato nao encontrado.',
            ], 404);
        }

        return response()->file(
            Storage::disk('local')->path($contract->file_path),
            ['Content-Type' => 'application/pdf']
        );
    }
}
