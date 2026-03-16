<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,'.$userId],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in($this->user()->isAdmin() ? ['admin', 'manager', 'agent'] : ['manager', 'agent']),
                function ($attribute, $value, $fail) {
                    $model = $this->route('user');
                    if ($model->id !== $this->user()->id) {
                        return;
                    }
                    $roleHierarchy = ['agent' => 0, 'manager' => 1, 'admin' => 2];
                    $currentLevel = $roleHierarchy[$model->role] ?? 0;
                    $newLevel = $roleHierarchy[$value] ?? 0;
                    if ($newLevel < $currentLevel) {
                        $fail('You cannot demote yourself.');
                    }
                },
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
            'name.max' => 'The name may not be greater than 255 characters.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email has already been taken.',
            'password.min' => 'The password must be at least 8 characters.',
            'role.in' => 'The role must be one of: admin, manager, agent. Only admins can update the role to admin.',
            'role.demote' => 'You cannot demote yourself.',
        ];
    }
}
