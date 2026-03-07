<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmployeeController extends Controller
{
    #[OA\Get(
        path: '/api/employees',
        operationId: 'listEmployees',
        summary: 'Lista colaboradores',
        tags: ['Employees'],
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
                description: 'Lista paginada de colaboradores',
                content: new OA\JsonContent(ref: '#/components/schemas/PaginatedEmployeesResponse')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $employees = Employee::query()->latest()->paginate(max(1, min($perPage, 100)));

        return response()->json($employees);
    }

    #[OA\Post(
        path: '/api/employees',
        operationId: 'storeEmployee',
        summary: 'Cria colaborador',
        tags: ['Employees'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone', 'cpf'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Joao Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao.silva@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '(11) 99999-9999'),
                    new OA\Property(property: 'cpf', type: 'string', example: '52998224725'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Colaborador criado',
                content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResource')
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validacao',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
        ]
    )]
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::query()->create($request->validated());

        return response()->json($employee, 201);
    }

    #[OA\Get(
        path: '/api/employees/{employee}',
        operationId: 'showEmployee',
        summary: 'Detalha colaborador por ID',
        tags: ['Employees'],
        parameters: [
            new OA\Parameter(
                name: 'employee',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Colaborador encontrado',
                content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResource')
            ),
            new OA\Response(response: 404, description: 'Colaborador nao encontrado'),
        ]
    )]
    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee);
    }
}
