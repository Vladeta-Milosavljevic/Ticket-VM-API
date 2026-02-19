<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'urgency' => ['sometimes', 'in:low,medium,high,critical'],
            'deadline' => ['sometimes', 'date', 'after_or_equal:now'],
            'status' => ['sometimes', 'in:open,in_progress,pending_review,completed,rejected,cancelled'],
            'category_id' => ['sometimes', 'nullable', Rule::exists('categories', 'id')->where('is_archived', 0)],
            'manager_id' => ['sometimes', 'exists:users,id'],
            'agent_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'completed_by_agent_at' => ['sometimes', 'nullable', 'date'],
            'completed_by_manager_at' => ['sometimes', 'nullable', 'date'],
            'rejected_at' => ['sometimes', 'nullable', 'date'],
            'rejection_reason' => ['required_if:rejected_at,!=,null', 'nullable', 'string'],
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
            'title.max' => 'The title may not be greater than 255 characters.',
            'urgency.in' => 'The urgency must be one of: low, medium, high, critical.',
            'deadline.date' => 'The deadline must be a valid date.',
            'deadline.after_or_equal' => 'The deadline must be a current or future date.',
            'status.in' => 'The status must be one of: open, in_progress, pending_review, completed, rejected, cancelled.',
            'category_id.exists' => 'The selected category does not exist or is archived.',
            'manager_id.exists' => 'The selected manager does not exist.',
            'agent_id.exists' => 'The selected agent does not exist.',
            'completed_by_agent_at.date' => 'The completed by agent date must be a valid date.',
            'completed_by_manager_at.date' => 'The completed by manager date must be a valid date.',
            'rejected_at.date' => 'The rejected date must be a valid date.',
            'rejection_reason.required_if' => 'The rejection reason is required when the ticket is rejected.',
        ];
    }
}
