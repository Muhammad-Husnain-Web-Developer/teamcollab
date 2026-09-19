<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'timezone'     => ['nullable', 'string', 'timezone'],
            'company_size' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            'logo'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'settings'     => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'          => 'Workspace name must be at least 2 characters.',
            'name.max'          => 'Workspace name may not exceed 100 characters.',
            'logo.image'        => 'The logo must be an image.',
            'logo.mimes'        => 'Logo must be a JPEG, PNG, or WebP image.',
            'logo.max'          => 'Logo file size may not exceed 2MB.',
            'company_size.in'   => 'Please select a valid company size.',
        ];
    }
}
