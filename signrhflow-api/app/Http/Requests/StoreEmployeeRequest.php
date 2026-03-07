<?php

namespace App\Http\Requests;

use App\Rules\ValidCpf;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phoneDigits = preg_replace('/\D/', '', (string) $this->input('phone')) ?? '';

        if (str_starts_with($phoneDigits, '55') && in_array(strlen($phoneDigits), [12, 13], true)) {
            $phoneDigits = substr($phoneDigits, 2);
        }

        $this->merge([
            'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')),
            'phone' => $phoneDigits,
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
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:employees,email'],
            'phone' => ['required', 'string', 'regex:/^[1-9]{2}9?\d{8}$/'],
            'cpf' => ['required', 'string', new ValidCpf(), 'unique:employees,cpf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'O campo phone deve estar no formato brasileiro, ex.: (11) 99999-9999.',
            'cpf.unique' => 'O CPF informado ja esta cadastrado.',
        ];
    }
}
