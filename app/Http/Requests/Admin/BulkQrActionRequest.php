<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Backs the "select codes, then do something to all of them" toolbar. */
class BulkQrActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['assign', 'unassign', 'activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('qr_codes', 'id')],
            'user_id' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one QR code first.',
            'user_id.required_if' => 'Choose the customer these codes should belong to.',
        ];
    }
}
