<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role'  => ['required', 'string', 'in:admin,member,guest'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required to send an invitation.',
            'email.email'    => 'Please provide a valid email address.',
            'role.required'  => 'Please assign a role for this member.',
            'role.in'        => 'Role must be one of: admin, member, or guest.',
        ];
    }
}
