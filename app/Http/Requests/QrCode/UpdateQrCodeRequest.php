<?php

namespace App\Http\Requests\QrCode;

use App\Rules\SafeRedirectUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateTargetUrl', $this->route('qrCode'));
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'target_url' => ['nullable', 'string', 'max:2000', new SafeRedirectUrl],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Accept "my-menu.com" and turn it into a valid https:// link. */
    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('target_url'));

        if ($url !== '' && ! preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $this->merge([
            'target_url' => $url === '' ? null : $url,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function messages(): array
    {
        return [
            'target_url.max' => 'The destination link is too long.',
        ];
    }
}
