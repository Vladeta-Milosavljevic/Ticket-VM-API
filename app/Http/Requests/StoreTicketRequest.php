<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
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
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('is_archived', 0)],
            'manager_id' => [$user?->isAdmin() || $user?->isManager() ? 'nullable' : 'required',
                Rule::exists('users', 'id')->whereNull('deleted_at')->whereIn('role', ['manager', 'admin'])],
            'agent_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')->where('role', 'agent')],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpeg,png,gif,pdf,doc,docx,txt',
                'mimetypes:image/jpeg,image/png,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain',
                'max:10240',
            ],
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
            'category_id.exists' => 'The selected category does not exist or is archived.',
            'manager_id.required' => 'The manager field is required.',
            'manager_id.exists' => 'The selected manager does not exist.',
            'agent_id.exists' => 'The selected agent does not exist.',
        ];
    }
}
