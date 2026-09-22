<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($this->allowedRoles())],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'confirmed', Password::min(8)],

            // Optional: create the customer and their codes in one step.
            'qr_quantity' => ['nullable', 'integer', 'min:0', 'max:'.config('qr.max_batch_quantity')],
            'qr_label_prefix' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** Only a super admin may mint another staff account. */
    private function allowedRoles(): array
    {
        return $this->user()->isSuperAdmin()
            ? array_column(UserRole::cases(), 'value')
            : [UserRole::User->value];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
