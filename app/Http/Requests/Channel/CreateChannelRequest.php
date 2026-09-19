<?php

namespace App\Http\Requests\Channel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateChannelRequest extends FormRequest
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
            'name'        => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type'        => ['required', 'string', 'in:public,private'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A channel name is required.',
            'name.min'      => 'Channel name must be at least 2 characters.',
            'name.max'      => 'Channel name may not exceed 100 characters.',
            'name.regex'    => 'Channel name may only contain lowercase letters, numbers, hyphens, and underscores.',
            'type.required' => 'Please select a channel type.',
            'type.in'       => 'Channel type must be either public or private.',
        ];
    }
}
