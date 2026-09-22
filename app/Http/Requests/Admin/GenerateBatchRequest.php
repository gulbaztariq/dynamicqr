<?php

namespace App\Http\Requests\Admin;

use App\Rules\SafeRedirectUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaff();
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('qr.max_batch_quantity')],
            'label_prefix' => ['nullable', 'string', 'max:60'],
            // Leave blank to build an unassigned pool you hand out later.
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'target_url' => ['nullable', 'string', 'max:2000', new SafeRedirectUrl],
            'user_can_edit' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('target_url'));

        if ($url !== '' && ! preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $this->merge([
            'target_url' => $url === '' ? null : $url,
            'user_can_edit' => $this->boolean('user_can_edit', true),
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }

    public function messages(): array
    {
        return [
            'quantity.max' => 'You can generate at most :max codes in one batch.',
        ];
    }
}
