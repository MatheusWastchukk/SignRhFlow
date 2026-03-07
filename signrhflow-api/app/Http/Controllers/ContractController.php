<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Jobs\SendContractToAutentique;
use App\Models\Contract;
use App\Services\ContractPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

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
        $data['status'] = $data['status'] ?? Contract::STATUS_DRAFT;
        $data['file_path'] = $data['file_path'] ?? 'contracts/pending.pdf';

        $contract = Contract::query()->create($data);
        $pdfPath = $contractPdfService->generateAndStore($contract);
        $contract->forceFill(['file_path' => $pdfPath])->save();
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
    public function pdf(Contract $contract): Response|JsonResponse
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
}
