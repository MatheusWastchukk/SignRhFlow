<?php

namespace App\Http\Requests;

use App\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $rawPhone = (string) $this->input('phone');
        $normalizedPhone = preg_replace('/(?!^\+)\D/', '', trim($rawPhone)) ?? '';

        $this->merge([
            'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')),
            'phone' => $normalizedPhone,
        ]);
    }

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'cpf' => ['required', 'string', new ValidCpf],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'O campo phone deve estar no formato internacional E.164, ex.: +5511999999999.',
        ];
    }
}
