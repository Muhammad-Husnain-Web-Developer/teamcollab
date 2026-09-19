<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWorkspaceRequest extends FormRequest
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
            'name'         => ['required', 'string', 'min:2', 'max:100'],
            'slug'         => ['required', 'string', 'min:2', 'max:50', 'unique:tenants,id', 'regex:/^[a-z0-9\-]+$/'],
            'timezone'     => ['nullable', 'string', 'timezone'],
            'company_size' => ['nullable', 'string', 'in:1-10,11-50,51-200,201-1000,1000+,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Workspace name is required.',
            'name.min'          => 'Workspace name must be at least 2 characters.',
            'name.max'          => 'Workspace name may not exceed 100 characters.',
            'slug.required'     => 'A workspace URL slug is required.',
            'slug.unique'       => 'This workspace URL is already taken.',
            'slug.min'          => 'Workspace URL must be at least 2 characters.',
            'slug.max'          => 'Workspace URL may not exceed 50 characters.',
            'slug.regex'        => 'Workspace URL may only contain lowercase letters, numbers, and hyphens.',
            'company_size.in'   => 'Please select a valid company size option.',
        ];
    }
}
