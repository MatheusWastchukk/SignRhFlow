<?php

namespace App\Http\Requests;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'delivery_method' => [
                'required',
                'string',
                Rule::in([Contract::DELIVERY_EMAIL, Contract::DELIVERY_WHATSAPP]),
            ],
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
            'file_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
