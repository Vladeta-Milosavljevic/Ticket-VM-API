<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCategoryRequest extends FormRequest
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
            'active_only' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'active_only.boolean' => 'The active only field must be a boolean.',
            'archived.boolean' => 'The archived field must be a boolean.',
        ];
    }
}
