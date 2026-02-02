<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'urgency' => ['required', 'in:low,medium,high,critical'],
            'deadline' => ['required', 'date', 'after:now'],
            'category_id' => ['nullable', 'exists:categories,id'],
            // routes are protected, but in theory the user could still be null
            'manager_id' => [$user?->isAdmin() || $user?->isManager() ? 'nullable' : 'required', 'exists:users,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'description.required' => 'The description field is required.',
            'urgency.required' => 'The urgency field is required.',
            'urgency.in' => 'The urgency must be one of: low, medium, high, critical.',
            'deadline.required' => 'The deadline field is required.',
            'deadline.date' => 'The deadline must be a valid date.',
            'deadline.after' => 'The deadline must be a future date.',
            'category_id.exists' => 'The selected category does not exist.',
            'manager_id.required' => 'The manager field is required.',
            'manager_id.exists' => 'The selected manager does not exist.',
            'agent_id.exists' => 'The selected agent does not exist.',
        ];
    }
}
