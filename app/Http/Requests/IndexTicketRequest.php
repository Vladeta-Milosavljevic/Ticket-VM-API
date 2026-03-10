<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTicketRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_archived', 0)],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'agent_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(['open', 'in_progress', 'pending_review', 'completed', 'rejected', 'cancelled'])],
            'urgency' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'unassigned' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'deadline_from' => ['nullable', 'date'],
            'deadline_to' => [
                'nullable',
                'date',
                Rule::when($this->filled('deadline_from'), 'after_or_equal:deadline_from'),
            ],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'deadline', 'urgency', 'status'])],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
