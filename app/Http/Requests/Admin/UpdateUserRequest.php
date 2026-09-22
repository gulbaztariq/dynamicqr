<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($this->allowedRoles())],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    private function allowedRoles(): array
    {
        return $this->user()->isSuperAdmin()
            ? array_column(UserRole::cases(), 'value')
            : [UserRole::User->value];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
