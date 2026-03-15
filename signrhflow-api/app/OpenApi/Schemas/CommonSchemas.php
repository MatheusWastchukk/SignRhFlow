<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeResource',
    required: ['id', 'name', 'email', 'phone', 'cpf'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'phone', type: 'string'),
        new OA\Property(property: 'cpf', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ContractResource',
    required: ['id', 'employee_id', 'status', 'delivery_method', 'file_path'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'employee_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'autentique_document_id', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PENDING', 'SIGNED', 'REJECTED']),
        new OA\Property(property: 'delivery_method', type: 'string', enum: ['EMAIL', 'WHATSAPP']),
        new OA\Property(property: 'file_path', type: 'string'),
        new OA\Property(property: 'pdf_generated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'signed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'employee', ref: '#/components/schemas/EmployeeResource'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginatorLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginatorMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'from', type: 'integer', nullable: true),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'path', type: 'string'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'to', type: 'integer', nullable: true),
        new OA\Property(property: 'total', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginatedEmployeesResponse',
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/EmployeeResource')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginatorLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatorMeta'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginatedContractsResponse',
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ContractResource')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginatorLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginatorMeta'),
    ],
    type: 'object'
)]
class CommonSchemas
{
}
